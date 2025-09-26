<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Hooks
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Callback for before_delete_post action.
 * Delete attachements and Update feed when deleting a post.
 *
 * @param int|string $post_id The post id going to be deleted.
 *
 * @return bool false if feed id from $post_id param does not exist, true in all other cases.
 */
function lvjm_delete_post( $post_id ) {
	$feed_id = get_post_meta( $post_id, 'feed', true );

	if ( '' === $feed_id ) {
		return false;
	}

	// delete thumb.
	global $_wp_additional_image_sizes;
	$upload_dir  = wp_upload_dir();
	$attachments = get_children(
		array(
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'post_parent'    => $post_id,
		)
	);
	if ( $attachments ) {
		foreach ( (array) $attachments as $attachment ) {
			wp_delete_attachment( $attachment->ID, true );
		}
	}
	// decrement video counter in feed.
	$saved_feeds      = WPSCORE()->get_product_option( 'LVJM', 'feeds' );
	$saved_feed       = LVJM()->get_feed( $feed_id );
	$new_total_videos = 0;

	if ( isset( $saved_feed['total_videos'] ) && is_int( $saved_feed['total_videos'] ) ) {
		LVJM()->update_feed( $feed_id, array( 'total_videos' => intval( $saved_feed['total_videos'] ) - 1 ) );
	}
	return true;
}
add_action( 'before_delete_post', 'lvjm_delete_post' );

/**
 * Callback for xbox_before_save_field action / lvjm-enable-auto-import option.
 * Clear Scheduled hook and set it up if auto-import is set to "on".
 *
 * @param int|string $new_value "on" or "off to enable or not the auto-import.
 *
 * @return void
 */
function lvjm_enable_auto_import( $new_value ) {

	wp_clear_scheduled_hook( 'lvjm_update_one_feed' );

	if ( 'on' === $new_value ) {
		$default_frequency_value = xbox_get_field_value( 'lvjm-options', 'lvjm-auto-import-frequency' );
		wp_schedule_event( time(), $default_frequency_value, 'lvjm_update_one_feed' );
	}
}
add_action( 'xbox_before_save_field_lvjm-enable-auto-import', 'lvjm_enable_auto_import' );

/**
 * Callback for xbox_before_save_field action / lvjm-enable-auto-import option.
 * Clear Scheduled hook and set it up if auto-import is set to "on".
 *
 * @param int|string $frequency_value The frequency to use to redefine the schedule event frequency in the auto-import.
 *
 * @return void
 */
function lvjm_upate_auto_import_frequency( $frequency_value ) {
	if ( 'on' === xbox_get_field_value( 'lvjm-options', 'lvjm-enable-auto-import' ) ) {
		wp_clear_scheduled_hook( 'lvjm_update_one_feed' );
		wp_schedule_event( time(), $frequency_value, 'lvjm_update_one_feed' );
	}
}
add_action( 'xbox_before_save_field_lvjm-auto-import-frequency', 'lvjm_upate_auto_import_frequency' );
