<?php
/**
 * Tags normalization helpers.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

if ( ! function_exists( 'lvjm_normalize_tags_array' ) ) {
	/**
	 * Normalize tags input into a clean array.
	 *
	 * @param string|array $raw_tags Raw tags input.
	 * @param array        $context  Optional context (mode/source).
	 * @return array Normalized tag array.
	 */
	function lvjm_normalize_tags_array( $raw_tags, $context = array() ) {
		$mode          = isset( $context['mode'] ) ? (string) $context['mode'] : 'unknown';
		$source        = isset( $context['source'] ) ? (string) $context['source'] : '';
		$delimiter     = '';
		$normalized_in = $raw_tags;
		$raw_string    = is_array( $raw_tags ) ? wp_json_encode( $raw_tags ) : (string) $raw_tags;
		$tags          = array();

		if ( is_array( $raw_tags ) ) {
			$tags = $raw_tags;
		} else {
			$normalized_in = trim( (string) $raw_tags );
			if ( '' !== $normalized_in && false !== strpos( $normalized_in, '|' ) ) {
				$delimiter = '|';
				$tags      = explode( '|', $normalized_in );
			} elseif ( '' !== $normalized_in && ( false !== strpos( $normalized_in, ',' ) || false !== strpos( $normalized_in, ';' ) ) ) {
				$delimiter = ',';
				$tags      = explode( ',', str_replace( ';', ',', $normalized_in ) );
			} elseif ( '' !== $normalized_in ) {
				$tags = array( $normalized_in );
			}
		}

		$tags = array_filter(
			array_map( 'trim', (array) $tags ),
			static function( $tag ) {
				return '' !== $tag;
			}
		);
		$tags = array_values( array_unique( $tags ) );

		if ( defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER ) {
			$source_suffix = '' !== $source ? ' source=' . $source : '';
			if ( '|' === $delimiter ) {
				error_log( '[TMW-TAGS][WARN] pipe-delimited tags detected, normalizing...' );
			}
			error_log(
				sprintf(
					'[TMW-TAGS] raw="%s" mode=%s delimiter="%s" count=%d%s',
					$raw_string,
					$mode,
					$delimiter,
					count( $tags ),
					$source_suffix
				)
			);
			error_log( '[TMW-TAGS] normalized=' . wp_json_encode( $tags ) );
		}

		return $tags;
	}
}
