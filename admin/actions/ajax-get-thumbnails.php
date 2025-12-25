<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

require_once dirname( __DIR__ ) . '/includes/vpapi-helpers.php';

if ( ! function_exists( 'lvjm_normalize_vpapi_csv_url' ) ) {
	/**
	 * Normalize VPAPI URLs from CSV values.
	 *
	 * @param string $url URL to normalize.
	 * @return string
	 */
	function lvjm_normalize_vpapi_csv_url( $url ) {
		$url = str_replace( '\\/\\/', '//', (string) $url );
		$url = str_replace( '\\/', '/', $url );
		$url = lvjm_https_url( $url );
		return trim( $url );
	}
}

if ( ! function_exists( 'lvjm_parse_csv_preview_images' ) ) {
	/**
	 * Parse preview images from a CSV field.
	 *
	 * @param string $raw Preview image field value.
	 * @return array
	 */
	function lvjm_parse_csv_preview_images( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		$list    = json_last_error() === JSON_ERROR_NONE ? $decoded : preg_split( '/\s*[|,]\s*/', $raw );
		$thumbs  = array();

		foreach ( (array) $list as $preview ) {
			$thumb = '';
			if ( is_array( $preview ) ) {
				$thumb = isset( $preview['url'] ) ? $preview['url'] : ( isset( $preview['src'] ) ? $preview['src'] : '' );
			} else {
				$thumb = $preview;
			}
			$thumb = lvjm_normalize_vpapi_csv_url( $thumb );
			if ( '' !== $thumb ) {
				$thumbs[] = $thumb;
			}
		}

		return array_values( array_unique( $thumbs ) );
	}
}

if ( ! function_exists( 'lvjm_load_vpapi_master_thumb_map' ) ) {
	/**
	 * Load and cache VPAPI master thumbnails CSV.
	 *
	 * @return array
	 */
	function lvjm_load_vpapi_master_thumb_map() {
		$transient_key = 'lvjm_vpapi_master_thumb_map_v1';
		$cached        = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$file_path = dirname( __DIR__, 2 ) . '/data/vpapi_master.csv';
		$thumb_map = array();
		$ttl       = 12 * HOUR_IN_SECONDS;

		if ( ! file_exists( $file_path ) ) {
			error_log( '[TMW-FIX] vpapi_master.csv is missing at ' . $file_path );
			set_transient( $transient_key, $thumb_map, 10 * MINUTE_IN_SECONDS );
			return $thumb_map;
		}

		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			error_log( '[TMW-FIX] Unable to read vpapi_master.csv at ' . $file_path );
			set_transient( $transient_key, $thumb_map, 10 * MINUTE_IN_SECONDS );
			return $thumb_map;
		}

		$header = fgetcsv( $handle );
		if ( empty( $header ) ) {
			fclose( $handle );
			set_transient( $transient_key, $thumb_map, 10 * MINUTE_IN_SECONDS );
			return $thumb_map;
		}

		$header_map = array();
		foreach ( $header as $index => $column ) {
			$header_map[ strtolower( trim( $column ) ) ] = $index;
		}

		if ( ! isset( $header_map['video_id'] ) ) {
			fclose( $handle );
			set_transient( $transient_key, $thumb_map, 10 * MINUTE_IN_SECONDS );
			return $thumb_map;
		}

		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			$video_id = isset( $row[ $header_map['video_id'] ] ) ? trim( (string) $row[ $header_map['video_id'] ] ) : '';
			if ( '' === $video_id ) {
				continue;
			}

			$thumb_url   = '';
			$thumbs_urls = array();

			if ( isset( $header_map['thumbimage'] ) && isset( $row[ $header_map['thumbimage'] ] ) ) {
				$thumb_url = lvjm_normalize_vpapi_csv_url( $row[ $header_map['thumbimage'] ] );
			}

			if ( isset( $header_map['previewimages'] ) && isset( $row[ $header_map['previewimages'] ] ) ) {
				$thumbs_urls = lvjm_parse_csv_preview_images( $row[ $header_map['previewimages'] ] );
			}

			if ( '' === $thumb_url && empty( $thumbs_urls ) ) {
				continue;
			}

			$thumb_map[ $video_id ] = array(
				'thumb_url'   => $thumb_url,
				'thumbs_urls' => $thumbs_urls,
			);
		}

		fclose( $handle );

		if ( empty( $thumb_map ) ) {
			$ttl = 10 * MINUTE_IN_SECONDS;
		}

		set_transient( $transient_key, $thumb_map, $ttl );

		return $thumb_map;
	}
}

