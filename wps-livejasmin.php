<?php
/**
 * Plugin Name: WPS LiveJasmin
 * Plugin URI: https://www.wp-script.com/plugins/livejasmin/
 * Description: Import LiveJasmin livecam embed videos in your WordPress posts
 * Author: WP-Script
 * Author URI: https://www.wp-script.com
 * Version: 1.5.0
 * Text Domain: lvjm_lang
 * Domain Path: /languages
 * Requires PHP: 7.2
 *
 * @package lvjm\main
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

define( 'LVJM_VERSION', '1.5.0' );
define( 'LVJM_DIR', plugin_dir_path( __FILE__ ) );
define( 'LVJM_URL', plugin_dir_url( __FILE__ ) );
define( 'LVJM_FILE', __FILE__ );

require_once LVJM_DIR . 'tgmpa/class-tgm-plugin-activation.php';
require_once LVJM_DIR . 'tgmpa/config.php';
require_once 'vendor/autoload.php';

/**
 * Create the plugin instance in a function and call it.
 */
if ( ! function_exists( 'lvjm' ) ) {
	/**
	 * Run the plugin.
	 *
	 * @return LVJM The plugin instance or null if the plugin is not connected.
	 */
	function lvjm() {
		return LVJM::instance();
	}
}

add_action(
	'plugins_loaded',
	function () {
		if ( function_exists( 'WPSCORE' ) && 'connected' === WPSCORE()->get_product_status( 'LVJM' ) ) {
			lvjm();
		}
	}
);
