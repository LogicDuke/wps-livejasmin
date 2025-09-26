<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Save partner options.
 *
 * @return void
 */
function lvjm_save_partner_options() {
	check_ajax_referer( 'ajax-nonce', 'nonce' );

	if ( ! isset( $_POST['partner_id'], $_POST['partner_options'] ) ) {
		wp_die( 'Some parameters are missing!' );
	}

	$partner_id      = sanitize_text_field( wp_unslash( $_POST['partner_id'] ) );
	$partner_options = $_POST['partner_options'];
	$is_configured   = true;

	foreach ( $partner_options as $option ) {
		if ( ! isset( $option['id'] ) ) {
			continue;
		}

		$option['value'] = trim( $option['value'] );

		$options_to_save[ $option['id'] ] = $option['value'];

		if ( 'site' === $option['id'] ) {
			$whitelabel_id                    = LVJM()->get_whitelabel_id_from_url( $option['value'] );
			$options_to_save['whitelabel_id'] = $whitelabel_id;

			if ( false === $whitelabel_id ) {
				$options_to_save['whitelabel_id'] = '';
				$options_to_save['site']          = '';
			}
		}

		if ( 'true' === $option['required'] && '' === $option['value'] ) {
			$is_configured = false;
		}
	}

	WPSCORE()->update_product_option( 'LVJM', $partner_id . '_options', $options_to_save );

	wp_send_json(
		array(
			'is_configured' => $is_configured,
			'site'          => $options_to_save['site'],
			'whitelabel_id' => $options_to_save['whitelabel_id'],
		)
	);

	wp_die();
}
add_action( 'wp_ajax_lvjm_save_partner_options', 'lvjm_save_partner_options' );
