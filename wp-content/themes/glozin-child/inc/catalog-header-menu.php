<?php
/**
 * Меню «Каталог» в шапке — из нового дерева каталога (31.10.2026).
 *
 * Сами WP-меню остались от подрядчика и ведут на старые группы 1С, которые
 * теперь отдают 301 (mu-plugin casa24-catalog-map.php). Вместо правки сотен
 * пунктов вручную вывод подменяется деревом casa24_catalog_menu_tree() —
 * оно всегда совпадает с витриной (пустые разделы уже отброшены), на RU и RO.
 *
 * - Компьютер: сетка Glozin mega-menu у пункта с классом
 *   glozin-primary-products-menu («Каталог» в «Каталог и акции RU/RO») —
 *   разделы колонками, как раньше были группы 1С.
 * - Телефон: меню панели-«гамбургера» (тема: header_mobile_menu_primary_menu,
 *   «category menu mob») — разделы → группы → подгруппы.
 *
 * Без mu-plugin выводятся исходные WP-меню. Откат — убрать подключение файла
 * в functions.php, сами меню не менялись.
 *
 * @package Glozin_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Новое дерево каталога готово для вывода в меню?
 *
 * @return bool
 */
function glozin_child_header_menu_ready() {
	return function_exists( 'casa24_catalog_menu_tree' ) && casa24_catalog_is_ready() && casa24_catalog_menu_tree();
}

/**
 * Сетка mega-menu пункта «Каталог»: разделы нового дерева в 5 колонок.
 *
 * @param string   $output Готовый вывод (пусто — Glozin выведет свою сетку).
 * @param WP_Post  $item   Пункт меню.
 * @return string
 */
function glozin_child_header_menu_grid( $output, $item ) {
	if ( '' !== $output || ! in_array( 'glozin-primary-products-menu', (array) $item->classes, true ) || ! glozin_child_header_menu_ready() ) {
		return $output;
	}

	$sections = casa24_catalog_menu_tree();
	$columns  = array_chunk( $sections, (int) ceil( count( $sections ) / 5 ) );

	$output  = '<ul class="sub-menu mega-menu mega-menu--grid">';
	$output .= '<li class="mega-menu-container container-xxl">';
	$output .= '<div class="mega-menu__row">';

	foreach ( $columns as $column ) {
		$output .= '<ul class="mega-menu__column" style="flex: 1;">';
		foreach ( $column as $section ) {
			$output .= sprintf(
				'<li class="menu-item menu-item-catalog-%1$s"><a href="%2$s">%3$s</a></li>',
				esc_attr( $section['key'] ),
				esc_url( $section['url'] ),
				esc_html( $section['name'] )
			);
		}
		$output .= '</ul>';
	}

	$output .= '</div></li></ul>';

	return $output;
}
add_filter( 'glozin_addons_mega_menu_grid_output', 'glozin_child_header_menu_grid', 10, 2 );

/**
 * Пункты меню панели телефона: вложенные списки в разметке wp_nav_menu.
 *
 * @param array[] $nodes Узлы casa24_catalog_menu_tree().
 * @param int     $depth Уровень (0 — разделы).
 * @return string
 */
function glozin_child_header_menu_items( $nodes, $depth = 0 ) {
	$current = is_product_category() && defined( 'CASA24_CATALOG_META' ) ? (string) get_term_meta( get_queried_object_id(), CASA24_CATALOG_META, true ) : '';
	$indent  = str_repeat( "\t", $depth );
	$output  = '';

	foreach ( $nodes as $node ) {
		$classes = array( 'menu-item', 'menu-item-type-taxonomy', 'menu-item-object-product_cat' );
		if ( $node['children'] ) {
			$classes[] = 'menu-item-has-children';
		}
		if ( '' !== $current && $current === $node['key'] ) {
			$classes[] = 'current-menu-item';
		}
		$classes[] = 'menu-item-catalog-' . $node['key'];

		$output .= sprintf(
			'%1$s<li id="menu-item-catalog-%2$s" class="%3$s"><a href="%4$s" data-title="%5$s">%6$s</a>',
			$indent,
			esc_attr( $node['key'] ),
			esc_attr( implode( ' ', $classes ) ),
			esc_url( $node['url'] ),
			esc_attr( $node['name'] ),
			esc_html( $node['name'] )
		);

		if ( $node['children'] ) {
			$output .= "\n" . $indent . '<ul class="sub-menu">' . "\n";
			$output .= glozin_child_header_menu_items( $node['children'], $depth + 1 );
			$output .= $indent . '</ul>' . "\n" . $indent;
		}

		$output .= "</li>\n";
	}

	return $output;
}

/**
 * Меню панели телефона «category menu mob» — новым деревом.
 *
 * Тема выводит его через wp_nav_menu( 'menu' => header_mobile_menu_primary_menu,
 * 'theme_location' => '__no_such_location' ); WPML подставляет slug меню языка.
 *
 * @param string|null $output Готовый вывод.
 * @param stdClass    $args   Аргументы wp_nav_menu().
 * @return string|null
 */
function glozin_child_header_menu_mobile( $output, $args ) {
	if ( null !== $output || '__no_such_location' !== $args->theme_location || ! class_exists( '\Glozin\Helper' ) ) {
		return $output;
	}

	// WPML подменяет 'menu' (slug, id или объект) — сравниваем сами меню, приведённые к RU.
	$wanted = wp_get_nav_menu_object( \Glozin\Helper::get_option( 'header_mobile_menu_primary_menu' ) );
	$menu   = wp_get_nav_menu_object( $args->menu );
	if ( ! $wanted || ! $menu || ! glozin_child_header_menu_ready() ) {
		return $output;
	}
	$lang = casa24_catalog_default_lang();
	if ( (int) apply_filters( 'wpml_object_id', $menu->term_id, 'nav_menu', true, $lang ) !== (int) apply_filters( 'wpml_object_id', $wanted->term_id, 'nav_menu', true, $lang ) ) {
		return $output;
	}

	$items = '<ul id="menu-casa24-catalog" class="' . esc_attr( $args->menu_class ) . '">' . glozin_child_header_menu_items( casa24_catalog_menu_tree() ) . '</ul>';

	if ( $args->container ) {
		$tag   = in_array( $args->container, array( 'div', 'nav' ), true ) ? $args->container : 'div';
		$items = '<' . $tag . ' class="' . esc_attr( $args->container_class ) . '">' . $items . '</' . $tag . '>';
	}

	return $items;
}
add_filter( 'pre_wp_nav_menu', 'glozin_child_header_menu_mobile', 10, 2 );
