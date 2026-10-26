<?php
/**
 * Картинки разделов каталога — коллажи из 4 товаров раздела (26.10.2026).
 *
 * Запуск на сервере (сначала только staging!):
 *   wp eval-file category-collages.php            — пробный прогон: какие товары попадут
 *   wp eval-file category-collages.php apply      — собрать коллажи и поставить картинками разделов
 *   wp eval-file category-collages.php rollback   — вернуть прежние картинки
 *
 * Разделы — верхнего уровня (RU). Товары: опубликованы, видимы в каталоге, в
 * наличии, с фото не меньше 300px. Продаж на сайте нет (счётчики 0), поэтому
 * «популярность» = разнообразие: по одному товару из самых больших подразделов
 * (по числу подходящих товаров), в каждом — самое крупное фото. Если товаров
 * меньше 4 — коллаж из 1–3 фото.
 *
 * Коллаж: 800×800 WebP, белый фон, ячейки 2×2 с тонкими разделителями, фото
 * целиком (без обрезки). Кладётся в uploads/category-collages/, регистрируется
 * как вложение и ставится в thumbnail_id раздела и его переводов WPML. Прежний
 * thumbnail_id сохраняется в мету _casa24_prev_thumbnail_id для отката.
 *
 * @package Casa24
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$casa24_mode = isset( $args[0] ) ? $args[0] : 'dry';

const CASA24_COLLAGE_SIZE = 800;
const CASA24_COLLAGE_GAP  = 2;
const CASA24_CELL_PAD     = 24;

/**
 * Подходящие товары раздела (с подразделами), сгруппированные по подразделу.
 *
 * @param WP_Term $top Раздел верхнего уровня.
 * @return array<int, array> [ term_id подраздела => [ [id, file, w, h, name], ... ] ]
 */
function casa24_collage_candidates( $top ) {
	$ids = wc_get_products(
		array(
			'status'       => 'publish',
			'limit'        => -1,
			'category'     => array( $top->slug ),
			'visibility'   => 'visible',
			'stock_status' => 'instock',
			'return'       => 'ids',
		)
	);

	$groups = array();
	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		$img_id  = $product ? $product->get_image_id() : 0;
		if ( ! $img_id ) {
			continue;
		}

		$file = get_attached_file( $img_id );
		if ( ! $file || ! file_exists( $file ) ) {
			continue;
		}

		$size = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- битые файлы просто пропускаем.
		if ( ! $size || min( $size[0], $size[1] ) < 300 ) {
			continue;
		}

		// Подраздел: самая глубокая категория товара внутри этого раздела.
		$group = $top->term_id;
		$depth = -1;
		foreach ( wc_get_product_term_ids( $id, 'product_cat' ) as $cat_id ) {
			$ancestors = get_ancestors( $cat_id, 'product_cat' );
			if ( $cat_id !== $top->term_id && ! in_array( $top->term_id, $ancestors, true ) ) {
				continue;
			}
			if ( count( $ancestors ) > $depth ) {
				$depth = count( $ancestors );
				$group = $cat_id;
			}
		}

		$groups[ $group ][] = array(
			'id'   => $id,
			'file' => $file,
			'w'    => $size[0],
			'h'    => $size[1],
			'name' => $product->get_name(),
		);
	}

	return $groups;
}

/**
 * До 4 товаров: по одному из самых больших подразделов, в каждом — самое крупное фото.
 *
 * @param array $groups Результат casa24_collage_candidates().
 * @return array
 */
function casa24_collage_pick( $groups ) {
	uasort(
		$groups,
		function ( $a, $b ) {
			return count( $b ) - count( $a );
		}
	);

	foreach ( $groups as &$items ) {
		usort(
			$items,
			function ( $a, $b ) {
				return min( $b['w'], $b['h'] ) - min( $a['w'], $a['h'] );
			}
		);
	}
	unset( $items );

	$picked = array();
	for ( $round = 0; count( $picked ) < 4; $round++ ) {
		$added = false;
		foreach ( $groups as $items ) {
			if ( isset( $items[ $round ] ) && count( $picked ) < 4 ) {
				$picked[] = $items[ $round ];
				$added    = true;
			}
		}
		if ( ! $added ) {
			break;
		}
	}

	return $picked;
}

/**
 * Собрать коллаж 800×800 (WebP).
 *
 * @param array  $items Выбранные товары (1–4).
 * @param string $dest  Путь к файлу.
 * @return bool
 */
