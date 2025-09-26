<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Load partner cats in Ajax.
 *
 * @since 1.0.0
 *
 * @return void
 */
function lvjm_load_partner_cats() {
	check_ajax_referer( 'ajax-nonce', 'nonce' );
	if ( ! isset( $_POST['partner_id'] ) ) {
		wp_die( 'Partner ID parameter is missing!' );
	}

	if ( ! isset( $_POST['method'] ) ) {
		wp_die( 'Method parameter is missing!' );
	}

	$partner_id         = sanitize_text_field( wp_unslash( $_POST['partner_id'] ) );
	$partner_categories = LVJM()->get_ordered_categories();
	$cats_used          = array();

	$feeds = LVJM()->get_feeds();

	foreach ( (array) $feeds as $feed ) {
		if ( $partner_id === $feed['partner_id'] ) {
			array_push( $cats_used, $feed['partner_cat'] );
		}
	}
	unset( $feeds );

	$output = array();
	$i      = 0;

	foreach ( (array) $partner_categories as $partner_category ) {
		$output[ $i ] = $partner_category;
		if ( 'optgroup' === $partner_category['id'] ) {
			foreach ( $partner_category['sub_cats'] as $index => $partner_sub_cat ) {
				$output[ $i ]['sub_cats'][ $index ]['disabled'] = in_array( $partner_sub_cat['id'], $cats_used, true );
			}
		} else {
			$output[ $i ]['disabled'] = in_array( $partner_category['id'], $cats_used, true );
		}
		++$i;
	}
	// Inject custom category
if (is_array($output) && isset($output[0]['id'])) {
    array_unshift($output, array(
        'id' => 'all_straight',
        'name' => 'All Straight Categories'
    ));
}
wp_send_json( $output );
	wp_die();
}
add_action( 'wp_ajax_lvjm_load_partner_cats', 'lvjm_load_partner_cats' );
