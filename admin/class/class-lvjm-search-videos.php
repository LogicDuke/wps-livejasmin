<?php
/**
 * Admin Class plugin file.
 *
 * @package LIVEJASMIN\Admin\Class
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Search Videos Class.
 *
 * @since 1.0.0
 */
class LVJM_Search_Videos {

	/**
	 * The params.
	 *
	 * @var array $params
	 * @access private
	 */
	private $params;

	/**
	 * The errors.
	 *
	 * @var array $errors
	 * @access private
	 */
	private $errors;

	/**
	 * The feed_url.
	 *
	 * @var string $feed_url
	 * @access private
	 */
	private $feed_url;

	/**
	 * The feed_infos.
	 *
	 * @var object $feed_infos
	 * @access private
	 */
	private $feed_infos;

	/**
	 * The videos.
	 *
	 * @var array $videos
	 * @access private
	 */
	private $videos;

	/**
	 * The searched_data.
	 *
	 * @var array $searched_data
	 * @access private
	 */
	private $searched_data;

	/**
	 * The wp_version.
	 *
	 * @var string $wp_version
	 * @access private
	 */
	private $wp_version;

	/**
	 * The partner_existing_videos_ids.
	 *
	 * @var array $partner_existing_videos_ids
	 * @access private
	 */
	private $partner_existing_videos_ids;

	/**
	 * The partner_unwanted_videos_ids.
	 *
	 * @var array $partner_unwanted_videos_ids
	 * @access private
	 */
	private $partner_unwanted_videos_ids;

	/**
	 * Item constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params The params needed to make the search.
	 * @return void
	 */
	public function __construct( $params ) {
		global $wp_version;
		$this->wp_version = $wp_version;
		$this->params     = $params;

		// connecting to API.
		$api_params = array(
			'license_key'  => WPSCORE()->get_license_key(),
			'signature'    => WPSCORE()->get_client_signature(),
			'server_addr'  => WPSCORE()->get_server_addr(),
			'server_name'  => WPSCORE()->get_server_name(),
			'core_version' => WPSCORE_VERSION,
			'time'         => ceil( time() / 1000 ),
			'partner_id'   => $this->params['partner']['id'],
		);

		$args = array(
			'timeout'   => 50,
			'sslverify' => false,
		);

		$base64_params = base64_encode( wp_json_encode( $api_params ) );

		$response = wp_remote_get( WPSCORE()->get_api_url( 'lvjm/get_feed', $base64_params ), $args );

		if ( ! is_wp_error( $response ) && 'application/json; charset=UTF-8' === $response['headers']['content-type'] ) {

			$response_body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( null === $response_body ) {
				WPSCORE()->write_log( 'error', 'Connection to API (get_feed) failed (null)', __FILE__, __LINE__ );
				return false;
			} elseif ( 200 !== $response_body->data->status ) {
				WPSCORE()->write_log( 'error', 'Connection to API (get_feed) failed (status: <code>' . $response_body->data->status . '</code> message: <code>' . $response_body->message . '</code>)', __FILE__, __LINE__ );
				return false;
			} else {
				// success.
				if ( isset( $response_body->data->feed_infos ) ) {
					$this->feed_infos = $response_body->data->feed_infos;
					$this->feed_url   = $this->get_partner_feed_infos( $this->feed_infos->feed_url->data );
					if ( ! $this->feed_url ) {
						WPSCORE()->write_log( 'error', 'Connection to Partner\'s API failed (feed url: <code>' . $this->feed_url . '</code> partner id: <code>:' . $this->params['partner']['id'] . '</code>)', __FILE__, __LINE__ );
						return false;
					}
					switch ( $this->params['partner']['data_type'] ) {
						case 'json':
							if ( $this->is_performer_search() ) {
								return $this->retrieve_performer_videos_from_json_feed();
							}
							return $this->retrieve_videos_from_json_feed();
						default:
							break;
					}
				} else {
					WPSCORE()->write_log( 'error', 'Connection to API (get_feed) failed (message: <code>' . $response_body->message . '</code>)', __FILE__, __LINE__ );
				}
			}
		} elseif ( isset( $response->errors['http_request_failed'] ) ) {
				WPSCORE()->write_log( 'error', 'Connection to API (get_feed) failed (error: <code>' . wp_json_encode( $response->errors ) . '</code>)', __FILE__, __LINE__ );
				return false;
		}
		return false;
	}

