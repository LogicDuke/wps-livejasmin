<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Search for videos in Ajax or PHP call.
 *
 * @since 1.0.0
 *
 * @param mixed $params       Array of parameters if this function is called in PHP.
 * @return void|array $output New post ID if success, -1 if not. Returned only if this function is called in PHP.
 */
function lvjm_search_videos( $params = '' ) {
	$ajax_call = '' === $params;

	if ( $ajax_call ) {
		check_ajax_referer( 'ajax-nonce', 'nonce' );
		$params = $_POST;
		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
			WPSCORE()->write_log(
				'info',
				'[TMW-IMPORTER] Incoming AJAX payload ' . wp_json_encode( $params ),
				__FILE__,
				__LINE__
			);
		}
	}

	$search_videos = new LVJM_Search_Videos( $params );
	$errors        = array();

	if ( $search_videos->has_errors() ) {
		$errors = $search_videos->get_errors();
	}

	$videos = $search_videos->get_videos();

	if ( ! $ajax_call ) {
		return $videos;
	}

	$searched_data = $search_videos->get_searched_data();

	wp_send_json(
		array(
			'videos'        => $videos,
			'searched_data' => $searched_data,
			'errors'        => $errors,
		)
	);

	wp_die();
}
add_action( 'wp_ajax_lvjm_search_videos', 'lvjm_search_videos' );
