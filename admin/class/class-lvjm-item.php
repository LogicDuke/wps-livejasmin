<?php
/**
 * Admin Class plugin file.
 *
 * @package LIVEJASMIN\Admin\Class
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Generic Item Class
 *
 * @since 1.0.0
 */
class LVJM_Item {

	/**
	 * Clean a given string for better text rendering from APIs.
	 *
	 * @since 1.0.0
	 *
	 * @param string $string The string to clean.
	 * @return string        The string cleaned.
	 */
	public static function clean_string( $string ) {
		return (string) stripslashes( trim( str_replace( array( '<p>', '</p>' ), '', LVJM_Encoding::UTF8FixWin1252Chars( html_entity_decode( $string, ENT_QUOTES ) ) ) ) );
	}

	/**
	 * Get the id of a given item.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item The item.
	 * @return string The id of the given item.
	 */
	public static function get_id( $item ) {
		return (string) $item['id'];
	}

	/**
	 * Test if a given item is valid or not.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item The item.
	 * @return bool true if the item is valid, false if not.
	 */
	public static function is_valid( $item ) {
		return '' !== $item['id'];
	}

	/**
	 * Get the data needed (only item ID) for JS response in the JS ajax call for a given item.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item The item.
	 * @return string The item ID.
	 */
	public static function get_data_for_js( $item ) {
		return (string) $item['id'];
	}

	/**
	 * Get the formatted data needed for JSON response in the JS ajax call for a given item.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item The item.
	 * @return array The formatted array ready to be used for a JSON response.
	 */
	public static function get_data_for_json( $item ) {

		if ( 'off' === xbox_get_field_value( 'lvjm-options', 'import-title' ) ) {
			$item['title'] = '';
		}
		// if ( 'off' === xbox_get_field_value( 'lvjm-options', 'import-description' ) ) {
		// $item['desc'] = '';
		// }
		if ( 'off' === xbox_get_field_value( 'lvjm-options', 'import-tags' ) ) {
			$item['tags'] = '';
		}
		if ( 'off' === xbox_get_field_value( 'lvjm-options', 'import-actors' ) ) {
			$item['actors'] = '';
		}

		return array(
			'id'           => (string) $item['id'],
			'title'        => (string) $item['title'],
			'desc'         => (string) $item['desc'],
			'tags'         => (string) $item['tags'],
			'duration'     => (string) self::get_length_in_seconds( $item ),
			'thumb_url'    => (string) $item['thumb_url'],
			'thumbs_urls'  => (array) $item['thumbs_urls'],
			'trailer_url'  => (string) $item['trailer_url'],
			'video_url'    => (string) $item['video_url'],
			'tracking_url' => (string) $item['tracking_url'],
			'quality'      => (string) $item['quality'],
			'isHd'         => (string) $item['isHd'],
			'uploader'     => (string) $item['uploader'],
			'embed'        => (string) $item['code'],
			'actors'       => (string) $item['actors'],
			'checked'      => true,
			'grabbed'      => false,
		);
	}

	/**
	 * Get the length in seconds for a given item.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item  The item.
	 * @return int $length The length in seconds.
	 */
	public static function get_length_in_seconds( $item ) {
		$length = $item['length'];
		$format = $item['length_format'];

		if ( ! ( $length && $format ) ) {
			return false;
		}

		switch ( $format ) {
			case 'mm':
				$length = (int) $length * 60;
				break;
			case 'mm,ss':
				$length = explode( ',', $length );
				$mm     = (int) $length[0] * 60;
				$ss     = (int) $length[1];
				$length = $mm + $ss;
				break;
			case 'coupe_sss':
				break;
			case 'ss':
				break;
			case 'hh:mm:ss':
				$length = explode( ':', $length );
				$hh     = (int) $length[0] * 60 * 60;
				$mm     = (int) $length[1] * 60;
				$ss     = (int) $length[2];
				$length = $hh + $mm + $ss;
				break;
			case 'mm:ss':
				$length = explode( ':', $length );
				$mm     = (int) $length[0] * 60;
				$ss     = (int) $length[1];
				$length = $mm + $ss;
				break;
			case 'mm,ss/mm.ss':
				$length = str_replace( '.', ',', $length );
				$length = explode( ',', $length );
				$mm     = (int) $length[0] * 60;
				$ss     = (int) $length[1];
				$length = $mm + $ss;
				break;
			case 'mmmsss':
				$length = explode( 'm', $length );
				$mm     = (int) $length[0] * 60;
				$ss     = (int) str_replace( 's', '', $length[1] );
				$length = $mm + $ss;
				break;
			case 'min sec':
				$length = explode( 'min ', $length );
				$mm     = (int) $length[0] * 60;
				$ss     = (int) str_replace( 'sec', '', $length[1] );
				$length = $mm + $ss;
				break;
			case 'mmm:sss':
				$length = str_replace( 'm', '', $length );
				$length = str_replace( 's', '', $length );
				$length = explode( ':', $length );
				$mm     = (int) $length[0] * 60;
				$ss     = (int) $length[1];
				$length = $mm + $ss;
				break;

			case 'xvideos':
				preg_match_all( '/[0-9]+/', $length, $matches );
				$matches = $matches[0];
				switch ( count( $matches ) ) {
					case 1:
						if ( strpos( $length, 'min' ) !== false ) {
							$length = $matches[0] * 60;
						} else {
							$length = $matches[0] * 1;
						}
						break;
					case 2:
						if ( strpos( $length, 'h' ) !== false && strpos( $length, 'min' ) !== false ) {
							$length = $matches[0] * 60 * 60 + $matches[1] * 60;
						} else {
							$length = $matches[0] * 60 + $matches[1];
						}
						break;
					case 3:
						$length = $matches[0] * 60 * 60 + $matches[1] * 60 + $matches[1];
						break;
				}
				break;
		}
		return (int) $length;
	}
}
