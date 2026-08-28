<?php
/**
 * Plugin Name:       DraftCraft
 * Plugin URI:        https://artisanswp.com/plugin/draftcraft/
 * Description:       Never run out of blog content. DraftCraft uses AI (via your OpenRouter key) to write and schedule high-quality draft posts, with featured images, SEO sync, FAQs, TOC, internal links, and bulk keyword queues.
 * Version:           1.2.3
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            ArtisansWP
 * Author URI:        https://artisanswp.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       draftcraft
 * Domain Path:       /languages
 *
 * @package   DraftCraft
 * @author    ArtisansWP
 * @copyright 2026 ArtisansWP
 * @license   GPL-2.0-or-later
 */

// Exit if accessed directly — security baseline.
defined( 'ABSPATH' ) || exit;

// ============================================================
// Constants
// ============================================================
define( 'DRAFTCRAFT_VERSION', '1.2.3' );
define( 'DRAFTCRAFT_PLUGIN_FILE', __FILE__ );
define( 'DRAFTCRAFT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DRAFTCRAFT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DRAFTCRAFT_OPTION_KEY', 'draftcraft_settings' );
define( 'DRAFTCRAFT_CRON_HOOK', 'draftcraft_cron_generate' );
define( 'DRAFTCRAFT_IMAGE_CRON_HOOK', 'draftcraft_cron_generate_image' );
define( 'DRAFTCRAFT_NONCE_SETTINGS', 'draftcraft_save_settings' );
define( 'DRAFTCRAFT_NONCE_TRIGGER', 'draftcraft_manual_trigger' );
// phpcs:disable PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Plugin uses user-provided OpenRouter API key for custom model routing.
define( 'DRAFTCRAFT_API_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions' );
define( 'DRAFTCRAFT_MODELS_ENDPOINT', 'https://openrouter.ai/api/v1/models' );
// phpcs:enable PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
define( 'DRAFTCRAFT_MODELS_TRANSIENT', 'draftcraft_models_cache' );
define( 'DRAFTCRAFT_IMAGE_MODELS_TRANSIENT', 'draftcraft_image_models_cache' );

// ============================================================
// Core Includes & Bootstrap
// ============================================================
require_once DRAFTCRAFT_PLUGIN_DIR . 'includes/helpers.php';
require_once DRAFTCRAFT_PLUGIN_DIR . 'includes/class-draftcraft-schema.php';
require_once DRAFTCRAFT_PLUGIN_DIR . 'includes/class-draftcraft-toc.php';
require_once DRAFTCRAFT_PLUGIN_DIR . 'includes/class-draftcraft-blocks.php';
require_once DRAFTCRAFT_PLUGIN_DIR . 'includes/class-draftcraft-cron.php';
require_once DRAFTCRAFT_PLUGIN_DIR . 'includes/class-draftcraft-pipeline.php';

// Initialize frontend/core modules.
DraftCraft_Schema::init();
DraftCraft_TOC::init();

// Admin and AJAX modules.
if ( is_admin() || wp_doing_ajax() ) {
	include_once DRAFTCRAFT_PLUGIN_DIR . 'includes/class-draftcraft-admin.php';
}


/**
 * Load modules in admin, cron, or AJAX contexts.
 *
 * @since 1.2.0
 */
function draftcraft_load_runtime_modules(): void {
	static $loaded = false;
	if ( $loaded ) {
		return;
	}

	$loaded = true;

	include_once DRAFTCRAFT_PLUGIN_DIR . 'includes/class-draftcraft-seo.php';
	include_once DRAFTCRAFT_PLUGIN_DIR . 'includes/class-draftcraft-internal-links.php';
	include_once DRAFTCRAFT_PLUGIN_DIR . 'includes/class-draftcraft-bulk.php';
	include_once DRAFTCRAFT_PLUGIN_DIR . 'includes/class-draftcraft-media.php';
}//end draftcraft_load_runtime_modules()


/**
 * Conditionally bootstrap admin/cron modules.
 *
 * @since 1.2.0
 */
function draftcraft_maybe_boot_modules(): void {
	if ( is_admin() || wp_doing_cron() || wp_doing_ajax() ) {
		draftcraft_load_runtime_modules();
		if ( class_exists( 'DraftCraft_Internal_Links', false ) ) {
			add_action( 'transition_post_status', array( 'DraftCraft_Internal_Links', 'maybe_bust_cache' ), 10, 3 );
		}

		if ( is_admin() && class_exists( 'DraftCraft_Bulk', false ) ) {
			DraftCraft_Bulk::init();
			add_action( 'admin_notices', array( 'DraftCraft_Bulk', 'maybe_show_notice' ) );
		}
	}
}//end draftcraft_maybe_boot_modules()


add_action( 'plugins_loaded', 'draftcraft_maybe_boot_modules', 5 );

// Ensure modules exist before pipeline / image cron fire.
add_action( DRAFTCRAFT_CRON_HOOK, 'draftcraft_load_runtime_modules', 1 );
add_action( DRAFTCRAFT_IMAGE_CRON_HOOK, 'draftcraft_load_runtime_modules', 1 );
add_action( 'rest_api_init', 'draftcraft_load_runtime_modules', 1 );

// ============================================================
// Lifecycle Hooks
// ============================================================
register_activation_hook( DRAFTCRAFT_PLUGIN_FILE, 'draftcraft_activate' );
register_deactivation_hook( DRAFTCRAFT_PLUGIN_FILE, 'draftcraft_deactivate' );


/**
 * Fires on plugin activation.
 * Schedules the cron event if settings already exist.
 */
function draftcraft_activate(): void {
	$settings = draftcraft_get_settings();
	if ( ! empty( $settings['schedule'] ) && '1' === $settings['automation_on'] ) {
		draftcraft_register_cron( $settings['schedule'] );
	}

	/*
	 * Fires immediately after the plugin is activated.
	 *
	 * @since 1.1.0
	 * @param array $settings Current plugin settings.
	 */
	do_action( 'draftcraft_activated', $settings );
}//end draftcraft_activate()


/**
 * Fires on plugin deactivation.
 * Clears all scheduled cron events cleanly.
 */
function draftcraft_deactivate(): void {
	draftcraft_clear_cron();
	wp_clear_scheduled_hook( DRAFTCRAFT_IMAGE_CRON_HOOK );

	/*
	 * Fires immediately after the plugin is deactivated.
	 *
	 * @since 1.1.0
	 */
	do_action( 'draftcraft_deactivated' );
}//end draftcraft_deactivate()
