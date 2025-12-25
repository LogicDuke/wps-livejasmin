<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

require_once dirname( __DIR__ ) . '/includes/vpapi-helpers.php';

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

		$cache_key = 'lvjm_vpapi_thumbs_' . sanitize_key( $partner_id ) . '_' . sanitize_key( $video_id ) . '_' . sanitize_key( $locale );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			wp_send_json_success( $cached );
		}

		$details_payload = lvjm_fetch_video_details_cached( $video_id, $partner_id, $locale );
		$details_video   = lvjm_extract_vpapi_video_object( $details_payload );

		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
			$helper_status = array(
				'fetch'   => function_exists( 'lvjm_fetch_video_details_cached' ),
				'extract' => function_exists( 'lvjm_extract_vpapi_video_object' ),
				'collect' => function_exists( 'lvjm_collect_vpapi_thumbs_urls' ),
				'detail'  => function_exists( 'lvjm_get_vpapi_detail_value' ),
			);
			$details_keys  = is_array( $details_payload ) ? implode( ',', array_keys( $details_payload ) ) : 'non-array';
			$thumb_keys    = array( 'thumbsUrls', 'thumbUrls', 'thumbs_urls', 'thumb_urls', 'thumbs', 'thumbnails', 'thumbnailUrls', 'thumbnailsUrls' );
			$thumb_present = array();
			foreach ( $thumb_keys as $thumb_key ) {
				if ( isset( $details_video[ $thumb_key ] ) ) {
					$thumb_present[] = $thumb_key;
				}
			}
			$status_code = isset( $GLOBALS['lvjm_vpapi_last_status_code'] ) ? $GLOBALS['lvjm_vpapi_last_status_code'] : null;
			lvjm_importer_log(
				'info',
				sprintf(
					'Thumbs debug video_id=%s partner_id=%s locale=%s helpers=%s status=%s details_keys=[%s] thumb_keys=[%s]',
					$video_id,
					$partner_id,
					$locale,
					wp_json_encode( $helper_status ),
					is_null( $status_code ) ? 'n/a' : $status_code,
					$details_keys,
					implode( ',', $thumb_present )
				)
			);
		}

		if ( empty( $details_video ) ) {
			$payload = array(
				'thumbs_urls'   => array(),
				'thumb_url'     => '',
				'status'        => 'missing_details',
				'error_message' => 'VPAPI details unavailable.',
			);
			set_transient( $cache_key, $payload, 12 * HOUR_IN_SECONDS );
			wp_send_json_success( $payload );
		}

		$thumbs_urls = lvjm_collect_vpapi_thumbs_urls( $details_video );
		$thumb_url   = lvjm_get_vpapi_detail_value( $details_video, array( 'thumbUrl', 'thumb_url', 'thumbURL', 'thumb' ) );
		$thumb_url   = lvjm_https_url( $thumb_url );

		if ( '' === $thumb_url && ! empty( $thumbs_urls ) ) {
			$thumb_url = $thumbs_urls[0];
		}

		$payload = array(
			'thumbs_urls' => $thumbs_urls,
			'thumb_url'   => $thumb_url,
			'status'      => empty( $thumbs_urls ) ? 'no_thumbnails' : 'ok',
		);

		set_transient( $cache_key, $payload, 12 * HOUR_IN_SECONDS );

		wp_send_json_success( $payload );
	}
	add_action( 'wp_ajax_lvjm_get_thumbnails', 'lvjm_get_video_thumbnails' );
}
