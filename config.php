<?php

error_log('[WPS-LiveJasmin] Config loaded');
/**
 * Config plugin file.
 *
 * @package LIVEJASMIN\Main
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Define Constants
 */
define( 'LVJM_VERSION', '1.3.2' );
define( 'LVJM_DIR', plugin_dir_path( __FILE__ ) );
define( 'LVJM_URL', plugin_dir_url( __FILE__ ) );
define( 'LVJM_FILE', __FILE__ );

/**
 * Navigation config
 */
self::$config['nav'] = array(
	'35'           => array(
		'slug'     => 'lvjm-import-videos',
		'callback' => 'lvjm_import_videos_page',
		'title'    => 'LiveJasmin',
		'icon'     => 'fa-play-circle',
	),
	'lvjm-options' => array(
		'slug' => 'lvjm-options',
	),
);

/**
 * JS config
 */
self::$config['scripts']['js'] = array(
	// vendors.
	'LVJM_bootstrap-select.js' => array(
		'in_pages'  => array( 'lvjm-import-videos' ),
		'path'      => 'admin/vendors/bootstrap-select/bootstrap-select.min.js',
		'require'   => array(),
		'version'   => '1.12.4',
		'in_footer' => false,
	),
	'LVJM_vue-paginate.js'     => array(
		'in_pages'  => array( 'lvjm-import-videos' ),
		'path'      => 'admin/vendors/vue-paginate/vue-paginate.min.js',
		'require'   => array(),
		'version'   => '3.6.0',
		'in_footer' => false,
	),
	// pages.
	'LVJM_import-videos.js'    => array(
		'in_pages'  => array( 'lvjm-import-videos' ),
		'path'      => 'admin/pages/page-import-videos.js',
		'require'   => array(),
		'version'   => LVJM_VERSION,
		'in_footer' => false,
		'localize'  => array(
			'ajax'       => true,
			'objectL10n' => array(),
		),
	),
);

/**
 *  CSS config.
 */
self::$config['scripts']['css'] = array(
	// vendors.
	'LVJM_bootstrap-select.css' => array(
		'in_pages' => array( 'lvjm-import-videos' ),
		'path'     => 'admin/vendors/bootstrap-select/bootstrap-select.min.css',
		'require'  => array(),
		'version'  => '1.12.4',
		'media'    => 'all',
	),
	// assets.
	'LVJM_admin.css'            => array(
		'in_pages' => array( 'lvjm-import-videos' ),
		'path'     => 'admin/assets/css/admin.css',
		'require'  => array(),
		'version'  => LVJM_VERSION,
		'media'    => 'all',
	),
);
