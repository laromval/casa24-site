<?php

spl_autoload_register( function ( $class ) {
	if ( false === strpos( $class, 'Glozin' ) ) {
		return;
	}

	$relative_class_name = preg_replace( '/^Glozin\\\\/', '', $class );
	$relative_class_name = strtolower( $relative_class_name );
	$relative_class_name = str_replace( '_', '-', $relative_class_name );
	$relative_class_name = str_replace( '\\', '/', $relative_class_name );

	$file = get_stylesheet_directory() . '/inc/' . $relative_class_name . '.php';

	if ( is_readable( $file ) ) {
		include $file;
	}
}, true, true );

add_action( 'wp_enqueue_scripts', 'glozin_child_enqueue_scripts', 20 );
function glozin_child_enqueue_scripts() {
	wp_enqueue_style( 'glozin-child-style', get_stylesheet_uri() );
}

add_filter( 'default_checkout_billing_country', 'glozin_child_default_country' );
add_filter( 'default_checkout_shipping_country', 'glozin_child_default_country' );
function glozin_child_default_country() {
	return 'MD';
}

add_filter( 'woocommerce_default_address_fields', 'glozin_child_make_postcode_optional' );
function glozin_child_make_postcode_optional( $fields ) {
	if ( isset( $fields['postcode'] ) ) {
		$fields['postcode']['required'] = false;
	}
	return $fields;
}

/**
 * Modify checkout billing fields: remove city, move state before address.
 * Uses woocommerce_default_address_fields (before country locale) and 
 * woocommerce_get_country_locale (for MD override) to ensure state
 * appears before address_1 regardless of how WC generates the form.
 */

// 1. Change default priority for ALL countries (base)
add_filter( 'woocommerce_default_address_fields', 'glozin_child_reorder_default_address_fields', 9999 );
function glozin_child_reorder_default_address_fields( $fields ) {
	// Move state before address_1 (state < 50, address = 50)
	if ( isset( $fields['state'] ) ) {
		$fields['state']['priority'] = 48;
		$fields['state']['required'] = false;
	}

	// Remove city globally
	if ( isset( $fields['city'] ) ) {
		unset( $fields['city'] );
	}

	return $fields;
}

// 2. Override MD locale to ensure state priority is 48
add_filter( 'woocommerce_get_country_locale', 'glozin_child_md_locale_state_priority', 9999 );
function glozin_child_md_locale_state_priority( $locales ) {
	if ( isset( $locales['MD'] ) ) {
		if ( isset( $locales['MD']['state'] ) ) {
			$locales['MD']['state']['priority'] = 48;
		} else {
			$locales['MD']['state'] = array( 'priority' => 48 );
		}
	} else {
		$locales['MD'] = array( 'state' => array( 'priority' => 48 ) );
	}
	return $locales;
}

// 3. Final fallback: physically reorder array after all filters and sorting
add_filter( 'woocommerce_checkout_fields', 'glozin_child_checkout_force_field_order', 9999 );
function glozin_child_checkout_force_field_order( $fields ) {
	if ( isset( $fields['billing']['billing_city'] ) ) {
		unset( $fields['billing']['billing_city'] );
	}

	if ( isset( $fields['billing']['billing_state'] ) && isset( $fields['billing']['billing_address_1'] ) ) {
		$keys = array_keys( $fields['billing'] );
		$state_key = array_search( 'billing_state', $keys );
		$address_key = array_search( 'billing_address_1', $keys );

		if ( $state_key !== false && $address_key !== false && $state_key > $address_key ) {
			$state_field = array( 'billing_state' => $fields['billing']['billing_state'] );
			unset( $fields['billing']['billing_state'] );

			$before = array_slice( $fields['billing'], 0, $address_key, true );
			$after = array_slice( $fields['billing'], $address_key, null, true );

			$fields['billing'] = $before + $state_field + $after;
		}
	}

	return $fields;
}

/**
 * Make My Account orders table column headers translatable via WPML.
 * Uses 'glozin' text domain so they appear in String Translation.
 */
