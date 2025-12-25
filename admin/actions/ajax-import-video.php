<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

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
		if ( false !== strpos( $path, '/client/list' ) ) {
			$path = str_replace( '/client/list', '/client/details/' . rawurlencode( $video_id ), $path );
		} else {
			$path = rtrim( $path, '/' ) . '/client/details/' . rawurlencode( $video_id );
		}
		$parsed_url['path'] = $path;

		$scheme   = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$port     = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
		$user     = isset( $parsed_url['user'] ) ? $parsed_url['user'] : '';
		$pass     = isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
		$pass     = ( $user || $pass ) ? "$pass@" : '';
		$query    = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
		$fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';

		$details_url = "$scheme$user$pass$host$port$path$query$fragment";
		if ( '' !== $locale ) {
			$details_url .= ( false === strpos( $details_url, '?' ) ? '?' : '&' ) . 'locale=' . rawurlencode( $locale );
		}

		$args = array(
			'timeout'   => 300,
			'sslverify' => false,
		);

		$response = wp_remote_get( $details_url, $args );
		if ( is_wp_error( $response ) ) {
			return array();
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $response_body ) ) {
			return array();
		}

		set_transient( $transient_key, $response_body, 12 * HOUR_IN_SECONDS );

		return $response_body;
	}
}

if ( ! function_exists( 'lvjm_get_detail_value' ) ) {
	/**
	 * Retrieve the first non-empty value from details data.
	 *
	 * @param array $data The details data.
	 * @param array $keys Keys to check in order.
	 * @return string
	 */
	function lvjm_get_detail_value( $data, $keys ) {
		foreach ( $keys as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				continue;
			}
			$value = $data[ $key ];
			if ( is_array( $value ) ) {
				$value = reset( $value );
			}
			$value = (string) $value;
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}
}

/**
 * Import videos in Ajax or PHP call.
 *
 * @param mixed $params       Array of parameters if this function is called in PHP.
 * @return void|array $output New post ID if success, -1 if not. Returned only if this function is called in PHP.
 */
