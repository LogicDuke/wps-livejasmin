<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Get embed player and actors ids in Ajax or PHP call.
 *
 * @throws Exception If AwEmpire Api is not working.
 *
 * @param mixed $params       Array of parameters if this function is called in PHP.
 *
 * @return void|array $output New post ID if success, -1 if not. Returned only if this function is called in PHP.
 */
function lvjm_get_embed_and_actors( $params = '' ) {
	$ajax_call = '' === $params;

	if ( $ajax_call ) {
		check_ajax_referer( 'ajax-nonce', 'nonce' );
		$params = $_POST;
	}

	if ( ! isset( $params['video_id'] ) ) {
		wp_die( 'Some parameters are missing!' );
	}

	$output = array(
		'performer_name' => '',
		'embed'          => '',
	);

	$saved_partner_options = WPSCORE()->get_product_option( 'LVJM', 'livejasmin_options' );
	$psid                  = $saved_partner_options['psid'];
	$access_key            = $saved_partner_options['accesskey'];
	$primary_color         = str_replace( '#', '', xbox_get_field_value( 'lvjm-options', 'primary-color' ) );
	$label_color           = str_replace( '#', '', xbox_get_field_value( 'lvjm-options', 'label-color' ) );
	$client_ip             = '90.90.90.90';
	$api_url               = 'https://pt.ptawe.com/api/video-promotion/v1/details/' . $params['video_id'] . '?clientIp=' . $client_ip . '&primaryColor=' . $primary_color . '&labelColor=' . $label_color . '&psid=' . $psid . '&accessKey=' . $access_key;
	$args                  = array(
		'timeout' => 300,
	);

	$response = wp_remote_get( $api_url, $args );

	if ( is_wp_error( $response ) ) {
		return false;
	}
	$container_id    = 'lvjm-player-' . $params['video_id'];
	$response_body   = json_decode( wp_remote_retrieve_body( $response ), true );
	$embed_container = '<div class="player" data-awe-container-id="' . $container_id . '" style="width:640px;height:480px"></div>';

	if ( ! isset( $response_body['data'], $response_body['data']['playerEmbedScript'] ) ) {
		if ( $ajax_call ) {
			wp_send_json( $output );
			wp_die();
		}
		throw new Exception( 'AwEmpire Api not working' );
	}

	$embed_script = str_replace( '{CONTAINER}', $container_id, $response_body['data']['playerEmbedScript'] );
	$output       = array(
		'performer_name' => lvjm_get_performer_name_by_id( $response_body['data']['performerId'] ),
		'embed'          => $embed_container . $embed_script,
	);
	if ( ! $ajax_call ) {
		return $output;
	}
	wp_send_json( $output );
	wp_die();
}
add_action( 'wp_ajax_lvjm_get_embed_and_actors', 'lvjm_get_embed_and_actors' );

/**
 * Format a Livejasmin performer id to retrieve their name.
 * E.g: LucyMuller => Lucy Muller.
 *
 * @param string $performer_id The performer id (e.g: LucyMuller).
 * @return string The performer name (e.g: Lucy Muller).
 */
function lvjm_get_performer_name_by_id( $performer_id ) {
	return ucfirst( trim( preg_replace( '/(?<!\ )[A-Z]/', ' $0', $performer_id ) ) );
}
