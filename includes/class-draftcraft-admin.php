<?php
/**
 * DraftCraft — Admin UI & Settings Controller
 *
 * Handles admin menus, asset enqueueing, settings saving, metaboxes,
 * post-list filters, category description generation, and AJAX endpoints.
 *
 * @package DraftCraft
 * @since   1.2.1
 */

defined( 'ABSPATH' ) || exit;

// ============================================================.
// Admin Menu.
// ============================================================.
add_action( 'admin_menu', 'draftcraft_register_admin_menu' );


/**
 * Register top-level DraftCraft admin menu.
 *
 * @since 1.0.0
 */
function draftcraft_register_admin_menu(): void {
	add_menu_page(
		__( 'DraftCraft', 'draftcraft' ),
		__( 'DraftCraft', 'draftcraft' ),
		'manage_options',
		'draftcraft',
		'draftcraft_render_settings_page',
		'dashicons-media-document',
		58
	);
}//end draftcraft_register_admin_menu()


// ============================================================.
// Enqueue Admin Assets.
// ============================================================.
add_action( 'admin_enqueue_scripts', 'draftcraft_enqueue_assets' );


/**
 * Enqueue styles and scripts on the DraftCraft admin settings page.
 *
 * @since 1.0.0
 * @param string $hook Admin page hook.
 */
function draftcraft_enqueue_assets( string $hook ): void {
	if ( 'toplevel_page_draftcraft' !== $hook ) {
		return;
	}

	wp_enqueue_style(
		'draftcraft-admin',
		DRAFTCRAFT_PLUGIN_URL . 'assets/admin.css',
		array(),
		DRAFTCRAFT_VERSION
	);

	wp_enqueue_script(
		'draftcraft-admin',
		DRAFTCRAFT_PLUGIN_URL . 'assets/admin.js',
		array(
			'jquery',
			'jquery-ui-sortable',
		),
		DRAFTCRAFT_VERSION,
		true
	);

	$cached_models       = get_transient( DRAFTCRAFT_MODELS_TRANSIENT );
	$cached_image_models = get_transient( DRAFTCRAFT_IMAGE_MODELS_TRANSIENT );
	$settings_inline     = draftcraft_get_settings();

	wp_localize_script(
		'draftcraft-admin',
		'draftCraftData',
		array(
			'ajaxUrl'            => esc_url( admin_url( 'admin-ajax.php' ) ),
			'nonceTrigger'       => wp_create_nonce( DRAFTCRAFT_NONCE_TRIGGER ),
			'nonceStatus'        => wp_create_nonce( 'draftcraft_pipeline_status' ),
			'nonceImageGenerate' => wp_create_nonce( 'draftcraft_generate_image_now' ),
			'pipelineBusy'       => (bool) get_transient( 'draftcraft_pipeline_lock' ),
			'fetchNonce'         => wp_create_nonce( 'draftcraft_fetch_models' ),
			'nonceCsvImport'     => wp_create_nonce( 'draftcraft_import_csv' ),
			'cachedModels'       => ! empty( $cached_models ) ? $cached_models : array(),
			'cachedImageModels'  => ! empty( $cached_image_models ) ? $cached_image_models : array(),
			'savedModel'         => draftcraft_get_active_model( $settings_inline ),
			'savedImageModel'    => sanitize_text_field( ( $settings_inline['image_model'] ?? '' ) ),
			'imageProvider'      => sanitize_key( ( $settings_inline['image_provider'] ?? 'none' ) ),
			'strings'            => array(
				'generating'          => __( 'Generating post…', 'draftcraft' ),
				'success'             => __( 'Post created successfully!', 'draftcraft' ),
				'error'               => __( 'An error occurred. Please try again.', 'draftcraft' ),
				'fetching'            => __( 'Refreshing models…', 'draftcraft' ),
				'modelsLoaded'        => __( 'Models refreshed!', 'draftcraft' ),
				'modelsFailed'        => __( 'Could not fetch models. Try again later.', 'draftcraft' ),
				'btnFetch'            => __( 'Fetch Models', 'draftcraft' ),
				'btnRefresh'          => __( 'Refresh Models', 'draftcraft' ),
				'cachedNotice'        => __( 'Loaded from cache · Click Refresh to update', 'draftcraft' ),
				'editPost'            => __( 'View / Edit post →', 'draftcraft' ),
				'noModels'            => __( '— No models available —', 'draftcraft' ),
				'timeout'             => __( 'Request timed out. OpenRouter may be slow — try again or use a faster model.', 'draftcraft' ),
				'btnGenerate'         => __( 'Generate Post Now', 'draftcraft' ),
				'alreadyRunning'      => __( 'A generation is already in progress. Please wait…', 'draftcraft' ),
				'csvImporting'        => __( 'Importing keywords…', 'draftcraft' ),
				'csvNoFile'           => __( 'Please choose a CSV file first.', 'draftcraft' ),
				'csvImportBtn'        => __( 'Import CSV', 'draftcraft' ),
				/* translators: 1: pending queue count, 2: total queue count. */
				'queueStats'          => __( 'Queue: %1$d pending / %2$d total.', 'draftcraft' ),
				'saveChanges'         => __( 'Save Changes', 'draftcraft' ),
				'unsavedNotice'       => __( 'You have unsaved changes. Click Save Changes to apply.', 'draftcraft' ),
				'saveHelp'            => __( 'Apply changes across all panels.', 'draftcraft' ),
				'leaveWarning'        => __( 'You have unsaved changes in DraftCraft settings. Are you sure you want to leave?', 'draftcraft' ),
				'stepContentTitle'    => __( 'Step 1/2: Generating Article & SEO Metadata…', 'draftcraft' ),
				'stepContentDesc'     => __( 'OpenRouter AI is generating title, content, TOC, and schema…', 'draftcraft' ),
				'stepContentDone'     => __( '✓ Article Drafted: ', 'draftcraft' ),
				'stepContentDoneDesc' => __( 'Post content, SEO, and schema saved to WordPress.', 'draftcraft' ),
				'stepImageTitle'      => __( 'Step 2/2: Generating Featured Image…', 'draftcraft' ),
				'stepImagePending'    => __( 'Will generate and attach once article is drafted.', 'draftcraft' ),
				'stepImageActive'     => __( 'Step 2/2: Generating & Attaching Featured Image…', 'draftcraft' ),
				'stepImageConnecting' => __( 'Connecting to ', 'draftcraft' ),
				'stepImageDone'       => __( '✓ Featured image attached to Media Library!', 'draftcraft' ),
				'stepImageDoneDesc'   => __( 'Featured image set as post thumbnail.', 'draftcraft' ),
				'stepImageWarn'       => __( 'Featured image could not be generated (Post content is saved).', 'draftcraft' ),
			),
		)
	);
}//end draftcraft_enqueue_assets()


