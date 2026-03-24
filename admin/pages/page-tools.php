<?php
/**
 * Admin Tools Page plugin file.
 *
 * @package LIVEJASMIN\Admin\Pages
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

if ( ! function_exists( 'lvjm_get_category_mapping_settings' ) ) {
	require_once LVJM_DIR . 'admin/actions/lvjm-taxonomy-mapping.php';
}

/**
 * Delete transients by prefix.
 *
 * @param array $prefixes List of transient prefixes to delete.
 * @return void
 */
function lvjm_delete_transients_by_prefixes( array $prefixes ) {
	global $wpdb;

	foreach ( $prefixes as $prefix ) {
		$transient_like = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		$timeout_like   = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $transient_like ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_like ) );
	}
}

/**
 * Callback for the plugin Tools page.
 *
 * @since 1.0.0
 *
 * @return void
 */
function lvjm_tools_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'lvjm_lang' ) );
	}

	$notice_message = '';

	if ( isset( $_POST['lvjm_save_category_mapping'] ) ) {
		check_admin_referer( 'lvjm_save_category_mapping_action', 'lvjm_save_category_mapping_nonce' );

		$source_labels      = isset( $_POST['mapping_source_label'] ) ? (array) wp_unslash( $_POST['mapping_source_label'] ) : array();
		$destination_termid = isset( $_POST['mapping_destination_term_id'] ) ? (array) wp_unslash( $_POST['mapping_destination_term_id'] ) : array();
		$create_if_missing  = isset( $_POST['mapping_create_if_missing'] ) ? (array) wp_unslash( $_POST['mapping_create_if_missing'] ) : array();
		$create_term_name   = isset( $_POST['mapping_create_term_name'] ) ? (array) wp_unslash( $_POST['mapping_create_term_name'] ) : array();

		$rows = array();
		foreach ( $source_labels as $index => $raw_source_label ) {
			$source_label = sanitize_text_field( (string) $raw_source_label );
			if ( '' === trim( $source_label ) ) {
				continue;
			}

			$rows[] = array(
				'source_label'        => $source_label,
				'destination_term_id' => isset( $destination_termid[ $index ] ) ? intval( $destination_termid[ $index ] ) : 0,
				'create_if_missing'   => isset( $create_if_missing[ $index ] ) ? 1 : 0,
				'create_term_name'    => isset( $create_term_name[ $index ] ) ? sanitize_text_field( (string) $create_term_name[ $index ] ) : '',
			);
		}

		$settings = array(
			'rows'                        => $rows,
			'suppress_category_like_tags' => isset( $_POST['suppress_category_like_tags'] ) ? 1 : 0,
		);
		lvjm_save_category_mapping_settings( $settings );
		$notice_message = esc_html__( 'Category mapping settings saved.', 'lvjm_lang' );
	}

	if ( isset( $_POST['lvjm_reset_removed_videos'] ) ) {
		check_admin_referer( 'lvjm_reset_removed_videos_action', 'lvjm_reset_removed_videos_nonce' );

		WPSCORE()->update_product_option( 'LVJM', 'removed_videos_ids', array() );
		lvjm_delete_transients_by_prefixes(
			array(
				'lvjm_perf_v2_',
				'lvjm_search_',
			)
		);
		WPSCORE()->write_log( 'info', '[TMW-FIX][LVJM-RESET] removed_videos_ids fully cleared by admin', __FILE__, __LINE__ );
		$notice_message = esc_html__( 'Removed video IDs cleared and importer caches reset.', 'lvjm_lang' );
	}
	?>
	<div id="wp-script">
		<div class="content-tabs">
			<?php WPSCORE()->display_logo(); ?>
			<?php WPSCORE()->display_tabs(); ?>
			<div class="tab-content">
				<div class="tab-pane fade in active" id="lvjm-tools">
					<div>
						<ul class="list-inline">
							<li><a href="admin.php?page=lvjm-import-videos"><i class="fa fa-cloud-download"></i> <?php esc_html_e( 'Import videos', 'lvjm_lang' ); ?></a></li>
							<li>|</li>
							<li><a href="admin.php?page=lvjm-options"><i class="fa fa-wrench"></i> <?php esc_html_e( 'Options', 'lvjm_lang' ); ?></a></li>
							<li>|</li>
							<li class="active"><a href="admin.php?page=lvjm-tools"><i class="fa fa-shield"></i> <?php esc_html_e( 'Tools', 'lvjm_lang' ); ?></a></li>
						</ul>
					</div>
					<?php if ( '' !== $notice_message ) : ?>
						<div class="notice notice-success is-dismissible">
							<p><?php echo esc_html( $notice_message ); ?></p>
						</div>
					<?php endif; ?>
					<div class="block-white block-white-first">
						<h3><?php esc_html_e( 'LiveJasmin Tools', 'lvjm_lang' ); ?></h3>
						<p><?php esc_html_e( 'Reset the list of removed LiveJasmin videos to allow them to appear in importer results again.', 'lvjm_lang' ); ?></p>
						<form method="post">
							<?php wp_nonce_field( 'lvjm_reset_removed_videos_action', 'lvjm_reset_removed_videos_nonce' ); ?>
							<button type="submit" name="lvjm_reset_removed_videos" class="btn btn-danger">
								<i class="fa fa-trash" aria-hidden="true"></i> <?php esc_html_e( 'Reset LiveJasmin Removed Videos', 'lvjm_lang' ); ?>
							</button>
						</form>
					</div>

					<?php
					$custom_taxonomy   = xbox_get_field_value( 'lvjm-options', 'custom-video-categories' );
					$category_taxonomy = '' !== $custom_taxonomy ? $custom_taxonomy : 'category';
					$taxonomy_terms    = get_terms(
						array(
							'taxonomy'   => $category_taxonomy,
							'hide_empty' => false,
						)
					);
					if ( is_wp_error( $taxonomy_terms ) ) {
						$taxonomy_terms = array();
					}
					$mapping_rows   = lvjm_build_category_mapping_admin_rows();
					$mapping_config = lvjm_get_category_mapping_settings();
					?>
					<div class="block-white block-white-last">
						<h3><?php esc_html_e( 'Import Category Mapping', 'lvjm_lang' ); ?></h3>
						<p><?php esc_html_e( 'Map raw source categories to SEO destination categories. Mappings are applied only to future imports.', 'lvjm_lang' ); ?></p>
						<form method="post">
							<?php wp_nonce_field( 'lvjm_save_category_mapping_action', 'lvjm_save_category_mapping_nonce' ); ?>
							<table class="widefat striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Source category label', 'lvjm_lang' ); ?></th>
										<th><?php echo esc_html( sprintf( __( 'Destination term (%s)', 'lvjm_lang' ), $category_taxonomy ) ); ?></th>
										<th><?php esc_html_e( 'Create if missing', 'lvjm_lang' ); ?></th>
										<th><?php esc_html_e( 'New destination name', 'lvjm_lang' ); ?></th>
									</tr>
								</thead>
								<tbody>
								<?php foreach ( $mapping_rows as $index => $mapping_row ) : ?>
									<tr>
										<td>
											<input type="text" class="regular-text" name="mapping_source_label[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( isset( $mapping_row['source_label'] ) ? $mapping_row['source_label'] : '' ); ?>" placeholder="<?php esc_attr_e( 'ex: Amateur', 'lvjm_lang' ); ?>">
										</td>
										<td>
											<select name="mapping_destination_term_id[<?php echo esc_attr( $index ); ?>]">
												<option value="0"><?php esc_html_e( '- Select destination -', 'lvjm_lang' ); ?></option>
												<?php foreach ( (array) $taxonomy_terms as $taxonomy_term ) : ?>
													<option value="<?php echo esc_attr( $taxonomy_term->term_id ); ?>" <?php selected( intval( isset( $mapping_row['destination_term_id'] ) ? $mapping_row['destination_term_id'] : 0 ), intval( $taxonomy_term->term_id ) ); ?>>
														<?php echo esc_html( $taxonomy_term->name ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</td>
										<td>
											<label>
												<input type="checkbox" name="mapping_create_if_missing[<?php echo esc_attr( $index ); ?>]" value="1" <?php checked( ! empty( $mapping_row['create_if_missing'] ) ); ?>>
												<?php esc_html_e( 'Allow', 'lvjm_lang' ); ?>
											</label>
										</td>
										<td>
											<input type="text" class="regular-text" name="mapping_create_term_name[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( isset( $mapping_row['create_term_name'] ) ? $mapping_row['create_term_name'] : '' ); ?>" placeholder="<?php esc_attr_e( 'ex: Amateur Cam Girls', 'lvjm_lang' ); ?>">
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
							<p>
								<label>
									<input type="checkbox" name="suppress_category_like_tags" value="1" <?php checked( ! empty( $mapping_config['suppress_category_like_tags'] ) ); ?>>
									<?php esc_html_e( 'Suppress tags that duplicate mapped category intent', 'lvjm_lang' ); ?>
								</label>
							</p>
							<button type="submit" name="lvjm_save_category_mapping" class="btn btn-primary">
								<i class="fa fa-save" aria-hidden="true"></i> <?php esc_html_e( 'Save Category Mapping', 'lvjm_lang' ); ?>
							</button>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}