function lvjm_import_video( $params = '' ) {
	$ajax_call = '' === $params;

	if ( $ajax_call ) {
		check_ajax_referer( 'ajax-nonce', 'nonce' );
		$params = $_POST;
	}

	if ( ! isset( $params['partner_id'], $params['video_infos'], $params['status'], $params['feed_id'], $params['cat_s'], $params['cat_wp'] ) ) {
		wp_die( 'Some parameters are missing!' );
	}

	if ( empty( $params['video_infos']['thumb_url'] ) || empty( $params['video_infos']['trailer_url'] ) ) {
		$video_id     = isset( $params['video_infos']['id'] ) ? $params['video_infos']['id'] : '';
		$details      = lvjm_fetch_video_details_cached( $video_id, $params['partner_id'], isset( $params['locale'] ) ? (string) $params['locale'] : '' );
		$details_data = isset( $details['data'] ) && is_array( $details['data'] ) ? $details['data'] : $details;

		if ( empty( $params['video_infos']['thumb_url'] ) ) {
			$params['video_infos']['thumb_url'] = lvjm_get_detail_value(
				$details_data,
				array(
					'thumb_url',
					'thumbUrl',
					'thumbnailUrl',
					'thumb',
					'previewUrl',
					'preview',
				)
			);
		}

		if ( empty( $params['video_infos']['trailer_url'] ) ) {
			$params['video_infos']['trailer_url'] = lvjm_get_detail_value(
				$details_data,
				array(
					'trailer_url',
					'trailerUrl',
					'trailer',
					'previewVideoUrl',
					'videoTrailerUrl',
				)
			);
		}
	}

	// get custom post type.
	$custom_post_type = xbox_get_field_value( 'lvjm-options', 'custom-video-post-type' );

	// prepare post data.
	$post_args = array(
		'post_author'  => '1',
		'post_status'  => '' !== $params['status'] ? $params['status'] : xbox_get_field_value( 'lvjm-options', 'default-status' ),
		'post_type'    => '' !== $custom_post_type ? $custom_post_type : 'post',
		'post_title'   => isset( $params['video_infos']['title'] ) ? $params['video_infos']['title'] : 'Untitled',
		'post_content' => isset( $params['video_infos']['desc'] ) ? $params['video_infos']['desc'] : '',
	);

	// insert post.
	$post_id = wp_insert_post( $post_args, true );

	// add post metas & taxonomies.
	if ( is_wp_error( $post_id ) ) {
		$output = -1;
	} else {
		// add embed and actors.
		$more_data                       = lvjm_get_embed_and_actors( array( 'video_id' => $params['video_infos']['id'] ) );
		$params['video_infos']['embed']  = $more_data['embed'];
		$params['video_infos']['actors'] = empty( $params['video_infos']['actors'] ) ? $more_data['performer_name'] : $params['video_infos']['actors'];

		// add partner id.
		update_post_meta( $post_id, 'partner', (string) $params['partner_id'] );
		// add video id.
		update_post_meta( $post_id, 'video_id', (string) $params['video_infos']['id'] );
		// add main thumb.
		update_post_meta( $post_id, 'thumb', (string) $params['video_infos']['thumb_url'] );
		// add partner_cat.
		update_post_meta( $post_id, 'partner_cat', (string) $params['cat_s'] );
		// add feed.
		update_post_meta( $post_id, 'feed', (string) $params['feed_id'] );
		// add video length.
		$custom_duration = xbox_get_field_value( 'lvjm-options', 'custom-duration' );
		update_post_meta( $post_id, '' !== $custom_duration ? $custom_duration : 'duration', (string) $params['video_infos']['duration'] );
		// add embed player.
		$custom_embed_player = xbox_get_field_value( 'lvjm-options', 'custom-embed-player' );
		update_post_meta( $post_id, '' !== $custom_embed_player ? $custom_embed_player : 'embed', (string) $params['video_infos']['embed'] );
		// add video url.
		$custom_video_url = xbox_get_field_value( 'lvjm-options', 'custom-video-url' );
		update_post_meta( $post_id, '' !== $custom_video_url ? $custom_video_url : 'video_url', (string) $params['video_infos']['video_url'] );
		// add tracking url.
		$custom_tracking_url = xbox_get_field_value( 'lvjm-options', 'custom-tracking-url' );
		update_post_meta( $post_id, '' !== $custom_tracking_url ? $custom_tracking_url : 'tracking_url', (string) $params['video_infos']['tracking_url'] );
		// add quality.
		$custom_quality = xbox_get_field_value( 'lvjm-options', 'custom-quality' );
		update_post_meta( $post_id, '' !== $custom_quality ? $custom_quality : 'quality', (string) $params['video_infos']['quality'] );
		// add isHd.
		$custom_is_hd = xbox_get_field_value( 'lvjm-options', 'custom-isHd' );
		$is_hd_data   = (string) $params['video_infos']['isHd'];
		if ( '1' === $is_hd_data ) {
			$is_hd_data = 'yes';
		} else {
			$is_hd_data = 'no';
		}
		update_post_meta( $post_id, '' !== $custom_is_hd ? $custom_is_hd : 'isHd', $is_hd_data );
		// add uploader.
		$custom_uploader = xbox_get_field_value( 'lvjm-options', 'custom-uploader' );
		update_post_meta( $post_id, '' !== $custom_uploader ? $custom_uploader : 'uploader', (string) $params['video_infos']['uploader'] );
		// add category.
		$custom_taxonomy = xbox_get_field_value( 'lvjm-options', 'custom-video-categories' );
		wp_set_object_terms( $post_id, intval( $params['cat_wp'] ), '' !== $custom_taxonomy ? $custom_taxonomy : 'category', false );
		// add tags.
		$custom_tags = xbox_get_field_value( 'lvjm-options', 'custom-video-tags' );
		if ( '' === $custom_tags ) {
			$custom_tags = 'post_tag';
		}
		wp_set_object_terms( $post_id, explode( ',', str_replace( ';', ',', (string) $params['video_infos']['tags'] ) ), LVJM()->call_by_ref( $custom_tags ), false );
		// add actors.
		$custom_actors = xbox_get_field_value( 'lvjm-options', 'custom-video-actors' );
		if ( '' === $custom_actors ) { $custom_actors = 'models'; }
		if ( 'actors' === $custom_actors ) { $custom_actors = 'models'; }

		if ( '' === $custom_actors ) {
			$custom_actors = 'actors';
		}
		if ( ! empty( $params['video_infos']['actors'] ) ) {
			wp_set_object_terms( $post_id, explode( ',', str_replace( ';', ',', (string) $params['video_infos']['actors'] ) ), LVJM()->call_by_ref( $custom_actors ), false );
		}
		// add thumbs.
		foreach ( (array) $params['video_infos']['thumbs_urls'] as $thumb ) {
			if ( ! empty( $thumb ) ) {
				add_post_meta( $post_id, 'thumbs', $thumb, false );
			}
		}
		// add trailer.
		update_post_meta( $post_id, 'trailer_url', (string) $params['video_infos']['trailer_url'] );

		// downloading main thumb.
		if ( 'on' === xbox_get_field_value( 'lvjm-options', 'import-thumb' ) ) {

			$default_thumb = (string) $params['video_infos']['thumb_url'];

			if ( strpos( $default_thumb, 'http' ) === false ) {
				$default_thumb = 'http:' . $default_thumb;
			}

			// magic sideload image returns an HTML image, not an ID.
			$media = LVJM()->media_sideload_image( $default_thumb, $post_id, null, $params['partner_id'] );

			// therefore we must find it so we can set it as featured ID.
			if ( ! empty( $media ) && ! is_wp_error( $media ) ) {
				$args = array(
					'post_type'      => 'attachment',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'post_parent'    => $post_id,
				);

				// reference new image to set as featured.
				$attachments = get_posts( $args );
				if ( isset( $attachments ) && is_array( $attachments ) ) {
					foreach ( $attachments as $attachment ) {
						// grab partner_id of full size images (so no 300x150 nonsense in path).
						$default_thumb = wp_get_attachment_image_src( $attachment->ID, 'full' );
						// determine if in the $media image we created, the string of the URL exists.
						if ( strpos( $media, $default_thumb[0] ) !== false ) {
							// if so, we found our image. set it as thumbnail.
							set_post_thumbnail( $post_id, $attachment->ID );
							// only want one image.
							break;
						}
					}
				}
			}
		}

		// post format video.
		set_post_format( $post_id, 'video' );
		$output = $params['video_infos']['id'];
	}

	if ( ! $ajax_call ) {
		return $output;
	}

	wp_send_json( $output );

	wp_die();
}
add_action( 'wp_ajax_lvjm_import_video', 'lvjm_import_video' );
