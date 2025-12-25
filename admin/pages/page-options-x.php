<?php
/**
 * Admin POptions Page plugin file.
 *
 * @package LIVEJASMIN\Admin\Pages
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

add_filter( 'lvjm-options', 'lvjm_options_page' );

/**
 * Filter on lvjm-options to prepend the WP-Script header on the options page.
 *
 * @since 1.0.0
 *
 * @param string $options_table  HTML container for all the plugin options in the options page.
 * @return string $content       HTML container with WP-Script header followed by all the plugin options.
 */
function lvjm_options_page( $options_table ) {
	$output  = '<div id="wp-script"><div class="content-tabs">';
	$output .= WPSCORE()->display_logo( false );
	$output .= WPSCORE()->display_tabs( false );
	$output .= '
		<div class="tab-content tab-options">
			<div class="tab-pane fade in active" id="LVJM-options-tab">
				<div v-cloak>
					<ul class="list-inline">
						<li><a href="admin.php?page=lvjm-import-videos"><i class="fa fa-cloud-download"></i> ' . esc_html__( 'Import videos', 'lvjm_lang' ) . '</a></li>
						<li>|</li>
						<li class="active"><a href="admin.php?page=lvjm-options"><i class="fa fa-wrench"></i> ' . esc_html__( 'Options', 'lvjm_lang' ) . '</a></li>
					</ul>
				</div>
			</div>
		';
	$output .= $options_table;
	$output .= '</div>';
	$output .= WPSCORE()->display_footer( false );
	$output .= '</div></div>';
	return $output;
}

add_action( 'xbox_init', 'lvjm_options' );

/**
 * Action on xbox_init to create all the plugin options.
 *
 * @since 1.0.0
 *
 * @return void
 */
