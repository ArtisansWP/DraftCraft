<?php
/**
 * DraftCraft — Helper Functions & Settings Utilities
 *
 * Core utility functions, settings getters/helpers, and template loaders.
 *
 * @package DraftCraft
 * @since   1.2.1
 */

defined( 'ABSPATH' ) || exit;


/**
 * Debug-gated logger — never writes on production unless WP_DEBUG is on.
 *
 * @since 1.2.0
 * @param string $message Log message.
 */
function draftcraft_log( string $message ): void {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
     // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[DraftCraft] ' . $message );
	}
}//end draftcraft_log()


/**
 * Multibyte-safe string length.
 *
 * @since  1.2.0
 * @param  string $text Text.
 * @return integer
 */
function draftcraft_strlen( string $text ): int {
	return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $text ) : strlen( $text );
}//end draftcraft_strlen()


/**
 * Multibyte-safe substring.
 *
 * @since  1.2.0
 * @param  string  $text   Text.
 * @param  integer $start  Start.
 * @param  integer $length Length.
 * @return string
 */
function draftcraft_substr( string $text, int $start, int $length ): string {
	return function_exists( 'mb_substr' ) ? (string) mb_substr( $text, $start, $length ) : substr( $text, $start, $length );
}//end draftcraft_substr()


/**
 * Robust JSON extraction and recovery from AI model response.
 *
 * Handles markdown code fences, unescaped newlines in JSON strings,
 * leading/trailing commentary, and broken JSON fallbacks.
 *
 * @since  1.2.3
 * @param  string $content_raw Raw text from LLM response.
 * @return array<string,mixed>|null Associative array with at least 'title' and 'content', or null on failure.
 */
function draftcraft_parse_model_json( string $content_raw ): ?array {
	$content_raw = trim( $content_raw );
	if ( '' === $content_raw ) {
		return null;
	}

	// 1. Strip markdown fences if wrapped.
	if ( preg_match( '/```(?:json)?\s*([\s\S]+?)(?:```|$)/i', $content_raw, $m ) ) {
		$stripped = trim( $m[1] );
	} else {
		$stripped = $content_raw;
	}

	// 2. Extract substring from first { to last }.
	$start = strpos( $stripped, '{' );
	$end   = strrpos( $stripped, '}' );
	if ( false !== $start && false !== $end && $end > $start ) {
		$json_str = substr( $stripped, $start, $end - $start + 1 );
	} else {
		$json_str = $stripped;
	}

	// 3. Try direct json_decode.
	$data = json_decode( $json_str, true );
	if ( is_array( $data ) && ! empty( $data['title'] ) && ! empty( $data['content'] ) ) {
		return $data;
	}

	// 4. Try fixing literal unescaped newlines and control characters inside JSON strings.
	$fixed = preg_replace_callback(
		'/"(?:[^"\\\\]|\\\\.)*"/',
		function ( $matches ) {
			return str_replace(
				array( "\r\n", "\r", "\n", "\t", "\x00", "\x08", "\x0B", "\x0C", "\x1F" ),
				array( '\n', '\n', '\n', '\t', '', '', '', '', '' ),
				$matches[0]
			);
		},
		$json_str
	);

	$data = json_decode( $fixed, true );
	if ( is_array( $data ) && ! empty( $data['title'] ) && ! empty( $data['content'] ) ) {
		return $data;
	}

	// 5. Robust regex fallback extraction for title and content.
	$title   = '';
	$content = '';

	if ( preg_match( '/"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/i', $json_str, $tm ) ) {
		$title = stripcslashes( $tm[1] );
	}

	if ( preg_match( '/"content"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/is', $fixed, $cm ) ) {
		$content = stripcslashes( $cm[1] );
	} elseif ( preg_match( '/"content"\s*:\s*"([\s\S]+)/i', $json_str, $cm ) ) {
		// Content was truncated or has unescaped quotes.
		$raw_c   = $cm[1];
		$raw_c   = preg_replace( '/"\s*,\s*"(?:focus_keyword|meta_title|meta_description|faqs)"[\s\S]*$/i', '', $raw_c );
		$raw_c   = preg_replace( '/"\s*\}?\s*$/', '', $raw_c );
		$content = stripcslashes( $raw_c );
	}

	// 6. If title or content still empty, check if output is pure HTML or Markdown.
	if ( empty( $title ) || empty( $content ) ) {
		if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $content_raw, $h1_m ) ) {
			$title   = wp_strip_all_tags( $h1_m[1] );
			$content = preg_replace( '/<h1[^>]*>.*?<\/h1>/is', '', $content_raw, 1 );
		} elseif ( preg_match( '/^#\s+(.+)$/m', $content_raw, $h1_m ) ) {
			$title   = trim( $h1_m[1] );
			$content = preg_replace( '/^#\s+.+$/m', '', $content_raw, 1 );
		}
	}

	if ( ! empty( $title ) && ! empty( $content ) ) {
		$res = array(
			'title'   => trim( $title ),
			'content' => trim( $content ),
		);

		if ( preg_match( '/"focus_keyword"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/i', $json_str, $km ) ) {
			$res['focus_keyword'] = stripcslashes( $km[1] );
		}
		if ( preg_match( '/"meta_title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/i', $json_str, $mtm ) ) {
			$res['meta_title'] = stripcslashes( $mtm[1] );
		}
		if ( preg_match( '/"meta_description"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/i', $json_str, $mdm ) ) {
			$res['meta_description'] = stripcslashes( $mdm[1] );
		}
		if ( preg_match( '/"faqs"\s*:\s*(\[\s*\{[\s\S]*?\}\s*\])/i', $json_str, $fqm ) ) {
			$faqs = json_decode( $fqm[1], true );
			if ( is_array( $faqs ) ) {
				$res['faqs'] = $faqs;
			}
		}

		return $res;
	}

	return null;
}//end draftcraft_parse_model_json()


