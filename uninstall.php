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

// Delete transients.
delete_transient( 'draftcraft_models_cache' );
delete_transient( 'draftcraft_image_models_cache' );
delete_transient( 'draftcraft_pipeline_lock' );
delete_transient( 'draftcraft_bulk_notice' );
delete_transient( 'draftcraft_aioseo_table' );
delete_transient( 'draftcraft_link_candidates_' . md5( 'post' ) );
delete_transient( 'draftcraft_link_candidates_v2_' . md5( 'post' ) );

// Clear scheduled hooks.
wp_clear_scheduled_hook( 'draftcraft_cron_generate' );
wp_clear_scheduled_hook( 'draftcraft_cron_generate_image' );
