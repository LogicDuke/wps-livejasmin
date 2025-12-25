<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Update a feed in Ajax from some given params.
 *
 * @since 1.0.0
 *
 * @param mixed $params       Array of parameters if this function is called in PHP.
 * @return void|array $output New post ID if success, -1 if not. Returned only if this function is called in PHP.
 */
function lvjm_update_feed( $params = '' ) {
	$ajax_call          = '' === $params;
	$last_update_method = $ajax_call ? 'manual' : 'auto';

	if ( $ajax_call ) {
		check_ajax_referer( 'ajax-nonce', 'nonce' );
		$params = $_POST;
	}

	if ( ! isset( $params['feed_id'] ) ) {
		wp_die( 'Some parameters are missing!' );
	}

	if ( isset( $params['total_videos'] ) && $params['total_videos'] > 0 ) {

		// prepare new feed options to save.
		$feed_data['last_update']        = current_time( 'Y-m-d H:i:s' );
		$feed_data['last_update_method'] = $last_update_method;

		$saved_feed = LVJM()->get_feed( $params['feed_id'] );
		if ( ! is_array( $saved_feed ) ) {
			$saved_feed = array( 'total_videos' => 0 );
		}
		$feed_data['total_videos'] = intval( $saved_feed['total_videos'] ) + intval( $params['total_videos'] );

		if ( 'create' === $params['method'] ) {
			$feed_data['status']      = xbox_get_field_value( 'lvjm-options', 'default-status' );
			$feed_data['auto_import'] = false;
		}

		unset( $saved_feed );

		// update feed data.
		LVJM()->update_feed( $params['feed_id'], $feed_data );

		$output = array(
			'feed' => LVJM()->get_feed( $params['feed_id'] ),
		);

	} else {
		$output = false;
	}

	if ( ! $ajax_call ) {

		return $output;
	}

	wp_send_json( $output );

	wp_die();
}

add_action( 'wp_ajax_lvjm_update_feed', 'lvjm_update_feed' );