// ============================================================.
// WP Admin Posts List Custom Column & Filters.
// ============================================================.
add_filter( 'manage_post_posts_columns', 'draftcraft_add_posts_column' );


/**
 * Add DraftCraft column header to post list table.
 *
 * @since 1.0.0
 * @param array $columns Column list.
 * @return array
 */
function draftcraft_add_posts_column( array $columns ): array {
	$columns['draftcraft_generated'] = __( 'DraftCraft', 'draftcraft' );
	return $columns;
}//end draftcraft_add_posts_column()


add_action( 'manage_post_posts_custom_column', 'draftcraft_render_posts_column', 10, 2 );


/**
 * Render custom DraftCraft badge column content on post list table.
 *
 * @since 1.0.0
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 */
function draftcraft_render_posts_column( string $column, int $post_id ): void {
	if ( 'draftcraft_generated' === $column ) {
		$generated = get_post_meta( $post_id, '_draftcraft_generated', true );
		if ( '1' === $generated ) {
			echo '<span class="draftcraft-post-badge" style="background:#e3f2fd; color:#0d47a1; padding:3px 8px; border-radius:4px; font-weight:600; font-size:11px; display:inline-flex; align-items:center; gap:4px;">';
			echo '<span class="dashicons dashicons-schedule" style="font-size:14px; width:14px; height:14px; line-height:1; color:#0d47a1; margin:0;"></span> ';
			echo esc_html__( 'Generated', 'draftcraft' );
			echo '</span>';
		} else {
			echo '<span style="color:#aaa;">—</span>';
		}
	}
}//end draftcraft_render_posts_column()


add_action( 'restrict_manage_posts', 'draftcraft_restrict_manage_posts_filter' );


/**
 * Add DraftCraft generation filter dropdown to Posts list screen.
 *
 * @since 1.0.0
 * @param string $post_type Post type slug.
 */
