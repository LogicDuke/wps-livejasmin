<?php
/**
 * Repair missing video to model relations.
 *
 * @package LVJM\Admin\Actions
 */

add_action( 'admin_init', 'lvjm_repair_missing_model_relations' );

/**
 * Run a one-time repair for missing tmw_related_model meta.
 *
 * @return void
 */
function lvjm_repair_missing_model_relations() {
	@set_time_limit( 0 );
	@ini_set( 'memory_limit', '1024M' );

	if ( get_option( 'tmw_missing_model_relations_fixed' ) ) {
		return;
	}

	$video_post_type = xbox_get_field_value( 'lvjm-options', 'custom-video-post-type' );
	$video_post_type = '' !== $video_post_type ? $video_post_type : 'post';

	if ( ! post_type_exists( $video_post_type ) ) {
		$video_post_type = 'post';
	}

	$model_post_type = 'model';
	if ( ! post_type_exists( $model_post_type ) ) {
		lvjm_repair_missing_model_relations_log( '[TMW-REL-FIX] Model post type not found, aborting.' );
		update_option( 'tmw_missing_model_relations_fixed', time() );
		return;
	}

	$models = get_posts(
		array(
			'post_type'      => $model_post_type,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	if ( empty( $models ) ) {
		lvjm_repair_missing_model_relations_log( '[TMW-REL-FIX] No models found to process.' );
		update_option( 'tmw_missing_model_relations_fixed', time() );
		return;
	}

	$meta_keys = array(
		'uploader',
		'model',
		'modelName',
		'performer',
		'performer_id',
		'performerId',
		'username',
	);

	foreach ( $models as $model_id ) {
		$model = get_post( $model_id );
		if ( ! $model ) {
			continue;
		}

		$identifiers = array_filter(
			array_unique(
				array_merge(
					array(
						$model->post_title,
						$model->post_name,
					),
					array_map(
						'sanitize_text_field',
						array(
							get_post_meta( $model_id, 'performer_id', true ),
							get_post_meta( $model_id, 'performerId', true ),
							get_post_meta( $model_id, 'model', true ),
							get_post_meta( $model_id, 'modelName', true ),
							get_post_meta( $model_id, 'uploader', true ),
							get_post_meta( $model_id, 'username', true ),
						)
					)
				)
			)
		);

		if ( empty( $identifiers ) ) {
			continue;
		}

		lvjm_repair_missing_model_relations_log(
			'[TMW-REL-FIX] Processing model ' . $model_id . ' (' . $model->post_title . ')'
		);

		$meta_query = array( 'relation' => 'OR' );
		foreach ( $meta_keys as $meta_key ) {
			foreach ( $identifiers as $identifier ) {
				$meta_query[] = array(
					'key'     => $meta_key,
					'value'   => $identifier,
					'compare' => '=',
				);
			}
		}

		$videos = get_posts(
			array(
				'post_type'      => $video_post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => $meta_query,
			)
		);

		if ( empty( $videos ) ) {
			continue;
		}

		foreach ( $videos as $video_id ) {
			$existing_relation = get_post_meta( $video_id, 'tmw_related_model', true );
			if ( '' !== $existing_relation && 0 !== $existing_relation ) {
				continue;
			}

			update_post_meta( $video_id, 'tmw_related_model', $model_id );
			lvjm_repair_missing_model_relations_log(
				'[TMW-REL-FIX] Linked video ' . $video_id . ' → model ' . $model_id
			);
		}
	}

	lvjm_repair_missing_model_relations_log( '[TMW-REL-FIX] Completed' );
	update_option( 'tmw_missing_model_relations_fixed', time() );
}

/**
 * Log helper for the migration.
 *
 * @param string $message Log message.
 * @return void
 */
function lvjm_repair_missing_model_relations_log( $message ) {
	if ( function_exists( 'WPSCORE' ) && WPSCORE() ) {
		WPSCORE()->write_log( 'info', $message, __FILE__, __LINE__ );
		return;
	}

	error_log( $message );
}
