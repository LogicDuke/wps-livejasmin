<?php
/**
 * Admin Tools Page plugin file.
 *
 * @package LIVEJASMIN\Admin\Pages
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

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
					<div class="block-white block-white-first block-white-last">
						<h3><?php esc_html_e( 'LiveJasmin Tools', 'lvjm_lang' ); ?></h3>
						<p><?php esc_html_e( 'Reset the list of removed LiveJasmin videos to allow them to appear in importer results again.', 'lvjm_lang' ); ?></p>
						<form method="post">
							<?php wp_nonce_field( 'lvjm_reset_removed_videos_action', 'lvjm_reset_removed_videos_nonce' ); ?>
							<button type="submit" name="lvjm_reset_removed_videos" class="btn btn-danger">
								<i class="fa fa-trash" aria-hidden="true"></i> <?php esc_html_e( 'Reset LiveJasmin Removed Videos', 'lvjm_lang' ); ?>
							</button>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}
