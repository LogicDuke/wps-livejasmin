<?php
/**
 * Category mapping helpers for import taxonomy assignment.
 *
 * @package LIVEJASMIN\Admin\Actions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

if ( ! function_exists( 'lvjm_get_default_source_category_seed_labels' ) ) {
	/**
	 * Get default source labels that should be available for mapping.
	 *
	 * @return array
	 */
	function lvjm_get_default_source_category_seed_labels() {
		return array(
			'cam girl',
			'amateur',
			'big tits',
			'anal',
			'blonde',
			'latina',
			'milf',
			'live cam models',
		);
	}
}

if ( ! function_exists( 'lvjm_get_default_destination_label_seed_map' ) ) {
	/**
	 * Get recommended SEO destination labels by source label.
	 *
	 * @return array
	 */
	function lvjm_get_default_destination_label_seed_map() {
		return array(
			'cam girl'        => 'Free Cam Girls',
			'amateur'         => 'Amateur Cam Girls',
			'big tits'        => 'Big Tits Cam Girls',
			'anal'            => 'Anal Cam Girls',
			'blonde'          => 'Blonde Cam Girls',
			'latina'          => 'Latina Cam Girls',
			'milf'            => 'Milf Cam Girls',
			'live cam models' => 'Live Cam Models',
		);
	}
}

if ( ! function_exists( 'lvjm_normalize_source_category_label' ) ) {
	/**
	 * Normalize source category labels so mappings survive partner formatting.
	 *
	 * @param string $label Raw source label.
	 * @return string
	 */
	function lvjm_normalize_source_category_label( $label ) {
		$normalized = strtolower( trim( (string) $label ) );
		if ( '' === $normalized ) {
			return '';
		}

		$normalized = preg_replace( '/\s*\((straight|gay|shemale)\)\s*$/i', '', $normalized );
		$normalized = preg_replace( '/\s+(straight|gay|shemale)$/i', '', $normalized );
		$normalized = str_replace( 'kw::', '', $normalized );
		$normalized = str_replace( 'optgroup::', '', $normalized );
		$normalized = preg_replace( '/\s+/', ' ', $normalized );

		return trim( (string) $normalized );
	}
}

if ( ! function_exists( 'lvjm_get_category_mapping_settings' ) ) {
	/**
	 * Load category mapping settings.
	 *
	 * @return array
	 */
	function lvjm_get_category_mapping_settings() {
		$settings = WPSCORE()->get_product_option( 'LVJM', 'import_category_mapping' );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings['rows'] = isset( $settings['rows'] ) && is_array( $settings['rows'] ) ? $settings['rows'] : array();
		$settings['suppress_category_like_tags'] = isset( $settings['suppress_category_like_tags'] ) ? (int) $settings['suppress_category_like_tags'] : 1;

		return $settings;
	}
}

if ( ! function_exists( 'lvjm_save_category_mapping_settings' ) ) {
	/**
	 * Save category mapping settings.
	 *
	 * @param array $settings Mapping settings.
	 * @return void
	 */
	function lvjm_save_category_mapping_settings( array $settings ) {
		WPSCORE()->update_product_option( 'LVJM', 'import_category_mapping', $settings );
	}
}

if ( ! function_exists( 'lvjm_get_category_mapping_rows_indexed' ) ) {
	/**
	 * Get configured mapping rows indexed by normalized source label.
	 *
	 * @return array
	 */
	function lvjm_get_category_mapping_rows_indexed() {
		$settings = lvjm_get_category_mapping_settings();
		$indexed  = array();

		foreach ( (array) $settings['rows'] as $row ) {
			$source_label = isset( $row['source_label'] ) ? (string) $row['source_label'] : '';
			$source_key   = lvjm_normalize_source_category_label( $source_label );
			if ( '' === $source_key ) {
				continue;
			}

			$indexed[ $source_key ] = array(
				'source_label'        => $source_label,
				'destination_term_id' => isset( $row['destination_term_id'] ) ? intval( $row['destination_term_id'] ) : 0,
				'create_if_missing'   => ! empty( $row['create_if_missing'] ) ? 1 : 0,
				'create_term_name'    => isset( $row['create_term_name'] ) ? sanitize_text_field( (string) $row['create_term_name'] ) : '',
			);
		}

		return $indexed;
	}
}

