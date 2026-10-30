<?php
/**
 * Картинки разделов каталога — коллажи из 4 товаров раздела (26.10.2026).
 *
 * Запуск на сервере (сначала только staging!):
 *   wp eval-file category-collages.php            — пробный прогон: какие товары попадут
 *   wp eval-file category-collages.php apply      — собрать коллажи и поставить картинками разделов
 *   wp eval-file category-collages.php rollback   — вернуть прежние картинки
 *   wp eval-file category-collages.php report 813 — все кандидаты раздела с оценкой фото
 *   wp eval-file category-collages.php apply 813 961 — пересобрать только эти разделы (можно и группы
 *     второго уровня: коллаж — по одному фото из разных подкатегорий)
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
 * Оценка фото по уменьшенной копии 48×48: доля белых пикселей по краю
 * (white — 1.0 = чистый белый фон, снимки с телефона на сером фоне ~0) и
 * средняя яркость центра (lum — чем ниже, тем контрастнее товар на белом).
 *
 * @param string $file Путь к файлу.
 * @return array{white: float, lum: float}
 */
function casa24_collage_quality( $file ) {
	$src = @imagecreatefromstring( file_get_contents( $file ) ); // phpcs:ignore
	if ( ! $src ) {
		return array( 'white' => 0.0, 'lum' => 255.0 );
	}

	$n     = 48;
	$small = imagecreatetruecolor( $n, $n );
	imagecopyresampled( $small, $src, 0, 0, 0, 0, $n, $n, imagesx( $src ), imagesy( $src ) );
	imagedestroy( $src );

	$edge  = 0;
	$white = 0;
	$lum   = 0;
	$cnt   = 0;
	for ( $y = 0; $y < $n; $y++ ) {
		for ( $x = 0; $x < $n; $x++ ) {
			$rgb = imagecolorat( $small, $x, $y );
			$r   = ( $rgb >> 16 ) & 255;
			$g   = ( $rgb >> 8 ) & 255;
			$b   = $rgb & 255;
			if ( $x < 3 || $y < 3 || $x >= $n - 3 || $y >= $n - 3 ) {
				++$edge;
				if ( $r > 232 && $g > 232 && $b > 232 ) {
					++$white;
				}
			} elseif ( $x > $n / 4 && $x < $n * 3 / 4 && $y > $n / 4 && $y < $n * 3 / 4 ) {
				$lum += 0.299 * $r + 0.587 * $g + 0.114 * $b;
				++$cnt;
			}
		}
	}
	imagedestroy( $small );

	return array(
		'white' => $edge ? $white / $edge : 0.0,
		'lum'   => $cnt ? $lum / $cnt : 255.0,
	);
}

/**
 * Ручные правки подбора по замечаниям владельца (26.10.2026).
 * include — эти товары ставятся первыми; exclude — никогда не берутся.
 *
 * @return array<int, array>
 */
function casa24_collage_overrides() {
	return array(
		// Бидоны, бочки: металлическая канистра вместо рассеивателя для поливалки (тот — не из этого раздела).
		961 => array(
			'include'      => array( 1069224 ),
			'exclude_name' => '/рассеиватель/iu',
		),
		// Двери: сразу видно оба подраздела, контрастные двери (белые на белом
		// фоне терялись). Сверху входные KM003 и KM01, снизу межкомнатные
		// Fortis «орех» и Model 189. Фото у дверей мелкие (178×283) — min 170.
		813  => array(
			'include'   => array( 1055385, 1055398, 1053767, 1055401 ),
			'any_stock' => true,
			'min'       => 170,
		),
		// Сетка для штукатурки: три разных вида; у серпянки 100мм/15см/45м одно
		// и то же фото — берём одно. «Сетка стеклотканная 5×5» — фото с телефона
		// на сером фоне, не берём (отсеивается по белизне фона).
		1381 => array( 'include' => array( 1068363, 1091276, 1068373 ), 'only_include' => true ),
		// Сетка метал, проволока: в наличии ничего нет — фото из каталога, от 170px.
		1379 => array( 'any_stock' => true, 'min' => 170 ),
	);
}

/**
 * Подходящие товары раздела (с подразделами), сгруппированные по подразделу.
 *
 * Если в наличии нет ничего — вызывается с $any_stock = true: фото товара
 * годится для картинки раздела, даже если его сейчас нет на складе.
 *
 * @param WP_Term $top       Раздел верхнего уровня.
 * @param bool    $any_stock Брать и товары не в наличии / скрытые.
 * @param int     $min_px    Минимальная сторона фото.
 * @return array<int, array> [ term_id подраздела => [ [id, file, w, h, name, white, lum, price], ... ] ]
 */
