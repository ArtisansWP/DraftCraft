<?php
/**
 * DraftCraft — Generation Pipeline Core
 *
 * Handles AI prompt construction, OpenRouter API requests, response decoding,
 * post creation, internal linking, SEO sync, and image dispatching.
 *
 * @package DraftCraft
 * @since   1.2.1
 */

defined( 'ABSPATH' ) || exit;


/**
 * Deferred featured-image generation (non-blocking for main pipeline).
 *
 * @since 1.2.0
 * @param integer $post_id Post ID.
 * @param string  $title   Post title.
 * @param string  $keyword Focus keyword.
 */
function draftcraft_cron_generate_image( int $post_id, string $title = '', string $keyword = '' ): void {
	draftcraft_load_runtime_modules();

	if ( $post_id < 1 || ! get_post( $post_id ) || has_post_thumbnail( $post_id ) ) {
		return;
	}

	if ( ! class_exists( 'DraftCraft_Media', false ) ) {
		return;
	}

	$settings = draftcraft_get_settings();
	if ( ! DraftCraft_Media::is_enabled( $settings ) ) {
		return;
	}

	if ( '' === $title ) {
		$title = get_the_title( $post_id );
	}

	if ( '' === $keyword ) {
		$keyword = (string) get_post_meta( $post_id, '_draftcraft_focus_keyword', true );
	}

	DraftCraft_Media::generate_featured_image( $post_id, $title, $keyword, $settings );
}//end draftcraft_cron_generate_image()


/**
 * Main generation pipeline.
 * Calls the OpenRouter API and inserts a post into WordPress.
 *
 * Developer hooks are fired at every key stage so this pipeline
 * can be extended without modifying the plugin source.
 *
 * @since  1.0.0
 * @return array{success: bool, message?: string, post_id?: int, title?: string, edit_url?: string}
 */