if ( ! function_exists( 'lvjm_build_category_mapping_admin_rows' ) ) {
	/**
	 * Build rows for admin rendering.
	 *
	 * @return array
	 */
	function lvjm_build_category_mapping_admin_rows() {
		$default_sources = lvjm_get_default_source_category_seed_labels();
		$defaults_map    = lvjm_get_default_destination_label_seed_map();
		$indexed         = lvjm_get_category_mapping_rows_indexed();
		$rows            = array();

		foreach ( $default_sources as $source_label ) {
			$key = lvjm_normalize_source_category_label( $source_label );
			if ( isset( $indexed[ $key ] ) ) {
				$rows[] = $indexed[ $key ];
				unset( $indexed[ $key ] );
				continue;
			}

			$rows[] = array(
				'source_label'        => $source_label,
				'destination_term_id' => 0,
				'create_if_missing'   => 0,
				'create_term_name'    => isset( $defaults_map[ $key ] ) ? $defaults_map[ $key ] : '',
			);
		}

		foreach ( $indexed as $row ) {
			$rows[] = $row;
		}

		$rows[] = array(
			'source_label'        => '',
			'destination_term_id' => 0,
			'create_if_missing'   => 0,
			'create_term_name'    => '',
		);

		return $rows;
	}
}

if ( ! function_exists( 'lvjm_resolve_destination_term_from_mapping' ) ) {
	/**
	 * Resolve a destination term id for an incoming source label.
	 *
	 * @param string $source_label Source label from feed.
	 * @param string $taxonomy Destination taxonomy.
	 * @return int
	 */
	function lvjm_resolve_destination_term_from_mapping( $source_label, $taxonomy ) {
		$source_key = lvjm_normalize_source_category_label( $source_label );
		if ( '' === $source_key ) {
			return 0;
		}

		$indexed = lvjm_get_category_mapping_rows_indexed();
		if ( ! isset( $indexed[ $source_key ] ) ) {
			return 0;
		}

		$row     = $indexed[ $source_key ];
		$term_id = isset( $row['destination_term_id'] ) ? intval( $row['destination_term_id'] ) : 0;
		if ( $term_id > 0 ) {
			$term = get_term( $term_id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return intval( $term_id );
			}
		}

		if ( empty( $row['create_if_missing'] ) || '' === $row['create_term_name'] ) {
			return 0;
		}

		$existing_by_name = get_term_by( 'name', $row['create_term_name'], $taxonomy );
		if ( $existing_by_name && ! is_wp_error( $existing_by_name ) ) {
			return intval( $existing_by_name->term_id );
		}

		$created = wp_insert_term( $row['create_term_name'], $taxonomy );
		if ( is_wp_error( $created ) || ! isset( $created['term_id'] ) ) {
			return 0;
		}

		$new_term_id = intval( $created['term_id'] );

		$settings = lvjm_get_category_mapping_settings();
		foreach ( $settings['rows'] as $index => $settings_row ) {
			$settings_source_key = lvjm_normalize_source_category_label( isset( $settings_row['source_label'] ) ? $settings_row['source_label'] : '' );
			if ( $settings_source_key === $source_key ) {
				$settings['rows'][ $index ]['destination_term_id'] = $new_term_id;
			}
		}
		lvjm_save_category_mapping_settings( $settings );

		return $new_term_id;
	}
}

if ( ! function_exists( 'lvjm_should_suppress_category_like_tags' ) ) {
	/**
	 * Determine if category-like tags should be suppressed.
	 *
	 * @return bool
	 */
	function lvjm_should_suppress_category_like_tags() {
		$settings = lvjm_get_category_mapping_settings();
		return ! empty( $settings['suppress_category_like_tags'] );
	}
}

if ( ! function_exists( 'lvjm_filter_import_tags_against_category_context' ) ) {
	/**
	 * Remove category-duplicate tags when mapping is applied.
	 *
	 * @param array  $tags Raw normalized tags.
	 * @param string $source_label Source category label.
	 * @param int    $destination_term_id Destination term id.
	 * @param string $category_taxonomy Category taxonomy.
	 * @return array
	 */
	function lvjm_filter_import_tags_against_category_context( array $tags, $source_label, $destination_term_id, $category_taxonomy ) {
		if ( empty( $tags ) || ! lvjm_should_suppress_category_like_tags() ) {
			return $tags;
		}

		$block_keys   = array();
		$source_clean = lvjm_normalize_source_category_label( $source_label );
		if ( '' !== $source_clean ) {
			$block_keys[] = sanitize_title( $source_clean );
		}

		$destination_term_id = intval( $destination_term_id );
		if ( $destination_term_id > 0 ) {
			$term = get_term( $destination_term_id, $category_taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$block_keys[] = sanitize_title( $term->name );
				$block_keys[] = sanitize_title( $term->slug );
			}
		}

		$block_keys = array_values( array_unique( array_filter( $block_keys ) ) );
		if ( empty( $block_keys ) ) {
			return $tags;
		}

		return array_values(
			array_filter(
				$tags,
				static function ( $tag ) use ( $block_keys ) {
					$tag_key = sanitize_title( (string) $tag );
					return '' !== $tag_key && ! in_array( $tag_key, $block_keys, true );
				}
			)
		);
	}
}
