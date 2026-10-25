<?php
/**
 * Barcode search for glozin-addons AJAX search.
 *
 * The glozin-addons plugin builds its own SQL query for AJAX search
 * and does NOT include global_unique_id (barcode) in it.
 * No filters are provided to modify the query.
 *
 * Solution: fork the Posts class with barcode support, then swap
 * the AJAX handler via remove_action / add_action.
 *
 * @package Glozin Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Forked Posts class ──────────────────────────────────────────── */

class Glozin_Child_Barcode_Search_Posts {

	protected static $instance = null;
	protected static $posts    = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function get_products_base() {
		if ( isset( self::$posts ) ) {
			return self::$posts;
		}
		global $wpdb;

		$products_ids = array();
		$output       = array();
		$keyword      = trim( $_POST['term'] );

		$query_var = $this->get_query_var();
		$query     = $wpdb->get_results( $query_var );
		self::$posts = array();

		$use_wpml     = defined( 'ICL_SITEPRESS_VERSION' );
		$current_lang = null;
		if ( $use_wpml ) {
			$current_lang = apply_filters( 'wpml_current_language', null );
			if ( empty( $current_lang ) || $current_lang === 'all' ) {
				$current_lang = apply_filters( 'wpml_default_language', null );
			}
		}

		foreach ( $query as $post ) {
			if ( 'product' !== $post->post_type ) {
				continue;
			}
			if ( $use_wpml ) {
				$post_lang_details = apply_filters( 'wpml_post_language_details', null, $post->ID );
				if ( ! is_array( $post_lang_details ) || empty( $post_lang_details['language_code'] ) ) {
					continue;
				}
				if ( $post_lang_details['language_code'] !== $current_lang ) {
					continue;
				}
			}
			$products_ids[] = $post->ID;
		}

		ob_start();
		wc_setup_loop( array( 'columns' => 5, 'name' => 'ajax_search_popular_products' ) );
		\Glozin\Addons\Modules\Advanced_Search\Helper::get_template_loop( $products_ids );
		$output['products'] = ob_get_clean();

		$output['view_all'] = sprintf(
			'<div class="results-content-view-all-results w-100 text-center mt-30"><a href="%s" class="gz-button gz-button-hover-effect">%s</a></div>',
			esc_url( home_url( '/' ) . '?s=' . $keyword . '&post_type=product' ),
			esc_html__( 'View all Results', 'glozin-addons' ),
		);

		return $output;
	}

	/**
	 * Build the SQL query.
	 *
	 * IDENTICAL to the original glozin-addons Posts::get_query_var()
	 * except for the added global_unique_id LIKE clause.
	 */
	private function get_query_var() {
		global $wpdb;

		$result_number = isset( $_POST['ajax_search_number'] ) ? intval( $_POST['ajax_search_number'] ) : 0;
		$keyword       = trim( $_POST['term'] );

		$query_var    = '';
		$fields       = 'posts.ID, posts.post_type';
		$search_string = '%' . $keyword . '%';

		if ( get_option( 'glozin_ajax_search_products', 'yes' ) == 'yes' ) {
			$sku_join = $sku_where = $product_by = $post_status = $visibility_query = '';
			$sku_join = " LEFT JOIN {$wpdb->wc_product_meta_lookup} wc_product_meta_lookup ON posts.ID = wc_product_meta_lookup.product_id ";

			$product_visibility_terms = wc_get_product_visibility_term_ids();
			$excluded_terms           = array();

			if ( isset( $product_visibility_terms['exclude-from-search'] ) ) {
				$excluded_terms[] = $product_visibility_terms['exclude-from-search'];
			}

			if ( ! empty( $excluded_terms ) ) {
				$placeholders    = implode( ',', array_fill( 0, count( $excluded_terms ), '%d' ) );
				$visibility_join = "
					LEFT JOIN {$wpdb->term_relationships} visibility_tr
					ON posts.ID = visibility_tr.object_id
					AND visibility_tr.term_taxonomy_id IN ($placeholders)
				";
				$visibility_query = " AND visibility_tr.object_id IS NULL ";
			}

			if ( get_option( 'glozin_ajax_search_products_by_sku', 'yes' ) == 'yes' ) {
				$sku_where = ' OR ' . $wpdb->prepare( "(wc_product_meta_lookup.sku LIKE %s) ", $search_string );
			}

			/* ── ADDED: barcode search ── */
			$sku_where .= ' OR ' . $wpdb->prepare( "(wc_product_meta_lookup.global_unique_id LIKE %s) ", $search_string );

			if ( get_option( 'glozin_ajax_search_products_by_title', 'yes' ) === 'yes' ) {
				$product_by = $wpdb->prepare( "(posts.post_title LIKE %s) ", $search_string );
			}

			if ( get_option( 'glozin_ajax_search_products_by_content', 'yes' ) === 'yes' ) {
				$product_by .= ! empty( $product_by ) ? ' OR' : '';
				$product_by .= $wpdb->prepare( "(posts.post_content LIKE %s) ", $search_string );
			}

			$product_by = ! empty( $product_by ) ? ' AND (' . $product_by . ')' : '';

			$post_status = is_user_logged_in() && current_user_can( 'read_private_posts' ) ? " OR posts.post_status = 'private'" : "";

			$query_var = "(SELECT {$fields} FROM {$wpdb->posts} as posts"
				. $sku_join
				. $visibility_join .
				"WHERE 1 = 1
				{$product_by}
				AND(
					posts.post_type = 'product'
					AND	( posts.post_status = 'publish' {$post_status} )
				)"
				. $visibility_query
				. $sku_where .
				"ORDER BY posts.post_date DESC
				LIMIT %d
				)";

			if ( ! empty( $excluded_terms ) ) {
				$prepare_args = array_merge( $excluded_terms, array( $result_number ) );
				$query_var    = $wpdb->prepare( $query_var, ...$prepare_args );
			} else {
				$query_var = $wpdb->prepare( $query_var, $result_number );
			}
		}

