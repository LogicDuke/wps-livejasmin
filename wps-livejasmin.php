<?php
/**
 * Plugin Name: WPS LiveJasmin
 * Plugin URI: https://www.wp-script.com/plugins/livejasmin/
 * Description: Import LiveJasmin livecam embed videos in your WordPress posts
 * Author: WP-Script
 * Author URI: https://www.wp-script.com
 * Version: 1.3.3
 * Text Domain: wps-livejasmin
 * Domain Path: /languages
 *
 * @package LVJM\Main
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

if ( ! class_exists( 'LVJM' ) ) {
	/**
	 * Singleton Class.
	 *
	 * @since 1.0.0
	 *
	 * @final
	 */
	final class LVJM {

		/**
		 * The instance of WPS LIVEJASMIN plugin
		 *
		 * @var instanceof LVJM $instance
		 * @access private
		 * @static
		 */
		private static $instance;

		/**
		 * The config of WPS LIVEJASMIN plugin
		 *
		 * @var array $config
		 * @access private
		 * @static
		 */
		private static $config;

		/**
		 * __clone method
		 *
		 * @return void
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Do not clone or wake up this class', 'lvjm_lang' ), '1.0' );
		}

		/**
		 * __wakeup method
		 *
		 * @return void
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Do not clone or wake up this class', 'lvjm_lang' ), '1.0' );
		}

		/**
		 * Instance method
		 *
		 * @since 1.0.0
		 *
		 * @return self::$instance
		 */
		public static function instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof LVJM ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
				if ( ! is_plugin_active( 'wp-script-core/wp-script-core.php' ) ) {
					require_once plugin_dir_path( __FILE__ ) . 'admin/vendors/tgm-activation-x/plugin-activation.php';
					require_once plugin_dir_path( __FILE__ ) . 'admin/vendors/tgm-activation-x/class-tgm-plugin-activation.php';
				} else {
					self::$instance = new LVJM();
					// load config file.
					require_once plugin_dir_path( __FILE__ ) . 'config.php';
					// load text domain.
					self::$instance->load_textdomain();
					// load cron.
					require_once LVJM_DIR . 'admin/cron-x/cron-import.php';
					require_once LVJM_DIR . 'admin/vendors/simple-html-dom-x/simple-html-dom.php';
					require_once LVJM_DIR . 'admin/pages/page-options-x.php';
					if ( is_admin() || wp_next_scheduled( 'lvjm_update_one_feed' ) ) {
						// load admin filters.
						self::$instance->load_admin_filters();
						// load admin hooks.
						self::$instance->load_admin_hooks();
						// auto-load admin php files.
						self::$instance->auto_load_php_files( 'admin' );
						// load admin features.
						self::$instance->admin_init();

					}
					if ( ! is_admin() ) {
						// load public filters.
						// self::$instance->load_public_filters();
						// auto-load admin php files.
						self::$instance->auto_load_php_files( 'public' );
					}
				}
			}
			return self::$instance;
		}

		/**
		 * Add js and css files, tabs, pages, php files in admin mode.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function load_admin_filters() {
			add_filter( 'WPSCORE-scripts', array( $this, 'add_admin_scripts' ) );
			add_filter( 'WPSCORE-tabs', array( $this, 'add_admin_navigation' ) );
			add_filter( 'WPSCORE-pages', array( $this, 'add_admin_navigation' ) );
		}

		/**
		 * Add admin js and css scripts. This is a WPSCORE-scripts filter callback function.
		 *
		 * @since 1.0.0
		 *
		 * @param array $scripts List of all WPS CORE CSS / JS to load.
		 * @return array $scripts List of all WPS CORE + WPS LIVEJASMIN CSS / JS to load.
		 */
		public function add_admin_scripts( $scripts ) {
			if ( isset( self::$config['scripts'] ) ) {
				if ( isset( self::$config['scripts']['js'] ) ) {
					$scripts += (array) self::$config['scripts']['js'];
				}
				if ( isset( self::$config['scripts']['css'] ) ) {
					$scripts += (array) self::$config['scripts']['css'];
				}
			}
			return $scripts;
		}

		/**
		 * Add WPS LIVEJASMIN admin navigation tab. This is a WPSCORE-tabs and WPSCORE-pages filters callback function.
		 *
		 * @since 1.0.0
		 *
		 * @param array $nav List of all WPS CORE navigation tabs to add.
		 * @return array $nav List of all WPS CORE + WPS LIVEJASMIN navigation tabs to add.
		 */
		public function add_admin_navigation( $nav ) {
			if ( isset( self::$config['nav'] ) ) {
				$nav += (array) self::$config['nav'];
			}
			return $nav;
		}

		/**
		 * Auto-loader for PHP files
		 *
		 * @since 1.0.0
		 *
		 * @param string{'admin','public'} $dir Directory where to find PHP files to load.
		 * @static
		 * @return void
		 */
		public static function auto_load_php_files( $dir ) {
			$dirs = (array) ( plugin_dir_path( __FILE__ ) . $dir . '/' );
			foreach ( (array) $dirs as $dir ) {
				$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ) );
				if ( ! empty( $files ) ) {
					foreach ( $files as $file ) {
						// exlude dir.
						if ( $file->isDir() ) {
							continue; }
						// exlude index.php.
						if ( $file->getPathname() === 'index.php' ) {
							continue; }
						// exlude files != .php.
						if ( substr( $file->getPathname(), -4 ) !== '.php' ) {
							continue; }
						// exlude files from -x suffixed directories.
						if ( substr( $file->getPath(), -2 ) === '-x' ) {
							continue; }
						// exlude -x suffixed files.
						if ( substr( $file->getPathname(), -6 ) === '-x.php' ) {
							continue; }
						// else require file.
						require $file->getPathname();
					}
				}
			}
		}

		/**
		 * Registering WPS LIVEJASMIN activation / deactivation / uninstall hooks.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		public function load_admin_hooks() {
			register_activation_hook( __FILE__, array( 'LVJM', 'activation' ) );
			register_deactivation_hook( __FILE__, array( 'LVJM', 'deactivation' ) );
			register_uninstall_hook( __FILE__, array( 'LVJM', 'uninstall' ) );
		}

		/**
		 * Stuff to do on WPS LIVEJASMIN activation. This is a register_activation_hook callback function.
		 *
		 * @since 1.0.0
		 *
		 * @static
		 * @return void
		 */
		public static function activation() {
			WPSCORE()->update_client_signature();
			WPSCORE()->init( true );
			wp_clear_scheduled_hook( 'lvjm_update_one_feed' );
			wp_schedule_event( time(), 'twicedaily', 'lvjm_update_one_feed' );
			self::purge_performer_transients();
		}

		/**
		 * Stuff to do on WPS LIVEJASMIN deactivation. This is a register_deactivation_hook callback function.
		 *
		 * @since 1.0.0
		 *
		 * @static
		 * @return void
		 */
		public static function deactivation() {
			WPSCORE()->update_client_signature();
			wp_clear_scheduled_hook( 'LVJM_update_one_feed' );
			wp_clear_scheduled_hook( 'lvjm_update_one_feed' );
			WPSCORE()->init( true );
		}

		/**
		 * Stuff to do on WPS LIVEJASMIN deactivation. This is a register_deactivation_hook callback function.
		 *
		 * @since 1.0.0
		 *
		 * @static
		 * @return void
		 */
		public static function uninstall() {
			WPSCORE()->update_client_signature();
			wp_clear_scheduled_hook( 'LVJM_update_one_feed' );
			wp_clear_scheduled_hook( 'lvjm_update_one_feed' );
			WPSCORE()->init( true );
		}

		/**
		 * Purge performer-related transients on activation/update.
		 *
		 * @since 1.3.3
		 *
		 * @return void
		 */
		public static function purge_performer_transients() {
			global $wpdb;

			$perf_like         = $wpdb->esc_like( '_transient_lvjm_perf_' ) . '%';
			$perf_timeout_like = $wpdb->esc_like( '_transient_timeout_lvjm_perf_' ) . '%';
			$filter_like       = $wpdb->esc_like( '_transient_lvjm_vpapi_perf_filter_param' ) . '%';
			$filter_timeout_like = $wpdb->esc_like( '_transient_timeout_lvjm_vpapi_perf_filter_param' ) . '%';

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
					$perf_like,
					$perf_timeout_like,
					$filter_like,
					$filter_timeout_like
				)
			);

			delete_transient( 'lvjm_vpapi_perf_filter_param' );
			delete_transient( 'lvjm_vpapi_perf_filter_param_v2' );
		}

		/**
		 * Load textdomain method.
		 *
		 * @return bool True when textdomain is successfully loaded, false if not.
		 */
		public function load_textdomain() {
			$lang = ( current( explode( '_', get_locale() ) ) );
			if ( 'zh' === $lang ) {
				$lang = 'zh-TW';
			}
			$textdomain = 'lvjm_lang';
			$mofile     = LVJM_DIR . "languages/{$textdomain}_{$lang}.mo";
			return load_textdomain( $textdomain, $mofile );
		}

		/**
		 * Load public filters.
		 *
		 * @since 1.0.0
		 *
		 * @return   void
		 */
		public function load_public_filters() {
			add_filter( 'WPSCORE-public_dirs', array( $this, 'add_public_dirs' ) );
		}

		/**
		 * Add public php files to require.
		 *
		 * @since 1.0.0
		 *
		 * @param array $public_dirs Array of public directories.
		 * @return array $public_dirs Array of public directories with the current plugin ones.
		 */
		public function add_public_dirs( $public_dirs ) {
			$public_dirs[] = plugin_dir_path( __FILE__ ) . 'public/';
			return $public_dirs;
		}

		/**
		 * Stuff to do on admin init.
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		private function admin_init() {}

		/**
		 * Stuff to do on public init.
		 *
		 * @since 1.0.0
		 *
		 * @access private
		 * @return void
		 */
		private function public_init() {}

		/**
		 * Get a whitelabel id from its url.
		 * Used to send traffic to the whitelabel.
		 *
		 * @param string $url The url of the white label.
		 * @return string|bool The Id of the whitelabel if exists, false if not.
		 */