/**
 * Render a template/view file with an extracted scope.
 *
 * @since 1.2.1
 * @param string $template_name Relative template path without extension inside templates/.
 * @param array  $args          Data arguments to pass into the template scope.
 */
function draftcraft_render_template( string $template_name, array $args = array() ): void {
	if ( str_contains( $template_name, '..' ) ) {
		return;
	}

	$file = DRAFTCRAFT_PLUGIN_DIR . 'templates/' . ltrim( $template_name, '/' ) . '.php';
	if ( file_exists( $file ) ) {
     // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Scoped template variable extraction.
		extract( $args, EXTR_SKIP );
		include $file;
	}
}//end draftcraft_render_template()


/**
 * Compose the generation system prompt in priority layers.
 *
 * User custom style is preserved, but DraftCraft platform rules always
 * come last and explicitly take priority on conflicts.
 *
 * @since  1.2.1
 * @param  string $writing_style  User Content System Prompt (voice/tone).
 * @param  string $context        Category / link candidate context.
 * @param  string $platform_rules Mandatory DraftCraft + feature rules.
 * @return string
 */
function draftcraft_compose_system_prompt( string $writing_style, string $context = '', string $platform_rules = '' ): string {
	$sections   = array();
	$sections[] = "## Writing style\nUse the following as the article's voice, tone, audience, and stylistic preferences. If anything here conflicts with Platform rules, Platform rules win.\n\n" . trim( $writing_style );

	$context = trim( $context );
	if ( '' !== $context ) {
		$sections[] = "## Context\n" . $context;
	}

	$platform_rules = trim( $platform_rules );
	if ( '' !== $platform_rules ) {
		$sections[] = "## Platform rules (mandatory)\nThese requirements always apply. They take priority over Writing style when there is any conflict. Do not ignore, weaken, skip, or reinterpret them.\n\n" . $platform_rules;
	}

	return implode( "\n\n", $sections );
}//end draftcraft_compose_system_prompt()


/**
 * Returns plugin settings merged with sensible defaults.
 *
 * @since  1.0.0
 * @return array<string, mixed>
 */