add_filter( 'woocommerce_account_orders_columns', 'glozin_child_account_orders_columns', 20 );
function glozin_child_account_orders_columns( $columns ) {
	return array(
		'order-number'  => __( 'Order', 'glozin' ),
		'order-date'    => __( 'Date', 'glozin' ),
		'order-status'  => __( 'Status', 'glozin' ),
		'order-total'   => __( 'Total', 'glozin' ),
		'order-actions' => __( 'Actions', 'glozin' ),
	);
}

/**
 * Default country to MD on my-account edit address page when not set.
 */
add_filter( 'woocommerce_my_account_edit_address_field_value', 'glozin_child_edit_address_default_country', 10, 3 );
function glozin_child_edit_address_default_country( $value, $key, $load_address ) {
	if ( ! $value && in_array( $key, array( 'billing_country', 'shipping_country' ) ) ) {
		return 'MD';
	}
	return $value;
}

/**
 * Auto-check "I have read and agree to the terms" checkbox on checkout page.
 */
add_action('wp_footer', 'glozin_child_checkout_terms_default');
function glozin_child_checkout_terms_default() {
    if (!is_checkout()) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var termsCheckbox = document.getElementById('terms');
        if (termsCheckbox) {
            termsCheckbox.checked = true;
        }
    });
    </script>
    <?php
}

add_filter( 'woocommerce_account_menu_items', 'remove_downloads_my_account', 999 );

function remove_downloads_my_account( $items ) {
    unset( $items['downloads'] );
    return $items;
}

add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );

add_filter( 'woocommerce_my_account_get_addresses', 'remove_shipping_address', 10, 2 );

function remove_shipping_address( $addresses, $customer_id ) {
    unset( $addresses['shipping'] );
    return $addresses;
}

add_filter( 'woocommerce_catalog_orderby', 'glozin_child_remove_orderby_options', 20 );
function glozin_child_remove_orderby_options( $orderby ) {
	unset( $orderby['date'], $orderby['date-asc'], $orderby['rating'] );
	return $orderby;
}


add_filter( 'woocommerce_product_add_to_cart_text', 'glozin_child_translate_atc_text', 20, 2 );
function glozin_child_translate_atc_text( $text, $product ) {
	if ( $product->is_type( 'variable' ) ) {
		return esc_html__( 'Select options', 'glozin' );
	}
	// if ( $product->is_type( 'grouped' ) ) {
	// 	return esc_html__( 'View products', 'glozin' );
	// }
	// if ( $product->is_type( 'external' ) ) {
	// 	return esc_html__( 'Read more', 'glozin' );
	// }
	// if ( $product->is_type( 'simple' ) ) {
	// 	return esc_html__( 'Add to cart', 'glozin' );
	// }
	return $text;
}

add_filter( 'glozin_svg_icons_ui', 'glozin_child_replace_search_not_found_icon' );
function glozin_child_replace_search_not_found_icon( $icons ) {
	$icons['search-not-found'] = '<svg width="15" height="16" aria-hidden="true" role="img" focusable="false"> <use href="#shopping-bag" xlink:href="#shopping-bag"></use> </svg>';
	return $icons;
}

/**
 * Last-resort fix for WPML custom_badges_text syncing issue.
 *
 * Why this is needed: WPML's "translate" mode for custom fields still
 * overwrites the translation's value with the original's cached value
 * after the theme's save_post hook. We bypass all filters by writing
 * the user's POST value directly to the postmeta table after every
 * other hook has run.
 *
 * Scope: only writes to the post currently being saved in admin
 * (the translation being edited). The original is left untouched.
 */
