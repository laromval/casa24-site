<?php
/**
 * Фото для коллажей на плитках каталога «папками» (29.10.2026).
 *
 * collage-picks.json: ключ подгруппы нового дерева → до 3 вложений (фото
 * товаров), отобранных вручную: белый фон, от 400px, товар без упаковки,
 * без водяных знаков. Плитка раздела берёт их у своих подгрупп
 * (glozin-child/inc/catalog-folders.php). Кладётся в мету _casa24_collage_imgs
 * категории и её RO-перевода; у подгрупп не из списка мета удаляется.
 *
 * Запуск на сервере: wp eval-file collage-picks-apply.php [путь к json]
 *
 * @package Casa24
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$casa24_file  = isset( $args[0] ) ? $args[0] : __DIR__ . '/collage-picks.json';
$casa24_picks = json_decode( (string) file_get_contents( $casa24_file ), true );
if ( ! is_array( $casa24_picks ) ) {
	WP_CLI::error( "не прочитан $casa24_file" );
}

$casa24_ru  = casa24_catalog_term_ids( casa24_catalog_default_lang(), true );
$casa24_ro  = casa24_catalog_term_ids( 'ro', true );
$casa24_set = 0;

foreach ( $casa24_ru as $casa24_key => $casa24_term ) {
	$casa24_ids = isset( $casa24_picks[ $casa24_key ] ) ? array_map( 'intval', $casa24_picks[ $casa24_key ]['img'] ) : array();
	$casa24_ids = array_values( array_filter( $casa24_ids, 'wp_attachment_is_image' ) );

	foreach ( array( $casa24_term, $casa24_ro[ $casa24_key ] ?? 0 ) as $casa24_id ) {
		if ( ! $casa24_id ) {
			continue;
		}
		if ( $casa24_ids ) {
			update_term_meta( $casa24_id, '_casa24_collage_imgs', implode( ',', $casa24_ids ) );
		} else {
			delete_term_meta( $casa24_id, '_casa24_collage_imgs' );
		}
	}
	$casa24_set += $casa24_ids ? 1 : 0;
}

WP_CLI::success( "фото для коллажей у $casa24_set подгрупп" );