function draftcraft_restrict_manage_posts_filter( string $post_type ): void {
	if ( 'post' !== $post_type ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin list-table filter dropdown (read-only GET).
	$selected = isset( $_GET['draftcraft_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['draftcraft_filter'] ) ) : '';
	?>
	<select name="draftcraft_filter" id="draftcraft_filter">
		<option value=""><?php esc_html_e( 'All Sources', 'draftcraft' ); ?></option>
		<option value="1" <?php selected( $selected, '1' ); ?>><?php esc_html_e( 'DraftCraft Generated', 'draftcraft' ); ?></option>
		<option value="0" <?php selected( $selected, '0' ); ?>><?php esc_html_e( 'Manual / Others', 'draftcraft' ); ?></option>
	</select>
	<?php
}//end draftcraft_restrict_manage_posts_filter()


add_filter( 'parse_query', 'draftcraft_parse_query_filter' );


/**
 * Filter WP_Query based on the DraftCraft generation dropdown selection.
 *
 * @since 1.0.0
 * @param WP_Query $query Main query instance.
 */
function draftcraft_parse_query_filter( $query ): void {
	global $pagenow;
	if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin list-table filter (read-only GET).
	$get_post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	// Ensure we are filtering the main 'post' query.
	if ( 'post' !== $query->get( 'post_type' ) && ( empty( $query->get( 'post_type' ) ) && 'post' !== $get_post_type ) ) {
		return;
	}

	if ( ! isset( $_GET['draftcraft_filter'] ) || '' === $_GET['draftcraft_filter'] ) {
		return;
	}

	$filter = sanitize_text_field( wp_unslash( $_GET['draftcraft_filter'] ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$meta_query = (array) $query->get( 'meta_query' );

	if ( '1' === $filter ) {
		$meta_query[] = array(
			'key'     => '_draftcraft_generated',
			'value'   => '1',
			'compare' => '=',
		);
	} elseif ( '0' === $filter ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => '_draftcraft_generated',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_draftcraft_generated',
				'value'   => '1',
				'compare' => '!=',
			),
		);
	}

	$query->set( 'meta_query', $meta_query );
}//end draftcraft_parse_query_filter()


// ============================================================.
// WP Admin Post Edit Screen - AI Assistant Meta Box.
// ============================================================.
add_action( 'add_meta_boxes', 'draftcraft_register_editor_meta_box' );


/**
 * Register DraftCraft AI Assistant metabox on post edit screen.
 *
 * @since 1.2.1
 */
function draftcraft_register_editor_meta_box(): void {
	global $post;
	if ( ! $post ) {
		return;
	}

	$generated = get_post_meta( $post->ID, '_draftcraft_generated', true );
	if ( '1' === $generated ) {
		add_meta_box(
			'draftcraft_ai_assistant_normal',
			__( 'DraftCraft AI Assistant', 'draftcraft' ),
			'draftcraft_render_editor_meta_box',
			get_post_type( $post ),
			'normal',
			'high'
		);
	}
}//end draftcraft_register_editor_meta_box()


/**
 * Render DraftCraft AI Assistant metabox content.
 *
 * @since 1.2.1
 * @param WP_Post $post Current post object.
 */
function draftcraft_render_editor_meta_box( $post ): void {
	draftcraft_render_template( 'admin/editor-metabox', array( 'post' => $post ) );
}//end draftcraft_render_editor_meta_box()


add_action( 'admin_enqueue_scripts', 'draftcraft_enqueue_editor_assets' );


/**
 * Enqueue the AI Post Assistant script on the post edit screen.
 *
 * Only loaded for DraftCraft-generated posts to keep the admin lean.
 *
 * @since 1.2.1
 * @param string $hook Current admin page hook.
 */
function draftcraft_enqueue_editor_assets( string $hook ): void {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	global $post;
	if ( ! $post || '1' !== get_post_meta( $post->ID, '_draftcraft_generated', true ) ) {
		return;
	}

	wp_enqueue_script(
		'draftcraft-editor',
		DRAFTCRAFT_PLUGIN_URL . 'assets/editor.js',
		array( 'jquery' ),
		DRAFTCRAFT_VERSION,
		true
	);

	wp_localize_script(
		'draftcraft-editor',
		'draftCraftEditorData',
		array(
			'postId'  => absint( $post->ID ),
			'nonce'   => wp_create_nonce( 'draftcraft_ai_edit' ),
			'strings' => array(
				'noInstruction' => __( 'Please enter some instructions for the AI.', 'draftcraft' ),
				'applying'      => __( 'Applying changes…', 'draftcraft' ),
				'working'       => __( 'AI is rewriting… Please wait. This can take up to a minute.', 'draftcraft' ),
				'success'       => __( 'AI changes applied successfully!', 'draftcraft' ),
				'error'         => __( 'An error occurred.', 'draftcraft' ),
				'serverError'   => __( 'Could not contact server.', 'draftcraft' ),
				'btnLabel'      => __( 'Apply AI Changes', 'draftcraft' ),
			),
		)
	);
}//end draftcraft_enqueue_editor_assets()


add_action( 'wp_ajax_draftcraft_ai_edit_post', 'draftcraft_ajax_ai_edit_post' );


/**
 * Handle AJAX request to edit post content using AI instructions.
 *
 * @since 1.2.1
 */
function draftcraft_ajax_ai_edit_post(): void {
	check_ajax_referer( 'draftcraft_ai_edit', 'nonce' );

	$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	$instruction = isset( $_POST['instruction'] ) ? sanitize_textarea_field( wp_unslash( $_POST['instruction'] ) ) : '';

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions to edit this post.', 'draftcraft' ) ), 403 );
	}

	// Only allow AI edits on DraftCraft-generated posts.
	if ( '1' !== get_post_meta( $post_id, '_draftcraft_generated', true ) ) {
		wp_send_json_error( array( 'message' => __( 'This post was not generated by DraftCraft.', 'draftcraft' ) ), 403 );
	}

	if ( empty( $instruction ) ) {
		wp_send_json_error( array( 'message' => __( 'Instruction is empty.', 'draftcraft' ) ) );
	}

	// Cap instruction length to prevent abuse / oversized payloads.
	if ( draftcraft_strlen( $instruction ) > 2000 ) {
		wp_send_json_error( array( 'message' => __( 'Instruction is too long (max 2000 characters).', 'draftcraft' ) ) );
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		wp_send_json_error( array( 'message' => __( 'Post not found.', 'draftcraft' ) ) );
	}

	$settings = draftcraft_get_settings();
	$api_key  = $settings['api_key'];
	$model    = draftcraft_get_active_model( $settings );

	if ( empty( $api_key ) || empty( $model ) ) {
		wp_send_json_error( array( 'message' => __( 'Please configure your API key and model first.', 'draftcraft' ) ) );
	}

	// Call OpenRouter to rewrite the post.
	$system_prompt = "You are a professional editor. You must rewrite the blog post title and content based on the user's instructions.
Respond ONLY with a JSON object containing the keys 'title' and 'content'.
Do NOT include the post title inside the 'content' field as a heading (do not start the content with an H1, H2, or H3 containing the title). The 'content' field must only contain the body copy of the post.
\"content\" must be raw valid HTML only (use <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <pre><code>). Never use Markdown (no ##, **, or ``` fences).
Do not return any other text, markdown wrapper, or explanation.
Format:
{
  \"title\": \"updated title\",
  \"content\": \"updated HTML content\"
}";

	$user_prompt = sprintf(
		"Original Title: %s\n\nOriginal Content:\n%s\n\nEditing Instructions: %s\n\nProvide the updated title and content in JSON format.",
		$post->post_title,
		$post->post_content,
		$instruction
	);

	$body = array(
		'model'    => $model,
		'messages' => array(
			array(
				'role'    => 'system',
				'content' => $system_prompt,
			),
			array(
				'role'    => 'user',
				'content' => $user_prompt,
			),
		),
	);

	$response = wp_remote_post(
		DRAFTCRAFT_API_ENDPOINT,
		array(
			'timeout' => 45,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		)
	);

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => $response->get_error_message() ) );
	}

	$http_code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $http_code ) {
		$err_body = wp_remote_retrieve_body( $response );
		$err_msg  = function_exists( 'draftcraft_parse_api_error' ) ? draftcraft_parse_api_error( $http_code, $err_body ) : sprintf( 'OpenRouter API returned HTTP %d.', $http_code );
		wp_send_json_error( array( 'message' => $err_msg ) );
	}

	$res_body = wp_remote_retrieve_body( $response );
	$decoded  = json_decode( $res_body, true );
	$content  = ( $decoded['choices'][0]['message']['content'] ?? '' );

	// Parse JSON from response.
	// Clean potential markdown wrapping like ```json.
	$content = preg_replace( '/^```(?:json)?\s*/i', '', trim( $content ) );
	$content = preg_replace( '/\s*```$/', '', $content );

	$parsed = json_decode( $content, true );
	if ( JSON_ERROR_NONE !== json_last_error() || empty( $parsed['title'] ) || empty( $parsed['content'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Could not parse updated content from AI response. Ensure instructions are valid.', 'draftcraft' ) ) );
	}

	// Update post title and content.
	$edited_content = wp_kses_post( $parsed['content'] );
	if ( class_exists( 'DraftCraft_Blocks' ) ) {
		$edited_content = DraftCraft_Blocks::normalize_for_editor( $edited_content );
	}

	$update_result = wp_update_post(
		array(
			'ID'           => $post_id,
			'post_title'   => wp_slash( sanitize_text_field( $parsed['title'] ) ),
			'post_content' => wp_slash( $edited_content ),
		),
		true
	);

	if ( is_wp_error( $update_result ) ) {
		wp_send_json_error( array( 'message' => $update_result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'title'   => sanitize_text_field( $parsed['title'] ),
			'content' => $edited_content,
			'message' => __( 'AI changes applied successfully!', 'draftcraft' ),
		)
	);
}//end draftcraft_ajax_ai_edit_post()


// ============================================================.
// Term Edit Screen — AI Category Description Generator.
// ============================================================.
add_action( 'admin_enqueue_scripts', 'draftcraft_enqueue_term_ai_assets' );


/**
 * Enqueue AI description UI on taxonomy term edit screens only.
 *
 * Note: Only term.php (edit existing term). Not edit-tags.php (list / add new),
 * where Rank Math / layout can place the button in the wrong spot.
 *
 * @since 1.2.1
 * @param string $hook Current admin page hook.
 */
function draftcraft_enqueue_term_ai_assets( string $hook ): void {
	// Edit existing term only — not the Categories list / Add New screen.
	if ( 'term.php' !== $hook ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || empty( $screen->taxonomy ) ) {
		return;
	}

	$taxonomy = sanitize_key( $screen->taxonomy );

	$tax_obj = get_taxonomy( $taxonomy );
	if ( ! $tax_obj || ! $tax_obj->hierarchical || ! current_user_can( $tax_obj->cap->edit_terms ) ) {
		return;
	}

	// Only taxonomies attached to the Posts post type.
	if ( empty( $tax_obj->object_type ) || ! in_array( 'post', (array) $tax_obj->object_type, true ) ) {
		return;
	}

	add_action( 'admin_footer', 'draftcraft_print_term_ai_footer' );
}//end draftcraft_enqueue_term_ai_assets()


/**
 * Print the Generate with DraftCraft button + script on term edit screens.
 *
 * Compatible with Rank Math, which replaces #description with
 * #rank_math_description_editor via wp_editor().
 *
 * @since 1.2.1
 */
function draftcraft_print_term_ai_footer(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || empty( $screen->taxonomy ) || 'term' !== $screen->base ) {
		return;
	}

	wp_enqueue_script(
		'draftcraft-term-ai',
		DRAFTCRAFT_PLUGIN_URL . 'assets/term-ai.js',
		array( 'jquery' ),
		DRAFTCRAFT_VERSION,
		true
	);

	wp_localize_script(
		'draftcraft-term-ai',
		'draftCraftTermData',
		array(
			'ajaxUrl' => esc_url( admin_url( 'admin-ajax.php' ) ),
			'nonce'   => wp_create_nonce( 'draftcraft_generate_term_desc' ),
			'strings' => array(
				'generating' => __( 'Generating description…', 'draftcraft' ),
				'success'    => __( 'Description generated. Review and save the term.', 'draftcraft' ),
				'error'      => __( 'Could not generate description.', 'draftcraft' ),
				'needName'   => __( 'Enter a name first.', 'draftcraft' ),
				'btnLabel'   => __( 'Generate with DraftCraft', 'draftcraft' ),
				'confirm'    => __( 'Replace the current description with an AI-generated one?', 'draftcraft' ),
			),
		)
	);
}//end draftcraft_print_term_ai_footer()


add_action( 'wp_ajax_draftcraft_generate_term_desc', 'draftcraft_ajax_generate_term_desc' );


/**
 * Handle AJAX request to generate taxonomy term description from name and slug.
 *
 * @since 1.2.1
 */
function draftcraft_ajax_generate_term_desc(): void {
	check_ajax_referer( 'draftcraft_generate_term_desc', 'nonce' );

	$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$slug     = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
	$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : 'category';

	if ( '' === $name ) {
		wp_send_json_error( array( 'message' => __( 'Term name is required.', 'draftcraft' ) ) );
	}

	$tax_obj = get_taxonomy( $taxonomy );
	if ( ! $tax_obj || ! current_user_can( $tax_obj->cap->edit_terms ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'draftcraft' ) ), 403 );
	}

	if ( empty( $tax_obj->object_type ) || ! in_array( 'post', (array) $tax_obj->object_type, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Only Posts taxonomies are supported.', 'draftcraft' ) ), 400 );
	}

	$settings = draftcraft_get_settings();
	$api_key  = $settings['api_key'];
	$model    = draftcraft_get_active_model( $settings );

	if ( empty( $api_key ) || empty( $model ) ) {
		wp_send_json_error( array( 'message' => __( 'Configure your OpenRouter API key and content model in DraftCraft first.', 'draftcraft' ) ) );
	}

	if ( '' === $slug ) {
		$slug = sanitize_title( $name );
	}

	$tax_label = ! empty( $tax_obj->labels->singular_name ) ? $tax_obj->labels->singular_name : __( 'Category', 'draftcraft' );

	$system_prompt = 'You write concise category descriptions for a WordPress blog. Describe only what kinds of posts and topics belong in this category. Do not mention any plugin, tool, brand name, or content-generation instructions. Do not tell writers how to write (no tone/style directives). Respond with plain text only — no JSON, no markdown, no quotes, no title heading. Length: 2–3 sentences (about 30–60 words).';

	$user_prompt = sprintf(
		"Write a short description for this WordPress %1\$s explaining what kinds of posts it contains.\n\nName: %2\$s\nSlug: %3\$s",
		$tax_label,
		$name,
		$slug
	);

	/*
	 * Filter the system prompt for AI term description generation.
	 *
	 * @since 1.2.1
	 * @param string $system_prompt System prompt.
	 * @param string $name          Term name.
	 * @param string $slug          Term slug.
	 * @param string $taxonomy      Taxonomy slug.
	 */
	$system_prompt = (string) apply_filters( 'draftcraft_term_desc_system_prompt', $system_prompt, $name, $slug, $taxonomy );

	/*
	 * Filter the user prompt for AI term description generation.
	 *
	 * @since 1.2.1
	 * @param string $user_prompt User prompt.
	 * @param string $name        Term name.
	 * @param string $slug        Term slug.
	 * @param string $taxonomy    Taxonomy slug.
	 */
	$user_prompt = (string) apply_filters( 'draftcraft_term_desc_user_prompt', $user_prompt, $name, $slug, $taxonomy );

	$response = wp_remote_post(
		DRAFTCRAFT_API_ENDPOINT,
		array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
				'HTTP-Referer'  => home_url( '/' ),
				'X-Title'       => sanitize_text_field( get_bloginfo( 'name' ) ),
			),
			'body'    => wp_json_encode(
				array(
					'model'       => $model,
					'messages'    => array(
						array(
							'role'    => 'system',
							'content' => $system_prompt,
						),
						array(
							'role'    => 'user',
							'content' => $user_prompt,
						),
					),
					'temperature' => 0.6,
				)
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => $response->get_error_message() ) );
	}

	$http_code = (int) wp_remote_retrieve_response_code( $response );
	$res_body  = wp_remote_retrieve_body( $response );

	if ( 200 !== $http_code ) {
		$err_msg = function_exists( 'draftcraft_parse_api_error' ) ? draftcraft_parse_api_error( $http_code, $res_body ) : sprintf( 'OpenRouter API returned HTTP %d.', $http_code );
		wp_send_json_error( array( 'message' => $err_msg ) );
	}

	$decoded = json_decode( $res_body, true );
	$content = trim( (string) ( $decoded['choices'][0]['message']['content'] ?? '' ) );

	// Strip accidental markdown fences / wrapping quotes.
	$content = preg_replace( '/^```(?:\w+)?\s*/i', '', $content );
	$content = preg_replace( '/\s*```$/', '', $content );
	$content = trim( $content, " \t\n\r\0\x0B\"'" );

	if ( '' === $content ) {
		wp_send_json_error( array( 'message' => __( 'Empty response from AI.', 'draftcraft' ) ) );
	}

	// Keep descriptions focused for DraftCraft prompt context.
	if ( function_exists( 'draftcraft_strlen' ) && draftcraft_strlen( $content ) > 800 ) {
		$content = draftcraft_substr( $content, 0, 797 ) . '…';
	} elseif ( mb_strlen( $content ) > 800 ) {
		$content = mb_substr( $content, 0, 797 ) . '…';
	}

	$description = sanitize_textarea_field( $content );

	/*
	 * Filter the AI-generated term description before returning to the editor.
	 *
	 * @since 1.2.1
	 * @param string $description Generated description.
	 * @param string $name        Term name.
	 * @param string $slug        Term slug.
	 * @param string $taxonomy    Taxonomy slug.
	 */
	$description = (string) apply_filters( 'draftcraft_term_description', $description, $name, $slug, $taxonomy );

	wp_send_json_success( array( 'description' => $description ) );
}//end draftcraft_ajax_generate_term_desc()


