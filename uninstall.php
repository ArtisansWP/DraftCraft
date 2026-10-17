<?php
/**
 * DraftCraft Uninstall
 *
 * Cleans up options, transients, and cron schedules on plugin uninstall.
 *
 * @package DraftCraft
 * @since   1.0.0
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Delete options.
delete_option( 'draftcraft_settings' );
delete_option( 'draftcraft_rotation_index' );
delete_option( 'draftcraft_keyword_queue' );
delete_option( 'draftcraft_db_version' );

global $wpdb;

$transient_likes = array(
	$wpdb->esc_like( '_transient_draftcraft_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_draftcraft_' ) . '%',
	$wpdb->esc_like( '_site_transient_draftcraft_' ) . '%',
	$wpdb->esc_like( '_site_transient_timeout_draftcraft_' ) . '%',
);

foreach ( $transient_likes as $like ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall prefix cleanup; table name cannot be a placeholder.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$like
		)
	);
}

if ( is_multisite() ) {
	foreach ( array(
		$wpdb->esc_like( '_site_transient_draftcraft_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_draftcraft_' ) . '%',
	) as $like ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall prefix cleanup; table name cannot be a placeholder.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
				$like
			)
		);
	}
}

// Clear scheduled hooks.
wp_clear_scheduled_hook( 'draftcraft_cron_generate' );
wp_clear_scheduled_hook( 'draftcraft_cron_generate_image' );
