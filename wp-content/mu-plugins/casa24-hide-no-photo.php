<?php
/**
 * Plugin Name: Casa24 — товары без фото не показываются на витрине
 * Description: Товары без главного фото (на 30.10.2026 — 7 239 из 18 421, из них 1 311 в наличии) портят вид каталога. Убираем их из всех списков на сайте: магазин, категории, поиск, подборки на главной, «Похожие товары». Как только у товара появляется фото — он снова виден сам, ничего включать не нужно.
 *
 * Решение владельца 30.10.2026. Товар не снимается с публикации и не меняется
 * в базе: страница товара по прямой ссылке открывается (старые ссылки, заказы),
 * админка, обмен с 1С, WP-CLI и REST (кроме Store API витрины) видят все товары.
 *
 * Фото проверяем у RU-оригинала: у RO-переводов _thumbnail_id в базе нет,
 * WPML подставляет его из оригинала только при чтении через PHP.
 *
 * Товары «в наличии, но без фото» идут первыми в партии на поиск фото — tools/media/make_batch.py.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Запрос витрины, а не служебный: не админка (кроме ajax-подгрузок темы), не обмен с 1С, не cron/CLI,
 * из REST — только Store API (блоки WooCommerce на страницах).
 */
function casa24_hide_no_photo_context() {
	if ( defined( 'ITGLX_1C_EXCHANGE' ) || defined( '_1C_IMPORT' ) || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false !== strpos( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '/wc/store/' );
	}
	if ( wp_doing_ajax() ) {
		return 0 === strpos( (string) ( $_REQUEST['action'] ?? '' ), 'glozin_' );
	}
	return ! is_admin();
}

add_action(
	'pre_get_posts',
	function ( $q ) {
		$type = (array) $q->get( 'post_type' );
		if ( array( 'product' ) !== $type && ! ( $q->is_search() && ( ! $q->get( 'post_type' ) || in_array( 'product', $type, true ) ) ) ) {
			return;
		}
		if ( $q->is_singular() || ! casa24_hide_no_photo_context() ) {
			return;
		}
		$q->set( 'casa24_need_photo', 1 );
	}
);

/**
 * ID товаров без фото (RU и все их переводы) — одним запросом, в кэше на 10 минут; подзапрос
 * на каждую строку WP_Query замедлял главную на ~3,5 с. Смена главного фото сбрасывает кэш сразу.
 */
function casa24_no_photo_ids() {
	$ids = get_transient( 'casa24_no_photo_ids' );
	if ( false === $ids ) {
		global $wpdb;
		$ids = implode(
			',',
			array_map(
				'intval',
				$wpdb->get_col(
					"SELECT t.element_id FROM {$wpdb->prefix}icl_translations ru
					JOIN {$wpdb->prefix}icl_translations t ON t.trid = ru.trid AND t.element_type = 'post_product'
					JOIN {$wpdb->posts} p ON p.ID = ru.element_id AND p.post_type = 'product'
					LEFT JOIN {$wpdb->postmeta} m ON m.post_id = ru.element_id AND m.meta_key = '_thumbnail_id'
					WHERE ru.element_type = 'post_product' AND ru.language_code = 'ru'
					  AND ( m.meta_value IS NULL OR m.meta_value IN ( '', '0' ) )"
				)
			)
		);
		set_transient( 'casa24_no_photo_ids', $ids, 10 * MINUTE_IN_SECONDS );
	}
	return $ids;
}

foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $casa24_hook ) {
	add_action(
		$casa24_hook,
		function ( $mid, $post_id, $key ) {
			if ( '_thumbnail_id' === $key ) {
				delete_transient( 'casa24_no_photo_ids' );
			}
		},
		10,
		3
	);
}

add_filter(
	'posts_where',
	function ( $where, $q ) {
		if ( ! $q->get( 'casa24_need_photo' ) ) {
			return $where;
		}
		$ids = casa24_no_photo_ids();
		global $wpdb;
		return '' === $ids ? $where : $where . " AND {$wpdb->posts}.ID NOT IN ( {$ids} )";
	},
	10,
	2
);

// «Похожие товары» WooCommerce выбирает прямым SQL, мимо WP_Query
add_filter(
	'woocommerce_related_products',
	function ( $ids ) {
		return casa24_hide_no_photo_context() ? array_values( array_filter( $ids, 'get_post_thumbnail_id' ) ) : $ids;
	}
);
