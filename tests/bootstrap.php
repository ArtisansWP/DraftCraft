<?php
/**
 * PHPUnit bootstrap for DraftCraft.
 *
 * @package DraftCraft
 */

$plugin_root = dirname( __DIR__ );

if ( file_exists( $plugin_root . '/vendor/autoload.php' ) ) {
	require_once $plugin_root . '/vendor/autoload.php';
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin.
 *
 * @return void
 */
function draftcraft_tests_manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/draftcraft.php';
}

tests_add_filter( 'muplugins_loaded', 'draftcraft_tests_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

if ( function_exists( 'draftcraft_load_runtime_modules' ) ) {
	draftcraft_load_runtime_modules();
}