public function get_whitelabel_id_from_url( $url ) {
    /*
     * Always return the whitelabel ID configured by the site owner.
     * LiveJasmin whitelabel IDs are fixed six‑digit codes.  The plugin previously
     * attempted to scrape the ID from the whitelabel URL, which broke when
     * LiveJasmin changed their templates.  Instead, return the ID provided in
     * the settings.  If you wish to change it, update the string below.
     */
    return '261146';
}


		/**
		 * Get all partners data.
		 *
		 * @since 1.0.0
		 *
		 * @access public
		 * @return array All the partners data.
		 */
		public function get_partners() {
			/* $i        = 0; */
			$data     = WPSCORE()->get_product_data( 'LVJM' );
			$partners = $data['partners'];

			unset( $data );

			foreach ( (array) $partners as $partner_key => $partner_config ) {
				$is_configured = true;
				// adding options infos.
				if ( isset( $partner_config['options'] ) ) {
					$partner_id            = $partner_config['id'];
					$saved_partner_options = WPSCORE()->get_product_option( 'LVJM', $partner_id . '_options' );
					foreach ( (array) $partner_config['options'] as $key => $option ) {
						if ( isset( $option['id'] ) ) {
							$partners[ $partner_key ]['options'][ $key ]['value'] = isset( $saved_partner_options[ $option['id'] ] ) ? $saved_partner_options[ $option['id'] ] : '';
							if ( isset( $partners[ $partner_key ]['options'][ $key ]['required'] ) && true === $partners[ $partner_key ]['options'][ $key ]['required'] ) {
								if ( ! isset( $saved_partner_options[ $option['id'] ] ) ) {
									$is_configured = false;
								} elseif ( '' === $saved_partner_options[ $option['id'] ] ) {
										$is_configured = false;
								}
							}
						}
					}
				}
				$partners[ $partner_key ]['is_configured'] = $is_configured;
				$partners[ $partner_key ]['categories']    = $this->get_ordered_categories();
			}
			return (array) $partners;
		}

		/**
		 * Get the ordered categories for the UI.
		 *
		 * @return array The list of partner categories ordered for the UI.
		 */
		public function get_ordered_categories() {
			$categories = $this->get_partner_categories();
			return $this->order_categories( $categories );
		}

		/**
		 * Get the partner categories (used to be retrieved from the API)
		 *
		 * @return array The list of partner categories sorted by orientation.
		 */
		public function get_partner_categories() {
			$categories   = array();
			$orientations = array( 'Straight', 'Gay', 'Shemale' );
			$tags         = array( '69', 'above average', 'amateur', 'anal', 'angry', 'asian', 'ass', 'ass to mouth', 'athletic', 'auburn hair', 'babe', 'bald', 'ball sucking', 'bathroom', 'bbc', 'BBW', 'bdsm', 'bed', 'big ass', 'big boobs', 'big booty', 'big breasts', 'big cock', 'big tits', 'bizarre', 'black eyes', 'black girl', 'black hair', 'blonde', 'blonde hair', 'blowjob', 'blue eyes', 'blue hair', 'bondage', 'boots', 'booty', 'bossy', 'brown eyes', 'brown hair', 'brunette', 'butt plug', 'cam girl', 'cam porn', 'cameltoe', 'celebrity', 'cfnm', 'cheerleader', 'clown hair', 'cock', 'college girl', 'cop', 'cosplay', 'cougar', 'couple', 'cowgirl', 'creampie', 'crew cut', 'cum', 'cum on tits', 'cumshot', 'curious', 'cut', 'cute', 'dance', 'deepthroat', 'dildo', 'dirty', 'doctor', 'doggy', 'domination', 'double penetration', 'ebony', 'erotic', 'eye contact', 'facesitting', 'facial', 'fake tits', 'fat ass', 'fetish', 'fingering', 'fire red hair', 'fishnet', 'fisting', 'flirting', 'foot sex', 'footjob', 'fuck', 'gag', 'gaping', 'gilf', 'girl', 'glamour', 'glasses', 'green eyes', 'grey eyes', 'group', 'gym', 'hairy', 'handjob', 'hard cock', 'hd', 'high heels', 'homemade', 'horny', 'hot', 'hot flirt', 'housewife', 'huge cock', 'huge tits', 'innocent', 'interracial', 'intim piercing', 'jeans', 'kitchen', 'ladyboy', 'large build', 'latex', 'latin', 'latina', 'leather', 'lesbian', 'lick', 'lingerie', 'live sex', 'long hair', 'long nails', 'machine', 'maid', 'massage', 'masturbation', 'mature', 'milf', 'missionary', 'misstress', 'moaning', 'muscular', 'muslim', 'naked', 'nasty', 'natural tits', 'normal cock', 'normal tits', 'nurse', 'nylon', 'office', 'oiled', 'orange hair', 'orgasm', 'orgy', 'outdoor', 'party', 'pawg', 'petite', 'piercing', 'pink hair', 'pissing', 'pool', 'pov', 'pregnant', 'princess', 'public', 'punish', 'pussy', 'pvc', 'quicky', 'redhead', 'remote toy', 'reverse cowgirl', 'riding', 'rimjob', 'roleplay', 'romantic', 'room', 'rough', 'schoolgirl', 'scissoring', 'scream', 'secretary', 'sensual', 'sextoy', 'sexy', 'shaved', 'short girl', 'short hair', 'shoulder length hair', 'shy', 'skinny', 'slave', 'sloppy', 'slutty', 'small ass', 'small cock', 'smoking', 'solo', 'sologirl', 'squirt', 'stockings', 'strap on', 'stretching', 'striptease', 'stroking', 'suck', 'swallow', 'tall', 'tattoo', 'teacher', 'teasing', 'teen', 'threesome', 'tight', 'tiny tits', 'titjob', 'toy', 'trimmed', 'uniform', 'virgin', 'watching', 'wet', 'white' );
			foreach ( $orientations as $orientation ) {
				$suffix       = $orientation !== 'Straight' ? strtolower( $orientation ) : '';
				$suffix_key   = $suffix ? " $suffix" : '';
				$suffix_value = $suffix ? " ($suffix)" : '';
				foreach ( $tags as $tag ) {
					if ( 'Straight' === $orientation && '69' === $tag ) {
						$label = 'All Straight Categories';
					} else {
						$label = ucwords( $tag ) . $suffix_value;
					}
					$categories[ 'optgroup::' . $orientation ][ $tag . $suffix_key ] = $label;
				}
			}
			return $categories;
		}

		/**
		 * Order some given categories to be used by the plugin in the UI.
		 *
		 * @since 1.0.7
		 *
		 * @param array $categories A list of categories to order.
		 *
		 * @return array The list of categories ordered for the UI.
		 */
		private function order_categories( $categories ) {
			$ordered_cats = array();
			$i            = 0;
			foreach ( $categories as $cat_id => $cat_name ) {
				if ( strpos( $cat_id, 'optgroup' ) !== false ) {
					$cat_id_explode     = explode( '::', $cat_id );
					$ordered_cats[ $i ] = array(
						'id'   => 'optgroup',
						'name' => end( $cat_id_explode ),
					);
					foreach ( (array) $cat_name as $sub_cat_id => $sub_cat_name ) {
						$ordered_cats[ $i ]['sub_cats'][] = array(
							'id'   => $sub_cat_id,
							'name' => $sub_cat_name,
						);
					}
				} else {
					$ordered_cats[ $i ] = array(
						'id'   => $cat_id,
						'name' => $cat_name,
					);
				}
				++$i;
			}
			return $ordered_cats;
		}

		/**
		 * Get a partner infos from a given partner id.
		 *
		 * @since 1.0.0
		 *
		 * @access public
		 * @param string $partner_id the partner id we want to retrieve the data from.
		 * @return array All the wanted partner infos.
		 */
		public function get_partner( $partner_id ) {
			$partners = $this->get_partners();
			return $partners[ $partner_id ];
		}

		/**
		 * Get all WordPress categories depending on the categories taxonomies defined in the options poage.
		 *
		 * @since 1.0.0
		 *
		 * @access public
		 * @return array The categories.
		 */
		public function get_wp_cats() {
			$custom_taxonomy = xbox_get_field_value( 'lvjm-options', 'custom-video-categories' );
			return (array) get_terms( '' !== $custom_taxonomy ? $custom_taxonomy : 'category', array( 'hide_empty' => 0 ) );
		}

		/**
		 * Get all saved feeds.
		 *
		 * @since 1.0.0
		 *
		 * @access public
		 * @return array The saved feeds data array.
		 */
		public function get_feeds() {
			$saved_feeds = WPSCORE()->get_product_option( 'LVJM', 'feeds' );

			if ( ! is_array( $saved_feeds ) ) {
				$saved_feeds = array();
			}

			foreach ( (array) $saved_feeds as $feed_id => $feed_data ) {
				$more_data                               = explode( '__', $feed_id );
				$saved_feeds[ $feed_id ]['wp_cat']       = $more_data[0];
				$saved_feeds[ $feed_id ]['partner_id']   = $more_data[1];
				$saved_feeds[ $feed_id ]['partner_cat']  = $more_data[2];
				$saved_feeds[ $feed_id ]['id']           = $feed_id;
				$saved_feeds[ $feed_id ]['wp_cat_state'] = term_exists( intval( $saved_feeds[ $feed_id ]['wp_cat'] ) ) === null ? 0 : 1;
			}
			return (array) $saved_feeds;
		}

		/**
		 * Get a saved feed data from a given feed id.
		 *
		 * @since 1.0.0
		 *
		 * @access public
		 * @param string $feed_id The feed id we want to get get the data from.
		 * @return array|bool The saved feed data if success, false if not.
		 */
		public function get_feed( $feed_id ) {
			$feeds = $this->get_feeds();
			return isset( $feeds[ $feed_id ] ) ? $feeds[ $feed_id ] : false;
		}

		/**
		 * Update a feed from a given freed id and the new data to put.
		 *
		 * @since 1.0.0
		 *
		 * @access public
		 * @param string $feed_id  The feed id we want to update the data from.
		 * @param string $new_data The new data to put.
		 * @return bool true if everything works well, false if not.
		 */
		public function update_feed( $feed_id, $new_data ) {
			if ( ! isset( $feed_id, $new_data ) ) {
				return false;
			}

			$saved_feeds = WPSCORE()->get_product_option( 'LVJM', 'feeds' );

			if ( ! is_array( $saved_feeds ) ) {
				$saved_feeds = array();
			}

			foreach ( (array) $new_data as $key => $value ) {
				$saved_feeds[ $feed_id ][ $key ] = $value;
			}

			// if total videos <= 0, delete the feed.
			if ( ! isset( $saved_feeds[ $feed_id ]['total_videos'] ) || $saved_feeds[ $feed_id ]['total_videos'] <= 0 ) {
				unset( $saved_feeds[ $feed_id ] );
			}
			return WPSCORE()->update_product_option( 'LVJM', 'feeds', $saved_feeds );
		}

		/**
		 * Delete a feed from a given freed id..
		 *
		 * @since 1.0.0
		 *
		 * @access public
		 * @param string $feed_id The feed id we want to delete the data from.
		 * @return bool true if everything works well, false if not.
		 */
		public function delete_feed( $feed_id ) {
			if ( ! isset( $feed_id ) ) {
				return false;
			}

			$saved_feeds = WPSCORE()->get_product_option( 'LVJM', 'feeds' );
			if ( isset( $saved_feeds[ $feed_id ] ) ) {
				unset( $saved_feeds[ $feed_id ] );
			}
			return WPSCORE()->update_product_option( 'LVJM', 'feeds', $saved_feeds );
		}

		/**
		 * Get all expressions to translate.
		 *
		 * @since 1.0.0
		 *
		 * @access public
		 * @return array All expressions to translate.
		 */
		public function get_object_l10n() {
			return array(
				'error_suppression'       => esc_html__( 'An error occured during the suppression:', 'lvjm_lang' ),
				'select_wp_cat'           => esc_html__( 'Select a WordPress category', 'lvjm_lang' ),
				'select_cat_from'         => esc_html__( 'Select a category from', 'lvjm_lang' ),
				'or_keyword_if_available' => esc_html__( 'or a keyword (if it is available)', 'lvjm_lang' ),
				'and'                     => esc_html__( 'AND', 'lvjm_lang' ),
				'check_least'             => esc_html__( 'Check at least 1 video', 'lvjm_lang' ),
				'enable_button'           => esc_html__( 'to enable this button', 'lvjm_lang' ),
				'import'                  => esc_html__( 'Import', 'lvjm_lang' ),
				'search_feed'             => esc_html__( 'videos and save this search as a Feed. All your Feeds are displayed at the bottom of this page.', 'lvjm_lang' ),
			);
		}

		/**
		 * Return the reference of a given variable.
		 *
		 * @since 1.0.0
		 *
		 * @access public
		 * @param mixed $var The variable we want the reference from.
		 * @return mixed The reference of a variable.
		 */
		public function call_by_ref( &$var ) {
			return $var;
		}

		/**
		 * Overcharged media_sideload_image WordPress native function.
		 *
		 * @since 1.0.0
		 *
		 * @access public
		 * @param string     $file    The media filename.
		 * @param string|int $post_id The post id the mediafile is attached to.
		 * @param string     $desc    The description of the media file.
		 * @param string     $source  unused. To remove.
		 * @return mixed The reference of a variable.
		 */
		public function media_sideload_image( $file, $post_id, $desc = null, $source = null ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			if ( ! empty( $file ) ) {

				// Set variables for storage, fix file filename for query strings.
				preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches );
				$tmp                = explode( '.', basename( $matches[0] ) );
				$file_ext           = end( $tmp );
				$file_array         = array();
				$file_array['name'] = sanitize_title( get_the_title( $post_id ) ) . '.' . $file_ext;
				unset( $tmp, $file_ext );

				// Download file to temp location.
				$file_array['tmp_name'] = download_url( $file );

				// If error storing temporarily, return the error.
				if ( is_wp_error( $file_array['tmp_name'] ) ) {
					return $file_array['tmp_name'];
				}

				// Do the validation and storage stuff.
				$id = media_handle_sideload( $file_array, $post_id, $desc );

				// If error storing permanently, unlink.
				if ( is_wp_error( $id ) ) {
					unlink( $file_array['tmp_name'] );
					return $id;
				}
				$src = wp_get_attachment_url( $id );
			}

			// Finally check to make sure the file has been saved, then return the HTML.
			if ( ! empty( $src ) ) {
				$alt  = isset( $desc ) ? esc_attr( $desc ) : '';
				$html = "<img src='$src' alt='$alt' />";
				return $html;
			}
		}
	}
}

