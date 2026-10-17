<?php
/**
 * Tests for DraftCraft_Blocks.
 *
 * @package DraftCraft
 */

/**
 * Class DraftCraft_Test_Blocks
 */
class DraftCraft_Test_Blocks extends WP_UnitTestCase {

	public function test_is_block_markup_and_markdown_heuristic() {
		$this->assertTrue( DraftCraft_Blocks::is_block_markup( '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' ) );
		$this->assertFalse( DraftCraft_Blocks::is_block_markup( '<p>Hi</p>' ) );
		$this->assertTrue( DraftCraft_Blocks::looks_like_markdown( "# Hello\n\nWorld" ) );
		$this->assertFalse( DraftCraft_Blocks::looks_like_markdown( '<h2>Hello</h2><p>World</p>' ) );
		$this->assertTrue( DraftCraft_Blocks::looks_like_markdown( "```php\necho 1;\n```" ) );
		$this->assertTrue( DraftCraft_Blocks::looks_like_markdown( 'This is **bold** text' ) );
	}

	public function test_markdown_to_html() {
		$md   = "# H1\n\n## H2\n\nA paragraph.\n\n- one\n- two\n\n1. a\n2. b\n\n> quote\n\n```js\ncode();\n```";
		$html = DraftCraft_Blocks::markdown_to_html( $md );
		$this->assertStringContainsString( '<h1>', $html );
		$this->assertStringContainsString( '<h2>', $html );
		$this->assertStringContainsString( '<ul>', $html );
		$this->assertStringContainsString( '<ol>', $html );
		$this->assertStringContainsString( '<blockquote>', $html );
		$this->assertStringContainsString( 'class="language-js"', $html );
		$this->assertStringContainsString( '&lt;b&gt;', DraftCraft_Blocks::markdown_to_html( 'Use <b>tags</b>' ) );
	}

	public function test_inline_markdown() {
		$out = DraftCraft_Blocks::inline_markdown( 'A `code` **bold** *em* [t](https://example.com) [bad](ftp://x)' );
		$this->assertStringContainsString( '<code>code</code>', $out );
		$this->assertStringContainsString( '<strong>bold</strong>', $out );
		$this->assertStringContainsString( '<em>em</em>', $out );
		$this->assertStringContainsString( 'href="https://example.com"', $out );
		$this->assertStringNotContainsString( '<a href="ftp://', $out );
	}

	public function test_coalesce_code_paragraphs() {
		$two  = '<p><code>a</code></p><p><code>b</code></p>';
		$one  = '<p><code>only</code></p>';
		$out  = DraftCraft_Blocks::coalesce_code_paragraphs( $two );
		$this->assertStringContainsString( '<pre><code>', $out );
		$this->assertSame( $one, DraftCraft_Blocks::coalesce_code_paragraphs( $one ) );
	}

	public function test_normalize_for_editor_empty_and_existing_blocks() {
		$this->assertSame( '', DraftCraft_Blocks::normalize_for_editor( '  ' ) );
		$existing = '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->';
		$this->assertSame( $existing, DraftCraft_Blocks::normalize_for_editor( $existing ) );
	}

	public function test_normalize_html_to_core_blocks() {
		$html   = '<p>Hello</p><h2>Sub</h2><ul><li>Item</li></ul><pre><code>echo 1;</code></pre>';
		$markup = DraftCraft_Blocks::normalize_for_editor( $html );
		$blocks = array_values( array_filter( parse_blocks( $markup ), static fn( $b ) => ! empty( $b['blockName'] ) ) );
		$names  = wp_list_pluck( $blocks, 'blockName' );
		$this->assertContains( 'core/paragraph', $names );
		$this->assertContains( 'core/heading', $names );
		$this->assertContains( 'core/list', $names );
		$this->assertContains( 'core/code', $names );
		$this->assertNotContains( 'core/preformatted', $names );
		$code = null;
		foreach ( $blocks as $block ) {
			if ( 'core/code' === $block['blockName'] ) {
				$code = $block;
				break;
			}
		}
		$this->assertNotNull( $code );
		$this->assertStringContainsString( 'wp-block-code', $code['innerHTML'] );
	}

	public function test_faq_becomes_group_block() {
		$faq    = DraftCraft_Schema::render_faq_html(
			array(
				array(
					'question' => 'Q?',
					'answer'   => 'A',
				),
			)
		);
		$markup = DraftCraft_Blocks::normalize_for_editor( $faq );
		$blocks = array_values( array_filter( parse_blocks( $markup ), static fn( $b ) => ! empty( $b['blockName'] ) ) );
		$this->assertSame( 'core/group', $blocks[0]['blockName'] );
		$this->assertSame( 'draftcraft-faq', $blocks[0]['attrs']['className'] );

		$inner_names = array();
		foreach ( $blocks[0]['innerBlocks'] as $inner ) {
			if ( ! empty( $inner['blockName'] ) ) {
				$inner_names[] = $inner['blockName'];
				if ( 'core/heading' === $inner['blockName'] && ! empty( $inner['attrs']['className'] ) ) {
					if ( 'draftcraft-faq-q' === $inner['attrs']['className'] ) {
						$this->assertSame( 'draftcraft-faq-q', $inner['attrs']['className'] );
					}
				}
			}
		}
		$this->assertContains( 'core/heading', $inner_names );

		$from_meta = DraftCraft_Blocks::render_faq_blocks(
			array(
				array(
					'question' => 'Q?',
					'answer'   => 'A',
				),
			)
		);
		$this->assertStringContainsString( 'Q?', $from_meta );
		$this->assertSame( '', DraftCraft_Blocks::render_faq_blocks( array() ) );
	}
}
