<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Toggle a feed for auto import in Ajax.
 *
 * @since 1.0.0
 *
 * @return void
 */
function lvjm_toggle_feed_auto_import() {
	check_ajax_referer( 'ajax-nonce', 'nonce' );

	if ( ! isset( $_POST['feed_id'], $_POST['new_value'] ) ) {
		wp_die( 'Some parameters are missing!' );
	}
	$feed_id   = sanitize_text_field( wp_unslash( $_POST['feed_id'] ) );
	$new_value = sanitize_text_field( wp_unslash( $_POST['new_value'] ) );

	$output = LVJM()->update_feed( $feed_id, array( 'auto_import' => 'true' === $new_value ) );

	wp_send_json( $output );

	wp_die();
}
add_action( 'wp_ajax_lvjm_toggle_feed_auto_import', 'lvjm_toggle_feed_auto_import' );