	/**
	 * Get videos from the current object.
	 *
	 * @since 1.0.0
	 *
	 * @return array The videos.
	 */
	public function get_videos() {
		return $this->videos;
	}

	/**
	 * Get searched data.
	 *
	 * @since 1.0.0
	 *
	 * @return array The searched data.
	 */
	public function get_searched_data() {
		return $this->searched_data;
	}

	/**
	 * Get errors.
	 *
	 * @since 1.0.0
	 *
	 * @return array The errors caught.
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Get errors.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if there are some errors, false if not.
	 */
	public function has_errors() {
		return ! empty( $this->errors );
	}

	/**
	 * Gett feed url with orientation.
	 *
	 * @since 1.0.7
	 *
	 * @return string The feed url with orientation.
	 */
	private function get_feed_url_with_orientation() {
		$parsed_url = wp_parse_url( $this->feed_url );
		parse_str( $parsed_url['query'], $old_query );
		$new_query = array();
		foreach ( $old_query as $key => $value ) {
			if ( 'tags' !== $key ) {
				$new_query[ $key ] = $value;
				continue;
			}
			$new_query['tags']              = $value;
			$new_query['sexualOrientation'] = 'straight';
			if ( strpos( $value, 'gay' ) !== false ) {
				$new_query[ $key ]              = trim( str_replace( 'gay', '', $value ) );
				$new_query['sexualOrientation'] = 'gay';
			}
			if ( strpos( $value, 'shemale' ) !== false ) {
				$new_query[ $key ]              = trim( str_replace( 'shemale', '', $value ) );
				$new_query['sexualOrientation'] = 'shemale';
			}
		}
		$parsed_url['query'] = http_build_query( $new_query );
		$feed_url            = $this->unparse_url( $parsed_url );
		return $feed_url;
	}

	/**
	 * Unparse a parsed url.
	 *
	 * @param array $parsed_url The parsed url.
	 *
	 * @return string The unparsed url.
	 */
	private function unparse_url( $parsed_url ) {
		$scheme   = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$port     = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
		$user     = isset( $parsed_url['user'] ) ? $parsed_url['user'] : '';
		$pass     = isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
		$pass     = ( $user || $pass ) ? "$pass@" : '';
		$path     = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';
		$query    = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
		$fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';
		return "$scheme$user$pass$host$port$path$query$fragment";
	}

	/**
	 * Check if current search is a performer search.
	 *
	 * @return bool True when performer search mode is enabled.
	 */
	private function is_performer_search() {
		return isset( $this->params['search_mode'], $this->params['performer_id'] )
			&& 'performer' === $this->params['search_mode']
			&& '' !== trim( (string) $this->params['performer_id'] );
	}

	/**
	 * Log importer debug messages when enabled.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	private function debug_importer_log( $message, $context = array() ) {
		if ( ! defined( 'LVJM_DEBUG_IMPORTER' ) || ! LVJM_DEBUG_IMPORTER ) {
			return;
		}
		$suffix = '';
		if ( ! empty( $context ) ) {
			$suffix = ' ' . wp_json_encode( $context );
		}
		WPSCORE()->write_log( 'info', '[TMW-IMPORTER] ' . $message . $suffix, __FILE__, __LINE__ );
	}

	/**
	 * Normalize a performer id for matching.
	 *
	 * @param string $performer_id Performer id.
	 * @return string Normalized performer id.
	 */
	private function normalize_performer_id( $performer_id ) {
		$normalized = strtolower( trim( (string) $performer_id ) );
		return preg_replace( '/[^a-z0-9]/', '', $normalized );
	}

	/**
	 * Normalize a URL to https when possible.
	 *
	 * @param string $url URL.
	 * @return string Normalized URL.
	 */
	private function normalize_https_url( $url ) {
		$url = (string) $url;
		if ( 0 === strpos( $url, 'http://' ) ) {
			return 'https://' . substr( $url, 7 );
		}
		return $url;
	}

