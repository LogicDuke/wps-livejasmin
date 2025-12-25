<?php
/**
 * Admin Class plugin file.
 *
 * @package LIVEJASMIN\Admin\Class
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Json Item Class
 *
 * @since 1.0.0
 */
class LVJM_Json_Item {
	/**
	 * The item.
	 *
	 * @var array $item
	 * @access protected
	 */
	protected $item;

	/**
	 * All data of the json item.
	 *
	 * @var array $item_all_data
	 * @access protected
	 */
	protected $item_all_data;

	/**
	 * Given params when creating the json item.
	 *
	 * @var array $params
	 * @access protected
	 */
	protected $params;

	/**
	 * Item constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item_data The data needed to create the item.
	 * @return void
	 */
	public function __construct( $item_data ) {
		$this->item_all_data = (array) $item_data;
	}

	/**
	 * Initialize the current Item to fill the paramas and the feed infos.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params      The params.
	 * @param array $feed_infos  The feed infos.
	 *
	 * @return bool false if $params or $feed_infos are empty, true in all other cases
	 */
	public function init( $params, $feed_infos ) {

		if ( empty( $params ) || empty( $feed_infos ) ) {
			return false;
		}

		$params     = json_decode( wp_json_encode( $params ), true );
		$feed_infos = json_decode( wp_json_encode( $feed_infos ), true );
		$partner_id = $params['partner']['id'];

		$this->params = $params;

		$this->item['id']            = $this->get_partner_feed_infos( 'feed_item_id', $partner_id, $feed_infos );
		$this->item['title']         = $this->get_partner_feed_infos( 'feed_item_title', $partner_id, $feed_infos );
		$this->item['desc']          = $this->get_partner_feed_infos( 'feed_item_desc', $partner_id, $feed_infos );
		$this->item['tags']          = $this->get_partner_feed_infos( 'feed_item_tags', $partner_id, $feed_infos );
		$this->item['length']        = $this->get_partner_feed_infos( 'feed_item_length', $partner_id, $feed_infos );
		$this->item['length_format'] = $this->get_partner_feed_infos( 'feed_item_length_format', $partner_id, $feed_infos );
		$this->item['thumb_url']     = $this->get_partner_feed_infos( 'feed_item_thumb_url', $partner_id, $feed_infos );

		$this->item['thumbs_urls'] = $this->get_partner_feed_infos( 'feed_item_thumbs_urls', $partner_id, $feed_infos );
		$this->item['thumbs_urls'] = explode( ',', $this->item['thumbs_urls'] );

		$this->item['trailer_url']  = $this->get_partner_feed_infos( 'feed_item_trailer_url', $partner_id, $feed_infos );
		$this->item['video_url']    = $this->get_partner_feed_infos( 'feed_item_video_url', $partner_id, $feed_infos );
		$this->item['tracking_url'] = $this->get_partner_feed_infos( 'feed_item_join_url', $partner_id, $feed_infos );
		$this->item['quality']      = $this->get_partner_feed_infos( 'feed_item_quality', $partner_id, $feed_infos );
		$this->item['isHd']         = $this->get_partner_feed_infos( 'feed_item_isHd', $partner_id, $feed_infos );
		$this->item['uploader']     = $this->get_partner_feed_infos( 'feed_item_uploader', $partner_id, $feed_infos );
		$this->item['code']         = $this->get_partner_feed_infos( 'feed_item_code', $partner_id, $feed_infos );
		$this->item['actors']       = $this->get_partner_feed_infos( 'feed_item_actors', $partner_id, $feed_infos );

		return true;
	}

	/**
	 * Get the id of the current item.
	 *
	 * @since 1.0.0
	 *
	 * @return string The id of the current item.
	 */
	public function get_id() {
		return LVJM_Item::get_id( $this->item );
	}

	/**
	 * Test if the current item is valid or not.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if the current item is valid, false if not.
	 */
	public function is_valid() {
		return LVJM_Item::is_valid( $this->item );
	}