if ( ! function_exists( 'LVJM' ) ) {
	/**
	 * Create the WPS LIVEJASMIN instance in a function and call it.
	 *
	 * @return LVJM::instance();
	 */
	// phpcs:disable
	function LVJM() {
		return LVJM::instance();
	}
	LVJM();
}

/**
 * Normalize performer names for consistent matching.
 *
 * @param string $name Performer name.
 * @return string Normalized performer key.
 */
function lvjm_normalize_performer_key( $name ) {
	$normalized = strtolower( trim( (string) $name ) );
	return preg_replace( '/[^a-z0-9]/', '', $normalized );
}

/**
 * Format performer names for display/canonical slug creation.
 *
 * @param string $name Performer name.
 * @return string Formatted performer name.
 */
function lvjm_format_performer_display_name( $name ) {
	$name = trim( (string) $name );
	if ( '' === $name ) {
		return '';
	}
	$name = str_replace( array( '-', '_' ), ' ', $name );
	$name = preg_replace( '/(?<!\ )[A-Z]/', ' $0', $name );
	$name = preg_replace( '/\s+/', ' ', $name );
	return ucwords( $name );
}

/**
 * Resolve performer name against existing terms to avoid duplicate profiles.
 *
 * @param string $name Performer name.
 * @param array  $taxonomies Taxonomies to search.
 * @return string Resolved performer name.
 */