	/**
	 * Normalize video URLs for output.
	 *
	 * @param array $video Video data.
	 * @return array Normalized video data.
	 */
	private function normalize_video_urls( $video ) {
		if ( isset( $video['thumb_url'] ) ) {
			$video['thumb_url'] = $this->normalize_https_url( $video['thumb_url'] );
		}
		if ( isset( $video['thumbs_urls'] ) && is_array( $video['thumbs_urls'] ) ) {
			$video['thumbs_urls'] = array_map( array( $this, 'normalize_https_url' ), $video['thumbs_urls'] );
		}
		return $video;
	}

	/**
	 * Normalize cache component values.
	 *
	 * @param string $value Cache component.
	 * @return string Normalized cache component.
	 */
	private function normalize_cache_component( $value ) {
		$normalized = strtolower( trim( (string) $value ) );
		return preg_replace( '/[^a-z0-9]+/', '_', $normalized );
	}

	/**
	 * Normalize a tag and detect orientation.
	 *
	 * @param string $tag Tag string.
	 * @return array Array with normalized tag and orientation.
	 */
	private function normalize_tag_and_orientation( $tag ) {
		$orientation = 'straight';
		$tag_value   = (string) $tag;
		if ( strpos( $tag_value, 'gay' ) !== false ) {
			$tag_value   = trim( str_replace( 'gay', '', $tag_value ) );
			$orientation = 'gay';
		}
		if ( strpos( $tag_value, 'shemale' ) !== false ) {
			$tag_value   = trim( str_replace( 'shemale', '', $tag_value ) );
			$orientation = 'shemale';
		}
		return array( $tag_value, $orientation );
	}

	/**
	 * Build a feed url for performer searches.
	 *
	 * @param string $tag Tag value.
	 * @param string $orientation Orientation value.
	 * @param bool   $omit_tags Whether to omit tags.
	 * @param string $filter_param Filter parameter name.
	 * @param string $filter_value Filter value.
	 * @return string The feed url.
	 */
	private function build_feed_url_for_performer( $tag, $orientation, $omit_tags, $filter_param = '', $filter_value = '' ) {
		$parsed_url = wp_parse_url( $this->feed_url );
		$query      = array();
		if ( isset( $parsed_url['query'] ) ) {
			parse_str( $parsed_url['query'], $query );
		}
		$query['sexualOrientation'] = $orientation;
		if ( $omit_tags ) {
			unset( $query['tags'] );
		} elseif ( '' !== $tag ) {
			$query['tags'] = $tag;
		}
		if ( '' !== $filter_param && '' !== $filter_value ) {
			$query[ $filter_param ] = $filter_value;
		}
		$parsed_url['query'] = http_build_query( $query );
		return $this->unparse_url( $parsed_url );
	}

	/**
	 * Extract performer id from a feed item.
	 *
	 * @param array $item Feed item.
	 * @return string Performer id if available.
	 */
	private function extract_performer_id_from_item( $item ) {
		$item = (array) $item;
		foreach ( array( 'performerId', 'performer_id', 'performer', 'model', 'modelName', 'uploader', 'uploaderId' ) as $key ) {
			if ( isset( $item[ $key ] ) ) {
				return (string) $item[ $key ];
			}
		}
		return '';
	}

	/**
	 * Extract feed items from a response body.
	 *
	 * @param array $response_body Response body.
	 * @return array Feed items.
	 */
	private function get_feed_items_from_response( $response_body ) {
		if ( ! is_array( $response_body ) || empty( $response_body['data'] ) ) {
			return array();
		}
		if ( isset( $this->feed_infos->feed_item_path->node ) ) {
			$root = $this->feed_infos->feed_item_path->node;
			return isset( $response_body['data'][ $root ] ) ? (array) $response_body['data'][ $root ] : array();
		}
		if ( isset( $response_body['data']['videos'] ) ) {
			return (array) $response_body['data']['videos'];
		}
		return (array) $response_body['data'];
	}