// ============================================================.
// Settings Save Handler.
// ============================================================.
add_action( 'admin_init', 'draftcraft_handle_settings_save' );


/**
 * Handle settings form submission and save to WordPress options.
 *
 * @since 1.0.0
 */
function draftcraft_handle_settings_save(): void {
	if ( ! isset( $_POST['draftcraft_save'] ) ) {
		return;
	}

	check_admin_referer( DRAFTCRAFT_NONCE_SETTINGS, 'draftcraft_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage these settings.', 'draftcraft' ) );
	}

	$old_settings = draftcraft_get_settings();

	// API Key.
	// If the masked placeholder is submitted unchanged, retain the stored key.
	$raw_key = sanitize_text_field( wp_unslash( ( $_POST['draftcraft_api_key'] ?? '' ) ) );
	$api_key = ( str_repeat( '•', 16 ) === $raw_key ) ? $old_settings['api_key'] : $raw_key;

	// Schedule validation.
	$allowed_schedules = array_keys( draftcraft_get_schedule_options() );
	$schedule          = sanitize_key( ( $_POST['draftcraft_schedule'] ?? 'daily' ) );
	if ( ! in_array( $schedule, $allowed_schedules, true ) ) {
		$schedule = 'daily';
	}

	// Post status validation.
	$post_status = sanitize_key( ( $_POST['draftcraft_post_status'] ?? 'draft' ) );
	if ( ! in_array( $post_status, array( 'draft', 'publish' ), true ) ) {
		$post_status = 'draft';
	}

	// Unsplash key (obfuscate like API key).
	$raw_unsplash = sanitize_text_field( wp_unslash( ( $_POST['draftcraft_unsplash_access_key'] ?? '' ) ) );
	$unsplash_key = ( str_repeat( '•', 16 ) === $raw_unsplash ) ? ( $old_settings['unsplash_access_key'] ?? '' ) : $raw_unsplash;

	// Image provider validation.
	$image_provider = sanitize_key( ( $_POST['draftcraft_image_provider'] ?? 'none' ) );
	if ( ! in_array( $image_provider, array( 'none', 'openrouter', 'unsplash' ), true ) ) {
		$image_provider = 'none';
	}

	// Internal links method.
	$links_method = sanitize_key( ( $_POST['draftcraft_internal_links_method'] ?? 'hybrid' ) );
	if ( ! in_array( $links_method, array( 'llm', 'local', 'hybrid' ), true ) ) {
		$links_method = 'hybrid';
	}

	$links_relevance = sanitize_key( ( $_POST['draftcraft_internal_links_relevance'] ?? 'balanced' ) );
	if ( ! in_array( $links_relevance, array( 'strict', 'balanced', 'loose' ), true ) ) {
		$links_relevance = 'balanced';
	}

	$submitted_categories = array_map( 'absint', (array) ( $_POST['draftcraft_categories'] ?? array() ) );

	$category_rotation        = isset( $_POST['draftcraft_category_rotation'] ) ? '1' : '0';
	$internal_links_method    = $links_method;
	$internal_links_relevance = $links_relevance;
	$image_system_prompt      = sanitize_textarea_field( wp_unslash( ( $_POST['draftcraft_image_system_prompt'] ?? '' ) ) );
	$saved_image_provider     = $image_provider;
	$saved_image_model        = sanitize_text_field( wp_unslash( ( $_POST['draftcraft_image_model'] ?? 'black-forest-labs/flux-1.1-pro' ) ) );
	$saved_unsplash_key       = $unsplash_key;
	$bulk_queue_priority      = isset( $_POST['draftcraft_bulk_queue_priority'] ) ? '1' : '0';

	$new_settings = array(
		'api_key'                  => $api_key,
		'model_dropdown'           => sanitize_text_field( wp_unslash( ( $_POST['draftcraft_model_dropdown'] ?? '' ) ) ),
		'system_prompt'            => sanitize_textarea_field( wp_unslash( ( $_POST['draftcraft_system_prompt'] ?? '' ) ) ),
		'image_system_prompt'      => $image_system_prompt,
		'categories'               => $submitted_categories,
		'category_order'           => array_map( 'absint', (array) ( $_POST['draftcraft_category_order'] ?? array() ) ),
		'category_rotation'        => $category_rotation,
		'post_status'              => $post_status,
		'post_author'              => isset( $_POST['draftcraft_post_author'] ) ? absint( $_POST['draftcraft_post_author'] ) : 0,
		'post_type'                => 'post',
		'schedule'                 => $schedule,
		'automation_on'            => isset( $_POST['draftcraft_automation_on'] ) ? '1' : '0',
		'seo_sync_enabled'         => isset( $_POST['draftcraft_seo_sync_enabled'] ) ? '1' : '0',
		'faq_schema_enabled'       => isset( $_POST['draftcraft_faq_schema_enabled'] ) ? '1' : '0',
		'toc_enabled'              => isset( $_POST['draftcraft_toc_enabled'] ) ? '1' : '0',
		'toc_scroll_offset'        => isset( $_POST['draftcraft_toc_scroll_offset'] ) ? max( 0, min( 400, absint( $_POST['draftcraft_toc_scroll_offset'] ) ) ) : 96,
		'internal_links_enabled'   => isset( $_POST['draftcraft_internal_links_enabled'] ) ? '1' : '0',
		'internal_links_method'    => $internal_links_method,
		'internal_links_relevance' => $internal_links_relevance,
		'image_provider'           => $saved_image_provider,
		'image_model'              => $saved_image_model,
		'unsplash_access_key'      => $saved_unsplash_key,
		'bulk_queue_priority'      => $bulk_queue_priority,
	);

	// Filter category_order so it only contains checked category IDs.
	$new_settings['category_order'] = array_values( array_intersect( $new_settings['category_order'], $new_settings['categories'] ) );

	// Add any checked categories that weren't in order array (just in case).
	foreach ( $new_settings['categories'] as $cat_id ) {
		if ( ! in_array( $cat_id, $new_settings['category_order'], true ) ) {
			$new_settings['category_order'][] = $cat_id;
		}
	}

	update_option( DRAFTCRAFT_OPTION_KEY, $new_settings );

	// Reset rotation index when the category order changes.
	$old_order = array_map( 'absint', (array) ( $old_settings['category_order'] ?? array() ) );
	$new_order = $new_settings['category_order'];
	if ( $old_order !== $new_order || '1' !== $new_settings['category_rotation'] ) {
		update_option( 'draftcraft_rotation_index', 0 );
	}

	// Re-schedule cron without firing a run on this page load.
	// Previously used time() which made WP-Cron execute the pipeline immediately after Save.
	$previous_next = wp_next_scheduled( DRAFTCRAFT_CRON_HOOK );
	$same_schedule = ( $old_settings['schedule'] ?? '' ) === $new_settings['schedule'];
	$was_on        = ( $old_settings['automation_on'] ?? '0' ) === '1';

	draftcraft_clear_cron();
	if ( '1' === $new_settings['automation_on'] ) {
		$reuse_timestamp = ( $was_on && $same_schedule && $previous_next && $previous_next > time() ) ? (int) $previous_next : 0;
		draftcraft_register_cron( $new_settings['schedule'], $reuse_timestamp );
	}

	/*
	 * Fires after plugin settings are saved.
	 *
	 * @since 1.1.0
	 * @param array $new_settings Newly saved settings.
	 * @param array $old_settings Settings before save.
	 */
	do_action( 'draftcraft_settings_saved', $new_settings, $old_settings );

	// Redirect to prevent form re-submission on refresh, preserving active tab.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'api';
	wp_safe_redirect(
		add_query_arg(
			array(
				'page'             => 'draftcraft',
				'tab'              => $active_tab,
				'draftcraft_saved' => '1',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}//end draftcraft_handle_settings_save()


// Admin notice after redirect.
add_action( 'admin_notices', 'draftcraft_maybe_show_saved_notice' );


/**
 * Display settings saved notice after redirect.
 *
 * @since 1.0.0
 */
function draftcraft_maybe_show_saved_notice(): void {
	$screen = get_current_screen();
	if ( ! $screen || 'toplevel_page_draftcraft' !== $screen->id ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag after settings redirect.
	$saved = isset( $_GET['draftcraft_saved'] ) ? sanitize_text_field( wp_unslash( $_GET['draftcraft_saved'] ) ) : '';
	if ( '1' !== $saved ) {
		return;
	}

	echo '<div class="notice notice-success is-dismissible draftcraft-notice"><p><strong>' . esc_html__( 'DraftCraft', 'draftcraft' ) . '</strong> — ' . esc_html__( 'Settings saved successfully.', 'draftcraft' ) . '</p></div>';
}//end draftcraft_maybe_show_saved_notice()


// ============================================================.
// AJAX: Manual Trigger & Live Image Generation.
// ============================================================.
add_action( 'wp_ajax_draftcraft_manual_trigger', 'draftcraft_ajax_manual_trigger' );
add_action( 'wp_ajax_draftcraft_pipeline_status', 'draftcraft_ajax_pipeline_status' );
add_action( 'wp_ajax_draftcraft_generate_image_now', 'draftcraft_ajax_generate_image_now' );


/**
 * Whether the generation pipeline lock is currently held.
 *
 * @since 1.2.1
 */
function draftcraft_ajax_pipeline_status(): void {
	check_ajax_referer( 'draftcraft_pipeline_status', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'draftcraft' ) ), 403 );
	}

	wp_send_json_success(
		array(
			'busy' => (bool) get_transient( 'draftcraft_pipeline_lock' ),
		)
	);
}//end draftcraft_ajax_pipeline_status()


