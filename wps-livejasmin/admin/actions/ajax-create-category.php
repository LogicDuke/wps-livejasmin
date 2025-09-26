<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Create category in Ajax.
 *
 * @since 1.0.0
 *
 * @return void
 */
function lvjm_create_category() {
	check_ajax_referer( 'ajax-nonce', 'nonce' );

	if ( ! isset( $_POST['category_name'] ) ) {
		wp_die( '"category_name" parameter is missing!' );
	}

	$category_name = sanitize_text_field( wp_unslash( $_POST['category_name'] ) );

	$custom_categories = xbox_get_field_value( 'lvjm-options', 'custom-video-categories' );
	$params            = array(
		'cat_name' => $category_name,
		'taxonomy' => '' !== $custom_categories ? $custom_categories : 'category',
	);

	$new_cat_id = wp_insert_category( $params );

	$output = array(
		'new_cat_id' => $new_cat_id,
		'wp_cats'    => LVJM()->get_wp_cats(),
	);
	wp_send_json( $output );

	wp_die();
}
add_action( 'wp_ajax_lvjm_create_category', 'lvjm_create_category' );
