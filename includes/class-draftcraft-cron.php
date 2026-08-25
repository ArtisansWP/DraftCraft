<?php
/**
 * DraftCraft — Cron Scheduling & Tasks
 *
 * Manages WP-Cron schedule registration, intervals, and deferred task hooks.
 *
 * @package DraftCraft
 * @since   1.2.1
 */

defined( 'ABSPATH' ) || exit;


/**
 * Registers custom cron intervals.
 *
 * @param  array $schedules Existing cron schedules.
 * @return array
 */
function draftcraft_register_cron_schedules( array $schedules ): array {
	if ( ! isset( $schedules['weekly'] ) ) {
		$schedules['weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'draftcraft' ),
		);
	}

	return $schedules;
}//end draftcraft_register_cron_schedules()


add_filter( 'cron_schedules', 'draftcraft_register_cron_schedules' );


/**
 * Returns the available schedule options.
 * Filterable so developers can add custom intervals.
 *
 * @since  1.1.0
 * @return array<string, array{label:string, icon:string, sub:string}>
 */
function draftcraft_get_schedule_options(): array {
	$options = array(
		'hourly'     => array(
			'label' => __( 'Hourly', 'draftcraft' ),
			'icon'  => '⚡',
			'sub'   => __( 'Every hour', 'draftcraft' ),
		),
		'twicedaily' => array(
			'label' => __( 'Twice Daily', 'draftcraft' ),
			'icon'  => '🌓',
			'sub'   => __( 'Every 12 hrs', 'draftcraft' ),
		),
		'daily'      => array(
			'label' => __( 'Daily', 'draftcraft' ),
			'icon'  => '☀️',
			'sub'   => __( 'Once a day', 'draftcraft' ),
		),
		'weekly'     => array(
			'label' => __( 'Weekly', 'draftcraft' ),
			'icon'  => '📅',
			'sub'   => __( 'Once a week', 'draftcraft' ),
		),
	);

	/*
	 * Filter the list of available automation schedule options.
	 *
	 * @since 1.1.0
	 * @param array $options Default schedule options keyed by WP cron interval.
	 */
	return (array) apply_filters( 'draftcraft_schedule_options', $options );
}//end draftcraft_get_schedule_options()


/**
 * Registers the cron event if not already scheduled.
 *
 * First run is always in the future (one full schedule interval) unless a
 * future timestamp is passed. Using time() caused Save Settings to trigger
 * an immediate pipeline run on the following admin request.
 *
 * @since 1.0.0
 * @param string  $schedule  WP cron schedule key.
 * @param integer $timestamp Optional first-run unix timestamp (must be in the future).
 */
function draftcraft_register_cron( string $schedule, int $timestamp = 0 ): void {
	if ( wp_next_scheduled( DRAFTCRAFT_CRON_HOOK ) ) {
		return;
	}

	if ( $timestamp <= time() ) {
		$schedules = wp_get_schedules();
		$interval  = isset( $schedules[ $schedule ]['interval'] ) ? (int) $schedules[ $schedule ]['interval'] : DAY_IN_SECONDS;
		// At least 60s buffer so the current admin request never spawns a run.
		$timestamp = ( time() + max( 60, $interval ) );
	}

	wp_schedule_event( $timestamp, $schedule, DRAFTCRAFT_CRON_HOOK );
}//end draftcraft_register_cron()


/**
 * Removes all scheduled cron events for this plugin.
 *
 * @since 1.0.0
 */
function draftcraft_clear_cron(): void {
	$timestamp = wp_next_scheduled( DRAFTCRAFT_CRON_HOOK );
	if ( ! empty( $timestamp ) ) {
		wp_unschedule_event( $timestamp, DRAFTCRAFT_CRON_HOOK );
	}

	wp_clear_scheduled_hook( DRAFTCRAFT_CRON_HOOK );
}//end draftcraft_clear_cron()


// Hook scheduled cron actions to their respective handlers.
add_action( DRAFTCRAFT_CRON_HOOK, 'draftcraft_execute_pipeline' );
add_action( DRAFTCRAFT_IMAGE_CRON_HOOK, 'draftcraft_cron_generate_image', 10, 3 );
