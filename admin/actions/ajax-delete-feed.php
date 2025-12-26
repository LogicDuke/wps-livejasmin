<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Delete feed in Ajax.
 *
 * @since 1.0.0
 *
 * @return void
 */
function lvjm_delete_feed() {
	check_ajax_referer( 'ajax-nonce', 'nonce' );

	if ( ! isset( $_POST['feed_id'] ) ) {
		wp_die( 'Some parameters are missing!' );
	}

	$feed_id = sanitize_text_field( wp_unslash( $_POST['feed_id'] ) );

	$post_type = xbox_get_field_value( 'lvjm-options', 'custom-video-post-type' );
	if ( '' === $post_type ) {
		$post_type = 'post';
	}

	$feed_args = array(
		'post_type'      => $post_type,
		'post_status'    => array( 'any' ),
		'posts_per_page' => -1,
		'meta_query'     => array(
			array(
				'key'     => 'feed',
				'value'   => $feed_id,
				'compare' => '=',
			),
		),
	);

	$feed_posts = new WP_Query( $feed_args );

	if ( $feed_posts->have_posts() ) {
		while ( $feed_posts->have_posts() ) {
			$feed_posts->the_post();
			wp_delete_post( get_the_ID(), true );
		}
	}

	LVJM()->delete_feed( $feed_id );

	wp_die();
}
add_action( 'wp_ajax_lvjm_delete_feed', 'lvjm_delete_feed' );