function draftcraft_get_settings(): array {
	$defaults = array(
		'api_key'                  => '',
		'model_dropdown'           => '',
		'system_prompt'            => '',
		'image_system_prompt'      => '',
		'categories'               => array(),
		'category_order'           => array(),
		'category_rotation'        => '0',
		'post_status'              => 'draft',
		'post_author'              => 0,
		'schedule'                 => 'daily',
		'automation_on'            => '0',
		'post_type'                => 'post',
		// v1.2.0 — SEO / media / linking / FAQ features.
		'seo_sync_enabled'         => '1',
		'image_provider'           => 'none',
		'image_model'              => 'black-forest-labs/flux-1.1-pro',
		'unsplash_access_key'      => '',
		'internal_links_enabled'   => '1',
		'internal_links_method'    => 'hybrid',
		'internal_links_relevance' => 'balanced',
		'faq_schema_enabled'       => '1',
		'toc_enabled'              => '1',
		'toc_scroll_offset'        => 96,
		'bulk_queue_priority'      => '1',
	);

	$saved = get_option( DRAFTCRAFT_OPTION_KEY, null );

	// Fallback/Migration: If new option is empty, import from the old option to prevent data loss.
	if ( null === $saved ) {
		$old_settings = get_option( 'wp_aw_settings', null );
		if ( is_array( $old_settings ) ) {
			update_option( DRAFTCRAFT_OPTION_KEY, $old_settings );
			$saved = $old_settings;

			// Migrate rotation index.
			$old_rotation_index = get_option( 'wp_aw_rotation_index', null );
			if ( null !== $old_rotation_index ) {
				update_option( 'draftcraft_rotation_index', absint( $old_rotation_index ) );
			}
		}
	}

	$merged = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	// DraftCraft is limited to the built-in Posts post type.
	$merged['post_type'] = 'post';

	return $merged;
}//end draftcraft_get_settings()


/**
 * DraftCraft only supports the built-in post type.
 *
 * @since  1.2.1
 * @return string
 */
function draftcraft_get_post_type(): string {
	return 'post';
}//end draftcraft_get_post_type()


/**
 * Primary hierarchical taxonomy for posts (usually category).
 *
 * @since  1.2.1
 * @return string
 */
function draftcraft_get_target_taxonomy(): string {
	$taxonomies = get_object_taxonomies( 'post', 'objects' );
	foreach ( $taxonomies as $tax ) {
		if ( $tax->hierarchical && $tax->public ) {
			return $tax->name;
		}
	}

	return 'category';
}//end draftcraft_get_target_taxonomy()


/**
 * Returns the currently configured model identifier.
 *
 * @since  1.0.0
 * @param  array $settings Plugin settings array.
 * @return string
 */
function draftcraft_get_active_model( array $settings ): string {
	return sanitize_text_field( ( $settings['model_dropdown'] ?? '' ) );
}//end draftcraft_get_active_model()


/**
 * Resolves which category IDs will be assigned to the next post,
 * without advancing the rotation index.
 *
 * @since  1.1.0
 * @param  array $settings Plugin settings array.
 * @return int[] Array of WordPress category term IDs.
 */
function draftcraft_peek_next_categories( array $settings ): array {
	if ( '1' !== ( $settings['category_rotation'] ?? '0' ) ) {
		return array_values(
			array_filter( array_map( 'absint', (array) ( $settings['categories'] ?? array() ) ) )
		);
	}

	$order = draftcraft_resolve_rotation_order( $settings );

	if ( empty( $order ) ) {
		return array();
	}

	$index = ( absint( get_option( 'draftcraft_rotation_index', 0 ) ) % count( $order ) );
	return array( $order[ $index ] );
}//end draftcraft_peek_next_categories()


/**
 * Resolves the category rotation order based on settings.
 *
 * @param  array $settings Plugin settings array.
 * @return int[] Array of WordPress category term IDs in rotation order.
 */