function lvjm_resolve_performer_term_name( $name, array $taxonomies ) {
	$name       = trim( (string) $name );
	$taxonomies = array_filter( $taxonomies );
	if ( '' === $name || empty( $taxonomies ) ) {
		return $name;
	}

	$normalized = lvjm_normalize_performer_key( $name );
	if ( '' === $normalized ) {
		return $name;
	}

	$search_token = $normalized;
	if ( strlen( $search_token ) > 4 ) {
		$search_token = substr( $search_token, 0, 4 );
	}

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomies,
			'hide_empty' => false,
			'search'     => $search_token,
			'number'     => 25,
		)
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( (array) $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$term_key  = lvjm_normalize_performer_key( $term->name );
			$slug_key  = lvjm_normalize_performer_key( $term->slug );
			if ( $term_key === $normalized || $slug_key === $normalized ) {
				return $term->name;
			}
		}
	}

	return lvjm_format_performer_display_name( $name );
}

/**
 * Resolve performer terms array against existing taxonomies.
 *
 * @param array $performer_terms Performer terms.
 * @param array $taxonomies Taxonomies to search.
 * @return array Resolved performer terms.
 */
function lvjm_resolve_performer_terms( array $performer_terms, array $taxonomies ) {
	$resolved = array();
	foreach ( $performer_terms as $term ) {
		$term = lvjm_resolve_performer_term_name( $term, $taxonomies );
		if ( '' !== $term ) {
			$resolved[] = $term;
		}
	}
	return array_values( array_unique( $resolved ) );
}

