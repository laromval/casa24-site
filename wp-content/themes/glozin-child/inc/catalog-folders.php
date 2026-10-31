<?php
/**
 * Каталог «папками» (обратная связь владельца 29.10.2026).
 *
 * Раздел, у которого есть подкатегории с товарами, показывает их плитками с
 * картинками и под ними «Хиты продаж» этого раздела — без общего списка всех
 * товаров вперемешку и без фильтра. Слева — список подкатегорий со вложенными.
 * Конечная категория (без подкатегорий) — как раньше: товары и фильтр.
 * Корень магазина (/shop/) — так же, плитки разделов.
 *
 * Дерево — новое дерево каталога из mu-plugin casa24-catalog-map.php
 * (casa24_catalog_menu_tree(): пустые подкатегории в нём уже отброшены).
 * Без mu-plugin всё работает как в Glozin.
 *
 * Список товаров остаётся там, где по адресу ждут именно его: со 2-й
 * страницы, с фильтром/сортировкой в адресе, в поиске.
 *
 * Вывод — в woocommerce/archive-product.php (копия шаблона WooCommerce).
 *
 * @package Glozin_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * В адресе есть фильтр, сортировка и т.п. (метки рекламы не в счёт)?
 *
 * @return bool
 */
function glozin_child_folders_has_query_args() {
	foreach ( array_keys( $_GET ) as $key ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! preg_match( '/^(utm_|gclid$|fbclid$|yclid$|_gl$|lang$)/', (string) $key ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Найти узел дерева по ключу каталога.
 *
 * @param array[] $nodes Узлы casa24_catalog_menu_tree().
 * @param string  $key   Ключ (_casa24_catalog_key).
 * @return array|null
 */
function glozin_child_folders_find( $nodes, $key ) {
	foreach ( $nodes as $node ) {
		if ( $node['key'] === $key ) {
			return $node;
		}
		$found = glozin_child_folders_find( $node['children'], $key );
		if ( $found ) {
			return $found;
		}
	}

	return null;
}

/**
 * Текущая страница — «папка»? Тогда узел дерева с её подкатегориями.
 *
 * Вызывать не раньше хука wp (нужен разобранный запрос).
 *
 * @return array|null [ 'key', 'name', 'children' => [ ... ] ]
 */
function glozin_child_folders_node() {
	static $node = false;

	if ( false !== $node ) {
		return $node;
	}
	$node = null;

	if ( ! function_exists( 'casa24_catalog_menu_tree' ) || ! casa24_catalog_is_ready() || is_search() || is_paged() || glozin_child_folders_has_query_args() ) {
		return $node;
	}

	$tree = casa24_catalog_menu_tree();

	if ( is_product_category() ) {
		$key   = (string) get_term_meta( get_queried_object_id(), CASA24_CATALOG_META, true );
		$found = '' === $key ? null : glozin_child_folders_find( $tree, $key );
		if ( $found && ! empty( $found['children'] ) ) {
			$node = $found;
		}
	} elseif ( function_exists( 'glozin_child_is_shop_top_level' ) && glozin_child_is_shop_top_level() && $tree ) {
		$node = array(
			'key'      => '',
			'name'     => '',
			'children' => $tree,
		);
	}

	return $node;
}

/**
 * ID категорий нового дерева на текущем языке (ключ → term_id).
 *
 * @return int[]
 */
function glozin_child_folders_term_ids() {
	$lang = (string) apply_filters( 'wpml_current_language', casa24_catalog_default_lang() );
	$ids  = casa24_catalog_term_ids( $lang );

	return $ids ? $ids : casa24_catalog_term_ids( casa24_catalog_default_lang() );
}

/**
 * На «папке»: без карусели подкатегорий сверху (её заменяют плитки) и без
 * фильтра — контент на всю ширину.
 */
function glozin_child_folders_setup() {
	if ( ! glozin_child_folders_node() ) {
		return;
	}

	remove_action( 'glozin_after_site_content_open', 'glozin_child_custom_top_categories', 15 );
	add_filter( 'glozin_get_sidebar', '__return_false', 20 );
	add_filter(
		'glozin_site_layout',
		function () {
			return 'no-sidebar';
		},
		60
	);
	add_filter(
		'body_class',
		function ( $classes ) {
			$classes[] = 'casa24-folders-page';
			return $classes;
		}
	);
	add_action( 'wp_footer', 'glozin_child_folders_nav_script' );
}
add_action( 'wp', 'glozin_child_folders_setup', 30 );

/**
 * ID «хитов продаж» раздела: больше всего продаж, при равенстве — новее.
 * Только видимые в каталоге товары с фото (и в наличии, если так настроен
 * магазин). Кэш на 6 часов — запрос по большому разделу не дешёвый.
 * Ключ кэша — по языку и разделу (у RO-раздела свой term_id).
 *
 * @param int $term_id Категория (0 — весь магазин).
 * @param int $limit   Сколько товаров.
 * @return int[]
 */
function glozin_child_folders_hit_ids( $term_id, $limit = 8 ) {
	$lang      = (string) apply_filters( 'wpml_current_language', '' );
	$cache_key = 'casa24_hits_' . $lang . '_' . (int) $term_id;
	$ids       = get_transient( $cache_key );

	if ( is_array( $ids ) ) {
		return $ids;
	}

	$tax_query = array();
	if ( $term_id ) {
		$tax_query[] = array(
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => array( (int) $term_id ),
			'include_children' => true,
		);
	}

	// Кандидатов берём с запасом: фото проверяем уже после запроса. У RO-копий
	// товаров _thumbnail_id в базе нет — WPML подставляет фото русского
	// товара на лету, поэтому условие на фото в SQL отсекло бы весь RO.
	$args = array(
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'posts_per_page'         => $limit * 5,
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'tax_query'              => WC()->query->get_tax_query( $tax_query ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	);

	// Сначала то, что продавалось. Счётчик total_sales есть далеко не у всех
	// товаров (у товаров из 1С его нет, пока их не купили), поэтому остаток
	// добираем новыми товарами раздела.
	$sold               = $args;
	$sold['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'sales' => array(
			'key'     => 'total_sales',
			'value'   => 0,
			'compare' => '>',
			'type'    => 'NUMERIC',
		),
	);
	$sold['orderby']    = array(
		'sales' => 'DESC',
		'date'  => 'DESC',
	);
	$candidates = ( new WP_Query( $sold ) )->posts;

	$fresh                 = $args;
	$fresh['post__not_in'] = $candidates;
	$fresh['orderby']      = 'date';
	$fresh['order']        = 'DESC';
	$candidates            = array_merge( $candidates, ( new WP_Query( $fresh ) )->posts );

	$ids = array();
	foreach ( $candidates as $id ) {
		if ( get_post_thumbnail_id( $id ) ) {
			$ids[] = (int) $id;
		}
		if ( count( $ids ) >= $limit ) {
			break;
		}
	}

	set_transient( $cache_key, $ids, 6 * HOUR_IN_SECONDS );

	return $ids;
}

/**
 * Картинка категории (thumbnail_id) по узлу дерева.
 *
 * @param array $node Узел casa24_catalog_menu_tree().
 * @param int[] $ids  Ключ → term_id.
 * @return int
 */
function glozin_child_folders_thumb( $node, $ids ) {
	return empty( $ids[ $node['key'] ] ) ? 0 : (int) get_term_meta( $ids[ $node['key'] ], 'thumbnail_id', true );
}

/**
 * Отобранные фото товаров категории для коллажа (мета _casa24_collage_imgs,
 * ставит tools/category-collages/collage-picks-apply.php).
 *
 * @param array $node Узел casa24_catalog_menu_tree().
 * @param int[] $ids  Ключ → term_id.
 * @return int[]
 */
function glozin_child_folders_picks( $node, $ids ) {
	$meta = empty( $ids[ $node['key'] ] ) ? '' : (string) get_term_meta( $ids[ $node['key'] ], '_casa24_collage_imgs', true );

	return array_values( array_filter( array_map( 'intval', explode( ',', $meta ) ) ) );
}

/**
 * Плитка подкатегории.
 *
 * Если у неё есть свои подгруппы — коллаж 2×2 из фото товаров самых больших
 * подгрупп (обратная связь владельца 29.10.2026: по фото должно быть понятно,
 * что внутри; только хорошие фото на белом фоне, товар без упаковки — их
 * отбирали вручную, см. collage-picks-apply.php). Сначала по одному фото из
 * каждой подгруппы, если подгрупп с фото меньше четырёх — вторые фото тех же.
 * Меньше двух фото — одна картинка.
 *
 * @param array $child Узел дерева.
 * @param int[] $ids   Ключ → term_id.
 * @return string
 */
function glozin_child_folders_tile( $child, $ids ) {
	$thumbs = array();

	if ( $child['children'] ) {
		$subs = $child['children'];
		usort(
			$subs,
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);
		// Подгруппа без отобранных фото — её собственная картинка (фото товара, не
		// другой коллаж), иначе она выпадает и коллаж показывает одну подгруппу
		// (обратная связь 30.10.2026: «фото взяты только из одной категории»).
		$picks = array_filter(
			array_map(
				function ( $sub ) use ( $ids ) {
					$list = glozin_child_folders_picks( $sub, $ids );
					if ( ! $list ) {
						$own = glozin_child_folders_thumb( $sub, $ids );
						if ( $own && ! get_post_meta( $own, '_casa24_collage_for', true ) ) {
							$list = array( $own );
						}
					}
					return $list;
				},
				$subs
			)
		);
		for ( $round = 0; $round < 3 && count( $thumbs ) < 4; $round++ ) {
			foreach ( $picks as $list ) {
				if ( isset( $list[ $round ] ) && ! in_array( $list[ $round ], $thumbs, true ) ) {
					$thumbs[] = $list[ $round ];
				}
				if ( count( $thumbs ) >= 4 ) {
					break;
				}
			}
		}
	}
	if ( count( $thumbs ) < 2 ) {
		$thumbs = array_values( array_unique( array_merge( $thumbs, glozin_child_folders_picks( $child, $ids ) ) ) );
	}

	$image = '';
	if ( count( $thumbs ) >= 2 && $child['children'] ) {
		// Три фото — первое крупно слева, два справа друг над другом.
		$image = '<span class="casa24-folders__collage casa24-folders__collage--' . count( $thumbs ) . '">';
		foreach ( $thumbs as $thumb ) {
			$image .= '<span class="casa24-folders__collage-cell">' . wp_get_attachment_image( $thumb, 'medium', false, array( 'alt' => '' ) ) . '</span>';
		}
		$image .= '</span>';
	} else {
		$thumb = $thumbs ? $thumbs[0] : glozin_child_folders_thumb( $child, $ids );
		$image = $thumb ? wp_get_attachment_image( $thumb, 'medium', false, array( 'alt' => $child['name'] ) ) : '';
	}
	if ( ! $image ) {
		$image = wc_placeholder_img( 'medium' );
	}

	return sprintf(
		'<li class="casa24-folders__tile"><a href="%s"><span class="casa24-folders__tile-image">%s</span><span class="casa24-folders__tile-name">%s</span></a></li>',
		esc_url( $child['url'] ),
		$image,
		esc_html( $child['name'] )
	);
}

/**
 * Вывод «папки»: слева список подкатегорий, справа плитки и хиты продаж.
 */
function glozin_child_folders_render() {
	$node = glozin_child_folders_node();
	if ( ! $node ) {
		return;
	}

	$ids = glozin_child_folders_term_ids();

	echo '<div class="casa24-folders">';

	// Слева: подкатегории и их подгруппы (как оглавление раздела), под ними — «Рекомендуемые товары».
	echo '<div class="casa24-folders__side">';
	echo '<nav class="casa24-folders__nav" aria-label="' . esc_attr__( 'Подкатегории', 'glozin-child' ) . '">';
	foreach ( $node['children'] as $child ) {
		echo '<div class="casa24-folders__nav-group">';
		printf( '<a class="casa24-folders__nav-title" href="%s">%s</a>', esc_url( $child['url'] ), esc_html( $child['name'] ) );
		if ( $child['children'] ) {
			echo '<ul class="casa24-folders__nav-list">';
			foreach ( $child['children'] as $i => $grandchild ) {
				printf(
					'<li%s><a href="%s">%s</a></li>',
					$i >= 6 ? ' class="is-extra"' : '',
					esc_url( $grandchild['url'] ),
					esc_html( $grandchild['name'] )
				);
			}
			echo '</ul>';
			if ( count( $child['children'] ) > 6 ) {
				printf(
					'<button type="button" class="casa24-folders__nav-more" data-less="%s">%s</button>',
					esc_attr__( 'Свернуть', 'glozin-child' ),
					esc_html__( 'Показать ещё…', 'glozin-child' )
				);
			}
		}
		echo '</div>';
	}
	echo '</nav>';
	glozin_child_folders_recommended();
	echo '</div>';

	echo '<div class="casa24-folders__main">';

	// Плитки подкатегорий.
	echo '<ul class="casa24-folders__grid">';
	foreach ( $node['children'] as $child ) {
		echo glozin_child_folders_tile( $child, $ids ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- экранировано внутри.
	}
	echo '</ul>';

	// Хиты продаж раздела — обычные карточки товаров Glozin.
	$hit_ids = glozin_child_folders_hit_ids( is_product_category() ? get_queried_object_id() : 0 );
	if ( $hit_ids ) {
		$hits = new WP_Query(
			array(
				'post_type'      => 'product',
				'post__in'       => $hit_ids,
				'orderby'        => 'post__in',
				'posts_per_page' => count( $hit_ids ),
				'no_found_rows'  => true,
			)
		);

		echo '<section class="casa24-folders__hits">';
		printf(
			'<h2 class="casa24-folders__hits-title">%s</h2>',
			is_product_category() ? esc_html__( 'Хиты продаж в этой категории', 'glozin-child' ) : esc_html__( 'Хиты продаж', 'glozin-child' )
		);

		wc_set_loop_prop( 'name', 'casa24_hits' );
		wc_set_loop_prop( 'columns', 4 );
		wc_set_loop_prop( 'is_paginated', false );

		woocommerce_product_loop_start();
		while ( $hits->have_posts() ) {
			$hits->the_post();
			wc_get_template_part( 'content', 'product' );
		}
		woocommerce_product_loop_end();
		wp_reset_postdata();

		echo '</section>';
	}

	echo '</div></div>';
}

/**
 * «Рекомендуемые товары» (избранные) — тот же виджет Glozin и с теми же настройками,
 * что в панели фильтров конечного раздела (Внешний вид → Виджеты → Catalog Filters Sidebar).
 */
function glozin_child_folders_recommended() {
	$class = '\Glozin\Addons\Widgets\Products_List';
	if ( ! class_exists( $class ) ) {
		return;
	}

	$instances = get_option( 'widget_glozin-products-list', array() );
	$instance  = isset( $instances[1] ) ? $instances[1] : array(
		'title' => 'Recommended products',
		'limit' => 6,
		'type'  => 'featured_products',
	);

	// Glozin регистрирует виджет объектом, поэтому the_widget( $class ) его не находит — вызываем напрямую.
	$widget = new $class();
	$widget->widget(
		array(
			'before_widget' => '<div class="widget glozin-products-list-widget casa24-folders__recommended">',
			'after_widget'  => '</div>',
			'before_title'  => '<h2 class="widget-title casa24-folders__recommended-title">',
			'after_title'   => '</h2>',
		),
		$instance
	);
}

/**
 * «Показать ещё…» в списке подкатегорий слева.
 */
function glozin_child_folders_nav_script() {
	?>
	<script>
	document.addEventListener('click', function (e) {
		var button = e.target.closest && e.target.closest('.casa24-folders__nav-more');
		if (!button) {
			return;
		}
		var group = button.parentNode;
		var open = group.classList.toggle('is-open');
		if (!button.dataset.more) {
			button.dataset.more = button.textContent;
		}
		button.textContent = open ? button.dataset.less : button.dataset.more;
	});
	</script>
	<?php
}

/**
 * В конечной категории пункт «Категории» фильтра Glozin не фильтрует товары
 * на месте (AJAX), а открывает страницу выбранной категории: иначе, нажав в
 * фильтре большой раздел, покупатель снова получает «кашу» из всех товаров.
 */
function glozin_child_folders_filter_links() {
	if ( glozin_child_folders_node() || ! function_exists( 'casa24_catalog_menu_tree' ) || ! ( is_shop() || is_product_category() ) || is_search() ) {
		return;
	}

	$urls  = array();
	$ids   = glozin_child_folders_term_ids();
	$stack = casa24_catalog_menu_tree();
	while ( $stack ) {
		$node = array_pop( $stack );
		$term = empty( $ids[ $node['key'] ] ) ? null : get_term( $ids[ $node['key'] ], 'product_cat' );
		if ( $term && ! is_wp_error( $term ) && $node['url'] ) {
			$urls[ $term->slug ] = $node['url'];
		}
		foreach ( $node['children'] as $child ) {
			$stack[] = $child;
		}
	}
	if ( ! $urls ) {
		return;
	}
	?>
	<script>
	(function () {
		var urls = <?php echo wp_json_encode( $urls ); ?>;
		// В фазе захвата — раньше обработчика фильтра Glozin.
		document.addEventListener('click', function (e) {
			var name = e.target.closest && e.target.closest('.products-filter__filter.product_cat .products-filter__option-name');
			var item = name && name.closest('[data-value]');
			var url = item && urls[item.getAttribute('data-value')];
			if (!url) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			window.location.href = url;
		}, true);
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'glozin_child_folders_filter_links' );