if ( ! function_exists( 'lvjm_lookup_vpapi_master_thumbs' ) ) {
	/**
	 * Lookup thumbs for a video in the VPAPI master CSV.
	 *
	 * @param string $video_id Video id.
	 * @return array|null
	 */
	function lvjm_lookup_vpapi_master_thumbs( $video_id ) {
		$map = lvjm_load_vpapi_master_thumb_map();
		if ( isset( $map[ $video_id ] ) ) {
			return $map[ $video_id ];
		}

		return null;
	}
}

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
		$force      = isset( $_POST['force'] ) ? sanitize_text_field( wp_unslash( $_POST['force'] ) ) : '';

		if ( '' === $video_id ) {
			wp_send_json_error(
				array(
					'error_code'    => 'missing_video_id',
					'error_message' => 'Video id is required.',
				)
			);
		}

		error_log( sprintf( '[TMW-FIX] Thumbs ajax called video_id=%s', $video_id ) );

		$list_cache_key = 'lvjm_vpapi_list_thumbs_' . sanitize_key( $partner_id ) . '_' . sanitize_key( $video_id ) . '_' . sanitize_key( $locale );
		$cache_key = 'lvjm_vpapi_thumbs_v2_' . sanitize_key( $partner_id ) . '_' . sanitize_key( $video_id ) . '_' . sanitize_key( $locale );
		$use_cache = true;
		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER && '' !== $force ) {
			$use_cache = false;
		}
		if ( $use_cache ) {
			$cached_list = get_transient( $list_cache_key );
			if ( is_array( $cached_list ) && ( ! empty( $cached_list['thumbs_urls'] ) || ! empty( $cached_list['thumb_url'] ) ) ) {
				if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
					lvjm_importer_log(
						'info',
						sprintf(
							'Thumbs source=list-cache video_id=%s partner_id=%s locale=%s status=%s thumb_url=%s thumbs_samples=%s',
							$video_id,
							$partner_id,
							$locale,
							isset( $cached_list['status'] ) ? $cached_list['status'] : 'n/a',
							isset( $cached_list['thumb_url'] ) ? $cached_list['thumb_url'] : '',
							empty( $cached_list['thumbs_urls'] ) ? 'none' : implode( ',', array_slice( (array) $cached_list['thumbs_urls'], 0, 2 ) )
						)
					);
					lvjm_importer_log(
						'info',
						sprintf(
							'Thumbs source=list video_id=%s count=%d samples=%s',
							$video_id,
							isset( $cached_list['thumbs_urls'] ) ? count( (array) $cached_list['thumbs_urls'] ) : 0,
							empty( $cached_list['thumbs_urls'] ) ? 'none' : implode( ',', array_slice( (array) $cached_list['thumbs_urls'], 0, 2 ) )
						)
					);
				}
				error_log( sprintf( '[TMW-FIX] Thumbs source=FEED video_id=%s', $video_id ) );
				wp_send_json_success( $cached_list );
			}

			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				$cached_source = isset( $cached['source'] ) ? strtoupper( (string) $cached['source'] ) : 'VPAPI';
				error_log( sprintf( '[TMW-FIX] Thumbs source=%s video_id=%s', $cached_source, $video_id ) );
				if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
					lvjm_importer_log(
						'info',
						sprintf(
							'Thumbs cache hit video_id=%s partner_id=%s locale=%s status=%s thumb_url=%s thumbs_samples=%s',
							$video_id,
							$partner_id,
							$locale,
							isset( $cached['status'] ) ? $cached['status'] : 'n/a',
							isset( $cached['thumb_url'] ) ? $cached['thumb_url'] : '',
							empty( $cached['thumbs_urls'] ) ? 'none' : implode( ',', array_slice( (array) $cached['thumbs_urls'], 0, 2 ) )
						)
					);
				}
				wp_send_json_success( $cached );
			}
		}

		$csv_entry = lvjm_lookup_vpapi_master_thumbs( $video_id );
		if ( is_array( $csv_entry ) && ( ! empty( $csv_entry['thumbs_urls'] ) || ! empty( $csv_entry['thumb_url'] ) ) ) {
			$thumbs_urls = isset( $csv_entry['thumbs_urls'] ) ? (array) $csv_entry['thumbs_urls'] : array();
			$thumb_url   = isset( $csv_entry['thumb_url'] ) ? (string) $csv_entry['thumb_url'] : '';
			if ( empty( $thumbs_urls ) && '' !== $thumb_url ) {
				$thumbs_urls = array( $thumb_url );
			}
			$payload = array(
				'thumbs_urls' => $thumbs_urls,
				'thumb_url'   => $thumb_url,
				'status'      => 'ok',
				'source'      => 'csv',
			);
			error_log( sprintf( '[TMW-FIX] Thumbs source=CSV video_id=%s', $video_id ) );
			set_transient( $cache_key, $payload, 12 * HOUR_IN_SECONDS );
			wp_send_json_success( $payload );
		}

		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
			lvjm_importer_log( 'info', 'Thumbs source=details-fallback' );
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
			$data_keys     = ( isset( $details_payload['data'] ) && is_array( $details_payload['data'] ) ) ? implode( ',', array_keys( $details_payload['data'] ) ) : 'n/a';
			$video_keys    = ( isset( $details_payload['data']['video'] ) && is_array( $details_payload['data']['video'] ) ) ? implode( ',', array_keys( $details_payload['data']['video'] ) ) : 'n/a';
			$thumb_keys    = array( 'thumbsUrls', 'thumbUrls', 'thumbs_urls', 'thumb_urls', 'thumbs', 'thumbnails', 'thumbnailUrls', 'thumbnailsUrls', 'previewImages', 'preview_images', 'thumbImage', 'thumb_image' );
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
					'[TMW-FIX] Thumbs debug video_id=%s partner_id=%s locale=%s helpers=%s status=%s details_empty=%s details_keys=[%s] data_keys=[%s] data_video_keys=[%s] thumb_keys=[%s]',
					$video_id,
					$partner_id,
					$locale,
					wp_json_encode( $helper_status ),
					is_null( $status_code ) ? 'n/a' : $status_code,
					empty( $details_video ) ? 'yes' : 'no',
					$details_keys,
					$data_keys,
					$video_keys,
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
				'source'        => 'vpapi',
			);
			set_transient( $cache_key, $payload, 3 * MINUTE_IN_SECONDS );
			error_log( sprintf( '[TMW-FIX] Thumbs source=VPAPI video_id=%s', $video_id ) );
			if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
				lvjm_importer_log( 'info', 'Thumbs response status=missing_details count=0' );
			}
			wp_send_json_success( $payload );
		}

		$thumbs_urls = lvjm_collect_vpapi_thumbs_urls( $details_video );
		$thumb_url   = lvjm_get_vpapi_detail_value( $details_video, array( 'thumbUrl', 'thumb_url', 'thumbURL', 'thumb', 'thumbImage', 'thumb_image' ) );
		$thumb_url   = lvjm_https_url( $thumb_url );

		if ( empty( $thumbs_urls ) && '' === $thumb_url ) {
			usleep( 200000 );
			$details_payload = lvjm_fetch_video_details_cached( $video_id, $partner_id, $locale, true );
			$details_video   = lvjm_extract_vpapi_video_object( $details_payload );
			if ( ! empty( $details_video ) ) {
				$thumbs_urls = lvjm_collect_vpapi_thumbs_urls( $details_video );
				$thumb_url   = lvjm_get_vpapi_detail_value( $details_video, array( 'thumbUrl', 'thumb_url', 'thumbURL', 'thumb', 'thumbImage', 'thumb_image' ) );
				$thumb_url   = lvjm_https_url( $thumb_url );
			}
		}

		if ( '' === $thumb_url && ! empty( $thumbs_urls ) ) {
			$thumb_url = $thumbs_urls[0];
		}

		$payload = array(
			'thumbs_urls' => $thumbs_urls,
			'thumb_url'   => $thumb_url,
			'status'      => empty( $thumbs_urls ) ? 'no_thumbnails' : 'ok',
			'source'      => 'vpapi',
		);
		error_log( sprintf( '[TMW-FIX] Thumbs source=VPAPI video_id=%s', $video_id ) );

		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
			$details_url = isset( $GLOBALS['lvjm_vpapi_last_details_url'] ) ? lvjm_mask_sensitive_payload( $GLOBALS['lvjm_vpapi_last_details_url'] ) : 'n/a';
			lvjm_importer_log(
				'info',
				sprintf(
					'[TMW-FIX] Thumbs response video_id=%s partner_id=%s locale=%s details_url=%s thumb_url=%s thumbs_samples=%s',
					$video_id,
					$partner_id,
					$locale,
					$details_url,
					$thumb_url,
					empty( $thumbs_urls ) ? 'none' : implode( ',', array_slice( $thumbs_urls, 0, 2 ) )
				)
			);
			lvjm_importer_log(
				'info',
				sprintf(
					'Thumbs source=client_details video_id=%s count=%d samples=%s',
					$video_id,
					count( $thumbs_urls ),
					empty( $thumbs_urls ) ? 'none' : implode( ',', array_slice( $thumbs_urls, 0, 2 ) )
				)
			);
		}

		if ( 'ok' === $payload['status'] ) {
			set_transient( $cache_key, $payload, 12 * HOUR_IN_SECONDS );
		} elseif ( 'no_thumbnails' === $payload['status'] ) {
			set_transient( $cache_key, $payload, 15 * MINUTE_IN_SECONDS );
		} else {
			set_transient( $cache_key, $payload, 3 * MINUTE_IN_SECONDS );
		}
		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
			lvjm_importer_log(
				'info',
				sprintf(
					'[TMW-FIX] Thumbs response status=%s count=%d',
					$payload['status'],
					count( $thumbs_urls )
				)
			);
		}

		wp_send_json_success( $payload );
	}
	add_action( 'wp_ajax_lvjm_get_thumbnails', 'lvjm_get_video_thumbnails' );
}