/**
 * Handles the "Generate Test Post Now" AJAX request.
 *
 * @since 1.0.0
 */
function draftcraft_ajax_manual_trigger(): void {
	check_ajax_referer( DRAFTCRAFT_NONCE_TRIGGER, 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'draftcraft' ) ), 403 );
	}

	$result = draftcraft_execute_pipeline();

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		// Use HTTP 200 with success:false so admin.js can read the message.
		// HTTP 500 would send jQuery into its error handler and hide the real reason.
		wp_send_json_error(
			array(
				'message' => ( $result['message'] ?? __( 'Generation failed.', 'draftcraft' ) ),
			)
		);
	}
}//end draftcraft_ajax_manual_trigger()


/**
 * AJAX: Generate and attach featured image immediately for a post.
 * Used by the Quick Generate wizard for live real-time feedback.
 *
 * @since 1.2.1
 */
function draftcraft_ajax_generate_image_now(): void {
	check_ajax_referer( 'draftcraft_generate_image_now', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'draftcraft' ) ), 403 );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( $post_id < 1 || ! get_post( $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post ID.', 'draftcraft' ) ) );
	}

	if ( has_post_thumbnail( $post_id ) ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		wp_send_json_success(
			array(
				'message'       => __( 'Featured image already exists.', 'draftcraft' ),
				'attachment_id' => $thumb_id,
				'thumbnail_url' => wp_get_attachment_image_url( $thumb_id, 'thumbnail' ),
			)
		);
	}

	draftcraft_load_runtime_modules();

	if ( ! class_exists( 'DraftCraft_Media' ) ) {
		wp_send_json_error( array( 'message' => __( 'Media module is unavailable.', 'draftcraft' ) ) );
	}

	$settings = draftcraft_get_settings();
	if ( ! DraftCraft_Media::is_enabled( $settings ) ) {
		wp_send_json_error( array( 'message' => __( 'Featured image generation is disabled in settings.', 'draftcraft' ) ) );
	}

	$title   = get_the_title( $post_id );
	$keyword = (string) get_post_meta( $post_id, '_draftcraft_focus_keyword', true );

	$attachment_id = DraftCraft_Media::generate_featured_image( $post_id, $title, $keyword, $settings );

	// Clear single cron event if scheduled.
	wp_clear_scheduled_hook( DRAFTCRAFT_IMAGE_CRON_HOOK, array( $post_id, $title, $keyword ) );

	if ( $attachment_id > 0 ) {
		wp_send_json_success(
			array(
				'message'       => __( 'Featured image successfully generated and attached.', 'draftcraft' ),
				'attachment_id' => $attachment_id,
				'thumbnail_url' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
			)
		);
	} else {
		wp_send_json_error(
			array(
				'message' => __( 'Could not generate image. Post content was saved.', 'draftcraft' ),
			)
		);
	}
}//end draftcraft_ajax_generate_image_now()


