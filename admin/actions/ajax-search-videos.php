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
	}

	$search_videos = new LVJM_Search_Videos( $params );
	$errors        = array();

	if ( $search_videos->has_errors() ) {
		$errors = $search_videos->get_errors();
	}

	$videos = $search_videos->get_videos();
	// --- Performer filtering (addon) ---
	if ( isset( $params['performer'] ) ) {
		$performer = sanitize_text_field( (string) $params['performer'] );
		if ( '' !== $performer ) {
			$filtered = array();
			// Ensure helper exists to fetch performer name when not provided by feed
			if ( ! function_exists( 'lvjm_get_embed_and_actors' ) ) {
				$actions_file = dirname( __FILE__ ) . '/ajax-get-embed-and-actors.php';
				if ( file_exists( $actions_file ) ) {
					require_once $actions_file;
				}
			}
			foreach ( (array) $videos as $v ) {
				$match = false;
				$actors = '';
				if ( isset( $v['actors'] ) ) {
					$actors = (string) $v['actors'];
				}
				if ( '' !== $actors && false !== stripos( $actors, $performer ) ) {
					$match = true;
				} else {
					// Fallback: ask API for performer of this video ID
					if ( function_exists( 'lvjm_get_embed_and_actors' ) && isset( $v['id'] ) ) {
						try {
							$more = lvjm_get_embed_and_actors( array( 'video_id' => $v['id'] ) );
							if ( ! empty( $more['performer_name'] ) && false !== stripos( $more['performer_name'], $performer ) ) {
								$match = true;
								// also enrich actors for UI
								$v['actors'] = $more['performer_name'];
							}
						} catch ( \Throwable $e ) {
							// ignore
						}
					}
				}
				if ( $match ) {
					$filtered[] = $v;
				}
			}
			$videos = $filtered;
		}
	}
	// --- /Performer filtering ---

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