		if ( get_option( 'glozin_ajax_search_posts', 'yes' ) == 'yes' ) {
			$query_var .= $wpdb->prepare( "
				UNION ALL (SELECT {$fields} FROM {$wpdb->posts} as posts
				WHERE 1 = 1
					AND(posts.post_title LIKE %s)
					AND(
						posts.post_type = 'post'
						AND ( posts.post_status = 'publish' {$post_status} )
					)
				ORDER BY posts.post_date DESC
				LIMIT %d
				)",
				$search_string,
				$result_number
			);
		}

		if ( get_option( 'glozin_ajax_search_pages', 'yes' ) == 'yes' ) {
			$query_var .= $wpdb->prepare( "
				UNION ALL (SELECT {$fields} FROM {$wpdb->posts} as posts
				WHERE 1 = 1
					AND(posts.post_title LIKE %s)
					AND(
						posts.post_type = 'page'
						AND ( posts.post_status = 'publish' {$post_status} )
					)
				ORDER BY posts.post_date DESC
				LIMIT %d
				)",
				$search_string,
				$result_number
			);
		}

		return $query_var;
	}
}

/* ── Main WP_Query barcode search (search results page) ──────────── */

add_action( 'pre_get_posts', 'glozin_child_barcode_search_hook', 11 );
function glozin_child_barcode_search_hook( $query ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	if ( ! $query->is_search() || ! in_array( 'product', (array) $query->get( 'post_type' ) ) ) {
		return;
	}
	if ( get_option( 'glozin_ajax_search_products_by_sku', 'yes' ) !== 'yes' ) {
		return;
	}

	if ( class_exists( 'Glozin\Addons\Modules\Advanced_Search\Catalog' ) ) {
		$catalog_instance = \Glozin\Addons\Modules\Advanced_Search\Catalog::instance();
		remove_filter( 'posts_clauses', array( $catalog_instance, 'product_search_by_sku_query_clauses' ) );
	}

	add_filter( 'posts_clauses', 'glozin_child_barcode_search_clauses', 20 );
}

function glozin_child_barcode_search_clauses( $clauses ) {
	global $wpdb;
	$search_term = get_query_var( 's' );
	if ( ! is_string( $search_term ) || $search_term === '' ) {
		return $clauses;
	}

	$like = '%' . $wpdb->esc_like( $search_term ) . '%';

	$join = $clauses['join'];
	if ( $wpdb->wc_product_meta_lookup && ! str_contains( $join, "LEFT JOIN {$wpdb->wc_product_meta_lookup} wc_product_meta_lookup" ) ) {
		$join .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} wc_product_meta_lookup ON $wpdb->posts.ID = wc_product_meta_lookup.product_id ";
	}

	$where = $clauses['where'];
	$where = preg_replace(
		"/\(\s*{$wpdb->posts}\.post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
		"({$wpdb->posts}.post_title LIKE $1) OR (wc_product_meta_lookup.sku LIKE $1) OR (wc_product_meta_lookup.global_unique_id LIKE $1)",
		$where
	);

	$groupby = $clauses['groupby'];
	$id_group = "{$wpdb->posts}.ID";
	if ( ! strlen( trim( $groupby ) ) ) {
		$groupby = $id_group;
	} elseif ( ! preg_match( "/$id_group/", $groupby ) ) {
		$groupby = $groupby . ', ' . $id_group;
	}

	$clauses['join']    = $join;
	$clauses['where']   = $where;
	$clauses['groupby'] = $groupby;

	return $clauses;
}

/* ── AJAX handler swap ───────────────────────────────────────────── */