// ============================================================.
// AJAX: Fetch / Refresh Models.
// ============================================================.
add_action( 'wp_ajax_draftcraft_fetch_models', 'draftcraft_ajax_fetch_models' );


/**
 * Fetches available models fresh from the OpenRouter API,
 * stores them in a 24-hour transient, and returns the list.
 *
 * Pass type=image to fetch only image-generation models
 * (output_modalities=image). Defaults to text/content models.
 *
 * Always hits the remote endpoint — this is the explicit "Refresh" action.
 *
 * @since 1.0.0
 */
function draftcraft_ajax_fetch_models(): void {
	check_ajax_referer( 'draftcraft_fetch_models', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'draftcraft' ) ), 403 );
	}

	$type          = sanitize_key( wp_unslash( ( $_POST['type'] ?? 'text' ) ) );
	$is_image      = ( 'image' === $type );
	$transient_key = $is_image ? DRAFTCRAFT_IMAGE_MODELS_TRANSIENT : DRAFTCRAFT_MODELS_TRANSIENT;
	$endpoint      = DRAFTCRAFT_MODELS_ENDPOINT;
	if ( $is_image ) {
		$endpoint = add_query_arg( 'output_modalities', 'image', $endpoint );
	}

	$settings = draftcraft_get_settings();
	$api_key  = $settings['api_key'];

	$headers = array( 'Content-Type' => 'application/json' );
	if ( ! empty( $api_key ) ) {
		$headers['Authorization'] = 'Bearer ' . $api_key;
	}

	$response = wp_remote_get(
		$endpoint,
		array(
			'timeout' => 20,
			'headers' => $headers,
		)
	);

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => $response->get_error_message() ), 502 );
	}

	$http_code = (int) wp_remote_retrieve_response_code( $response );
	$body      = wp_remote_retrieve_body( $response );
	if ( 200 !== $http_code ) {
		$err_msg = function_exists( 'draftcraft_parse_api_error' ) ? draftcraft_parse_api_error( $http_code, $body ) : sprintf( 'Remote API returned HTTP %d.', $http_code );
		wp_send_json_error( array( 'message' => $err_msg ), 502 );
	}

	$decoded = json_decode( $body, true );

	if ( JSON_ERROR_NONE !== json_last_error() || empty( $decoded['data'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Could not parse models response.', 'draftcraft' ) ), 500 );
	}

	$models = array();
	foreach ( $decoded['data'] as $m ) {
		$id = sanitize_text_field( ( $m['id'] ?? '' ) );
		if ( '' === $id ) {
			continue;
		}

		// Extra safety: when fetching image models, require image in output_modalities.
		if ( $is_image ) {
			$output_modalities = ( $m['architecture']['output_modalities'] ?? array() );
			if ( ! is_array( $output_modalities ) || ! in_array( 'image', $output_modalities, true ) ) {
				continue;
			}
		}

		$name = sanitize_text_field( ( $m['name'] ?? $id ) );

		$pricing         = ( $m['pricing'] ?? array() );
		$prompt_cost     = ( floatval( ( $pricing['prompt'] ?? '0' ) ) * 1000000 );
		$completion_cost = ( floatval( ( $pricing['completion'] ?? '0' ) ) * 1000000 );

		$models[] = array(
			'id'              => $id,
			'name'            => $name,
			'prompt_cost'     => $prompt_cost,
			'completion_cost' => $completion_cost,
		);
	}//end foreach

	// Sort alphabetically by original name.
	usort( $models, static fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );

	// Append pricing format to display names.
	foreach ( $models as &$model ) {
		if ( 0.0 === $model['prompt_cost'] && 0.0 === $model['completion_cost'] ) {
			$pricing_text = __( 'Free', 'draftcraft' );
		} else {
			$p_cost = $model['prompt_cost'];
			$c_cost = $model['completion_cost'];

			$p_str = ( $p_cost >= 0.01 ) ? number_format( $p_cost, 2 ) : rtrim( sprintf( '%.4f', $p_cost ), '0' );
			$c_str = ( $c_cost >= 0.01 ) ? number_format( $c_cost, 2 ) : rtrim( sprintf( '%.4f', $c_cost ), '0' );

			$p_str = rtrim( $p_str, '.' );
			$c_str = rtrim( $c_str, '.' );

			$pricing_text = sprintf( '$%s / $%s per 1M tokens', $p_str, $c_str );
		}

		$model['name'] = sprintf( '%s (%s)', $model['name'], $pricing_text );
		unset( $model['prompt_cost'], $model['completion_cost'] );
	}

	unset( $model );

	/*
	 * Filter the list of models fetched from OpenRouter.
	 *
	 * @since 1.1.0
	 * @param array  $models Array of model objects with 'id' and 'name' keys.
	 * @param string $type   Model list type: 'text' or 'image'.
	 */
	$models = (array) apply_filters( 'draftcraft_models_list', $models, $is_image ? 'image' : 'text' );

	set_transient( $transient_key, $models, DAY_IN_SECONDS );

	wp_send_json_success(
		array(
			'models' => $models,
			'type'   => $is_image ? 'image' : 'text',
		)
	);
}//end draftcraft_ajax_fetch_models()


// ============================================================.
// Admin Settings Page Renderer.
// ============================================================.


/**
 * Render the main DraftCraft settings page and tab panels.
 *
 * @since 1.0.0
 */
function draftcraft_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings        = draftcraft_get_settings();
	$target_taxonomy = draftcraft_get_target_taxonomy();

	$categories = get_terms(
		array(
			'taxonomy'   => $target_taxonomy,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $categories ) ) {
		$categories = array();
	}

	$next_cron  = wp_next_scheduled( DRAFTCRAFT_CRON_HOOK );
	$masked_key = ! empty( $settings['api_key'] ) ? str_repeat( '•', 16 ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Settings page tab switcher (read-only GET).
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'api';
	$tabs       = array(
		'api'      => array(
			'label' => __( 'API & Model', 'draftcraft' ),
			'icon'  => 'dashicons-lock',
		),
		'content'  => array(
			'label' => __( 'Content', 'draftcraft' ),
			'icon'  => 'dashicons-text-page',
		),
		'seo'      => array(
			'label' => __( 'SEO', 'draftcraft' ),
			'icon'  => 'dashicons-chart-area',
		),
		'schedule' => array(
			'label' => __( 'Automation', 'draftcraft' ),
			'icon'  => 'dashicons-clock',
		),
		'drafts'   => array(
			'label' => __( 'AI Drafts Queue', 'draftcraft' ),
			'icon'  => 'dashicons-list-view',
		),
	);

	if ( ! array_key_exists( $active_tab, $tabs ) ) {
		$active_tab = 'api';
	}

	// Rotation preview for sidebar.
	$rot_term = null;
	if ( '1' === $settings['category_rotation'] && ! empty( $settings['categories'] ) ) {
		$selected  = array_values( array_filter( array_map( 'absint', (array) $settings['categories'] ) ) );
		$rot_order = array_values(
			array_filter(
				array_map( 'absint', (array) ( $settings['category_order'] ?? array() ) ),
				static fn( $id ) => in_array( $id, $selected, true )
			)
		);
		if ( empty( $rot_order ) ) {
			$rot_order = $selected;
		}

		$rot_index = ( absint( get_option( 'draftcraft_rotation_index', 0 ) ) % count( $rot_order ) );
		$rot_term  = get_term( $rot_order[ $rot_index ], $target_taxonomy );
	}

	draftcraft_render_template(
		'admin/settings-page',
		array(
			'settings'        => $settings,
			'tabs'            => $tabs,
			'active_tab'      => $active_tab,
			'masked_key'      => $masked_key,
			'categories'      => $categories,
			'target_taxonomy' => $target_taxonomy,
			'next_cron'       => $next_cron,
			'rot_term'        => $rot_term,
		)
	);
}//end draftcraft_render_settings_page()