add_action( 'shutdown', 'glozin_child_force_badge_meta', 999 );
function glozin_child_force_badge_meta() {
	if ( ! did_action( 'save_post' ) ) {
		return;
	}
	if ( empty( $_POST['post_ID'] ) || ! isset( $_POST['custom_badges_text'] ) ) {
		return;
	}
	if ( ! is_admin() ) {
		return;
	}

	$post_id = absint( $_POST['post_ID'] );
	$value   = sanitize_text_field( wp_unslash( $_POST['custom_badges_text'] ) );

	global $wpdb;
	$existing = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'custom_badges_text'",
		$post_id
	) );

	if ( $existing ) {
		$wpdb->update(
			$wpdb->postmeta,
			[ 'meta_value' => $value ],
			[ 'meta_id' => $existing ],
			[ '%s' ],
			[ '%d' ]
		);
	} else {
		$wpdb->insert(
			$wpdb->postmeta,
			[ 'post_id' => $post_id, 'meta_key' => 'custom_badges_text', 'meta_value' => $value ],
			[ '%d', '%s', '%s' ]
		);
	}

	wp_cache_delete( $post_id, 'post_meta' );
}

add_filter( 'wcboost_products_compare_button_add_text', 'glozin_child_compare_button_text' );
add_filter( 'wcboost_products_compare_button_remove_text', 'glozin_child_compare_button_text' );
add_filter( 'wcboost_products_compare_button_view_text', 'glozin_child_compare_button_text' );
function glozin_child_compare_button_text( $text ) {
	if ( ! function_exists( 'icl_register_string' ) || ! function_exists( 'icl_t' ) ) {
		return $text;
	}

	$current = current_filter();
	$type    = preg_replace( '/^wcboost_products_compare_button_/', '', $current );
	$type    = preg_replace( '/_text$/', '', $type );

	$context = 'wcboost_products_compare_button_text';
	$name    = $type;

	icl_register_string( $context, $name, $text );
	$translated = icl_t( $context, $name, $text );

	return $translated ?: $text;
}

function glozin_child_save_label_map() {
    return array(
        'ro' => 'Salvează: ',
        'ru' => 'Экономия: ',
        'en' => 'Save: ',
    );
}

add_filter( 'gettext', 'glozin_child_translate_save_label', 20, 3 );
function glozin_child_translate_save_label( $translated, $text, $domain ) {
    if ( $text !== 'Save: ' ) {
        return $translated;
    }
    if ( ! in_array( $domain, array( 'glozin', 'glozin-addons' ), true ) ) {
        return $translated;
    }

    $lang = defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : 'ru';
    $map  = glozin_child_save_label_map();

    if ( isset( $map[ $lang ] ) ) {
        return $map[ $lang ];
    }

    return $translated;
}


add_filter( 'woocommerce_package_rates', function( $rates ) {

    $free_rate_id = 'free_shipping:3';
    $paid_rate_id = 'flat_rate:5';

    if ( isset( $rates[$free_rate_id] ) ) {
        unset( $rates[$paid_rate_id] );
    }

    return $rates;
}, 100 );

/**
 * Translate WooCommerce state codes to full names for telsender plugin.
 * Moldova states are stored as codes (C = Chișinău, BL = Bălți, etc.).
 */
add_filter('tscf_filter_codetemplate', function ($replace, $order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return $replace;

    foreach (['billing', 'shipping'] as $type) {
        $key = '{' . $type . '-state}';
        if (isset($replace[$key]) && $replace[$key]) {
            $country = 'billing' === $type
                ? $order->get_billing_country()
                : $order->get_shipping_country();
            $states = WC()->countries->get_states($country);
            if ($states && isset($states[$replace[$key]])) {
                $replace[$key] = $states[$replace[$key]];
            }
        }
    }

    return $replace;
}, 10, 2);

require get_stylesheet_directory() . '/inc/maib-checkout-fix.php';
require get_stylesheet_directory() . '/inc/strip-search-from-category.php';
require get_stylesheet_directory() . '/inc/plugins/wpml-mcs-pagination.php';
// require get_stylesheet_directory() . '/inc/contact-us-button.php';
require get_stylesheet_directory() . '/inc/plugins/barcode-search.php';
require get_stylesheet_directory() . '/inc/plugins/top-categories-carousel.php';
require get_stylesheet_directory() . '/inc/hide-flag.php';
require get_stylesheet_directory() . '/inc/telsender-wpml-fix.php';
require get_stylesheet_directory() . '/inc/industrial-pro.php';