function draftcraft_execute_pipeline(): array {
	// Prevent concurrent runs (manual click overlapping cron, or double-click).
	if ( get_transient( 'draftcraft_pipeline_lock' ) ) {
		$msg = __( 'Another generation is already running. Please wait a moment and try again.', 'draftcraft' );
		draftcraft_log( $msg );
		return array(
			'success' => false,
			'message' => $msg,
		);
	}

	set_transient( 'draftcraft_pipeline_lock', '1', ( 3 * MINUTE_IN_SECONDS ) );

	draftcraft_load_runtime_modules();

	$settings = draftcraft_get_settings();
	$api_key  = $settings['api_key'];
	$model    = draftcraft_get_active_model( $settings );

	if ( empty( $api_key ) || empty( $model ) ) {
		delete_transient( 'draftcraft_pipeline_lock' );
		$msg = 'Execution aborted: API key or model not configured.';
		draftcraft_log( $msg );
		do_action( 'draftcraft_generation_failed', $msg, $settings );
		return array(
			'success' => false,
			'message' => $msg,
		);
	}

	/*
	 * Fires before the generation pipeline begins.
	 *
	 * @since 1.1.0
	 * @param array $settings Plugin settings.
	 */
	do_action( 'draftcraft_before_generate', $settings );

	// Bulk Keyword Queue — takes priority over rotation.
	$bulk_row       = null;
	$bulk_index     = null;
	$bulk_prompt    = null;
	$forced_keyword = '';

	if ( class_exists( 'DraftCraft_Bulk', false )
		&& ! empty( $settings['bulk_queue_priority'] )
		&& '1' === $settings['bulk_queue_priority']
	) {
		$bulk_row = DraftCraft_Bulk::peek_next();
		if ( $bulk_row ) {
			$bulk_index     = (int) ( $bulk_row['_queue_index'] ?? -1 );
			$bulk_prompt    = DraftCraft_Bulk::build_prompt_from_row( $bulk_row );
			$forced_keyword = ( $bulk_prompt['focus_keyword'] ?? '' );
		}
	}

	// Target Category Context Extraction.
	if ( $bulk_prompt && ! empty( $bulk_prompt['category_ids'] ) ) {
		$cat_ids = $bulk_prompt['category_ids'];
	} else {
		$cat_ids = draftcraft_peek_next_categories( $settings );
	}

	$category_context = '';

	if ( ! empty( $cat_ids ) ) {
		$target_taxonomy = draftcraft_get_target_taxonomy();

		$cat_info = array();
		foreach ( $cat_ids as $cat_id ) {
			$term = get_term( $cat_id, $target_taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$desc       = ! empty( $term->description ) ? $term->description : 'No description provided.';
				$cat_info[] = sprintf( 'Name: "%s" | Description: "%s"', $term->name, $desc );
			}
		}

		if ( ! empty( $cat_info ) ) {
			$category_context = "\n\nTarget Category Context:\n" . implode( "\n", $cat_info ) . "\n\nCRITICAL: Tailor the topic, style, tone, and focus of this post specifically to match the target category name and description context provided above.";
		}
	}

	// Internal Linking Context (v1.2.0).
	$link_context = '';
	if ( DraftCraft_Internal_Links::is_enabled( $settings ) ) {
		$method = ( $settings['internal_links_method'] ?? 'hybrid' );
		if ( in_array( $method, array( 'llm', 'hybrid' ), true ) ) {
			$candidates   = DraftCraft_Internal_Links::get_link_candidates( draftcraft_get_post_type() );
			$candidates   = DraftCraft_Internal_Links::rank_candidates(
				$candidates,
				array(
					'category_ids'  => $cat_ids,
					'focus_keyword' => $forced_keyword,
					'content'       => '',
				)
			);
			$link_context = DraftCraft_Internal_Links::build_prompt_context( $candidates, $settings );
		}
	}

	// Prompts.
	//
	// Layered so the user's custom voice/style never wipes DraftCraft rules:
	// 1) Writing style  — user Content System Prompt (or default).
	// 2) Context        — category + internal-link candidates.
	// 3) Platform rules — SEO / FAQ / linking / output (always win on conflict).
	$default_system = 'You are a professional blog writer. Write unique, high-quality, SEO-friendly content in clean HTML.';
	$user_style     = trim( (string) ( $settings['system_prompt'] ?? '' ) );
	if ( '' === $user_style ) {
		$user_style = $default_system;
	}

	$context_blocks = array();
	if ( '' !== trim( $category_context ) ) {
		$context_blocks[] = trim( $category_context );
	}

	if ( '' !== trim( $link_context ) ) {
		$context_blocks[] = trim( $link_context );
	}

	$platform_rules   = array();
	$platform_rules[] = 'Output requirements:
- Return a valid JSON object only (no markdown fences, no prose outside JSON).
- Required keys: "title" (string), "content" (raw HTML body).
- "content" must be at least 650 words of substantive article text (count words in text only, excluding tags).
- Use semantic HTML only: <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <blockquote>, <pre><code>.
- Never use Markdown syntax in "content" (no # headings, no **bold**, no - lists, no ``` code fences).
- Use H2/H3 headings, short paragraphs, and actionable detail.
- Do NOT put the post title inside "content" as an H1/H2/H3.
- Prefer the user\'s Writing style for tone and phrasing, but never break these Platform rules.';

	if ( DraftCraft_SEO::is_enabled( $settings ) ) {
		$platform_rules[] = trim( DraftCraft_SEO::get_prompt_instructions() );
	}

	if ( DraftCraft_Schema::is_enabled( $settings ) ) {
		$platform_rules[] = trim( DraftCraft_Schema::get_prompt_instructions() );
	}

	if ( DraftCraft_Internal_Links::is_enabled( $settings ) ) {
		$platform_rules[] = trim( DraftCraft_Internal_Links::get_prompt_instructions( $settings ) );
	}

	if ( $bulk_prompt && ! empty( $bulk_prompt['system_extra'] ) ) {
		$platform_rules[] = trim( (string) $bulk_prompt['system_extra'] );
	}

	$system_prompt = draftcraft_compose_system_prompt(
		$user_style,
		implode( "\n\n", $context_blocks ),
		implode( "\n\n", array_filter( $platform_rules ) )
	);

	/*
	 * Filter the system prompt sent to the AI.
	 *
	 * @since 1.1.0
	 * @param string $prompt   Resolved system prompt.
	 * @param array  $settings Plugin settings.
	 */
	$system_prompt = (string) apply_filters( 'draftcraft_system_prompt', $system_prompt, $settings );

	$default_user = 'Generate a unique, high-quality blog post as JSON. Follow the system message: honor Writing style for voice/tone, and obey Platform rules when anything conflicts. Keys: "title" (string) and "content" (raw valid HTML only — never Markdown, >= 650 words of body text). ';

	if ( DraftCraft_SEO::is_enabled( $settings ) ) {
		$default_user .= 'Also include "focus_keyword" (string), "meta_title" (string, under 60 chars), "meta_description" (string, under 160 chars). ';
	}

	if ( DraftCraft_Schema::is_enabled( $settings ) ) {
		$default_user .= 'Also include "faqs" (array of {question, answer} objects, 3–4 items). ';
	}

	$default_user .= 'Do not include any text outside the JSON object.';

	if ( $bulk_prompt && ! empty( $bulk_prompt['user_extra'] ) ) {
		$default_user .= ' ' . $bulk_prompt['user_extra'];
	}

	/*
	 * Filter the user prompt sent to the AI.
	 *
	 * @since 1.1.0
	 * @param string $prompt   User prompt string.
	 * @param array  $settings Plugin settings.
	 */
	$user_prompt = (string) apply_filters( 'draftcraft_user_prompt', $default_user, $settings );

	// Payload preparation.
	$payload = array(
		'model'           => $model,
		'messages'        => array(
			array(
				'role'    => 'system',
				'content' => $system_prompt,
			),
			array(
				'role'    => 'user',
				'content' => $user_prompt,
			),
		),
		'response_format' => array( 'type' => 'json_object' ),
	);

	/*
	 * Filter the full API request payload before it is sent.
	 *
	 * @since 1.1.0
	 * @param array $payload  API payload array.
	 * @param array $settings Plugin settings.
	 */
	$payload = (array) apply_filters( 'draftcraft_api_payload', $payload, $settings );

	// HTTP Headers preparation.
	$headers = array(
		'Authorization' => 'Bearer ' . $api_key,
		'Content-Type'  => 'application/json',
		'HTTP-Referer'  => get_site_url(),
		'X-Title'       => sanitize_text_field( get_bloginfo( 'name' ) ),
	);

	/*
	 * Filter the HTTP headers sent with the API request.
	 *
	 * @since 1.1.0
	 * @param array $headers  HTTP header key/value pairs.
	 * @param array $settings Plugin settings.
	 */
	$headers = (array) apply_filters( 'draftcraft_api_headers', $headers, $settings );

	/*
	 * Filter the API request timeout in seconds.
	 *
	 * @since 1.1.0
	 * @param int $timeout Timeout in seconds. Default 120.
	 */
	$timeout = absint( apply_filters( 'draftcraft_api_timeout', 120 ) );

	// API Request execution.
	$response = wp_remote_post(
		DRAFTCRAFT_API_ENDPOINT,
		array(
			'timeout'     => $timeout,
			'headers'     => $headers,
			'body'        => wp_json_encode( $payload ),
			'data_format' => 'body',
		)
	);

	if ( is_wp_error( $response ) ) {
		delete_transient( 'draftcraft_pipeline_lock' );
		$raw = $response->get_error_message();
		// cURL 28 / http_request_failed timeouts are common with long generations.
		if ( false !== stripos( $raw, 'timed out' ) || false !== stripos( $raw, 'timeout' ) ) {
			$msg = __( 'OpenRouter timed out before the post finished generating. Try again, or switch to a faster content model.', 'draftcraft' );
		} else {
			$msg = $raw;
		}

		draftcraft_log( 'HTTP error: ' . $raw );
		if ( null !== $bulk_index && $bulk_index >= 0 ) {
			DraftCraft_Bulk::fail_item( $bulk_index, $msg );
		}

		do_action( 'draftcraft_generation_failed', $msg, $settings );
		return array(
			'success' => false,
			'message' => $msg,
		);
	}//end if

	$http_code = (int) wp_remote_retrieve_response_code( $response );
	$body      = wp_remote_retrieve_body( $response );

	if ( 200 !== $http_code ) {
		delete_transient( 'draftcraft_pipeline_lock' );
		$msg = function_exists( 'draftcraft_parse_api_error' ) ? draftcraft_parse_api_error( $http_code, $body ) : sprintf( 'OpenRouter returned HTTP %d.', $http_code );
		draftcraft_log( $msg . ' Body: ' . substr( $body, 0, 300 ) );
		if ( null !== $bulk_index && $bulk_index >= 0 ) {
			DraftCraft_Bulk::fail_item( $bulk_index, $msg );
		}

		do_action( 'draftcraft_generation_failed', $msg, $settings );
		return array(
			'success' => false,
			'message' => $msg,
		);
	}

	// Parse API Response.
	$decoded = json_decode( $body, true );
	if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $decoded['choices'][0]['message']['content'] ) ) {
		delete_transient( 'draftcraft_pipeline_lock' );
		$msg = 'Failed to decode API response.';
		draftcraft_log( $msg );
		if ( null !== $bulk_index && $bulk_index >= 0 ) {
			DraftCraft_Bulk::fail_item( $bulk_index, $msg );
		}

		do_action( 'draftcraft_generation_failed', $msg, $settings );
		return array(
			'success' => false,
			'message' => $msg,
		);
	}

	/*
	 * Fires after a successful raw API response is received.
	 *
	 * @since 1.1.0
	 * @param array $decoded  Decoded API response array.
	 * @param array $settings Plugin settings.
	 */
	do_action( 'draftcraft_api_response_received', $decoded, $settings );

	$content_raw = $decoded['choices'][0]['message']['content'];

	// Parse Model JSON.
	$post_data = json_decode( $content_raw, true );

	// Fallback: strip markdown code fences if model wrapped JSON in them.
	if ( JSON_ERROR_NONE !== json_last_error() || empty( $post_data['title'] ) || empty( $post_data['content'] ) ) {
		if ( preg_match( '/```(?:json)?\s*([\s\S]+?)\s*```/i', $content_raw, $m ) ) {
			$post_data = json_decode( $m[1], true );
		}
	}

	if ( JSON_ERROR_NONE !== json_last_error() || empty( $post_data['title'] ) || empty( $post_data['content'] ) ) {
		delete_transient( 'draftcraft_pipeline_lock' );
		$msg = 'Invalid JSON structure returned by model.';
		draftcraft_log( $msg . ' Raw: ' . substr( $content_raw, 0, 500 ) );
		if ( null !== $bulk_index && $bulk_index >= 0 ) {
			DraftCraft_Bulk::fail_item( $bulk_index, $msg );
		}

		do_action( 'draftcraft_generation_failed', $msg, $settings );
		return array(
			'success' => false,
			'message' => $msg,
		);
	}

	// Post Content Processing (v1.2.0).
	$title   = sanitize_text_field( $post_data['title'] );
	$content = wp_kses_post( $post_data['content'] );

	// Resolve focus keyword early so content can be SEO-normalized.
	$seo_fields = DraftCraft_SEO::sanitize_seo_fields( $post_data );
	if ( empty( $seo_fields['focus_keyword'] ) && $forced_keyword ) {
		$seo_fields['focus_keyword'] = sanitize_text_field( $forced_keyword );
		$post_data['focus_keyword']  = $seo_fields['focus_keyword'];
	}

	// Deeper Rank Math polish (keyword placement, density, sentiment).
	if ( ! empty( $seo_fields['focus_keyword'] ) || '' !== $title ) {
		$optimized = DraftCraft_SEO::optimize_for_rank_math(
			$content,
			( $seo_fields['focus_keyword'] ?? '' ),
			$title
		);
		$content   = $optimized['content'];
		$title     = $optimized['title'];
	}

	if ( ! empty( $seo_fields['meta_title'] ) ) {
		$seo_fields['meta_title'] = DraftCraft_SEO::ensure_title_sentiment( $seo_fields['meta_title'] );
	}

	// Smart internal linking (ranked by category + topical overlap).
	$content = DraftCraft_Internal_Links::process_content(
		$content,
		$settings,
		0,
		array(
			'category_ids'  => $cat_ids,
			'focus_keyword' => ( $seo_fields['focus_keyword'] ?? $forced_keyword ),
			'content'       => $content,
		)
	);

	// Auto Table of Contents from H2/H3 headings (FAQ excluded, kept for end).
	$content = DraftCraft_TOC::process_content( $content, $settings );

	// Guarantee FAQ HTML exists at the end (from content or faqs JSON).
	if ( DraftCraft_Schema::is_enabled( $settings ) ) {
		$split = DraftCraft_TOC::split_faq_block( $content );
		$faq   = $split['faq'];
		if ( '' === $faq ) {
			$faq = DraftCraft_Schema::render_faq_html( DraftCraft_Schema::sanitize_faqs( $post_data ) );
		}

		$content = rtrim( $split['content'] ) . ( $faq ? "\n\n" . $faq : '' );
	}

	// Convert HTML/Markdown into native Gutenberg blocks so posts edit cleanly.
	if ( class_exists( 'DraftCraft_Blocks' ) ) {
		$content = DraftCraft_Blocks::normalize_for_editor( $content );
	}

	$post_name = DraftCraft_SEO::build_short_slug( $title, ( $seo_fields['focus_keyword'] ?? '' ) );

	$assigned_author = 0;
	if ( ! empty( $settings['post_author'] ) ) {
		$author_id = absint( $settings['post_author'] );
		if ( $author_id > 0 && get_userdata( $author_id ) ) {
			$assigned_author = $author_id;
		}
	}

	if ( ! $assigned_author ) {
		$current_user_id = get_current_user_id();
		if ( $current_user_id ) {
			$assigned_author = $current_user_id;
		} else {
			$admins          = get_users(
				array(
					'role'    => 'administrator',
					'number'  => 1,
					'fields'  => 'ID',
					'orderby' => 'ID',
				)
			);
			$assigned_author = ! empty( $admins ) ? (int) $admins[0] : 1;
		}
	}

	$post_args = array(
		'post_title'    => wp_slash( $title ),
		'post_name'     => $post_name,
		'post_content'  => wp_slash( $content ),
		'post_status'   => $settings['post_status'],
		'post_type'     => draftcraft_get_post_type(),
		'post_author'   => $assigned_author,
		'post_category' => $cat_ids,
	);

	/*
	 * Filter the arguments passed to wp_insert_post().
	 *
	 * @since 1.1.0
	 * @param array $post_args  wp_insert_post() argument array.
	 * @param array $post_data  Raw parsed data from the AI (title, content).
	 * @param array $settings   Plugin settings.
	 */
	$post_args = (array) apply_filters( 'draftcraft_post_args', $post_args, $post_data, $settings );

	$post_id = wp_insert_post( $post_args, true );

	if ( is_wp_error( $post_id ) ) {
		delete_transient( 'draftcraft_pipeline_lock' );
		$msg = $post_id->get_error_message();
		draftcraft_log( 'wp_insert_post error: ' . $msg );
		if ( null !== $bulk_index && $bulk_index >= 0 ) {
			DraftCraft_Bulk::fail_item( $bulk_index, $msg );
		}

		do_action( 'draftcraft_generation_failed', $msg, $settings );
		return array(
			'success' => false,
			'message' => $msg,
		);
	}

	update_post_meta( $post_id, '_draftcraft_generated', '1' );
	update_post_meta( $post_id, '_draftcraft_version', DRAFTCRAFT_VERSION );

	// Assign terms for the Posts taxonomy only.
	if ( ! empty( $cat_ids ) ) {
		wp_set_object_terms( $post_id, array_map( 'intval', $cat_ids ), draftcraft_get_target_taxonomy() );
	}

	// SEO Sync (v1.2.0).
	DraftCraft_SEO::sync_to_plugin( $post_id, $seo_fields, $settings );

	// FAQ Schema (v1.2.0).
	DraftCraft_Schema::save_faqs( $post_id, $post_data, $settings, $content );

	// Featured Image — deferred, non-blocking.
	$keyword_for_image = ! empty( $seo_fields['focus_keyword'] ) ? $seo_fields['focus_keyword'] : $forced_keyword;
	if ( class_exists( 'DraftCraft_Media', false ) && DraftCraft_Media::is_enabled( $settings ) ) {
		// Schedule separately so WP-Cron / AJAX are not blocked by image APIs.
		if ( ! wp_next_scheduled( DRAFTCRAFT_IMAGE_CRON_HOOK, array( $post_id, $title, $keyword_for_image ) ) ) {
			wp_schedule_single_event( ( time() + 15 ), DRAFTCRAFT_IMAGE_CRON_HOOK, array( $post_id, $title, $keyword_for_image ) );
		}
	}

	// Bulk queue advance OR category rotation.
	if ( null !== $bulk_index && $bulk_index >= 0 ) {
		DraftCraft_Bulk::complete_item( $bulk_index, $post_id );
	} else {
		draftcraft_advance_rotation_index( $settings );
	}

	draftcraft_log(
		sprintf(
			'Post created. ID: %d | Title: %s | Categories: %s | Keyword: %s',
			$post_id,
			$title,
			implode( ', ', $cat_ids ),
			$keyword_for_image
		)
	);

	/*
	 * Fires after a post has been successfully created by the pipeline.
	 *
	 * @since 1.1.0
	 * @param int   $post_id  Newly created post ID.
	 * @param array $settings Plugin settings at time of generation.
	 */
	do_action( 'draftcraft_post_created', $post_id, $settings );

	delete_transient( 'draftcraft_pipeline_lock' );

	$has_image_task = ( class_exists( 'DraftCraft_Media', false ) && DraftCraft_Media::is_enabled( $settings ) );

	return array(
		'success'        => true,
		'post_id'        => $post_id,
		'title'          => $title,
		'edit_url'       => get_edit_post_link( $post_id, 'raw' ),
		'has_image_task' => $has_image_task,
		'image_provider' => sanitize_key( ( $settings['image_provider'] ?? 'none' ) ),
		'keyword'        => $keyword_for_image,
	);
}//end draftcraft_execute_pipeline()