function casa24_collage_render( $items, $dest ) {
	$size   = CASA24_COLLAGE_SIZE;
	$canvas = imagecreatetruecolor( $size, $size );
	$white  = imagecolorallocate( $canvas, 255, 255, 255 );
	$line   = imagecolorallocate( $canvas, 232, 230, 225 );
	imagefill( $canvas, 0, 0, $white );

	$n    = count( $items );
	$half = intdiv( $size, 2 );

	// Ячейки [x, y, w, h] для 1–4 фото.
	if ( 1 === $n ) {
		$cells = array( array( 0, 0, $size, $size ) );
	} elseif ( 2 === $n ) {
		$cells = array( array( 0, 0, $half, $size ), array( $half, 0, $half, $size ) );
	} elseif ( 3 === $n ) {
		$cells = array( array( 0, 0, $half, $half ), array( $half, 0, $half, $half ), array( intdiv( $size, 4 ), $half, $half, $half ) );
	} else {
		$cells = array( array( 0, 0, $half, $half ), array( $half, 0, $half, $half ), array( 0, $half, $half, $half ), array( $half, $half, $half, $half ) );
	}

	foreach ( $items as $i => $item ) {
		$src = @imagecreatefromstring( file_get_contents( $item['file'] ) ); // phpcs:ignore
		if ( ! $src ) {
			continue;
		}

		list( $cx, $cy, $cw, $ch ) = $cells[ $i ];
		$box_w = $cw - 2 * CASA24_CELL_PAD;
		$box_h = $ch - 2 * CASA24_CELL_PAD;
		$sw    = imagesx( $src );
		$sh    = imagesy( $src );
		$scale = min( $box_w / $sw, $box_h / $sh );
		$dw    = (int) round( $sw * $scale );
		$dh    = (int) round( $sh * $scale );
		$dx    = $cx + CASA24_CELL_PAD + intdiv( $box_w - $dw, 2 );
		$dy    = $cy + CASA24_CELL_PAD + intdiv( $box_h - $dh, 2 );

		imagecopyresampled( $canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh );
		imagedestroy( $src );
	}

	// Тонкие разделители между ячейками.
	if ( $n >= 2 ) {
		imagefilledrectangle( $canvas, $half - 1, 0, $half, ( 2 === $n ? $size : $half ), $line );
	}
	if ( $n >= 3 ) {
		imagefilledrectangle( $canvas, 0, $half - 1, $size, $half, $line );
	}
	if ( 4 === $n ) {
		imagefilledrectangle( $canvas, $half - 1, $half, $half, $size, $line );
	}

	$ok = imagewebp( $canvas, $dest, 86 );
	imagedestroy( $canvas );

	return $ok;
}

/**
 * Поставить картинку разделу и его переводам WPML (с сохранением прежней).
 *
 * @param int $term_id       Раздел (RU).
 * @param int $attachment_id Вложение-коллаж.
 */
function casa24_collage_assign( $term_id, $attachment_id ) {
	$targets = array( $term_id );
	$trid    = apply_filters( 'wpml_element_trid', null, $term_id, 'tax_product_cat' );
	if ( $trid ) {
		foreach ( (array) apply_filters( 'wpml_get_element_translations', null, $trid, 'tax_product_cat' ) as $tr ) {
			$targets[] = (int) $tr->element_id;
		}
	}

	foreach ( array_unique( $targets ) as $tid ) {
		if ( '' === get_term_meta( $tid, '_casa24_prev_thumbnail_id', true ) ) {
			update_term_meta( $tid, '_casa24_prev_thumbnail_id', (string) (int) get_term_meta( $tid, 'thumbnail_id', true ) );
		}
		update_term_meta( $tid, 'thumbnail_id', $attachment_id );
	}
}

do_action( 'wpml_switch_language', 'ru' );

$tops = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'parent'     => 0,
		'hide_empty' => true,
		'exclude'    => array( (int) get_option( 'default_product_cat', 0 ) ),
	)
);

if ( 'rollback' === $casa24_mode ) {
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'meta_key'   => '_casa24_prev_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'lang'       => '',
		)
	);
	foreach ( $terms as $term ) {
		$prev = (int) get_term_meta( $term->term_id, '_casa24_prev_thumbnail_id', true );
		update_term_meta( $term->term_id, 'thumbnail_id', $prev ? $prev : '' );
		delete_term_meta( $term->term_id, '_casa24_prev_thumbnail_id' );
		echo 'откат: ' . $term->name . "\n";
	}
	return;
}

require_once ABSPATH . 'wp-admin/includes/image.php';

$upload = wp_upload_dir();
$dir    = trailingslashit( $upload['basedir'] ) . 'category-collages';
wp_mkdir_p( $dir );

foreach ( $tops as $top ) {
	$picked = casa24_collage_pick( casa24_collage_candidates( $top ) );

	echo $top->term_id . "\t" . $top->name . "\t" . count( $picked ) . ' фото: ' . implode( ' | ', wp_list_pluck( $picked, 'name' ) ) . "\n";

	if ( 'apply' !== $casa24_mode || ! $picked ) {
		continue;
	}

	$dest = $dir . '/category-' . $top->term_id . '.webp';
	if ( ! casa24_collage_render( $picked, $dest ) ) {
		echo "  ! не удалось собрать коллаж\n";
		continue;
	}

	// Повторный запуск — обновить существующее вложение, а не плодить новые.
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_casa24_collage_for', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => $top->term_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);

	if ( $existing ) {
		$attachment_id = $existing[0];
		update_attached_file( $attachment_id, $dest );
	} else {
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/webp',
				'post_title'     => $top->name . ' — раздел каталога',
				'post_status'    => 'inherit',
			),
			$dest
		);
		update_post_meta( $attachment_id, '_casa24_collage_for', $top->term_id );
	}

	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $top->name );
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $dest ) );
	casa24_collage_assign( $top->term_id, $attachment_id );
	echo '  → вложение ' . $attachment_id . "\n";
}
