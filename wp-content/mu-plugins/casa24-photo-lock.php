<?php
/**
 * Plugin Name: Casa24 — замок на фото товаров от обмена с 1С
 * Description: Фото товаров приходят из 1С (itgalaxy «WooCommerce - 1C Data Exchange»). Когда фото товара отредактировано на сайте (белый фон, tools/media/upload_batch.py), у товара и его RO-перевода ставится метка casa24_photo_lock = 1. Обмен с 1С у таких товаров не может поменять или удалить главное фото и галерею — остальное (цена, название, наличие) обновляется как обычно. Товары без метки получают фото из 1С как раньше.
 *
 * Менять фото помеченного товара можно из админки (страница товара),
 * через REST API пользователем с правом edit_products и из WP-CLI —
 * блокируется только обмен (запрос с type/mode протокола 1С) и фоновые
 * задачи без пользователя. Попытки обмена — WooCommerce → Статус → Журналы,
 * источник casa24-photo-lock.
 *
 * WP-CLI:
 *   wp casa24 photo-lock list               — товары с меткой
 *   wp casa24 photo-lock lock --ids=1,2     — поставить метку
 *   wp casa24 photo-lock unlock --ids=1,2   — снять (фото снова будет приходить из 1С)
 *   wp casa24 photo-lock sync-gallery --ids=1,2 — доп. фото RU-товара скопировать в переводы (RO)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CASA24_PHOTO_LOCK_META = 'casa24_photo_lock';
const CASA24_PHOTO_LOCK_KEYS = array( '_thumbnail_id', '_product_image_gallery' );

/**
 * Запрос пришёл от 1С по протоколу обмена (?type=catalog&mode=import и т. п.)?
 */
function casa24_photo_lock_is_1c_request() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	return isset( $_GET['type'], $_GET['mode'] ) && in_array( $_GET['type'], array( 'catalog', 'sale', 'get_catalog' ), true );
	// phpcs:enable
}

/**
 * Можно ли в этом запросе менять фото помеченного товара.
 */
function casa24_photo_lock_change_allowed() {
	if ( casa24_photo_lock_is_1c_request() ) {
		return false;
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return true;
	}
	if ( ! current_user_can( 'edit_products' ) ) {
		return false; // обмен, крон, фоновые очереди
	}
	return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( is_admin() && ! wp_doing_ajax() );
}

/**
 * Запретить запись фото, если товар помечен и запрос не разрешён.
 *
 * @param mixed  $check     Значение короткого замыкания (null — продолжить как обычно).
 * @param int    $object_id ID записи.
 * @param string $meta_key  Ключ метаполя.
 * @return mixed false — запись отменена, иначе $check без изменений.
 */
function casa24_photo_lock_guard( $check, $object_id, $meta_key ) {
	if ( null !== $check || ! in_array( $meta_key, CASA24_PHOTO_LOCK_KEYS, true ) || ! $object_id ) {
		return $check;
	}
	if ( 'product' !== get_post_type( $object_id ) || '1' !== (string) get_post_meta( $object_id, CASA24_PHOTO_LOCK_META, true ) ) {
		return $check;
	}
	if ( casa24_photo_lock_change_allowed() ) {
		return $check;
	}
	if ( function_exists( 'wc_get_logger' ) ) {
		wc_get_logger()->info( sprintf( 'товар %d: %s не изменён — фото отредактировано на сайте', $object_id, $meta_key ), array( 'source' => 'casa24-photo-lock' ) );
	}
	return false;
}
add_filter( 'update_post_metadata', 'casa24_photo_lock_guard', 10, 3 );
add_filter( 'add_post_metadata', 'casa24_photo_lock_guard', 10, 3 );
add_filter( 'delete_post_metadata', 'casa24_photo_lock_guard', 10, 3 );