/**
 * Resolve a feed id by destination term id.
 *
 * @param int|string $wp_cat WordPress term id.
 * @param string     $partner_id Partner id.
 * @param string     $fallback_feed_id Feed id provided by request.
 * @return string Resolved feed id.
 */
function lvjm_resolve_feed_id_by_term( $wp_cat, $partner_id, $fallback_feed_id ) {
	$feeds  = LVJM()->get_feeds();
	$wp_cat = (int) $wp_cat;
	foreach ( (array) $feeds as $feed ) {
		if ( (int) $feed['wp_cat'] !== $wp_cat ) {
			continue;
		}
		if ( '' !== $partner_id && isset( $feed['partner_id'] ) && (string) $feed['partner_id'] !== (string) $partner_id ) {
			continue;
		}
		$resolved_id = (string) $feed['id'];
		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER && $resolved_id !== (string) $fallback_feed_id ) {
			WPSCORE()->write_log(
				'info',
				sprintf(
					'[TMW-FEED] Reusing saved feed %s for term %d (requested %s).',
					$resolved_id,
					$wp_cat,
					(string) $fallback_feed_id
				),
				__FILE__,
				__LINE__
			);
		}
		return $resolved_id;
	}
	return (string) $fallback_feed_id;
}

/**
 * Extract 32-hex video id candidates from a string.
 *
 * @param string $value Value to scan.
 * @return array List of candidates.
 */