add_action( 'wp', 'glozin_child_barcode_search_swap_handler', 99 );

function glozin_child_barcode_search_swap_handler() {
	if ( ! class_exists( 'Glozin\Addons\Modules\Advanced_Search\AJAX_Search' ) ) {
		return;
	}

	$ajax_search_instance = \Glozin\Addons\Modules\Advanced_Search\AJAX_Search::instance();

	remove_action( 'wc_ajax_glozin_instance_search_form', array( $ajax_search_instance, 'instance_search_form' ) );
	add_action( 'wc_ajax_glozin_instance_search_form', 'glozin_child_barcode_search_handler' );
}

/**
 * Our replacement AJAX handler.
 *
 * Copied from AJAX_Search::instance_search_form() — calls our forked
 * Posts class instead of the original.
 */
function glozin_child_barcode_search_handler() {
	$output_products = Glozin_Child_Barcode_Search_Posts::instance()->get_products_base();
	$output_taxonomies = \Glozin\Addons\Modules\Advanced_Search\Helper::get( 'taxonomies' )->get_cats();
	$output_pages      = \Glozin\Addons\Modules\Advanced_Search\Helper::get( 'posts' )->get_pages();
	$output_posts      = \Glozin\Addons\Modules\Advanced_Search\Helper::get( 'posts' )->get_posts();

	$response  = '';
	$_response = '';

	if ( ! empty( $output_taxonomies ) ) {
		$_response .= $output_taxonomies;
	}

	if ( ! empty( $output_pages ) ) {
		$_response .= $output_pages;
	}

	if ( ! empty( $output_posts ) ) {
		$_response .= $output_posts;
	}

	$swiper_options = array(
		'slidesPerView' => array(
			'desktop' => ! empty( $_response ) ? 4 : 5,
			'tablet'  => 3,
			'mobile'  => 1,
		),
		'spaceBetween' => array(
			'desktop' => 20,
			'tablet'  => 20,
			'mobile'  => 15,
		),
	);

	if ( empty( $output_products['products'] ) && empty( $_response ) ) {
		$empty_icon = \Glozin\Addons\Helper::get_svg( 'search-not-found' );
		$response  .= sprintf(
			'<div class="list-item list-item-empty text-center">
				<div class="list-item-empty__icon fs-60 lh-1">%s</div>
				<p class="list-item-empty__text">%s</p>
			</div>',
			$empty_icon,
			esc_html__( "Sorry, we couldn't find any matching results for this search. These popular product might interest you.", 'glozin-addons' )
		);
	} else {
		if ( ! empty( $output_products['products'] ) ) {
			$response .= sprintf( '<div class="results-content-title fs-18 mb-20 heading-letter-spacing lh-normal">%s <span class="text-dark fw-medium">"%s"</span></div>', esc_html__( 'Search for ', 'glozin-addons' ), esc_attr( $_POST['term'] ) );

			if ( ! empty( \Glozin\Addons\Modules\Advanced_Search\Helper::get_suggestions_text() ) ) {
				$response .= \Glozin\Addons\Modules\Advanced_Search\Helper::get_suggestions_text();
			}

			$response .= '<div class="results-content-products glozin-swiper swiper glozin-product-carousel gz-arrows-middle navigation-class--tabletdots navigation-class--mobiledots" data-swiper=' . esc_attr( json_encode( $swiper_options ) ) . ' data-desktop="' . esc_attr( $swiper_options['slidesPerView']['desktop'] ) . '" data-tablet="' . esc_attr( $swiper_options['slidesPerView']['tablet'] ) . '" data-mobile="' . esc_attr( $swiper_options['slidesPerView']['mobile'] ) . '">'
				. $output_products['products']
				. \Glozin\Icon::inline_svg( array( 'icon' => 'icon-next', 'class' => 'swiper-button swiper-button-next' ) )
				. \Glozin\Icon::inline_svg( array( 'icon' => 'icon-back', 'class' => 'swiper-button swiper-button-prev' ) )
				. '<div class="swiper-pagination"></div>'
				. '</div>';

			$response .= $output_products['view_all'];
		}

		if ( ! empty( $_response ) ) {
			$_response = '<div class="results-content__left d-flex flex-column border-top border-md-none pt-30 pt-md-0 gap-30">' . $_response . '</div>';

			if ( empty( $output_products['products'] ) ) {
				$response = '<div class="results-content d-flex flex-column flex-md-row gap-30 list-item-empty">' . $_response . '<div class="results-content__right flex-grow-1">' . $response . '</div></div>';
			} else {
				$response = '<div class="results-content d-flex flex-column flex-md-row gap-30">' . $_response . '<div class="results-content__right flex-grow-1">' . $response . '</div></div>';
			}
		}
	}

	wp_send_json_success( $response );
	die();
}
