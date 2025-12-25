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

if ( ! function_exists( 'lvjm_mask_sensitive_payload' ) ) {
	/**
	 * Mask sensitive fields in logs.
	 *
	 * @param string $payload Raw payload string.
	 * @return string
	 */
	function lvjm_mask_sensitive_payload( $payload ) {
		$payload = (string) $payload;
		if ( '' === $payload ) {
			return $payload;
		}

		$patterns = array(
			'/(\"accessKey\"\s*:\s*\")([^"]+)(\")/i',
			'/(\"psid\"\s*:\s*\")([^"]+)(\")/i',
			'/(accessKey=)([^&\s"]+)/i',
			'/(psid=)([^&\s"]+)/i',
		);

		return preg_replace( $patterns, '$1***$3', $payload );
	}
}

if ( ! function_exists( 'lvjm_normalize_remote_url' ) ) {
	/**
	 * Normalize remote URLs from VPAPI payloads.
	 *
	 * @param string $url URL to normalize.
	 * @return string
	 */
	function lvjm_normalize_remote_url( $url ) {
		$url = str_replace( '\\/\\/', '//', (string) $url );
		$url = str_replace( '\\/', '/', $url );
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		if ( 0 === strpos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		if ( function_exists( 'lvjm_https_url' ) ) {
			$url = lvjm_https_url( $url );
		}

		return esc_url_raw( $url );
	}
}

if ( ! function_exists( 'lvjm_normalize_remote_urls' ) ) {
	/**
	 * Normalize a list of remote URLs.
	 *
	 * @param array $urls URLs to normalize.
	 * @return array
	 */
	function lvjm_normalize_remote_urls( $urls ) {
		$normalized = array();
		foreach ( (array) $urls as $url ) {
			$normalized_url = lvjm_normalize_remote_url( $url );
			if ( '' !== $normalized_url ) {
				$normalized[] = $normalized_url;
			}
		}

		return array_values( array_unique( $normalized ) );
	}
}

if ( ! function_exists( 'lvjm_build_vpapi_details_url' ) ) {
	/**
	 * Build VPAPI client details URL from the list feed URL.
	 *
	 * @param string $video_id      The video id.
	 * @param string $json_feed_url The list feed URL.
	 * @param string $locale        Optional locale.
	 * @return string
	 */
	function lvjm_build_vpapi_details_url( $video_id, $json_feed_url, $locale = '' ) {
		$video_id     = (string) $video_id;
		$json_feed_url = (string) $json_feed_url;
		if ( '' === $video_id || '' === $json_feed_url ) {
			return '';
		}

		$parsed_url = wp_parse_url( $json_feed_url );
		if ( empty( $parsed_url['path'] ) ) {
			return '';
		}

		$path = $parsed_url['path'];
		if ( false !== strpos( $path, '/api/video-promotion/v1/list' ) ) {
			$path = str_replace( '/api/video-promotion/v1/list', '/api/video-promotion/v1/client/details/' . rawurlencode( $video_id ) . '/', $path );
		} elseif ( preg_match( '#/video-promotion/v1/list/?$#', $path ) ) {
			$path = preg_replace( '#/video-promotion/v1/list/?$#', '/video-promotion/v1/client/details/' . rawurlencode( $video_id ) . '/', $path );
		} elseif ( preg_match( '#/list/?$#', $path ) && false !== strpos( $path, 'video-promotion' ) ) {
			$path = preg_replace( '#/list/?$#', '/client/details/' . rawurlencode( $video_id ) . '/', $path );
		} else {
			return '';
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

		return "$scheme$user$pass$host$port$path$query$fragment";
	}
}

if ( ! function_exists( 'lvjm_vpapi_details_has_thumbs' ) ) {
	/**
	 * Check whether details payload has thumbnails.
	 *
	 * @param array $details_video The details video object.
	 * @return bool
	 */
	function lvjm_vpapi_details_has_thumbs( $details_video ) {
		if ( ! is_array( $details_video ) ) {
			return false;
		}

		$keys = array(
			'thumbsUrls',
			'thumbUrls',
			'thumbs_urls',
			'thumb_urls',
			'thumbnailUrls',
			'thumbnailsUrls',
			'thumbnails',
			'thumbs',
			'timelineThumbnails',
			'previewImages',
			'preview_images',
			'thumbImage',
			'thumb_image',
		);

		foreach ( $keys as $key ) {
			if ( ! empty( $details_video[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}
}

if ( ! function_exists( 'lvjm_fetch_video_details_cached' ) ) {
	/**
	 * Fetch VPAPI video details and cache them with transients.
	 *
	 * @param string $video_id   The video id.
	 * @param string $partner_id The partner id.
	 * @param string $locale     The locale if available.
	 * @param bool   $force_refresh Force refresh bypassing transient cache.
	 * @return array
	 */
	function lvjm_fetch_video_details_cached( $video_id, $partner_id, $locale = '', $force_refresh = false ) {
		$video_id = (string) $video_id;
		if ( '' === $video_id ) {
			return array();
		}

		$transient_key = 'lvjm_vpapi_details_' . $video_id;
		$cached        = $force_refresh ? false : get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$saved_partner_options = WPSCORE()->get_product_option( 'LVJM', 'livejasmin_options' );
		$json_feed_url         = isset( $saved_partner_options['json_feed_url'] ) ? $saved_partner_options['json_feed_url'] : '';
		if ( '' === $json_feed_url ) {
			return array();
		}

		$details_url = lvjm_build_vpapi_details_url( $video_id, $json_feed_url, $locale );
		if ( '' === $details_url ) {
			return array();
		}

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
		$raw_body = wp_remote_retrieve_body( $response );
		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
			$logged_once = isset( $GLOBALS['lvjm_vpapi_logged_payload'] ) ? (bool) $GLOBALS['lvjm_vpapi_logged_payload'] : false;
			if ( ! $logged_once && '' !== $raw_body ) {
				$GLOBALS['lvjm_vpapi_logged_payload'] = true;
				$masked_payload                     = lvjm_mask_sensitive_payload( substr( $raw_body, 0, 1500 ) );
				lvjm_importer_log( 'info', 'VPAPI details raw payload (truncated)=' . $masked_payload );
			}
		}
		if ( $status_code < 200 || $status_code >= 300 ) {
			lvjm_importer_log( 'warning', 'VPAPI details request failed for ' . $details_url . ' (status: ' . $status_code . ').' );
			return array();
		}

		$response_body = json_decode( $raw_body, true );
		if ( ! is_array( $response_body ) ) {
			lvjm_importer_log( 'warning', 'VPAPI details request failed for ' . $details_url . ' (status: ' . $status_code . ').' );
			return array();
		}

		$details_video = function_exists( 'lvjm_extract_vpapi_video_object' ) ? lvjm_extract_vpapi_video_object( $response_body ) : array();
		$has_thumbs    = lvjm_vpapi_details_has_thumbs( $details_video );
		$cache_ttl     = $has_thumbs ? 12 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS;
		set_transient( $transient_key, $response_body, $cache_ttl );

		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
			$masked_url    = lvjm_mask_sensitive_payload( $details_url );
			$details_keys  = is_array( $response_body ) ? implode( ',', array_keys( $response_body ) ) : 'non-array';
			$video_keys    = is_array( $details_video ) ? implode( ',', array_keys( $details_video ) ) : 'n/a';
			$preview_value = array();
			if ( isset( $details_video['previewImages'] ) ) {
				$preview_value = $details_video['previewImages'];
			} elseif ( isset( $details_video['preview_images'] ) ) {
				$preview_value = $details_video['preview_images'];
			}
			if ( is_string( $preview_value ) ) {
				$preview_value = array_filter( array_map( 'trim', explode( ',', $preview_value ) ) );
			}
			$preview_count = is_array( $preview_value ) ? count( $preview_value ) : 0;
			$thumb_samples = array();
			if ( is_array( $details_video ) ) {
				$thumbs_urls  = lvjm_collect_vpapi_thumbs_urls( $details_video );
				$thumb_samples = array_slice( $thumbs_urls, 0, 2 );
			}
			lvjm_importer_log(
				'info',
				sprintf(
					'VPAPI details response url=%s status=%s keys=[%s] video_keys=[%s] previewImages_count=%d samples=%s',
					$masked_url,
					$status_code,
					$details_keys,
					$video_keys,
					$preview_count,
					empty( $thumb_samples ) ? 'none' : implode( ',', $thumb_samples )
				)
			);
		}

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

		if ( isset( $details['data']['video'] ) && is_array( $details['data']['video'] ) ) {
			return $details['data']['video'];
		}

		if ( isset( $details['data']['item'] ) && is_array( $details['data']['item'] ) ) {
			return $details['data']['item'];
		}

		if ( isset( $details['data']['items'][0] ) && is_array( $details['data']['items'][0] ) ) {
			return $details['data']['items'][0];
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

                // Ignore data/blob URLs.
                $lower = strtolower( $url );
                if ( 0 === strpos( $lower, 'data:' ) || 0 === strpos( $lower, 'blob:' ) ) {
                        return $url;
                }

                if ( 0 === strpos( $url, '//' ) ) {
                        $url = 'https:' . $url;
                }

                // If the admin is HTTPS, always upgrade to HTTPS to avoid mixed content.
                if ( is_ssl() ) {
                        return set_url_scheme( $url, 'https' );
                }

                // For non-http(s) schemes, return untouched.
                $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
                if ( $scheme && ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
                        return $url;
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
		$matched_keys = array();
		$sample_urls  = array();
		$thumbs_keys  = array( 'thumbsUrls', 'thumbUrls', 'thumbs_urls', 'thumb_urls', 'thumbnailUrls', 'thumbnailsUrls', 'thumbnails', 'thumbs', 'timelineThumbnails', 'previewImages', 'preview_images' );
		foreach ( $thumbs_keys as $thumbs_key ) {
			if ( ! isset( $details[ $thumbs_key ] ) ) {
				continue;
			}
			$value = $details[ $thumbs_key ];
			$matched_keys[] = $thumbs_key;
			if ( is_string( $value ) ) {
				$value = array_map( 'trim', explode( ',', $value ) );
			}
			if ( is_array( $value ) ) {
				foreach ( $value as $thumb ) {
					$thumb_url = '';
					if ( is_array( $thumb ) ) {
						$thumb_url = isset( $thumb['url'] ) ? $thumb['url'] : ( isset( $thumb['src'] ) ? $thumb['src'] : '' );
					} elseif ( is_string( $thumb ) ) {
						$thumb_url = $thumb;
					}
					$thumb_url = lvjm_normalize_remote_url( $thumb_url );
					if ( '' !== $thumb_url ) {
						$thumbs_urls[] = $thumb_url;
						if ( count( $sample_urls ) < 2 ) {
							$sample_urls[] = $thumb_url;
						}
					}
				}
			}
		}
		if ( isset( $details['assets']['thumbnails'] ) && is_array( $details['assets']['thumbnails'] ) ) {
			$matched_keys[] = 'assets.thumbnails';
			foreach ( $details['assets']['thumbnails'] as $thumb ) {
				$thumb_url = '';
				if ( is_array( $thumb ) ) {
					$thumb_url = isset( $thumb['url'] ) ? $thumb['url'] : ( isset( $thumb['src'] ) ? $thumb['src'] : '' );
				} elseif ( is_string( $thumb ) ) {
					$thumb_url = $thumb;
				}
				$thumb_url = lvjm_normalize_remote_url( $thumb_url );
				if ( '' !== $thumb_url ) {
					$thumbs_urls[] = $thumb_url;
					if ( count( $sample_urls ) < 2 ) {
						$sample_urls[] = $thumb_url;
					}
				}
			}
		}

		$thumbs_urls = array_values( array_unique( $thumbs_urls ) );

		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
			lvjm_importer_log(
				'info',
				sprintf(
					'VPAPI thumbs collected keys=%s count=%d samples=%s',
					empty( $matched_keys ) ? 'none' : implode( ',', array_unique( $matched_keys ) ),
					count( $thumbs_urls ),
					empty( $sample_urls ) ? 'none' : implode( ',', $sample_urls )
				)
			);
		}

		return $thumbs_urls;
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