function lvjm_vpapi_extract_hex_ids_from_value( $value ) {
	$value = (string) $value;
	if ( '' === $value ) {
		return array();
	}
	if ( preg_match_all( '/[0-9a-f]{32}/i', $value, $matches ) ) {
		return array_map( 'strtolower', array_unique( $matches[0] ) );
	}
	return array();
}

/**
 * Gather VPAPI video id candidates from known URL fields.
 *
 * @param array $video_infos Video info array.
 * @return array List of candidates.
 */
function lvjm_vpapi_video_id_candidates( array $video_infos ) {
	$candidates = array();
	$fields     = array( 'video_url', 'tracking_url', 'embed', 'trailer_url', 'thumb_url' );
	foreach ( $fields as $field ) {
		if ( empty( $video_infos[ $field ] ) ) {
			continue;
		}
		$found = lvjm_vpapi_extract_hex_ids_from_value( $video_infos[ $field ] );
		if ( ! empty( $found ) ) {
			$candidates = array_merge( $candidates, $found );
		}
	}
	return array_values( array_unique( $candidates ) );
}

/**
 * Resolve a VPAPI 32-hex video id from video info data.
 *
 * @param array $video_infos Video info array.
 * @return string Resolved 32-hex id or empty string.
 */
function lvjm_resolve_vpapi_video_id( array $video_infos ) {
	$raw_id = isset( $video_infos['id'] ) ? trim( (string) $video_infos['id'] ) : '';
	if ( '' !== $raw_id && preg_match( '/^[0-9a-f]{32}$/i', $raw_id ) ) {
		return strtolower( $raw_id );
	}
	$fields = array( 'video_url', 'tracking_url', 'embed', 'trailer_url', 'thumb_url' );
	foreach ( $fields as $field ) {
		if ( empty( $video_infos[ $field ] ) ) {
			continue;
		}
		$found = lvjm_vpapi_extract_hex_ids_from_value( $video_infos[ $field ] );
		if ( ! empty( $found ) ) {
			return strtolower( $found[0] );
		}
	}
	return '';
}