	/**
	 * Get the data needed (only item ID) for JS response in the JS ajax call for a given item.
	 *
	 * @since 1.0.0
	 *
	 * @return string The current item ID.
	 */
	public function get_data_for_js() {
		return LVJM_Item::get_data_for_js( $this->item );
	}

	/**
	 * Get the formatted data needed for JSON response in the JS ajax call for a given item.
	 *
	 * @since 1.0.0
	 *
	 * @param int $cpt A counter.
	 * @return array The formatted array ready to be used for a JSON response.
	 */
	public function get_data_for_json( $cpt = 0 ) {
		return LVJM_Item::get_data_for_json( $this->item, $cpt = 0 );
	}

	/**
	 * Get the partner feed info from given feed item, partner id and all feed infos to look into.
	 *
	 * @since 1.0.0
	 *
	 * @param string $partner_feed_item The partner feed item key.
	 * @param string $partner_id        The partner id.
	 * @param array  $feed_infos        The feed infos to look into.
	 *
	 * @return string|bool The feed info if success, false if not.
	 */
	private function get_partner_feed_infos( $partner_feed_item, $partner_id, $feed_infos ) {

		$feed_item_type = isset( $feed_infos[ $partner_feed_item ] ) ? key( $feed_infos[ $partner_feed_item ] ) : null;

		if ( isset( $feed_infos[ $partner_feed_item ][ $feed_item_type ] ) ) {
			$short_item = $feed_infos[ $partner_feed_item ][ $feed_item_type ];
			$results    = array();
			preg_match_all( '/<%(.+)%>/U', $short_item, $results );

			foreach ( $results[1] as $result ) {
				if ( strpos( $result, 'get_partner_option' ) !== false ) {
					$saved_partner_options = WPSCORE()->get_product_option( 'LVJM', $partner_id . '_options' );
					$option                = str_replace( array( 'get_partner_option("', '")' ), array( '', '' ), $result );
					$new_result            = '$saved_partner_options["' . $option . '"]';
					$short_item            = str_replace( '<%' . $result . '%>', eval( 'return ' . $new_result . ';' ), $partner_feed_item );

				} else {
					$short_item = str_replace( '<%' . $result . '%>', eval( 'return ' . $result . ';' ), $short_item );
				}
			}
			unset( $results );
			switch ( $feed_item_type ) {

				case 'node':
					if ( is_array( $this->item_all_data[ $short_item ] ) ) {
						$output = isset( $this->item_all_data[ $short_item ][0] ) ? $this->item_all_data[ $short_item ][0] : '';
					} else {
						$output = isset( $this->item_all_data[ $short_item ] ) ? $this->item_all_data[ $short_item ] : '';
					}
					break;

				case 'node_array':
					$struct = explode( '/', $short_item );
					if ( count( $struct ) == 2 ) {
						$output = implode( ',', (array) $this->item_all_data[ $struct[0] ][ $struct[1] ] );
					} else {
						$output = implode( ',', (array) $this->item_all_data[ $short_item ] );
					}
					break;

				case 'node_foreach':
					$exploded_final_node = explode( 'foreach:', $short_item );
					$final_node          = $exploded_final_node[1];
					$exploded_path       = explode( '/', $exploded_final_node[0] );
					$path                = $exploded_path[0];
					$output              = array();
					foreach ( (array) $this->item_all_data[ $path ] as $key => $value ) {
						if ( '{value}' === $final_node ) {
							if ( '' !== $value ) {
								$output[] = $value;
							}
						} else {
							$output[] = $value[ $final_node ];
						}
					}
					$output = implode( ',', (array) $output );
					break;

				default:
					$output = $short_item;
					break;
			}
		}
		if ( ! isset( $output ) ) {
			return false;
		}
		return LVJM_Item::clean_string( $output );
	}
}