function draftcraft_resolve_rotation_order( array $settings ): array {
	$selected = array_values(
		array_filter( array_map( 'absint', (array) ( $settings['categories'] ?? array() ) ) )
	);

	if ( empty( $selected ) ) {
		return array();
	}

	$order = array_values(
		array_filter(
			array_map( 'absint', (array) ( $settings['category_order'] ?? array() ) ),
			static fn( $id ) => in_array( $id, $selected, true )
		)
	);

	if ( empty( $order ) ) {
		$order = $selected;
	}

	return $order;
}//end draftcraft_resolve_rotation_order()


/**
 * Advances the rotation index cleanly.
 *
 * @since 1.1.0
 * @param array $settings Plugin settings array.
 */
function draftcraft_advance_rotation_index( array $settings ): void {
	if ( '1' !== ( $settings['category_rotation'] ?? '0' ) ) {
		return;
	}

	$order = draftcraft_resolve_rotation_order( $settings );

	if ( empty( $order ) ) {
		return;
	}

	$index      = absint( get_option( 'draftcraft_rotation_index', 0 ) );
	$next_index = ( ( $index + 1 ) % count( $order ) );
	update_option( 'draftcraft_rotation_index', $next_index, false );
}//end draftcraft_advance_rotation_index()


/**
 * Parse and format error messages from remote API responses (OpenRouter, etc.).
 *
 * Extracts the real API error description (e.g. insufficient credits, invalid key, rate limits)
 * so users clearly understand why a request failed in a BYOK environment.
 *
 * @since  1.2.3
 * @param  integer $http_code Response HTTP status code.
 * @param  string  $body      Response body string.
 * @param  string  $provider  Provider label (default 'OpenRouter').
 * @return string Human-friendly error message.
 */
function draftcraft_parse_api_error( int $http_code, string $body, string $provider = 'OpenRouter' ): string {
	$api_msg = '';

	if ( ! empty( $body ) ) {
		$decoded = json_decode( $body, true );
		if ( is_array( $decoded ) ) {
			if ( ! empty( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
				$api_msg = trim( $decoded['error']['message'] );
			} elseif ( ! empty( $decoded['error'] ) && is_string( $decoded['error'] ) ) {
				$api_msg = trim( $decoded['error'] );
			} elseif ( ! empty( $decoded['message'] ) && is_string( $decoded['message'] ) ) {
				$api_msg = trim( $decoded['message'] );
			}
		}
	}

	if ( ! empty( $api_msg ) ) {
		return sprintf( '%s: %s (HTTP %d)', $provider, $api_msg, $http_code );
	}

	// Helpful context fallbacks when API returns no message body.
	// phpcs:disable PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Error message instructions for OpenRouter key/credits.
	switch ( $http_code ) {
		case 401:
			return sprintf(
				/* translators: %s: provider name */
				__( '%s: Unauthorized or invalid API key. Please check your key at openrouter.ai/keys (HTTP 401).', 'draftcraft' ),
				$provider
			);

		case 402:
			return sprintf(
				/* translators: %s: provider name */
				__( '%s: Insufficient credits or payment required. Please top up your balance at openrouter.ai/credits (HTTP 402).', 'draftcraft' ),
				$provider
			);

		case 403:
			return sprintf(
				/* translators: %s: provider name */
				__( '%s: Access forbidden. Your key does not have permission for this model or feature (HTTP 403).', 'draftcraft' ),
				$provider
			);

		case 429:
			return sprintf(
				/* translators: %s: provider name */
				__( '%s: Rate limit reached or credit quota exhausted. Please check your OpenRouter usage (HTTP 429).', 'draftcraft' ),
				$provider
			);

		case 502:
		case 503:
		case 504:
			return sprintf(
				/* translators: 1: provider name, 2: HTTP code */
				__( '%1$s: Service temporarily unavailable or model upstream error (HTTP %2$d). Try again shortly.', 'draftcraft' ),
				$provider,
				$http_code
			);

		default:
			return sprintf(
				/* translators: 1: provider name, 2: HTTP code */
				__( '%1$s returned HTTP %2$d.', 'draftcraft' ),
				$provider,
				$http_code
			);
	}//end switch
	// phpcs:enable PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
}//end draftcraft_parse_api_error()
