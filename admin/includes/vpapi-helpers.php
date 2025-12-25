<?php
/**
 * VPAPI helpers shared across admin actions.
 *
 * @package LIVEJASMIN\Admin\Includes
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

if ( ! function_exists( 'lvjm_importer_log' ) ) {
	/**
	 * Log importer debug messages when enabled.
	 *
	 * @param string $level   Log level.
	 * @param string $message Message to log.
	 * @return void
	 */
	function lvjm_importer_log( $level, $message ) {
		if ( ! defined( 'LVJM_DEBUG_IMPORTER' ) || ! LVJM_DEBUG_IMPORTER ) {
			return;
		}

		$message = '[TMW-FIX] ' . $message;
		if ( function_exists( 'WPSCORE' ) ) {
			WPSCORE()->write_log( $level, $message, __FILE__, __LINE__ );
		} else {
			error_log( $message );
		}
	}
}

if ( ! function_exists( 'lvjm_fetch_video_details_cached' ) ) {
	/**
	 * Fetch VPAPI video details and cache them with transients.
	 *
	 * @param string $video_id   The video id.
	 * @param string $partner_id The partner id.
	 * @param string $locale     The locale if available.
	 * @return array
	 */
	function lvjm_fetch_video_details_cached( $video_id, $partner_id, $locale = '' ) {
		$video_id = (string) $video_id;
		if ( '' === $video_id ) {
			return array();
		}

		$transient_key = 'lvjm_vpapi_details_' . $video_id;
		$cached        = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$saved_partner_options = WPSCORE()->get_product_option( 'LVJM', 'livejasmin_options' );
		$json_feed_url         = isset( $saved_partner_options['json_feed_url'] ) ? $saved_partner_options['json_feed_url'] : '';
		if ( '' === $json_feed_url ) {
			return array();
		}

		$parsed_url = wp_parse_url( $json_feed_url );
		if ( empty( $parsed_url['path'] ) ) {
			return array();
		}

		$path = $parsed_url['path'];
		if ( false !== strpos( $path, '/api/video-promotion/v1/list' ) ) {
			$path = str_replace( '/api/video-promotion/v1/list', '/api/video-promotion/v1/details/' . rawurlencode( $video_id ), $path );
		} elseif ( preg_match( '#/video-promotion/v1/list/?$#', $path ) ) {
			$path = preg_replace( '#/video-promotion/v1/list/?$#', '/video-promotion/v1/details/' . rawurlencode( $video_id ), $path );
		} elseif ( preg_match( '#/list/?$#', $path ) && false !== strpos( $path, 'video-promotion' ) ) {
			$path = preg_replace( '#/list/?$#', '/details/' . rawurlencode( $video_id ), $path );
		} else {
			return array();
		}
		$parsed_url['path'] = $path;

		$query_params = array();
		if ( isset( $parsed_url['query'] ) ) {
			wp_parse_str( $parsed_url['query'], $query_params );
		}
		if ( '' !== $locale ) {
			$query_params['locale'] = $locale;
		}

		$scheme   = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$port     = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
		$user     = isset( $parsed_url['user'] ) ? $parsed_url['user'] : '';
		$pass     = isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
		$pass     = ( $user || $pass ) ? "$pass@" : '';
		$query    = ! empty( $query_params ) ? '?' . http_build_query( $query_params ) : '';
		$fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';

		$details_url = "$scheme$user$pass$host$port$path$query$fragment";

		$args = array(
			'timeout'   => 300,
			'sslverify' => false,
		);

		$response = wp_remote_get( $details_url, $args );
		if ( is_wp_error( $response ) ) {
			$GLOBALS['lvjm_vpapi_last_status_code'] = null;
			$GLOBALS['lvjm_vpapi_last_details_url'] = $details_url;
			lvjm_importer_log( 'warning', 'VPAPI details request failed for ' . $details_url . ' (status: n/a).' );
			return array();
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$GLOBALS['lvjm_vpapi_last_status_code'] = $status_code;
		$GLOBALS['lvjm_vpapi_last_details_url'] = $details_url;
		if ( $status_code < 200 || $status_code >= 300 ) {
			lvjm_importer_log( 'warning', 'VPAPI details request failed for ' . $details_url . ' (status: ' . $status_code . ').' );
			return array();
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $response_body ) ) {
			lvjm_importer_log( 'warning', 'VPAPI details request failed for ' . $details_url . ' (status: ' . $status_code . ').' );
			return array();
		}

		set_transient( $transient_key, $response_body, 12 * HOUR_IN_SECONDS );

		return $response_body;
	}
}