/**
 * Галерея RU-товара → в его переводы (RO).
 *
 * WooCommerce Multilingual подставляет переводу галерею оригинала через фильтр get_post_metadata,
 * поэтому update_post_meta у перевода видит «то же значение» и ничего не пишет, а витрина читает
 * пустую настоящую запись — доп. фото у RO не видны. Пишем запись перевода напрямую: delete + add
 * (add_post_meta с unique проверяет базу без фильтров).
 *
 * @param int $product_id ID RU-товара.
 * @return array ID переводов, которым записана галерея.
 */
function casa24_sync_gallery_to_translations( $product_id ) {
	global $wpdb;
	$value = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_product_image_gallery'", $product_id ) );
	$done  = array();
	$trid  = apply_filters( 'wpml_element_trid', null, $product_id, 'post_product' );
	foreach ( (array) apply_filters( 'wpml_get_element_translations', null, $trid, 'post_product' ) as $lang => $t ) {
		$tid = (int) $t->element_id;
		if ( $tid === (int) $product_id ) {
			continue;
		}
		$raw = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_product_image_gallery'", $tid ) );
		if ( $raw === $value ) {
			continue;
		}
		delete_post_meta( $tid, '_product_image_gallery' );
		if ( '' !== $value ) {
			add_post_meta( $tid, '_product_image_gallery', $value, true );
		}
		clean_post_cache( $tid );
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $tid );
		}
		do_action( 'litespeed_purge_post', $tid );
		$done[] = $tid;
	}
	return $done;
}

/**
 * После обновления товара через REST (скрипт заливки фото) — галерея в переводы.
 *
 * @param WC_Product $product Товар.
 */
function casa24_sync_gallery_after_rest( $product ) {
	$id   = $product->get_id();
	$lang = apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $id, 'element_type' => 'product' ) );
	if ( 'ru' === $lang ) {
		casa24_sync_gallery_to_translations( $id );
	}
}
add_action( 'woocommerce_rest_insert_product_object', 'casa24_sync_gallery_after_rest', 20 );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Метка «фото отредактировано на сайте» — обмен с 1С не меняет фото.
	 */
	class Casa24_Photo_Lock_CLI {

		/**
		 * Товары с меткой.
		 *
		 * @subcommand list
		 */
		public function list_() {
			$ids = get_posts(
				array(
					'post_type'   => 'product',
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
					'meta_key'    => CASA24_PHOTO_LOCK_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'  => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			WP_CLI::log( implode( ',', $ids ) );
			WP_CLI::success( count( $ids ) . ' товаров с меткой.' );
		}

		/**
		 * Поставить метку.
		 *
		 * ## OPTIONS
		 *
		 * --ids=<ids>
		 * : ID товаров через запятую.
		 */
		public function lock( $args, $assoc_args ) {
			foreach ( wp_parse_id_list( $assoc_args['ids'] ) as $id ) {
				update_post_meta( $id, CASA24_PHOTO_LOCK_META, '1' );
			}
			WP_CLI::success( 'Готово.' );
		}

		/**
		 * Скопировать галерею (доп. фото) RU-товаров в их переводы.
		 *
		 * ## OPTIONS
		 *
		 * --ids=<ids>
		 * : ID RU-товаров через запятую.
		 *
		 * @subcommand sync-gallery
		 */
		public function sync_gallery( $args, $assoc_args ) {
			foreach ( wp_parse_id_list( $assoc_args['ids'] ) as $id ) {
				WP_CLI::log( $id . ' → ' . implode( ',', casa24_sync_gallery_to_translations( $id ) ) );
			}
			WP_CLI::success( 'Готово.' );
		}

		/**
		 * Снять метку — фото товара снова будет приходить из 1С.
		 *
		 * ## OPTIONS
		 *
		 * --ids=<ids>
		 * : ID товаров через запятую.
		 */
		public function unlock( $args, $assoc_args ) {
			foreach ( wp_parse_id_list( $assoc_args['ids'] ) as $id ) {
				delete_post_meta( $id, CASA24_PHOTO_LOCK_META );
			}
			WP_CLI::success( 'Готово.' );
		}
	}

	WP_CLI::add_command( 'casa24 photo-lock', 'Casa24_Photo_Lock_CLI' );
}
