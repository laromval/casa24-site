<?php
/**
 * Plugin Name: Casa24 — обмен с 1С: цены и артикулы RU → RO
 * Description: Плагин обмена с 1С (itgalaxy «WooCommerce - 1C Data Exchange») пишет цену и артикул только в RU-товар, напрямую в метаполя — WooCommerce Multilingual об этом не узнаёт, и RO-перевод остаётся со старой ценой (на 27.10.2026 — 262 расхождения, «Model R14» в RO за 1 MDL). После обработки товара обменом копируем цену и артикул RU-товара во все его переводы.
 *
 * Разбор и проверка — docs/audit-price-sync-ru-ro-2026-10-27.md. Опция
 * обмена «Вызывать метод save у простых товаров…» не помогает: WCML
 * синхронизирует переводы только при сохранении из админки.
 *
 * Источник цен остаётся 1С: сюда попадает только то, что обмен только что
 * записал в RU-товар. Значения в RO меняются, только если отличаются.
 * Журнал — WooCommerce → Статус → Журналы, источник casa24-ro-sync.
 *
 * Разовое выравнивание всего каталога (WP-CLI):
 *   wp casa24 ro-sync --dry-run   — только показать, что изменится
 *   wp casa24 ro-sync             — выровнять
 *   wp casa24 ro-sync --ids=1055395,1059682
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Поля RU-товара, которые копируются в переводы: метаполе => сеттер WC_Product.
 */
const CASA24_RO_SYNC_FIELDS = array(
	'_regular_price' => 'set_regular_price',
	'_sale_price'    => 'set_sale_price',
	'_price'         => 'set_price',
	'_sku'           => 'set_sku',
);

/**
 * Скопировать цену и артикул RU-товара в его переводы.
 *
 * Значения RU читаются из метаполей (обмен пишет их через update_metadata,
 * объект WC_Product в кеше может быть старым), RO сохраняется через
 * WC_Product::save() — так обновляются таблица поиска wc_product_meta_lookup
 * и кеши.
 *
 * @param int  $product_id ID товара (только товар основного языка, остальные пропускаются).
 * @param bool $dry_run    Только вернуть изменения, ничего не сохранять.
 * @return array[] Изменения: [ ID перевода, поле, было, стало ].
 */
function casa24_ro_sync_product( $product_id, $dry_run = false ) {
	$product_id = (int) $product_id;

	if ( 'product' !== get_post_type( $product_id ) ) {
		return array();
	}

	$default_lang = apply_filters( 'wpml_default_language', null );
	$details      = apply_filters( 'wpml_post_language_details', null, $product_id );

	if ( ! $default_lang || ! is_array( $details ) || $default_lang !== $details['language_code'] ) {
		return array();
	}

	$trid         = apply_filters( 'wpml_element_trid', null, $product_id, 'post_product' );
	$translations = $trid ? apply_filters( 'wpml_get_element_translations', null, $trid, 'post_product' ) : array();
	$changes      = array();

	foreach ( (array) $translations as $translation ) {
		$translation_id = (int) $translation->element_id;

		if ( ! $translation_id || $translation_id === $product_id ) {
			continue;
		}

		$target = wc_get_product( $translation_id );

		if ( ! $target ) {
			continue;
		}

		$target_changes = array();

		foreach ( CASA24_RO_SYNC_FIELDS as $meta_key => $setter ) {
			$source_value = (string) get_post_meta( $product_id, $meta_key, true );
			$target_value = (string) get_post_meta( $translation_id, $meta_key, true );

			if ( $source_value === $target_value ) {
				continue;
			}

			$target_changes[] = array( $translation_id, $meta_key, $target_value, $source_value );

			if ( ! $dry_run ) {
				try {
					$target->{$setter}( $source_value );
				} catch ( WC_Data_Exception $e ) {
					// Например, артикул уже занят другим товаром — остальные поля всё равно копируем.
					casa24_ro_sync_log( 'warning', sprintf( 'RU %d → RO %d: %s не записан (%s)', $product_id, $translation_id, $meta_key, $e->getMessage() ) );
				}
			}
		}

		if ( $target_changes && ! $dry_run ) {
			$target->save();
			casa24_ro_sync_log( 'info', sprintf( 'RU %d → RO %d: %s', $product_id, $translation_id, wp_json_encode( $target_changes, JSON_UNESCAPED_UNICODE ) ) );
		}

		$changes = array_merge( $changes, $target_changes );
	}

	return $changes;
}

/**
 * Запись в журнал WooCommerce.
 *
 * @param string $level   Уровень (info, warning).
 * @param string $message Сообщение.
 */
function casa24_ro_sync_log( $level, $message ) {
	if ( function_exists( 'wc_get_logger' ) ) {
		wc_get_logger()->log( $level, $message, array( 'source' => 'casa24-ro-sync' ) );
	}
}

/**
 * Обработчик событий обмена: первый аргумент — ID товара.
 *
 * @param int $product_id ID товара.
 */
function casa24_ro_sync_after_1c( $product_id ) {
	casa24_ro_sync_product( $product_id );
}
// Основные данные товара (артикул) — до обработки предложений.
add_action( 'itglx_wc1c_after_product_info_resolve', 'casa24_ro_sync_after_1c', 20 );
// Предложение простого товара: цены и остатки уже записаны.
add_action( 'itglx_wc1c_after_product_offer_resolve', 'casa24_ro_sync_after_1c', 20 );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Выровнять цены и артикулы RO-переводов по RU-товарам.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Только показать изменения.
	 *
	 * [--ids=<ids>]
	 * : ID RU-товаров через запятую (по умолчанию — все товары основного языка).
	 *
	 * @param array $args       Позиционные аргументы (не используются).
	 * @param array $assoc_args Именованные аргументы.
	 */
	function casa24_ro_sync_cli( $args, $assoc_args ) {
		global $wpdb;

		$dry_run = ! empty( $assoc_args['dry-run'] );

		if ( ! empty( $assoc_args['ids'] ) ) {
			$ids = array_map( 'intval', explode( ',', $assoc_args['ids'] ) );
		} else {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type = 'post_product' AND language_code = %s",
					apply_filters( 'wpml_default_language', null )
				)
			);
		}

		$changed = 0;

		WP_CLI::line( "ru_id\tro_id\tfield\twas\tnow" );

		foreach ( $ids as $i => $id ) {
			foreach ( casa24_ro_sync_product( (int) $id, $dry_run ) as $change ) {
				WP_CLI::line( $id . "\t" . implode( "\t", $change ) );
				++$changed;
			}

			if ( 0 === $i % 500 ) {
				wp_cache_flush_runtime();
			}
		}

		WP_CLI::success( sprintf( '%s: товаров проверено %d, изменений полей %d.', $dry_run ? 'Пробный прогон' : 'Готово', count( $ids ), $changed ) );
	}
	WP_CLI::add_command( 'casa24 ro-sync', 'casa24_ro_sync_cli' );
}
