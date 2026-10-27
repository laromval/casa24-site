<?php
/**
 * Strip search parameter from product category archive queries.
 * Prevents the search query from persisting when navigating
 * from search results to a product category.
 */

// Fix the query so correct products are shown
add_action( 'pre_get_posts', 'glozin_child_strip_search_from_category_query' );
function glozin_child_strip_search_from_category_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( ! empty( $query->get( 'product_cat' ) ) && ! empty( $query->get( 's' ) ) ) {
		$query->set( 's', '' );
	}
}

// Fix the URL so 's' is removed from the address bar
add_action( 'wp_footer', 'glozin_child_strip_search_from_category_js' );
function glozin_child_strip_search_from_category_js() {
	if ( is_admin() ) {
		return;
	}
	?>
	<script>
	(function() {
		function stripSearchParam() {
			var url = new URL(window.location);
			if (url.searchParams.has('s')) {
				url.searchParams.delete('s');
				window.history.replaceState({}, '', url);
			}
		}

		// On initial page load
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', stripSearchParam);
		} else {
			stripSearchParam();
		}

		// After glozin AJAX filter updates the URL
		jQuery(document.body).on('glozin_products_filter_request_success', function() {
			setTimeout(stripSearchParam, 50);
		});

		// After glozin AJAX load more / pagination
		jQuery(document.body).on('glozin_get_products_ajax_loaded', function() {
			setTimeout(stripSearchParam, 50);
		});
	})();
	</script>
	<?php
}
