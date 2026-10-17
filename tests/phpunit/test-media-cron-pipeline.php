<?php
/**
 * Tests for media URL checks, cron, and pipeline guards.
 *
 * @package DraftCraft
 */

/**
 * Class DraftCraft_Test_Media_Cron_Pipeline
 */
class DraftCraft_Test_Media_Cron_Pipeline extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		draftcraft_clear_cron();
		wp_clear_scheduled_hook( DRAFTCRAFT_IMAGE_CRON_HOOK );
		delete_transient( 'draftcraft_pipeline_lock' );
	}

	public function tear_down() {
		draftcraft_clear_cron();
		wp_clear_scheduled_hook( DRAFTCRAFT_IMAGE_CRON_HOOK );
		delete_transient( 'draftcraft_pipeline_lock' );
		parent::tear_down();
	}

	public function test_is_allowed_image_source() {
		$this->assertTrue( DraftCraft_Media::is_allowed_image_source( 'data:image/png;base64,aaa' ) );
		$this->assertTrue( DraftCraft_Media::is_allowed_image_source( 'data:image/svg+xml;base64,aaa' ) );

		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$this->assertTrue( DraftCraft_Media::is_allowed_image_source( 'https://' . $host . '/img.jpg' ) );
		$this->assertFalse( DraftCraft_Media::is_allowed_image_source( 'http://' . $host . '/img.jpg' ) );
		$this->assertFalse( DraftCraft_Media::is_allowed_image_source( 'https://127.0.0.1/img.jpg' ) );
		$this->assertFalse( DraftCraft_Media::is_allowed_image_source( 'https://169.254.169.254/latest/meta-data/' ) );
	}

	public function test_media_is_enabled() {
		$this->assertFalse( DraftCraft_Media::is_enabled( array( 'image_provider' => 'none' ) ) );
		$this->assertTrue( DraftCraft_Media::is_enabled( array( 'image_provider' => 'unsplash' ) ) );
		$this->assertTrue( DraftCraft_Media::is_enabled( array( 'image_provider' => 'openrouter' ) ) );
	}

	public function test_register_cron_schedules() {
		$added = draftcraft_register_cron_schedules( array() );
		$this->assertArrayHasKey( 'weekly', $added );
		$this->assertSame( WEEK_IN_SECONDS, $added['weekly']['interval'] );

		$existing = array(
			'weekly' => array(
				'interval' => 123,
				'display'  => 'Custom',
			),
		);
		$kept = draftcraft_register_cron_schedules( $existing );
		$this->assertSame( 123, $kept['weekly']['interval'] );
	}

	public function test_register_cron_timing() {
		$before = time();
		draftcraft_register_cron( 'hourly' );
		$next = wp_next_scheduled( DRAFTCRAFT_CRON_HOOK );
		$this->assertNotFalse( $next, 'hourly cron should be scheduled' );
		$this->assertGreaterThanOrEqual( $before + HOUR_IN_SECONDS - 1, $next );
		$this->assertLessThan( $before + HOUR_IN_SECONDS + 60, $next );

		$same = $next;
		draftcraft_register_cron( 'hourly' );
		$this->assertSame( $same, wp_next_scheduled( DRAFTCRAFT_CRON_HOOK ) );

		draftcraft_clear_cron();
		draftcraft_register_cron( 'not-a-real-schedule' );
		$this->assertFalse( wp_next_scheduled( DRAFTCRAFT_CRON_HOOK ), 'WordPress rejects an unknown recurrence key' );

		$before_week = time();
		draftcraft_register_cron( 'weekly' );
		$week_next = wp_next_scheduled( DRAFTCRAFT_CRON_HOOK );
		$this->assertNotFalse( $week_next, 'custom weekly schedule should be registered' );
		$this->assertGreaterThanOrEqual( $before_week + WEEK_IN_SECONDS - 1, $week_next );

		draftcraft_clear_cron();
		$future = time() + 5000;
		draftcraft_register_cron( 'daily', $future );
		$this->assertSame( $future, wp_next_scheduled( DRAFTCRAFT_CRON_HOOK ) );
	}

	public function test_deactivate_clears_both_hooks() {
		wp_schedule_event( time() + 3600, 'hourly', DRAFTCRAFT_CRON_HOOK );
		wp_schedule_single_event( time() + 3600, DRAFTCRAFT_IMAGE_CRON_HOOK );
		draftcraft_deactivate();
		$this->assertFalse( wp_next_scheduled( DRAFTCRAFT_CRON_HOOK ) );
		$this->assertFalse( wp_next_scheduled( DRAFTCRAFT_IMAGE_CRON_HOOK ) );
	}

	public function test_pipeline_lock_and_missing_credentials() {
		set_transient( 'draftcraft_pipeline_lock', '1', 180 );
		$locked = draftcraft_execute_pipeline();
		$this->assertFalse( $locked['success'] );
		$this->assertStringContainsString( 'already running', $locked['message'] );

		delete_transient( 'draftcraft_pipeline_lock' );
		draftcraft_update_settings(
			array_merge(
				draftcraft_get_settings(),
				array(
					'api_key'        => '',
					'model_dropdown' => '',
				)
			)
		);
		$failed = false;
		add_action(
			'draftcraft_generation_failed',
			static function () use ( &$failed ) {
				$failed = true;
			}
		);
		$result = draftcraft_execute_pipeline();
		$this->assertFalse( $result['success'] );
		$this->assertFalse( get_transient( 'draftcraft_pipeline_lock' ) );
		$this->assertTrue( $failed );
	}
}
