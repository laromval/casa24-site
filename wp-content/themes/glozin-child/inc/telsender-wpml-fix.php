<?php
/**
 * Fix TelSender + WPML: show all CF7 forms regardless of language.
 *
 * WPML filters WP_Query via posts_join / posts_where to restrict results
 * to the current language. TelSender queries CF7 forms using WP_Query,
 * so only forms in the active language appear in its settings.
 *
 * This fix detects TelSender's CF7 form query in pre_get_posts and
 * temporarily removes WPML's language filtering for that specific query.
 *
 * @package Glozin Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'pre_get_posts', 'glozin_child_telsender_show_all_forms', 999 );

function glozin_child_telsender_show_all_forms( $query ) {
	if ( ! is_admin() || ! isset( $_GET['page'] ) || $_GET['page'] !== 'telsender' ) {
		return;
	}

	if ( ! isset( $query->query_vars['post_type'] ) ) {
		return;
	}

	$post_type = $query->query_vars['post_type'];

	if ( is_string( $post_type ) && $post_type === 'wpcf7_contact_form' ) {
		glozin_child_telsender_remove_wpml_filters();
		add_action( 'loop_end', 'glozin_child_telsender_restore_wpml_filters' );
	} elseif ( is_array( $post_type ) && in_array( 'wpcf7_contact_form', $post_type, true ) ) {
		glozin_child_telsender_remove_wpml_filters();
		add_action( 'loop_end', 'glozin_child_telsender_restore_wpml_filters' );
	}
}

function glozin_child_telsender_remove_wpml_filters() {
	global $wpml_query_filter;

	if ( ! $wpml_query_filter ) {
		return;
	}

	remove_filter( 'posts_join', array( $wpml_query_filter, 'posts_join_filter' ), 10 );
	remove_filter( 'posts_where', array( $wpml_query_filter, 'posts_where_filter' ), 10 );
}

function glozin_child_telsender_restore_wpml_filters() {
	global $wpml_query_filter;

	if ( ! $wpml_query_filter ) {
		return;
	}

	add_filter( 'posts_join', array( $wpml_query_filter, 'posts_join_filter' ), 10, 2 );
	add_filter( 'posts_where', array( $wpml_query_filter, 'posts_where_filter' ), 10, 2 );

	remove_action( 'loop_end', 'glozin_child_telsender_restore_wpml_filters' );
}
