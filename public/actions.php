<?php
/**
 * Public Actions plugin file.
 *
 * @package LIVEJASMIN\Public\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

add_action( 'init', 'lvjm_load_public_filters' );

/**
 * Load public filters.
 */
function lvjm_load_public_filters() {
	add_filter( 'the_content', 'lvjm_insert_video' );
	add_filter( 'get_post_metadata', 'lvjm_set_embed_redirect_and_responsiveness', 12, 4 );
}

/**
 * Filter on 'the_content' to insert the embed player in the content.
 *
 * @since 1.0.0
 *
 * @param string $content The content before the filter.
 * @return string $content The content after the filter.
 */
function lvjm_insert_video( $content ) {

	global $post;

	if ( ! is_object( $post ) ) {
		return $content;
	}

	$post_source       = get_post_meta( $post->ID, 'partner', true );
	$current_theme     = wp_get_theme();
	$player_in_content = xbox_get_field_value( 'lvjm-options', 'player-in-content' );

	if ( is_single() && 'WP-Script' !== $current_theme->get( 'Author' ) && 'on' === $player_in_content ) {
		$lvjm_partners = LVJM()->get_partners();
		if ( isset( $lvjm_partners[ $post_source ] ) ) {
			$embed           = get_post_meta( $post->ID, lvjm_get_embed_key(), true );
			$player_position = xbox_get_field_value( 'lvjm-options', 'player-position' );
			if ( 'before' === $player_position ) {
				$content = $embed . $content;
			} else {
				$content = $content . $embed;
			}
		}
	}
	return $content;
}

/**
 * Add site redirection param in Livejasmin script tag.
 * - Must set &site=jasmin by default.
 * - Must set $wl3&cobrandId=XXXXXX if XXXXXX exists, with XXXXXX is a 6 digit ID retrieved from the site redirect option when the options are saved.
 * Also add responsiveness to the video player.
 *
 * @param mixed  $meta_value The meta value of the current post meta.
 * @param int    $object_id  ID of the object metadata is for.
 * @param string $meta_key   Metadata key.
 * @param bool   $single     Whether to return only the first value of the specified $meta_key.
 *
 * @return mixed The maybe modified meta value.
 */
function lvjm_set_embed_redirect_and_responsiveness( $meta_value, $object_id, $meta_key, $single ) {
	if ( lvjm_get_embed_key() !== $meta_key ) {
		return $meta_value;
	}

	$meta_cache = wp_cache_get( $object_id, 'post_meta' );
	if ( ! $meta_cache ) {
		$meta_cache = update_meta_cache( 'post', array( $object_id ) );
		$meta_cache = $meta_cache[ $object_id ];
	}
	if ( isset( $meta_cache[ $meta_key ] ) ) {
		if ( $single ) {
			$meta_value = maybe_unserialize( $meta_cache[ $meta_key ][0] );
		} else {
			$meta_value = array_map( 'maybe_unserialize', $meta_cache[ $meta_key ] );
		}
	}

	if ( false === $single ) {
		return $meta_value;
	}

	if ( strpos( (string) $meta_value, 'lvjm-player' ) === false ) {
		return $meta_value;
	}

	// Else, Build site redirect param.
	$redirect_param = array( 'site' => 'jasmin' );
	$saved_options  = WPSCORE()->get_product_option( 'LVJM', 'livejasmin_options' );

	if ( isset( $saved_options['whitelabel_id'] ) && '' !== (string) $saved_options['whitelabel_id'] ) {
		$redirect_param = array(
			'site'      => 'wl3',
			'cobrandId' => (string) $saved_options['whitelabel_id'],
		);
	}

	// Add site redirect param in script src param.
	$meta_value_html_obj = str_get_html( $meta_value );
	if ( false === $meta_value_html_obj ) {
		return $meta_value;
	}
	$script_tags = $meta_value_html_obj->find( 'script' );
	if ( 0 === count( $script_tags ) ) {
		try {
			$video_id  = $meta_cache['video_id'][0];
			$more_data = lvjm_get_embed_and_actors( array( 'video_id' => $video_id ) );

			// Update embed player.
			update_post_meta( $object_id, lvjm_get_embed_key(), $more_data['embed'] );

			$custom_actors = xbox_get_field_value( 'lvjm-options', 'custom-video-actors' );
			if ( '' === $custom_actors ) {
				$custom_actors = 'actors';
			}
			if ( ! empty( $more_data['performer_name'] ) ) {
				wp_add_object_terms( $object_id, $more_data['performer_name'], $custom_actors );
			}
			return __( 'This video is not available in your country', 'lvjm_lang' );
		} catch ( \Exception $exception ) {
			unset( $exception );
			return __( 'This video is not available in your country', 'lvjm_lang' );
		}
	}
	$script_tag       = $script_tags[0];
	$script_tag->src .= '&' . http_build_query( $redirect_param );

	// add lvjm-player class to the video player id.
	$div_tags = $meta_value_html_obj->find( 'div.player' );
	if ( 0 !== count( $div_tags ) ) {
		$div_tag        = $div_tags[0];
		$div_tag->style = 'width: 100% !important;height: auto !important;aspect-ratio: 16/9;';
	}
	return $meta_value_html_obj->__toString();
}

/**
 * Get embed key from options.
 * Check if xbox_get_field_value() exists because it can be called before WPSCORE is loaded.
 *
 * @return string The embed key to use in get_post_meta to find embed value.
 */
function lvjm_get_embed_key() {
	if ( ! function_exists( 'xbox_get_field_value' ) ) {
		return '';
	}
	return xbox_get_field_value( 'lvjm-options', 'custom-embed-player', 'embed' );
}
