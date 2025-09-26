<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Change feed status in Ajax.
 *
 * @since 1.0.0
 *
 * @return void
 */
function lvjm_load_import_videos_data() {
	check_ajax_referer( 'ajax-nonce', 'nonce' );

	$feeds_array = array();
	$feeds       = LVJM()->get_feeds();

	if ( false !== $feeds ) {
		foreach ( $feeds as $feed ) {
			$feeds_array[] = $feed;
		}
	}
	$data = array(
		'feeds'             => $feeds_array,
		'objectL10n'        => LVJM()->get_object_l10n(),
		'partners'          => LVJM()->get_partners(),
		'videosLimit'       => xbox_get_field_value( 'lvjm-options', 'search-results' ),
		'WPCats'            => LVJM()->get_wp_cats(),
		'autoImportEnabled' => xbox_get_field_value( 'lvjm-options', 'lvjm-enable-auto-import' ),
	);
	wp_send_json( $data );
	wp_die();
}
add_action( 'wp_ajax_lvjm_load_import_videos_data', 'lvjm_load_import_videos_data' );
