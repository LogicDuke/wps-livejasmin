<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

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

	$params['feed_id'] = lvjm_resolve_feed_id_by_term( $params['cat_wp'], $params['partner_id'], $params['feed_id'] );

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
		$performer_terms = array();
		if ( ! empty( $params['video_infos']['actors'] ) ) {
			$performer_candidates = preg_split( '/[;,]/', (string) $params['video_infos']['actors'] );
			$performer_terms      = array_values(
				array_unique(
					array_filter(
						array_map( 'trim', (array) $performer_candidates ),
						static function ( $value ) {
							return '' !== $value;
						}
					)
				)
			);
		}
		if ( empty( $performer_terms ) && ! empty( $more_data['performer_name'] ) ) {
			$performer_terms = array( (string) $more_data['performer_name'] );
		}
		$custom_actors = xbox_get_field_value( 'lvjm-options', 'custom-video-actors' );
		if ( '' === $custom_actors ) {
			$custom_actors = 'actors';
		}
		$performer_terms = lvjm_resolve_performer_terms(
			$performer_terms,
			array_filter(
				array(
					$custom_actors,
					taxonomy_exists( 'models' ) ? 'models' : '',
				)
			)
		);
		$performer_name = '';
		if ( ! empty( $performer_terms ) ) {
			$performer_name = (string) $performer_terms[0];
		}

		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
			error_log(
				sprintf(
					'[LVJM-AUDIT][MODEL] Import performer detected: "%s"',
					$performer_name
				)
			);
		}

		$raw_video_id        = isset( $params['video_infos']['id'] ) ? (string) $params['video_infos']['id'] : '';
		$resolved_video_id   = lvjm_resolve_vpapi_video_id( $params['video_infos'] );
		$video_id_candidates = lvjm_vpapi_video_id_candidates( $params['video_infos'] );
		$api_thumb           = isset( $params['video_infos']['thumb_url'] ) ? (string) $params['video_infos']['thumb_url'] : '';
		$chosen_thumb_data   = lvjm_vpapi_main_thumb_for_video_infos( $params['video_infos'] );
		$csv_url             = isset( $chosen_thumb_data['url'] ) ? (string) $chosen_thumb_data['url'] : '';
		$thumb_source        = '' !== $csv_url && isset( $chosen_thumb_data['source'] ) ? (string) $chosen_thumb_data['source'] : 'api';
		$thumb_match_method  = isset( $chosen_thumb_data['match_method'] ) ? (string) $chosen_thumb_data['match_method'] : 'none';
		if ( '' !== $csv_url ) {
			$params['video_infos']['thumb_url'] = $csv_url;
		}
		$video_id = $raw_video_id;

		// add partner id.
		update_post_meta( $post_id, 'partner', (string) $params['partner_id'] );
		// add video id.
		update_post_meta( $post_id, 'video_id', $video_id );
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
		$normalized_tags = lvjm_normalize_tags_array(
			isset( $params['video_infos']['tags'] ) ? $params['video_infos']['tags'] : '',
			array(
				'mode'   => 'import',
				'source' => 'video_infos',
			)
		);
		wp_set_post_terms( $post_id, $normalized_tags, LVJM()->call_by_ref( $custom_tags ), false );
		// add actors.
		// Audit note: Search-by-Model imports currently only attach performers via the actors taxonomy.
		// There is no model CPT relationship meta written here to power /model/ links.
		if ( ! empty( $performer_terms ) ) {
			wp_set_object_terms( $post_id, $performer_terms, LVJM()->call_by_ref( $custom_actors ), false );
			if ( taxonomy_exists( 'models' ) ) {
				wp_set_object_terms( $post_id, $performer_terms, 'models', false );
			}
		}
		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
			$model_post = null;
			if ( '' !== $performer_name ) {
				$model_post = get_page_by_title( $performer_name, OBJECT, 'model' );
			}
			error_log(
				sprintf(
					'[LVJM-AUDIT][MODEL] Model lookup by title "%s" => %s',
					$performer_name,
					$model_post ? (string) $model_post->ID : 'not found'
				)
			);
			error_log(
				sprintf(
					'[LVJM-AUDIT][LINK] Performer linkage uses taxonomy "%s" via wp_set_object_terms; no model CPT permalink resolution here.',
					$custom_actors
				)
			);
			error_log(
				'[LVJM-AUDIT][BIO] No model CPT auto-create routine exists in lvjm_import_video; only actor taxonomy terms are assigned.'
			);
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

			$desired_thumb = (string) $params['video_infos']['thumb_url'];

			if ( '' !== $desired_thumb && strpos( $desired_thumb, 'http' ) === false ) {
				$desired_thumb = 'http:' . $desired_thumb;
			}

			$current_thumb_id      = get_post_thumbnail_id( $post_id );
			$stored_thumb_url      = (string) get_post_meta( $post_id, 'lvjm_thumb_url', true );
			$current_thumb_url     = $current_thumb_id ? wp_get_attachment_url( $current_thumb_id ) : '';
			$current_imported_flag = $current_thumb_id ? (int) get_post_meta( $current_thumb_id, 'lvjm_imported_thumb', true ) : 0;
			$thumb_file_path       = $current_thumb_id ? get_attached_file( $current_thumb_id ) : '';
			$thumb_missing         = $current_thumb_id && ( '' === $thumb_file_path || ! file_exists( $thumb_file_path ) );
			$thumb_matches         = ( '' !== $current_thumb_url && $current_thumb_url === $desired_thumb )
				|| ( '' !== $stored_thumb_url && $stored_thumb_url === $desired_thumb );
			$should_refresh        = '' !== $desired_thumb
				&& ( ! $current_thumb_id
					|| $thumb_missing
					|| '' === $current_thumb_url
					|| $stored_thumb_url !== $desired_thumb
					|| ( ! $thumb_matches && $current_imported_flag ) );
			$attachment_id         = $current_thumb_id;
			$previous_thumb_id     = $current_thumb_id;
			$refreshed             = 'no';

			if ( $should_refresh ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';

				$attachment_id = media_sideload_image( $desired_thumb, $post_id, null, 'id' );
				if ( is_wp_error( $attachment_id ) ) {
					// Fallback to legacy HTML return if needed.
					$media = LVJM()->media_sideload_image( $desired_thumb, $post_id, null, $params['partner_id'] );
					if ( ! empty( $media ) && ! is_wp_error( $media ) ) {
						$attachment_id = lvjm_resolve_attachment_id_from_media( $media, $post_id );
					} else {
						$attachment_id = 0;
					}
				}

				if ( $attachment_id ) {
					set_post_thumbnail( $post_id, $attachment_id );
					update_post_meta( $post_id, 'lvjm_thumb_url', $desired_thumb );
					update_post_meta( $post_id, 'lvjm_thumb_attachment_id', (int) $attachment_id );
					update_post_meta( $attachment_id, 'lvjm_imported_thumb', 1 );
					if ( $previous_thumb_id && $previous_thumb_id !== $attachment_id ) {
						$previous_imported = (int) get_post_meta( $previous_thumb_id, 'lvjm_imported_thumb', true );
						if ( $previous_imported ) {
							wp_delete_attachment( $previous_thumb_id, true );
						}
					}
					$refreshed = 'yes';
				}
			}

			if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
				$featured_after_id  = get_post_thumbnail_id( $post_id );
				$featured_after_url = $featured_after_id ? wp_get_attachment_url( $featured_after_id ) : '';
				$meta_thumb         = (string) get_post_meta( $post_id, 'thumb', true );
				error_log(
					sprintf(
						'[TMW-THUMB] post=%d raw_id=%s resolved_id=%s candidates=%s csv_hit=%s csv_url=%s match=%s desired=%s api=%s featured_before=%s refreshed=%s featured_after=%s meta_thumb=%s',
						$post_id,
						$raw_video_id,
						$resolved_video_id,
						implode( ',', $video_id_candidates ),
						'' !== $csv_url ? 'yes' : 'no',
						$csv_url,
						$thumb_match_method,
						$desired_thumb,
						$api_thumb,
						$current_thumb_id ? $current_thumb_id . ':' . $current_thumb_url : 'none',
						$refreshed,
						$featured_after_id ? $featured_after_id . ':' . $featured_after_url : 'none',
						$meta_thumb
					)
				);
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