	/**
	 * Log a feed response summary.
	 *
	 * @param string     $message Message prefix.
	 * @param string     $url Feed URL.
	 * @param array|WP_Error $response Response object.
	 * @param array|null $response_body Parsed response body.
	 * @return void
	 */
	private function log_feed_response( $message, $url, $response, $response_body ) {
		if ( is_wp_error( $response ) ) {
			$this->debug_importer_log(
				$message,
				array(
					'url'   => $url,
					'error' => $response->get_error_message(),
				)
			);
			return;
		}

		$items       = $this->get_feed_items_from_response( $response_body );
		$sample_keys = array();
		if ( ! empty( $items ) ) {
			$first_item  = (array) reset( $items );
			$sample_keys = array_keys( $first_item );
		}
		$this->debug_importer_log(
			$message,
			array(
				'url'         => $url,
				'status'      => wp_remote_retrieve_response_code( $response ),
				'count'       => count( $items ),
				'sample_keys' => $sample_keys,
			)
		);
	}

	/**
	 * Find videos from a json feed using performer search.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if there are no error, false if not.
	 */
	private function retrieve_performer_videos_from_json_feed() {
		$performer_raw        = isset( $this->params['performer_id'] ) ? sanitize_text_field( (string) $this->params['performer_id'] ) : '';
		$normalized_performer = $this->normalize_performer_id( $performer_raw );
		$tag_input            = isset( $this->params['cat_s'] ) ? sanitize_text_field( (string) $this->params['cat_s'] ) : '';

		if ( '' === $normalized_performer ) {
			$this->videos        = array();
			$this->searched_data = array(
				'videos_details' => array(),
				'counters'       => array(
					'valid_videos'    => 0,
					'invalid_videos'  => 0,
					'existing_videos' => 0,
					'removed_videos'  => 0,
				),
				'videos'         => array(),
			);
			return true;
		}

		list( $tag_value, $orientation ) = $this->normalize_tag_and_orientation( $tag_input );
		$tag_for_cache                  = '' !== $tag_value ? $tag_value : 'all';
		// Cache key includes orientation + tag (or all) + normalized performer id.
		$cache_key                      = 'lvjm_perf_' . $orientation . '_' . $this->normalize_cache_component( $tag_for_cache ) . '_' . $normalized_performer;
		$cached                         = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['videos'], $cached['searched_data'] ) ) {
			$this->videos        = $cached['videos'];
			$this->searched_data = $cached['searched_data'];
			$this->debug_importer_log( 'Performer search cache hit.', array( 'cache_key' => $cache_key ) );
			return true;
		}

		$limit                 = isset( $this->params['limit'] ) ? intval( $this->params['limit'] ) : 60;
		$limit                 = min( $limit, 60 );
		$existing_ids          = $this->get_partner_existing_ids();
		$array_valid_videos    = array();
		$counters              = array(
			'valid_videos'    => 0,
			'invalid_videos'  => 0,
			'existing_videos' => 0,
			'removed_videos'  => 0,
		);
		$videos_details        = array();
		$count_valid_feed_items = 0;
		$end                   = false;

		$args = array(
			'timeout'   => 300,
			'sslverify' => false,
		);
		$args['user-agent'] = 'WordPress/' . $this->wp_version . '; ' . home_url();
		if ( isset( $this->feed_infos->feed_auth ) ) {
			$args['headers'] = array( 'Authorization' => $this->get_partner_feed_infos( $this->feed_infos->feed_auth->data ) );
		}

		$current_page = intval( $this->get_partner_feed_infos( $this->feed_infos->feed_first_page->data ) );
		$paged        = '';
		if ( isset( $this->feed_infos->feed_paged ) ) {
			$paged = $this->get_partner_feed_infos( $this->feed_infos->feed_paged->data );
		}

		$this->debug_importer_log(
			'Performer search incoming params.',
			array(
				'search_mode'       => $this->params['search_mode'],
				'performer_id'      => $performer_raw,
				'cat_s'             => $tag_input,
				'sexualOrientation' => $orientation,
			)
		);

		$omit_tags = '' === $tag_value;
		if ( $omit_tags ) {
			$probe_url      = $this->build_feed_url_for_performer( $tag_value, $orientation, true );
			$probe_response = wp_remote_get( '' !== $paged ? $probe_url . $paged . $current_page : $probe_url, $args );
			$probe_body     = json_decode( wp_remote_retrieve_body( $probe_response ), true );
			$this->log_feed_response( 'Performer probe without tags.', $probe_url, $probe_response, $probe_body );
			$probe_items = $this->get_feed_items_from_response( $probe_body );
			if ( is_wp_error( $probe_response )
				|| empty( $probe_items ) ) {
				$tag_value = '69';
				$omit_tags = false;
				$this->debug_importer_log( 'Performer probe required tags fallback.', array( 'tags' => $tag_value ) );
			}
		}

		$filter_params = array( 'performerId', 'performer_id', 'performer', 'model', 'modelName', 'uploader', 'uploaderId', 'q', 'search' );
		$filter_used   = '';
		$root_feed_url = '';
		$first_body    = null;
		$attempted_urls = array();

		$cached_filter_param = get_transient( 'lvjm_vpapi_perf_filter_param' );
		if ( $cached_filter_param && 'none' !== $cached_filter_param ) {
			$candidate_url = $this->build_feed_url_for_performer( $tag_value, $orientation, $omit_tags, $cached_filter_param, $performer_raw );
			$attempted_urls[] = $candidate_url;
			$response      = wp_remote_get( '' !== $paged ? $candidate_url . $paged . $current_page : $candidate_url, $args );
			$body          = json_decode( wp_remote_retrieve_body( $response ), true );
			$this->log_feed_response( 'Performer cached filter probe.', $candidate_url, $response, $body );
			$items = $this->get_feed_items_from_response( $body );
			if ( ! empty( $items ) ) {
				$filter_used   = $cached_filter_param;
				$root_feed_url = $candidate_url;
				$first_body    = $body;
			} else {
				$cached_filter_param = false;
			}
		}

		if ( false === $cached_filter_param ) {
			foreach ( $filter_params as $filter_param ) {
				$candidate_url  = $this->build_feed_url_for_performer( $tag_value, $orientation, $omit_tags, $filter_param, $performer_raw );
				$attempted_urls[] = $candidate_url;
				$response       = wp_remote_get( '' !== $paged ? $candidate_url . $paged . $current_page : $candidate_url, $args );
				$body           = json_decode( wp_remote_retrieve_body( $response ), true );
				$this->log_feed_response( 'Performer filter probe.', $candidate_url, $response, $body );
				if ( is_wp_error( $response ) ) {
					continue;
				}
				$items = $this->get_feed_items_from_response( $body );
				if ( ! empty( $items ) ) {
					$filter_used   = $filter_param;
					$root_feed_url = $candidate_url;
					$first_body    = $body;
					break;
				}
			}
			set_transient( 'lvjm_vpapi_perf_filter_param', '' !== $filter_used ? $filter_used : 'none', DAY_IN_SECONDS );
		}

		if ( '' === $root_feed_url ) {
			$root_feed_url = $this->build_feed_url_for_performer( $tag_value, $orientation, $omit_tags );
			$attempted_urls[] = $root_feed_url;
			$this->debug_importer_log( 'Performer fallback scan enabled (no server-side filter param).' );
		}

		$pages_fetched = 0;
		$scanned_items = 0;
		$max_scan_items = 1200;
		while ( false === $end ) {
			if ( 0 === $pages_fetched && is_array( $first_body ) ) {
				$response_body = $first_body;
			} else {
				$this->feed_url = '' !== $paged ? $root_feed_url . $paged . $current_page : $root_feed_url;
				$response       = wp_remote_get( $this->feed_url, $args );

				if ( is_wp_error( $response ) ) {
					WPSCORE()->write_log( 'error', 'Retrieving videos from JSON feed failed<code>ERROR: ' . wp_json_encode( $response->errors ) . '</code>', __FILE__, __LINE__ );
					return false;
				}

				$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
				$this->log_feed_response( 'Performer feed page.', $this->feed_url, $response, $response_body );
			}

			if ( isset( $response_body['status'] ) && 'ERROR' === $response_body['status'] ) {
				$end              = true;
				$page_end         = true;
				$videos_details[] = array(
					'id'       => 'end',
					'response' => 'livejasmin API Error',
				);
			}

			if ( empty( $response_body['data']['videos'] ) || ( isset( $response_body['data']['pagination']['totalPages'] ) && $current_page > $response_body['data']['pagination']['totalPages'] ) ) {
				$end              = true;
				$page_end         = true;
				$videos_details[] = array(
					'id'       => 'end',
					'response' => 'No more videos',
				);
			} else {
				if ( isset( $this->feed_infos->feed_item_path->node ) ) {
					$root       = $this->feed_infos->feed_item_path->node;
					$array_feed = $response_body['data'][ $root ];
				} else {
					$array_feed = $response_body['data'];
				}
				$count_total_feed_items = count( $array_feed );
				$current_item           = 0;
				$page_end               = false;
			}

			while ( false === $page_end ) {
				$feed_item_data = $array_feed[ $current_item ];
				++$scanned_items;
				$performer_id   = $this->extract_performer_id_from_item( $feed_item_data );
				if ( '' === $performer_id || $this->normalize_performer_id( $performer_id ) !== $normalized_performer ) {
					++$current_item;
					if ( $current_item >= $count_total_feed_items ) {
						$page_end = true;
						++$current_page;
					}
					if ( $scanned_items >= $max_scan_items ) {
						$end = true;
						$page_end = true;
					}
					continue;
				}

				$feed_item = new LVJM_Json_Item( $feed_item_data );
				$feed_item->init( $this->params, $this->feed_infos );
				if ( $feed_item->is_valid() ) {
					if ( ! in_array( $feed_item->get_id(), (array) $existing_ids['partner_all_videos_ids'], true ) ) {
						$video_data           = (array) $feed_item->get_data_for_json( $count_valid_feed_items );
						$array_valid_videos[] = $this->normalize_video_urls( $video_data );
						$videos_details[]     = array(
							'id'       => $feed_item->get_id(),
							'response' => 'Success',
						);
						++$counters['valid_videos'];
						++$count_valid_feed_items;
					} elseif ( in_array( $feed_item->get_id(), (array) $existing_ids['partner_existing_videos_ids'], true ) ) {
						$videos_details[] = array(
							'id'       => $feed_item->get_id(),
							'response' => 'Already imported',
						);
						++$counters['existing_videos'];
					} elseif ( in_array( $feed_item->get_id(), (array) $existing_ids['partner_unwanted_videos_ids'], true ) ) {
						$videos_details[] = array(
							'id'       => $feed_item->get_id(),
							'response' => 'You removed it from search results',
						);
						++$counters['removed_videos'];
					}
				} else {
					$videos_details[] = array(
						'id'       => $feed_item->get_id(),
						'response' => 'Invalid',
					);
					++$counters['invalid_videos'];
				}

				if ( $count_valid_feed_items >= $limit || $current_item >= ( $count_total_feed_items - 1 ) ) {
					$page_end = true;
					++$current_page;
					if ( $count_valid_feed_items >= $limit ) {
						$end = true;
					}
				}
				if ( $scanned_items >= $max_scan_items ) {
					$end = true;
					$page_end = true;
				}
				++$current_item;
			}
			++$pages_fetched;
			if ( $pages_fetched >= 10 ) {
				$end = true;
			}
		}

		$this->searched_data = array(
			'videos_details' => $videos_details,
			'counters'       => $counters,
			'videos'         => $array_valid_videos,
		);
		$this->videos        = $array_valid_videos;

		set_transient(
			$cache_key,
			array(
				'videos'        => $this->videos,
				'searched_data' => $this->searched_data,
			),
			6 * HOUR_IN_SECONDS
		);

		$this->debug_importer_log(
			'Performer search completed.',
			array(
				'mode'          => 'performer',
				'tag'           => $tag_value,
				'orientation'   => $orientation,
				'filter_param'  => $filter_used,
				'pages_fetched' => $pages_fetched,
				'scanned_items' => $scanned_items,
				'results'       => count( $this->videos ),
			)
		);

		if ( empty( $this->videos ) ) {
			$this->debug_importer_log(
				'Performer search yielded no results.',
				array(
					'filter_param'   => '' !== $filter_used ? $filter_used : 'fallback_scan',
					'attempted_urls' => array_slice( $attempted_urls, -3 ),
				)
			);
		}

		return true;
	}

	/**
	 * Find videos from a json feed.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if there are no error, false if not.
	 */
	private function retrieve_videos_from_json_feed() {
		$existing_ids           = $this->get_partner_existing_ids();
		$array_valid_videos     = array();
		$counters               = array(
			'valid_videos'    => 0,
			'invalid_videos'  => 0,
			'existing_videos' => 0,
			'removed_videos'  => 0,
		);
		$videos_details         = array();
		$count_valid_feed_items = 0;
		$end                    = false;

		$root_feed_url = $this->get_feed_url_with_orientation();

		$args = array(
			'timeout'   => 300,
			'sslverify' => false,
		);

		$args['user-agent'] = 'WordPress/' . $this->wp_version . '; ' . home_url();

		if ( isset( $this->feed_infos->feed_auth ) ) {
			$args['headers'] = array( 'Authorization' => $this->get_partner_feed_infos( $this->feed_infos->feed_auth->data ) );
		}

		$current_page = intval( $this->get_partner_feed_infos( $this->feed_infos->feed_first_page->data ) );

		$paged = '';
		if ( isset( $this->feed_infos->feed_paged ) ) {
			$paged = $this->get_partner_feed_infos( $this->feed_infos->feed_paged->data );
		}

		$array_found_ids = array();

		while ( false === $end ) {

			if ( '' !== $paged ) {
					$this->feed_url = $root_feed_url . $paged . $current_page;
			}

			$response = wp_remote_get( $this->feed_url, $args );

			if ( is_wp_error( $response ) ) {
				WPSCORE()->write_log( 'error', 'Retrieving videos from JSON feed failed<code>ERROR: ' . wp_json_encode( $response->errors ) . '</code>', __FILE__, __LINE__ );
				return false;
			}

			if ( 403 === wp_remote_retrieve_response_code( $response ) ) {
				WPSCORE()->write_log( 'error', 'Your AWEmpire PSID or Access Key is wrong. Please configure LiveJasmin.', __FILE__, __LINE__ );
				$this->errors = array(
					'code'     => 'AWEmpire credentials error',
					'message'  => 'Your AWEmpire PSID or Access Key is wrong.',
					'solution' => 'Please configure LiveJasmin.',
				);
				return false;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $response_body['status'] && 'ERROR' === $response_body['status'] ) {
				$end              = true;
				$page_end         = true;
				$videos_details[] = array(
					'id'       => 'end',
					'response' => 'livejasmin API Error',
				);
			}

			// feed url last page reached.
			if ( 0 === count( (array) $response_body['data']['videos'] ) || $current_page > $response_body['data']['pagination']['totalPages'] ) {
				$end              = true;
				$page_end         = true;
				$videos_details[] = array(
					'id'       => 'end',
					'response' => 'No more videos',
				);
			} else {
				// améliorer root selon paramètres / ou si null dans la config.
				if ( isset( $this->feed_infos->feed_item_path->node ) ) {
					$root       = $this->feed_infos->feed_item_path->node;
					$array_feed = $response_body['data'][ $root ];
				} else {
					$root       = 0;
					$array_feed = $response_body['data'];
				}
				$count_total_feed_items = count( $array_feed );
				$current_item           = 0;
				$page_end               = false;
			}
			while ( false === $page_end ) {
				$feed_item = new LVJM_Json_Item( $array_feed[ $current_item ] );
				$feed_item->init( $this->params, $this->feed_infos );
				if ( $feed_item->is_valid() ) {
					if ( ! in_array( $feed_item->get_id(), (array) $existing_ids['partner_all_videos_ids'], true ) ) {
						$array_valid_videos[] = (array) $feed_item->get_data_for_json( $count_valid_feed_items );
						$videos_details[]     = array(
							'id'       => $feed_item->get_id(),
							'response' => 'Success',
						);
						++$counters['valid_videos'];
						++$count_valid_feed_items;
					} elseif ( in_array( $feed_item->get_id(), (array) $existing_ids['partner_existing_videos_ids'], true ) ) {
							$videos_details[] = array(
								'id'       => $feed_item->get_id(),
								'response' => 'Already imported',
							);
							++$counters['existing_videos'];
					} elseif ( in_array( $feed_item->get_id(), (array) $existing_ids['partner_unwanted_videos_ids'], true ) ) {
						$videos_details[] = array(
							'id'       => $feed_item->get_id(),
							'response' => 'You removed it from search results',
						);
						++$counters['removed_videos'];
					}
				} else {
					$videos_details[] = array(
						'id'       => $feed_item->get_id(),
						'response' => 'Invalid',
					);
					++$counters['invalid_videos'];
				}
				if ( ( $count_valid_feed_items >= $this->params['limit'] ) || $current_item >= ( $count_total_feed_items - 1 ) ) {
					$page_end = true;
					++$current_page;
					if ( $count_valid_feed_items >= $this->params['limit'] ) {
						$end = true;
					}
				}
				++$current_item;
			}
		}

		unset( $array_feed );
		$this->searched_data = array(
			'videos_details' => $videos_details,
			'counters'       => $counters,
			'videos'         => $array_valid_videos,
		);
		$this->videos        = $array_valid_videos;
		return true;
	}

	/**
	 * Get partner feed info from a feed item given.
	 *
	 * @since 1.0.0
	 *
	 * @param string $partner_feed_item The partner item.
	 * @return string The feede info.
	 */
	private function get_partner_feed_infos( $partner_feed_item ) {
		$results = array();
		preg_match_all( '/<%(.+)%>/U', $partner_feed_item, $results );

		foreach ( (array) $results[1] as $result ) {
			if ( strpos( $result, 'get_partner_option' ) !== false ) {
				$saved_partner_options = WPSCORE()->get_product_option( 'LVJM', $this->params['partner']['id'] . '_options' );
				$option                = str_replace( array( 'get_partner_option("', '")' ), array( '', '' ), $result );
				$new_result            = '$saved_partner_options["' . $option . '"]';
				$partner_feed_item     = str_replace( '<%' . $result . '%>', eval( 'return ' . $new_result . ';' ), $partner_feed_item );
			} else {
				$partner_feed_item = str_replace( '<%' . $result . '%>', eval( 'return ' . $result . ';' ), $partner_feed_item );
			}
		}

		return $partner_feed_item;
	}

	/**
	 * Get partner feed info from a feed item given.
	 *
	 * @since 1.0.0
	 *
	 * @return array The feede info.
	 */
	private function get_partner_existing_ids() {
		// retrieve existing ids from imported videos.
		global $wpdb;

		$custom_post_type = xbox_get_field_value( 'lvjm-options', 'custom-video-post-type' );
		$custom_post_type = '' !== $custom_post_type ? $custom_post_type : 'post';

		$query_str = "
			SELECT wposts.ID, wpostmetaVideoId.meta_value videoId
			FROM $wpdb->posts wposts, $wpdb->postmeta wpostmetasponsor, $wpdb->postmeta wpostmetaVideoId
			WHERE wposts.ID = wpostmetasponsor.post_id
			AND ( wpostmetasponsor.meta_key = 'partner' AND wpostmetasponsor.meta_value = %s )
			AND (wposts.ID =  wpostmetaVideoId.post_id AND wpostmetaVideoId.meta_key = 'video_id')
			AND wposts.post_type = %s
		";

		$bdd_videos                  = $wpdb->get_results( $wpdb->prepare( $query_str, $this->params['partner']['id'], $custom_post_type ), OBJECT );
		$partner_existing_videos_ids = array();
		foreach ( (array) $bdd_videos as $bdd_video ) {
			$partner_existing_videos_ids[] = $bdd_video->videoId;
		}
		unset( $bdd_videos );
		// retrieve existing ids from unwanted videos.
		$partner_unwanted_videos_ids = array();
		$unwanted_videos_ids         = WPSCORE()->get_product_option( 'LVJM', 'removed_videos_ids' );
		if ( isset( $unwanted_videos_ids[ $this->params['partner']['id'] ] ) && is_array( $unwanted_videos_ids[ $this->params['partner']['id'] ] ) ) {
			$partner_unwanted_videos_ids = $unwanted_videos_ids[ $this->params['partner']['id'] ];
		}
		unset( $unwanted_videos_ids );
		return array(
			'partner_existing_videos_ids' => $partner_existing_videos_ids,
			'partner_unwanted_videos_ids' => $partner_unwanted_videos_ids,
			'partner_all_videos_ids'      => array_merge( $partner_existing_videos_ids, $partner_unwanted_videos_ids ),
		);
	}
}