if ( ! function_exists( 'lvjm_extract_vpapi_video_object' ) ) {
	/**
	 * Extract the video object from a VPAPI details payload.
	 *
	 * @param array $details The VPAPI details payload.
	 * @return array
	 */
	function lvjm_extract_vpapi_video_object( $details ) {
		if ( ! is_array( $details ) ) {
			return array();
		}

		$wrappers = array( 'data', 'video', 'item' );
		foreach ( $wrappers as $wrapper ) {
			if ( isset( $details[ $wrapper ] ) && is_array( $details[ $wrapper ] ) ) {
				return $details[ $wrapper ];
			}
		}

		if ( isset( $details['items'][0] ) && is_array( $details['items'][0] ) ) {
			return $details['items'][0];
		}

		return $details;
	}
}

if ( ! function_exists( 'lvjm_https_url' ) ) {
	/**
	 * Normalize URLs to HTTPS.
	 *
	 * @param string $url The URL.
	 * @return string
	 */
	function lvjm_https_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		return set_url_scheme( $url, 'https' );
	}
}

if ( ! function_exists( 'lvjm_collect_vpapi_thumbs_urls' ) ) {
	/**
	 * Collect thumb URLs from VPAPI details.
	 *
	 * @param array $details The VPAPI detail payload.
	 * @return array
	 */
	function lvjm_collect_vpapi_thumbs_urls( $details ) {
		$thumbs_urls = array();
		$thumbs_keys = array( 'thumbsUrls', 'thumbUrls', 'thumbs_urls', 'thumb_urls', 'thumbnailUrls', 'thumbnailsUrls' );
		foreach ( $thumbs_keys as $thumbs_key ) {
			if ( isset( $details[ $thumbs_key ] ) && is_array( $details[ $thumbs_key ] ) ) {
				foreach ( $details[ $thumbs_key ] as $thumb_url ) {
					$thumb_url = lvjm_https_url( $thumb_url );
					if ( '' !== $thumb_url ) {
						$thumbs_urls[] = $thumb_url;
					}
				}
			}
		}
		$thumb_groups = array( 'thumbs', 'thumbnails' );
		foreach ( $thumb_groups as $thumb_group ) {
			if ( isset( $details[ $thumb_group ] ) && is_array( $details[ $thumb_group ] ) ) {
				foreach ( $details[ $thumb_group ] as $thumb ) {
					$thumb_url = '';
					if ( is_array( $thumb ) ) {
						$thumb_url = isset( $thumb['url'] ) ? $thumb['url'] : ( isset( $thumb['src'] ) ? $thumb['src'] : '' );
					} elseif ( is_string( $thumb ) ) {
						$thumb_url = $thumb;
					}
					$thumb_url = lvjm_https_url( $thumb_url );
					if ( '' !== $thumb_url ) {
						$thumbs_urls[] = $thumb_url;
					}
				}
			}
		}

		return array_values( array_unique( $thumbs_urls ) );
	}
}

if ( ! function_exists( 'lvjm_get_vpapi_detail_value' ) ) {
	/**
	 * Retrieve the first non-empty value from details data.
	 *
	 * @param array $details The details data.
	 * @param array $keys    Keys to check.
	 * @param mixed $default Default value.
	 * @return mixed
	 */
	function lvjm_get_vpapi_detail_value( $details, $keys, $default = '' ) {
		foreach ( (array) $keys as $key ) {
			if ( isset( $details[ $key ] ) && '' !== $details[ $key ] ) {
				return $details[ $key ];
			}
		}
		return $default;
	}
}