function lvjm_options() {
	$all_post_types = get_post_types();
	// exclude all built_in post types except Post.
	unset( $all_post_types['attachment'] );
	unset( $all_post_types['custom_css'] );
	unset( $all_post_types['customize_changeset'] );
	unset( $all_post_types['nav_menu_item'] );
	unset( $all_post_types['oembed_cache'] );
	unset( $all_post_types['page'] );
	unset( $all_post_types['revision'] );
	unset( $all_post_types['user_request'] );
	asort( $all_post_types, SORT_STRING );

	$all_taxonomies = get_taxonomies();
	// exclude all built_in post types except category and post_tag .
	unset( $all_taxonomies['nav_menu'] );
	unset( $all_taxonomies['link_category'] );
	unset( $all_taxonomies['post_format'] );
	if ( ! isset( $all_taxonomies['models'] ) ) {
		$all_taxonomies['models'] = 'models';
	}
	asort( $all_taxonomies, SORT_STRING );

	$options = array(
		'id'         => 'LVJM-options',
		'icon'       => XBOX_URL . 'img/xbox-light-small.png',
		'skin'       => 'pink',
		'layout'     => 'boxed',
		'header'     => array(
			'icon' => '<img src="' . XBOX_URL . 'img/xbox-light.png"/>',
			'desc' => 'Customize here your Theme',
		),
		'capability' => 'edit_published_posts',
	);
	$xbox    = xbox_new_admin_page( $options );

	$xbox->add_main_tab(
		array(
			'name'  => 'Main tab',
			'id'    => 'main-tab',
			'items' => array(
				'general'             => '<i class="xbox-icon xbox-icon-gear"></i>General',
				'auto-pilot'          => '<i class="xbox-icon xbox-icon-plane"></i>Auto-Pilot',
				'data-to-import'      => '<i class="xbox-icon xbox-icon-download"></i>Data to import',
				'theme-compatibility' => '<i class="xbox-icon xbox-icon-desktop"></i>Theme Compatibility',
			),
		)
	);

	/**
	 * General.
	 */
	$xbox->open_tab_item( 'general' );
		$xbox->add_field(
			array(
				'id'         => 'search-results',
				'name'       => 'Search results',
				'type'       => 'number',
				'default'    => 60,
				'grid'       => '2-of-6',
				'options'    => array(
					'unit' => 'videos / results',
				),
				'attributes' => array(
					'min'  => 6,
					'max'  => 120,
					'step' => 1,
				),
				'desc'       => 'Choose the number of videos displayed for each search (6 - 120).',
			)
		);

		$xbox->add_field(
			array(
				'id'      => 'default-status',
				'name'    => 'Default Status',
				'type'    => 'radio',
				'default' => 'publish',
				'items'   => array(
					'draft'   => 'Draft',
					'publish' => 'Publish',
				),
				'desc'    => 'Choose the default status of the imported videos (This option can be changed individually for each saved feed).',
			)
		);

		$xbox->add_field(
			array(
				'id'      => 'primary-color',
				'name'    => esc_html__( 'Player primary color', 'wpst' ),
				'type'    => 'colorpicker',
				'default' => '#BE0000',
				'desc'    => 'Set the color of player progress bar, volume and ad buttons. Will be applied only for new imports.',
				'grid'    => '2-of-8',
			)
		);

		$xbox->add_field(
			array(
				'id'      => 'label-color',
				'name'    => esc_html__( 'Player label color', 'wpst' ),
				'type'    => 'colorpicker',
				'default' => '#FFFFFF',
				'desc'    => 'Set the color of text inside the player. Will be applied only for new imports.',
				'grid'    => '2-of-8',
			)
		);
	$xbox->close_tab_item( 'general' );

	/**
	 * Auto-Pilot.
	 */
	$xbox->open_tab_item( 'auto-pilot' );
		$xbox->add_field(
			array(
				'name'    => 'Enable Auto-import',
				'id'      => 'lvjm-enable-auto-import',
				'type'    => 'switcher',
				'default' => 'on',
				'grid'    => '4-of-8',
				'desc'    => 'Enable auto-import features (Don\'t forget to set auto-import option to Enabled for any saved feed you want)',
			)
		);

		$xbox->open_mixed_field(
			array(
				'id'   => 'displayed-when:switch:lvjm-enable-auto-import:on:auto-import-settings',
				'name' => 'Auto import settings',
			)
		);
		$xbox->add_field(
			array(
				'id'         => 'auto-import-amount',
				'name'       => 'Amount of videos to import',
				'type'       => 'number',
				'default'    => 10,
				'grid'       => '4-of-8',
				'options'    => array(
					'unit' => 'videos / auto-import',
				),
				'attributes' => array(
					'min'  => 1,
					'max'  => 50,
					'step' => 1,
				),
				'desc'       => 'Choose how many videos to import (1 - 50)',
			)
		);

		$xbox->add_field(
			array(
				'name'    => 'Frequency',
				'id'      => 'lvjm-auto-import-frequency',
				'type'    => 'select',
				'desc'    => 'Choose how often to import videos',
				'default' => 'twicedaily',
				'items'   => array(
					'hourly'          => 'Every 1 hour',
					'every_six_hours' => 'Every 6 hours',
					'twicedaily'      => 'Every 12 hours',
					'daily'           => 'Every 24 hours',
				),
				'grid'    => '4-of-8-last',
			)
		);

		$xbox->add_field(
			array(
				'name'    => 'Server Cron',
				'id'      => 'auto-import-server-cron',
				'type'    => 'text',
				'desc'    => '<strong style="color: #ff0000;">Important:</strong> set a server cron <strong>only if WordPress native cron doesn\'t work</strong>. Copy this command and <a href="https://www.wp-script.com/setup-server-cron-job-wordpress/" target="_blank">setup your Server Cron job</a>',
				'default' => 'wget -qO- ' . site_url( '/wp-cron.php' ) . ' &> /dev/null',
			)
		);
		$xbox->close_mixed_field();

		$xbox->open_mixed_field(
			array(
				'name' => 'Proxy',
				'desc' => 'Use a proxy if your server IP has been banned by some partners',
			)
		);

		$xbox->add_field(
			array(
				'id'   => 'proxy-ip',
				'name' => 'IP Address',
				'type' => 'text',
				'grid' => '2-of-8',
				'desc' => 'Enter a valid Proxy IP',
			)
		);

		$xbox->add_field(
			array(
				'id'   => 'proxy-port',
				'name' => 'Port',
				'type' => 'text',
				'desc' => 'Enter a valid Proxy Port',
				'grid' => '2-of-8',
			)
		);

		$xbox->add_field(
			array(
				'id'   => 'proxy-user',
				'name' => 'User',
				'type' => 'text',
				'grid' => '2-of-8',
				'desc' => 'Enter the user name if auth required',
			)
		);

		$xbox->add_field(
			array(
				'id'   => 'proxy-password',
				'name' => 'Password',
				'type' => 'text',
				'desc' => 'Enter the password if auth required',
				'grid' => '2-of-8',
			)
		);
		$xbox->close_mixed_field();

	$xbox->close_tab_item( 'auto-pilot' );

	/**
	 * Data.
	 */
	$xbox->open_tab_item( 'data-to-import' );

	$xbox->add_field(
		array(
			'name'    => 'Title',
			'id'      => 'import-title',
			'type'    => 'switcher',
			'default' => 'on',
			'desc'    => 'Check if you want to import videos title',
		)
	);

	$xbox->add_field(
		array(
			'name'    => 'Main thumb file',
			'id'      => 'import-thumb',
			'type'    => 'switcher',
			'default' => 'on',
			'desc'    => 'Check if you want to download main thumb files (the thumb url will be saved in all cases)',
		)
	);

	// $xbox->add_field(
	// array(
	// 'name'    => 'Description',
	// 'id'      => 'import-description',
	// 'type'    => 'switcher',
	// 'default' => 'on',
	// 'desc'    => 'Check if you want to import videos description (when provided by the partner)',
	// )
	// );

	$xbox->add_field(
		array(
			'name'    => 'Tags',
			'id'      => 'import-tags',
			'type'    => 'switcher',
			'default' => 'on',
			'desc'    => 'Check if you want to import videos tags (when provided by the partner)',
		)
	);

	$xbox->add_field(
		array(
			'name'    => 'Actors',
			'id'      => 'import-actors',
			'type'    => 'switcher',
			'default' => 'on',
			'desc'    => 'Check if you want to import videos actors (when provided by the partner)',
		)
	);

	$xbox->close_tab_item( 'data-to-import' );

	/**
	 * Theme Compatibility.
	 */
	$xbox->open_tab_item( 'theme-compatibility' );

	$xbox->add_tab(
		array(
			'name'  => 'Theme compatibility tabs',
			'id'    => 'theme-compatibility-tabs',
			'items' => array(
				'player-in-content' => 'Player in content',
				'custom-fields'     => 'Custom fields',
				'custom-post-type'  => 'Custom post type',
			),
		)
	);

	/* Player in content */
	$xbox->open_tab_item( 'player-in-content' );
	$xbox->add_field(
		array(
			'id'      => 'player-in-content',
			'name'    => 'Player in content',
			'type'    => 'switcher',
			'default' => 'on',
			'desc'    => 'Check if you want to display the video player in the content',
			'grid'    => '2-of-8',
		)
	);

	// If on.
	$xbox->open_mixed_field(
		array(
			'id'   => 'displayed-when:switch:player-in-content:on:player-settings',
			'name' => 'Player position',
		)
	);

	$xbox->add_field(
		array(
			'id'      => 'player-position',
			'type'    => 'radio',
			'default' => 'before',
			'items'   => array(
				'before' => 'Before the content',
				'after'  => 'After the content',
			),
			'desc'    => 'Choose where to display the video player in the content',
			'grid'    => '4-of-8',
		)
	);

	$xbox->close_mixed_field();
	$xbox->close_tab_item( 'player-in-content' );

	/* Custom Fields */
	$xbox->open_tab_item( 'custom-fields' );
	$xbox->add_field(
		array(
			'id'      => 'custom-thumbnail',
			'name'    => 'Thumbnail',
			'type'    => 'text',
			'default' => 'thumb',
			'grid'    => '3-of-6',
		)
	);

	$xbox->add_field(
		array(
			'id'      => 'custom-embed-player',
			'name'    => 'Embed player',
			'type'    => 'text',
			'default' => 'embed',
			'grid'    => '3-of-6',
		)
	);

	$xbox->add_field(
		array(
			'id'      => 'custom-video-url',
			'name'    => 'Video URL',
			'type'    => 'text',
			'default' => 'video_url',
			'grid'    => '3-of-6',
		)
	);

	$xbox->add_field(
		array(
			'id'      => 'custom-duration',
			'name'    => 'Duration',
			'type'    => 'text',
			'default' => 'duration',
			'grid'    => '3-of-6',
		)
	);

	$xbox->add_field(
		array(
			'id'      => 'custom-tracking-url',
			'name'    => 'Tracking URL',
			'type'    => 'text',
			'default' => 'tracking_url',
			'grid'    => '3-of-6',
		)
	);
	$xbox->close_tab_item( 'custom-fields' );

	/* Custom post type */
	$xbox->open_tab_item( 'custom-post-type' );
	$xbox->add_field(
		array(
			'id'      => 'custom-video-post-type',
			'name'    => 'Video custom post type name',
			'type'    => 'select',
			'default' => 'post',
			'items'   => $all_post_types,
			'desc'    => 'Set the video custom post type used by your theme',
			'grid'    => '3-of-6',
		)
	);

	$xbox->add_field(
		array(
			'id'      => 'custom-video-categories',
			'name'    => 'Video custom categories',
			'type'    => 'select',
			'default' => 'category',
			'items'   => $all_taxonomies,
			'desc'    => 'Set the video categories used by your theme',
			'grid'    => '3-of-6',
		)
	);

	$xbox->add_field(
		array(
			'id'      => 'custom-video-actors',
			'name'    => 'Video custom models',
			'type'    => 'select',
			'default' => 'models',
			'items'   => $all_taxonomies,
			'desc'    => 'Set the video models taxonomy used by your theme',
			'grid'    => '3-of-6',
		)
	);

	$xbox->add_field(
		array(
			'id'      => 'custom-video-tags',
			'name'    => 'Video custom tags',
			'type'    => 'select',
			'default' => 'post_tag',
			'items'   => $all_taxonomies,
			'desc'    => 'Set the video tags used by your theme',
			'grid'    => '3-of-6',
		)
	);

	$xbox->close_tab_item( 'custom-post-type' );
	$xbox->close_tab( 'theme-compatibility-tabs' );
	$xbox->close_tab_item( 'theme-compatibility' );
	$xbox->close_tab( 'main-tab' );
}