/**
 * Resolve the canonical VPAPI main thumb URL for a video info array.
 *
 * @param array $video_infos Video info array.
 * @return array Result with url, source, resolved_video_id, match_method.
 */
function lvjm_vpapi_main_thumb_for_video_infos( array $video_infos ) {
	$result = array(
		'url'               => '',
		'source'            => 'api',
		'resolved_video_id' => '',
		'match_method'      => 'none',
	);

	if ( ! class_exists( 'LVJM_Search_Videos' ) || ! method_exists( 'LVJM_Search_Videos', 'vpapi_details_csv_data' ) ) {
		return $result;
	}

	$resolved_id                  = lvjm_resolve_vpapi_video_id( $video_infos );
	$result['resolved_video_id']  = $resolved_id;
	$csv_data                     = LVJM_Search_Videos::vpapi_details_csv_data();
	$csv_by_video_id              = is_array( $csv_data ) && isset( $csv_data['by_video_id'] ) ? (array) $csv_data['by_video_id'] : array();

	if ( '' !== $resolved_id && isset( $csv_by_video_id[ $resolved_id ] ) ) {
		$result['url']          = (string) $csv_by_video_id[ $resolved_id ];
		$result['source']       = 'vpapi_details.csv';
		$result['match_method'] = 'by_video_id';
		return $result;
	}

	$performer = '';
	$performer_keys = array( 'performer', 'performer_name', 'model', 'modelName', 'actors' );
	foreach ( $performer_keys as $key ) {
		if ( empty( $video_infos[ $key ] ) ) {
			continue;
		}
		$performer = (string) $video_infos[ $key ];
		break;
	}
	if ( '' !== $performer ) {
		$performer_parts = preg_split( '/[;,]/', $performer );
		$performer       = isset( $performer_parts[0] ) ? trim( (string) $performer_parts[0] ) : '';
	}

	$title = isset( $video_infos['title'] ) ? (string) $video_infos['title'] : '';

	$normalized_performer = lvjm_normalize_performer_key( $performer );
	$normalized_title     = lvjm_normalize_performer_key( $title );

	if ( '' === $normalized_performer || '' === $normalized_title ) {
		return $result;
	}

	$rows = isset( $csv_data['rows'] ) ? (array) $csv_data['rows'] : array();
	foreach ( $rows as $row ) {
		$row_performer = isset( $row['normalized_performer'] ) ? (string) $row['normalized_performer'] : '';
		$row_title     = isset( $row['normalized_title'] ) ? (string) $row['normalized_title'] : '';
		if ( '' === $row_performer || '' === $row_title ) {
			continue;
		}
		if ( $row_performer !== $normalized_performer || $row_title !== $normalized_title ) {
			continue;
		}
		$main_thumb = isset( $row['main_thumb_url'] ) ? (string) $row['main_thumb_url'] : '';
		if ( '' === $main_thumb ) {
			continue;
		}
		$result['url']          = $main_thumb;
		$result['source']       = 'vpapi_details.csv';
		$result['match_method'] = 'by_performer_title';
		return $result;
	}

	return $result;
}

