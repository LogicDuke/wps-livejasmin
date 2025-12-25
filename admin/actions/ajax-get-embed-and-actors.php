<?php
/**
 * Admin Action plugin file.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

if ( ! function_exists( 'lvjm_mask_secret' ) ) {
	/**
	 * Mask sensitive values for logging.
	 *
	 * @param string $value The secret value.
	 * @return string
	 */
	function lvjm_mask_secret( $value ) {
		$value  = (string) $value;
		$length = strlen( $value );
		if ( 0 === $length ) {
			return '';
		}
		if ( $length <= 8 ) {
			return str_repeat( '*', $length );
		}
		return substr( $value, 0, 4 ) . str_repeat( '*', $length - 8 ) . substr( $value, -4 );
	}
}

/**
 * Get embed player and actors ids in Ajax or PHP call.
 *
 * @param mixed $params       Array of parameters if this function is called in PHP.
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
		'error_code'     => '',
		'error_message'  => '',
	);

	$saved_partner_options = WPSCORE()->get_product_option( 'LVJM', 'livejasmin_options' );
	$psid                  = $saved_partner_options['psid'];
	$access_key            = $saved_partner_options['accesskey'];
	$primary_color         = str_replace( '#', '', xbox_get_field_value( 'lvjm-options', 'primary-color' ) );
	$label_color           = str_replace( '#', '', xbox_get_field_value( 'lvjm-options', 'label-color' ) );
	$client_ip             = '90.90.90.90';
	$api_url               = 'https://pt.ptawe.com/api/video-promotion/v1/details/' . $params['video_id'] . '?clientIp=' . $client_ip . '&primaryColor=' . $primary_color . '&labelColor=' . $label_color . '&psid=' . $psid . '&accessKey=' . $access_key;
	$masked_access_key     = lvjm_mask_secret( $access_key );
	$log_api_url           = $access_key ? str_replace( $access_key, $masked_access_key, $api_url ) : $api_url;
	$args                  = array(
		'timeout'   => 30,
		'sslverify' => false,
		'headers'   => array(
			'User-Agent' => 'Mozilla/5.0 (WordPress; LVJM Importer)',
			'Accept'     => 'application/json,text/plain,*/*',
		),
	);

	if ( function_exists( 'WPSCORE' ) ) {
		WPSCORE()->write_log(
			'info',
			'[TMW-FIX] LVJM preview request for video_id ' . $params['video_id'] . ' (URL: ' . $log_api_url . ').',
			__FILE__,
			__LINE__
		);
	}

	$response = wp_remote_get( $api_url, $args );

	if ( is_wp_error( $response ) ) {
		$output['error_code']    = 'wp_error';
		$output['error_message'] = $response->get_error_message();
		if ( function_exists( 'WPSCORE' ) ) {
			WPSCORE()->write_log(
				'warning',
				'[TMW-FIX] VPAPI request failed for video_id ' . $params['video_id'] . '. Error: ' . $response->get_error_message() . '. URL: ' . $log_api_url,
				__FILE__,
				__LINE__
			);
		}
		if ( $ajax_call ) {
			wp_send_json( $output );
			wp_die();
		}
		return $output;
	}
	$container_id    = 'lvjm-player-' . $params['video_id'];
	$status_code     = wp_remote_retrieve_response_code( $response );
	$response_raw    = wp_remote_retrieve_body( $response );
	$response_body   = json_decode( $response_raw, true );
    // Use a responsive container for the player instead of fixed width/height.
    // Setting aspect-ratio to 16/9 and 100% width makes the embed responsive while
    // preserving the maximum width of 640px for backward compatibility.
    $embed_container = '<div class="player" data-awe-container-id="' . $container_id . '" style="aspect-ratio:16/9;width:100%;"></div>';

	if ( ! is_array( $response_body ) ) {
		$output['error_code']    = 'invalid_json';
		$output['error_message'] = 'VPAPI returned non-JSON content (HTTP ' . $status_code . ').';
		if ( function_exists( 'WPSCORE' ) ) {
			WPSCORE()->write_log(
				'warning',
				'[TMW-FIX] VPAPI response not JSON for video_id ' . $params['video_id'] . ' (HTTP ' . $status_code . '). URL: ' . $log_api_url . '. Body: ' . substr( $response_raw, 0, 500 ),
				__FILE__,
				__LINE__
			);
		}
		if ( $ajax_call ) {
			wp_send_json( $output );
			wp_die();
		}
		return $output;
	}

	if ( ! isset( $response_body['data'], $response_body['data']['playerEmbedScript'] ) ) {
		$output['error_code']    = 'missing_embed';
		$output['error_message'] = 'VPAPI response missing player embed (HTTP ' . $status_code . ').';
		if ( function_exists( 'WPSCORE' ) ) {
			WPSCORE()->write_log(
				'warning',
				'[TMW-FIX] Missing playerEmbedScript for video_id ' . $params['video_id'] . ' (HTTP ' . $status_code . '). URL: ' . $log_api_url . '. Body: ' . substr( $response_raw, 0, 500 ),
				__FILE__,
				__LINE__
			);
		}
		if ( $ajax_call ) {
			wp_send_json( $output );
			wp_die();
		}
		return $output;
	}

	$embed_script = str_replace( '{CONTAINER}', $container_id, $response_body['data']['playerEmbedScript'] );
    // Append whitelabel redirect parameters directly into the embed script URL.  Without this
    // the player defaults to `siteId=jsm` which sends users to the main LiveJasmin domain.
    $whitelabel_id = ! empty( $saved_partner_options['whitelabel_id'] ) ? $saved_partner_options['whitelabel_id'] : '261146';
    $redirect_query = http_build_query( array( 'siteId' => 'wl3', 'cobrandId' => (string) $whitelabel_id ) );
    // Replace the src attribute by appending the query string.  We need to account for
    // existing query parameters so we choose '&' or '?' as the delimiter accordingly.
    $embed_script = preg_replace_callback( '/<script\s+[^>]*src="([^"]+)"/', function ( $matches ) use ( $redirect_query ) {
        $src       = $matches[1];
        $delimiter = ( false === strpos( $src, '?' ) ) ? '?' : '&';
        return '<script src="' . $src . $delimiter . $redirect_query . '"';
    }, $embed_script );
	$output       = array(
		'performer_name' => lvjm_get_performer_name_by_id( $response_body['data']['performerId'] ),
		'embed'          => $embed_container . $embed_script,
		'error_code'     => '',
		'error_message'  => '',
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
