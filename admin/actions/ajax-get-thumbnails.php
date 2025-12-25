<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

if ( ! function_exists( 'lvjm_get_video_thumbnails' ) ) {
	/**
	 * Fetch thumbnails for a video via VPAPI details.
	 *
	 * @return void
	 */
	function lvjm_get_video_thumbnails() {
		check_ajax_referer( 'ajax-nonce', 'nonce' );

		$video_id   = isset( $_POST['video_id'] ) ? sanitize_text_field( wp_unslash( $_POST['video_id'] ) ) : '';
		$partner_id = isset( $_POST['partner_id'] ) ? sanitize_text_field( wp_unslash( $_POST['partner_id'] ) ) : '';
		$locale     = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : '';

		if ( '' === $video_id ) {
			wp_send_json_error(
				array(
					'error_code'    => 'missing_video_id',
					'error_message' => 'Video id is required.',
				)
			);
		}

		if ( ! function_exists( 'lvjm_fetch_video_details_cached' ) ) {
			$actions_file = dirname( __FILE__ ) . '/ajax-search-videos.php';
			if ( file_exists( $actions_file ) ) {
				require_once $actions_file;
			}
		}

		$cache_key = 'lvjm_vpapi_thumbs_' . sanitize_key( $partner_id ) . '_' . sanitize_key( $video_id );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			wp_send_json_success( $cached );
		}

		$details_payload = function_exists( 'lvjm_fetch_video_details_cached' )
			? lvjm_fetch_video_details_cached( $video_id, $partner_id, $locale )
			: array();
		$details_video   = function_exists( 'lvjm_extract_vpapi_video_object' )
			? lvjm_extract_vpapi_video_object( $details_payload )
			: array();

		if ( empty( $details_video ) ) {
			wp_send_json_error(
				array(
					'error_code'    => 'missing_details',
					'error_message' => 'VPAPI details unavailable.',
				)
			);
		}

		$thumbs_urls = function_exists( 'lvjm_collect_vpapi_thumbs_urls' )
			? lvjm_collect_vpapi_thumbs_urls( $details_video )
			: array();
		$thumb_url   = function_exists( 'lvjm_get_vpapi_detail_value' )
			? lvjm_get_vpapi_detail_value( $details_video, array( 'thumbUrl', 'thumb_url', 'thumbURL', 'thumb' ) )
			: '';
		$thumb_url   = function_exists( 'lvjm_https_url' ) ? lvjm_https_url( $thumb_url ) : (string) $thumb_url;

		if ( '' === $thumb_url && ! empty( $thumbs_urls ) ) {
			$thumb_url = $thumbs_urls[0];
		}

		if ( empty( $thumbs_urls ) ) {
			wp_send_json_error(
				array(
					'error_code'    => 'no_thumbnails',
					'error_message' => 'No thumbnails available.',
					'thumb_url'     => $thumb_url,
					'thumbs_urls'   => array(),
				)
			);
		}

		$payload = array(
			'thumbs_urls' => $thumbs_urls,
			'thumb_url'   => $thumb_url,
		);

		set_transient( $cache_key, $payload, 12 * HOUR_IN_SECONDS );

		wp_send_json_success( $payload );
	}
	add_action( 'wp_ajax_lvjm_get_thumbnails', 'lvjm_get_video_thumbnails' );
}