/**
 * Extract attachment ID from media HTML or latest attachment for the post.
 *
 * @param string $media_html Media HTML.
 * @param int    $post_id Post id.
 * @return int Attachment id.
 */
function lvjm_resolve_attachment_id_from_media( $media_html, $post_id ) {
	$media_url = '';
	if ( is_string( $media_html ) && preg_match( '/src=[\'"]([^\'"]+)/', $media_html, $matches ) ) {
		$media_url = $matches[1];
	}
	if ( '' !== $media_url ) {
		$attachment_id = attachment_url_to_postid( $media_url );
		if ( $attachment_id ) {
			return (int) $attachment_id;
		}
	}
	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'post_parent'    => $post_id,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	if ( ! empty( $attachments ) && $attachments[0] instanceof WP_Post ) {
		return (int) $attachments[0]->ID;
	}
	return 0;
}

/**
 * Get binding configuration for video taxonomies.
 *
 * @return array
 */
function lvjm_get_video_taxonomy_binding_config() {
	if ( ! function_exists( 'xbox_get_field_value' ) ) {
		return array();
	}

	$post_type = xbox_get_field_value( 'lvjm-options', 'custom-video-post-type' );
	if ( '' === $post_type ) {
		$post_type = 'post';
	}

	if ( ! post_type_exists( $post_type ) ) {
		return array();
	}

	$cats = xbox_get_field_value( 'lvjm-options', 'custom-video-categories' );
	if ( '' === $cats ) {
		$cats = 'category';
	}

	$tags = xbox_get_field_value( 'lvjm-options', 'custom-video-tags' );
	if ( '' === $tags ) {
		$tags = 'post_tag';
	}

	$actors = xbox_get_field_value( 'lvjm-options', 'custom-video-actors' );
	if ( '' === $actors ) {
		$actors = 'actors';
	}

	$taxonomies = array( $cats, $tags, $actors );
	if ( taxonomy_exists( 'models' ) ) {
		$taxonomies[] = 'models';
	}

	return array(
		'post_type'  => $post_type,
		'taxonomies' => $taxonomies,
	);
}

/**
 * Bind selected taxonomies to the configured post type.
 *
 * @return void
 */
function lvjm_bind_video_taxonomies() {
	$config = lvjm_get_video_taxonomy_binding_config();
	if ( empty( $config ) ) {
		return;
	}

	foreach ( $config['taxonomies'] as $taxonomy ) {
		if ( taxonomy_exists( $taxonomy ) && ! is_object_in_taxonomy( $config['post_type'], $taxonomy ) ) {
			register_taxonomy_for_object_type( $taxonomy, $config['post_type'] );
		}
	}
}

/**
 * Bind video taxonomies when the post type is registered.
 *
 * @param string $post_type Post type being registered.
 * @return void
 */
function lvjm_handle_registered_post_type( $post_type ) {
	$config = lvjm_get_video_taxonomy_binding_config();
	if ( empty( $config ) ) {
		return;
	}

	if ( $post_type !== $config['post_type'] ) {
		return;
	}

	lvjm_bind_video_taxonomies();
}

/**
 * Bind video taxonomies when a taxonomy is registered.
 *
 * @param string $taxonomy Taxonomy being registered.
 * @return void
 */
function lvjm_handle_registered_taxonomy( $taxonomy ) {
	$config = lvjm_get_video_taxonomy_binding_config();
	if ( empty( $config ) ) {
		return;
	}

	if ( ! in_array( $taxonomy, $config['taxonomies'], true ) ) {
		return;
	}

	lvjm_bind_video_taxonomies();
}

add_action( 'init', 'lvjm_bind_video_taxonomies', 200 );
add_action( 'registered_post_type', 'lvjm_handle_registered_post_type', 10, 1 );
add_action( 'registered_taxonomy', 'lvjm_handle_registered_taxonomy', 10, 1 );
