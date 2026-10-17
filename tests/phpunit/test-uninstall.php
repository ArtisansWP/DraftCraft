<?php
/**
 * Tests for uninstall.php cleanup.
 *
 * @package DraftCraft
 */

/**
 * Class DraftCraft_Test_Uninstall
 */
class DraftCraft_Test_Uninstall extends WP_UnitTestCase {

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstall_deletes_plugin_data_only() {
		update_option( 'draftcraft_settings', array( 'api_key' => 'secret' ) );
		update_option( 'draftcraft_rotation_index', 3 );
		update_option( 'draftcraft_keyword_queue', array( array( 'keyword' => 'x' ) ) );
		update_option( 'draftcraft_db_version', '1.2.5' );
		update_option( 'unrelated_option', 'keep-me' );
		set_transient( 'draftcraft_models_cache', 'cached', HOUR_IN_SECONDS );
		wp_schedule_event( time() + 3600, 'hourly', 'draftcraft_cron_generate' );
		wp_schedule_single_event( time() + 3600, 'draftcraft_cron_generate_image' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		require dirname( __DIR__, 2 ) . '/uninstall.php';

		wp_cache_flush();

		$this->assertFalse( get_option( 'draftcraft_settings' ) );
		$this->assertFalse( get_option( 'draftcraft_rotation_index' ) );
		$this->assertFalse( get_option( 'draftcraft_keyword_queue' ) );
		$this->assertFalse( get_option( 'draftcraft_db_version' ) );
		$this->assertSame( 'keep-me', get_option( 'unrelated_option' ) );
		$this->assertFalse( get_transient( 'draftcraft_models_cache' ) );
		$this->assertFalse( wp_next_scheduled( 'draftcraft_cron_generate' ) );
		$this->assertFalse( wp_next_scheduled( 'draftcraft_cron_generate_image' ) );
	}
}
