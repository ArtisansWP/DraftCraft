<?php
/**
 * Tests for helper functions.
 *
 * @package DraftCraft
 */

/**
 * Class DraftCraft_Test_Helpers
 */
class DraftCraft_Test_Helpers extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		delete_option( DRAFTCRAFT_OPTION_KEY );
		delete_option( 'wp_aw_settings' );
		delete_option( 'wp_aw_rotation_index' );
		delete_option( 'draftcraft_rotation_index' );
	}

	public function test_parse_model_json_empty_returns_null() {
		$this->assertNull( draftcraft_parse_model_json( '' ) );
		$this->assertNull( draftcraft_parse_model_json( "  \n" ) );
	}

	public function test_parse_model_json_valid_object() {
		$raw = wp_json_encode(
			array(
				'title'   => 'Hello',
				'content' => '<p>Body</p>',
			)
		);
		$data = draftcraft_parse_model_json( $raw );
		$this->assertIsArray( $data );
		$this->assertSame( 'Hello', $data['title'] );
		$this->assertSame( '<p>Body</p>', $data['content'] );
	}

	public function test_parse_model_json_strips_fence() {
		$raw  = "```json\n{\"title\":\"Fenced\",\"content\":\"<p>Ok</p>\"}\n```";
		$data = draftcraft_parse_model_json( $raw );
		$this->assertSame( 'Fenced', $data['title'] );
		$this->assertSame( '<p>Ok</p>', $data['content'] );
	}

	public function test_parse_model_json_surrounding_prose() {
		$raw  = "Sure, here you go:\n{\"title\":\"Prose\",\"content\":\"<p>Body</p>\"}\nThanks!";
		$data = draftcraft_parse_model_json( $raw );
		$this->assertSame( 'Prose', $data['title'] );
		$this->assertSame( '<p>Body</p>', $data['content'] );
	}

	public function test_parse_model_json_raw_newline_in_content_only() {
		$raw  = "{\"title\":\"T\",\"content\":\"Line1\nLine2\"}";
		$data = draftcraft_parse_model_json( $raw );
		$this->assertIsArray( $data );
		$this->assertSame( 'T', $data['title'] );
		$this->assertStringContainsString( "Line1", $data['content'] );
		$this->assertStringContainsString( "Line2", $data['content'] );
		$this->assertStringNotContainsString( '\\n', $data['content'] );
	}

	public function test_parse_model_json_missing_title_or_content_returns_null() {
		$this->assertNull( draftcraft_parse_model_json( '{"foo":1}' ) );
		$this->assertNull( draftcraft_parse_model_json( '{"title":"Only"}' ) );
		$this->assertNull( draftcraft_parse_model_json( '{"content":"<p>Only</p>"}' ) );
	}

	public function test_parse_model_json_html_h1_fallback() {
		$raw  = '<h1>From Heading</h1><p>Rest of article</p>';
		$data = draftcraft_parse_model_json( $raw );
		$this->assertSame( 'From Heading', $data['title'] );
		$this->assertStringContainsString( 'Rest of article', $data['content'] );
		$this->assertStringNotContainsString( '<h1>', $data['content'] );
	}

	public function test_parse_model_json_markdown_heading_fallback() {
		$raw  = "# Markdown Title\n\nBody paragraph";
		$data = draftcraft_parse_model_json( $raw );
		$this->assertSame( 'Markdown Title', $data['title'] );
		$this->assertStringContainsString( 'Body paragraph', $data['content'] );
		$this->assertStringNotContainsString( '# Markdown Title', $data['content'] );
	}

	public function test_parse_model_json_copies_seo_and_faq_keys() {
		$raw  = wp_json_encode(
			array(
				'title'            => 'Hello',
				'content'          => '<p>Body</p>',
				'focus_keyword'    => 'router',
				'meta_title'       => 'Best router',
				'meta_description' => 'A guide.',
				'faqs'             => array(
					array(
						'question' => 'Q1?',
						'answer'   => 'A1',
					),
				),
			)
		);
		$data = draftcraft_parse_model_json( $raw );
		$this->assertSame( 'router', $data['focus_keyword'] );
		$this->assertSame( 'Best router', $data['meta_title'] );
		$this->assertSame( 'A guide.', $data['meta_description'] );
		$this->assertIsArray( $data['faqs'] );
		$this->assertSame( 'Q1?', $data['faqs'][0]['question'] );
	}

	public function test_compose_system_prompt_order() {
		$out = draftcraft_compose_system_prompt( '  Voice  ', '  Ctx  ', '  Rules  ' );
		$this->assertStringStartsWith( "## Writing style\n", $out );
		$style_pos    = strpos( $out, '## Writing style' );
		$context_pos  = strpos( $out, '## Context' );
		$platform_pos = strpos( $out, '## Platform rules (mandatory)' );
		$this->assertNotFalse( $style_pos );
		$this->assertNotFalse( $context_pos );
		$this->assertNotFalse( $platform_pos );
		$this->assertLessThan( $context_pos, $style_pos );
		$this->assertLessThan( $platform_pos, $context_pos );
		$this->assertStringContainsString( 'Voice', $out );
		$this->assertStringContainsString( 'Ctx', $out );
		$this->assertStringContainsString( 'Rules', $out );
	}

	public function test_compose_system_prompt_omits_empty_optional_sections() {
		$out = draftcraft_compose_system_prompt( 'Voice', '  ', '' );
		$this->assertStringContainsString( '## Writing style', $out );
		$this->assertStringNotContainsString( '## Context', $out );
		$this->assertStringNotContainsString( '## Platform rules (mandatory)', $out );
	}

	public function test_parse_api_error_prefers_nested_raw_json_message() {
		$body = wp_json_encode(
			array(
				'error' => array(
					'message'  => 'outer',
					'metadata' => array(
						'raw' => wp_json_encode(
							array(
								'error' => array(
									'message' => 'inner credits',
								),
							)
						),
					),
				),
			)
		);
		$msg = draftcraft_parse_api_error( 402, $body, 'OpenRouter' );
		$this->assertStringContainsString( 'inner credits', $msg );
		$this->assertStringContainsString( 'HTTP 402', $msg );
		$this->assertStringContainsString( 'OpenRouter', $msg );
		$this->assertStringNotContainsString( ': outer (', $msg );
	}

	public function test_parse_api_error_falls_back_through_body_shapes() {
		$raw_text = wp_json_encode(
			array(
				'error' => array(
					'metadata' => array(
						'raw' => 'plain raw',
					),
				),
			)
		);
		$this->assertStringContainsString( 'plain raw', draftcraft_parse_api_error( 400, $raw_text ) );

		$message = wp_json_encode( array( 'error' => array( 'message' => 'from message' ) ) );
		$this->assertStringContainsString( 'from message', draftcraft_parse_api_error( 400, $message ) );

		$string_error = wp_json_encode( array( 'error' => 'string err' ) );
		$this->assertStringContainsString( 'string err', draftcraft_parse_api_error( 400, $string_error ) );

		$top = wp_json_encode( array( 'message' => 'top msg' ) );
		$this->assertStringContainsString( 'top msg', draftcraft_parse_api_error( 400, $top ) );
	}

	public function test_parse_api_error_empty_body_status_fallbacks() {
		$this->assertStringContainsString( '401', draftcraft_parse_api_error( 401, '' ) );
		$this->assertStringContainsString( 'invalid API key', draftcraft_parse_api_error( 401, '' ) );
		$this->assertStringContainsString( '402', draftcraft_parse_api_error( 402, '' ) );
		$this->assertStringContainsString( 'credits', draftcraft_parse_api_error( 402, '' ) );
		$this->assertStringContainsString( '403', draftcraft_parse_api_error( 403, '' ) );
		$this->assertStringContainsString( '429', draftcraft_parse_api_error( 429, '' ) );
		$this->assertStringContainsString( 'unavailable', draftcraft_parse_api_error( 502, '' ) );
		$this->assertStringContainsString( 'unavailable', draftcraft_parse_api_error( 503, '' ) );
		$this->assertStringContainsString( 'unavailable', draftcraft_parse_api_error( 504, '' ) );
		$this->assertStringContainsString( 'HTTP 418', draftcraft_parse_api_error( 418, '' ) );
	}

	public function test_settings_defaults_and_forced_post_type() {
		$settings = draftcraft_get_settings();
		$this->assertSame( '', $settings['api_key'] );
		$this->assertSame( 'post', $settings['post_type'] );
		$this->assertSame( 'draft', $settings['post_status'] );

		draftcraft_update_settings( array( 'post_type' => 'page', 'api_key' => 'k' ) );
		$settings = draftcraft_get_settings();
		$this->assertSame( 'post', $settings['post_type'] );
		$this->assertSame( 'k', $settings['api_key'] );
	}

	public function test_settings_migrate_from_legacy_option() {
		update_option( 'wp_aw_settings', array( 'api_key' => 'legacy-key' ) );
		update_option( 'wp_aw_rotation_index', 4 );
		$settings = draftcraft_get_settings();
		$this->assertSame( 'legacy-key', $settings['api_key'] );
		$this->assertSame( 4, (int) get_option( 'draftcraft_rotation_index' ) );
	}

	public function test_empty_saved_settings_do_not_migrate() {
		update_option( DRAFTCRAFT_OPTION_KEY, array() );
		update_option( 'wp_aw_settings', array( 'api_key' => 'should-not-migrate' ) );
		$settings = draftcraft_get_settings();
		$this->assertSame( '', $settings['api_key'] );
	}

	public function test_resolve_rotation_order_filters_and_falls_back() {
		$settings = array(
			'categories'     => array( 1, 2, 'x', 0 ),
			'category_order' => array( 9, 2, 1 ),
		);
		$this->assertSame( array( 2, 1 ), draftcraft_resolve_rotation_order( $settings ) );

		$empty_order = array(
			'categories'     => array( 5, 6 ),
			'category_order' => array( 99 ),
		);
		$this->assertSame( array( 5, 6 ), draftcraft_resolve_rotation_order( $empty_order ) );
	}

	public function test_peek_and_advance_rotation() {
		$off = array(
			'category_rotation' => '0',
			'categories'        => array( 10, 20 ),
		);
		$this->assertSame( array( 10, 20 ), draftcraft_peek_next_categories( $off ) );

		$on = array(
			'category_rotation' => '1',
			'categories'        => array( 10, 20, 30 ),
			'category_order'    => array( 20, 10, 30 ),
		);
		update_option( 'draftcraft_rotation_index', 0 );
		$this->assertSame( array( 20 ), draftcraft_peek_next_categories( $on ) );
		draftcraft_advance_rotation_index( $on );
		$this->assertSame( array( 10 ), draftcraft_peek_next_categories( $on ) );

		update_option( 'draftcraft_rotation_index', 2 );
		draftcraft_advance_rotation_index( $on );
		$this->assertSame( 0, (int) get_option( 'draftcraft_rotation_index' ) );
		$this->assertSame( array( 20 ), draftcraft_peek_next_categories( $on ) );
	}

	public function test_empty_rotation_does_not_divide_by_zero() {
		$settings = array(
			'category_rotation' => '1',
			'categories'        => array(),
		);
		$this->assertSame( array(), draftcraft_peek_next_categories( $settings ) );
		draftcraft_advance_rotation_index( $settings );
		$this->assertSame( 0, (int) get_option( 'draftcraft_rotation_index', 0 ) );
	}

	public function test_advance_does_nothing_when_rotation_off() {
		update_option( 'draftcraft_rotation_index', 7 );
		draftcraft_advance_rotation_index(
			array(
				'category_rotation' => '0',
				'categories'        => array( 1 ),
			)
		);
		$this->assertSame( 7, (int) get_option( 'draftcraft_rotation_index' ) );
	}

	public function test_render_template_rejects_parent_traversal() {
		ob_start();
		draftcraft_render_template( '../draftcraft' );
		$out = ob_get_clean();
		$this->assertSame( '', $out );
	}

	public function test_multibyte_strlen_and_substr() {
		$this->assertSame( 5, draftcraft_strlen( 'café!' ) );
		$this->assertSame( 'é', draftcraft_substr( 'café', 3, 1 ) );
		$this->assertSame( 1, draftcraft_strlen( '🙂' ) );
	}
}