function casa24_collage_candidates( $top, $any_stock = false, $min_px = 300 ) {
	$query = array(
		'status'     => 'publish',
		'limit'      => -1,
		'category'   => array( $top->slug ),
		'return'     => 'ids',
	);
	if ( ! $any_stock ) {
		$query['visibility']   = 'visible';
		$query['stock_status'] = 'instock';
	}
	$ids = wc_get_products( $query );

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
		if ( ! $size || min( $size[0], $size[1] ) < $min_px ) {
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

		$quality = casa24_collage_quality( $file );

		$groups[ $group ][] = array(
			'id'    => $id,
			'file'  => $file,
			'w'     => $size[0],
			'h'     => $size[1],
			'name'  => $product->get_name(),
			'white' => $quality['white'],
			'lum'   => $quality['lum'],
			'price' => (float) $product->get_price(),
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
function casa24_collage_pick( $groups, $overrides = array() ) {
	$include = isset( $overrides['include'] ) ? $overrides['include'] : array();
	$exclude = isset( $overrides['exclude'] ) ? $overrides['exclude'] : array();
	$picked  = array();

	// Товары, выбранные владельцем, — первыми (даже если не прошли фильтры
	// наличия/размера); исключённые — убрать.
	foreach ( $include as $inc_id ) {
		$product = wc_get_product( $inc_id );
		$file    = $product && $product->get_image_id() ? get_attached_file( $product->get_image_id() ) : '';
		if ( $file && file_exists( $file ) ) {
			$size     = getimagesize( $file );
			$quality  = casa24_collage_quality( $file );
			$picked[] = array(
				'id'    => $inc_id,
				'file'  => $file,
				'w'     => $size[0],
				'h'     => $size[1],
				'name'  => $product->get_name(),
				'white' => $quality['white'],
				'lum'   => $quality['lum'],
				'price' => (float) $product->get_price(),
			);
		}
	}
	$include = array();
	foreach ( $groups as $gid => $items ) {
		foreach ( $items as $k => $item ) {
			$bad_name = isset( $overrides['exclude_name'] ) && preg_match( $overrides['exclude_name'], $item['name'] );
			if ( in_array( $item['id'], $exclude, true ) || $bad_name ) {
				unset( $groups[ $gid ][ $k ] );
			} elseif ( in_array( $item['id'], $include, true ) ) {
				$picked[] = $item;
				unset( $groups[ $gid ][ $k ] );
			}
		}
		$groups[ $gid ] = array_values( $groups[ $gid ] );
		if ( ! $groups[ $gid ] ) {
			unset( $groups[ $gid ] );
		}
	}

	// Фото с телефона на сером/цветном фоне (белого по краю < 55%) — внутри
	// подраздела только если в нём нет фото на белом. Подраздел целиком не
	// выкидываем: коллаж должен показывать разные подразделы (30.10.2026).
	foreach ( $groups as $gid => $items ) {
		$ok = array_values( array_filter( $items, function ( $i ) { return $i['white'] >= 0.55; } ) );
		if ( $ok ) {
			$groups[ $gid ] = $ok;
		}
	}

	uasort(
		$groups,
		function ( $a, $b ) {
			return count( $b ) - count( $a );
		}
	);

	// В подразделе — лучшее фото: белый фон, контрастный товар (тёмный на
	// белом виден лучше), крупный размер. Одинаковые названия без размеров
	// («Серпянка 100мм*20», «…*45») — не больше одного на коллаж.
	foreach ( $groups as &$items ) {
		usort(
			$items,
			function ( $a, $b ) {
				$sa = $a['white'] * 2 + ( 255 - $a['lum'] ) / 255 + min( $a['w'], $a['h'] ) / 2000;
				$sb = $b['white'] * 2 + ( 255 - $b['lum'] ) / 255 + min( $b['w'], $b['h'] ) / 2000;
				return $sb <=> $sa;
			}
		);
	}
	unset( $items );

	$seen = array();
	foreach ( $picked as $p ) {
		$seen[ casa24_collage_name_key( $p['name'] ) ] = true;
	}

	for ( $round = 0; count( $picked ) < 4 && $round < 50; $round++ ) {
		$added = false;
		foreach ( $groups as $items ) {
			if ( count( $picked ) >= 4 ) {
				break;
			}
			// В каждом подразделе берём следующее фото с новым «видом» товара.
			while ( isset( $items[ $round ] ) ) {
				$key = casa24_collage_name_key( $items[ $round ]['name'] );
				if ( ! isset( $seen[ $key ] ) ) {
					$seen[ $key ] = true;
					$picked[]     = $items[ $round ];
					$added        = true;
				}
				break;
			}
		}
		if ( ! $added && $round > 10 ) {
			break;
		}
	}

	if ( ! empty( $overrides['only_include'] ) ) {
		return array_slice( $picked, 0, 4 );
	}

	// Видов меньше 4 (в разделе один-два вида товара) — добираем фото с другим
	// «отпечатком» (белизна фона + яркость), чтобы не было одинаковых картинок.
	$prints = array();
	foreach ( $picked as $p ) {
		$prints[ round( $p['white'] * 20 ) . ':' . round( $p['lum'] / 8 ) ] = true;
	}
	foreach ( $groups as $items ) {
		foreach ( $items as $item ) {
			if ( count( $picked ) >= 4 ) {
				break 2;
			}
			$print = round( $item['white'] * 20 ) . ':' . round( $item['lum'] / 8 );
			if ( ! isset( $prints[ $print ] ) && ! in_array( $item['id'], wp_list_pluck( $picked, 'id' ), true ) ) {
				$prints[ $print ] = true;
				$picked[]         = $item;
			}
		}
	}

	return array_slice( $picked, 0, 4 );
}

/**
 * «Вид» товара для отсечения почти одинаковых фото: первые два слова названия
 * без цифр и размеров («Серпянка 100мм*20 Premium» → «серпянка premium»).
 *
 * @param string $name Название товара.
 * @return string
 */
function casa24_collage_name_key( $name ) {
	$words = preg_split( '/\s+/u', mb_strtolower( preg_replace( '/[0-9][0-9.,*xх×\/a-zа-я]*|[^\p{L}\s]/u', ' ', $name ) ) );
	$words = array_values( array_filter( $words, function ( $w ) { return mb_strlen( $w ) > 2; } ) );
	return implode( ' ', array_slice( $words, 0, 2 ) );
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

// ID в аргументах — любые разделы (и группы второго уровня с подкатегориями),
// без ID — все разделы верхнего уровня.
$casa24_ids = array_map( 'intval', array_slice( $args, 1 ) );
$tops       = get_terms(
	$casa24_ids && 'rollback' !== $casa24_mode
		? array( 'taxonomy' => 'product_cat', 'include' => $casa24_ids, 'hide_empty' => false )
		: array(
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

$casa24_only = array_map( 'intval', array_slice( $args, 1 ) );
$overrides   = casa24_collage_overrides();

foreach ( $tops as $top ) {
	if ( $casa24_only && ! in_array( $top->term_id, $casa24_only, true ) ) {
		continue;
	}

	$opt    = isset( $overrides[ $top->term_id ] ) ? $overrides[ $top->term_id ] : array();
	$min_px = isset( $opt['min'] ) ? $opt['min'] : 300;
	$groups = casa24_collage_candidates( $top, ! empty( $opt['any_stock'] ), $min_px );
	// Подкатегории без подходящих товаров в наличии — добрать из остальных её
	// товаров (в т. ч. не в наличии, фото от 140px — у многих групп 1С фото мелкие),
	// чтобы коллаж показывал все подкатегории, а не одну.
	foreach ( casa24_collage_candidates( $top, true, 140 ) as $gid => $items ) {
		if ( empty( $groups[ $gid ] ) ) {
			$groups[ $gid ] = $items;
		}
	}

	if ( 'report' === $casa24_mode ) {
		echo '== ' . $top->term_id . ' ' . $top->name . "\n";
		foreach ( $groups as $gid => $items ) {
			$g = get_term( $gid, 'product_cat' );
			foreach ( $items as $i ) {
				printf( "  [%s] #%d white=%.2f lum=%3d %dx%d %s\n", $g ? $g->name : $gid, $i['id'], $i['white'], $i['lum'], $i['w'], $i['h'], $i['name'] );
			}
		}
		continue;
	}

	$picked = casa24_collage_pick( $groups, $opt );

	echo $top->term_id . "\t" . $top->name . "\t" . count( $picked ) . ' фото: ' . implode( ' | ', wp_list_pluck( $picked, 'name' ) ) . "\n";

	if ( 'apply' !== $casa24_mode || ! $picked ) {
		continue;
	}

	// Дата сборки в имени файла — после пересборки браузеры и кэш LiteSpeed не
	// показывают старый коллаж под тем же адресом.
	$dest = $dir . '/category-' . $top->term_id . '-' . gmdate( 'YmdHis' ) . '.webp';
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

		// Удалить прежний файл коллажа и его уменьшенные копии.
		$old_file = get_attached_file( $attachment_id );
		$old_meta = wp_get_attachment_metadata( $attachment_id );
		if ( $old_file && $old_file !== $dest && file_exists( $old_file ) ) {
			foreach ( isset( $old_meta['sizes'] ) ? $old_meta['sizes'] : array() as $sz ) {
				@unlink( path_join( dirname( $old_file ), $sz['file'] ) ); // phpcs:ignore
			}
			@unlink( $old_file ); // phpcs:ignore
		}

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
