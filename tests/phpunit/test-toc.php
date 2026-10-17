<?php
/**
 * Tests for DraftCraft_TOC.
 *
 * @package DraftCraft
 */

/**
 * Class DraftCraft_Test_TOC
 */
class DraftCraft_Test_TOC extends WP_UnitTestCase {

	public function test_toc_label_strips_leading_numerals() {
		$this->assertSame( 'Secure Your Router', DraftCraft_TOC::toc_label( '1. Secure Your Router' ) );
		$this->assertSame( 'Title', DraftCraft_TOC::toc_label( '2) Title' ) );
		$this->assertSame( 'Title', DraftCraft_TOC::toc_label( '3: Title' ) );
		$this->assertSame( 'No Number', DraftCraft_TOC::toc_label( 'No Number' ) );
	}

	public function test_is_faq_heading() {
		$this->assertTrue( DraftCraft_TOC::is_faq_heading( 'Frequently Asked Questions' ) );
		$this->assertTrue( DraftCraft_TOC::is_faq_heading( 'frequently asked questions' ) );
		$this->assertTrue( DraftCraft_TOC::is_faq_heading( 'Anything', ' class="draftcraft-faq-q"' ) );
		$this->assertFalse( DraftCraft_TOC::is_faq_heading( 'FAQ tips' ) );
	}

	public function test_split_faq_block() {
		$html = '<p>Body</p><section class="draftcraft-faq"><div><p>Inner</p></div></section>';
		$split = DraftCraft_TOC::split_faq_block( $html );
		$this->assertSame( '<p>Body</p>', $split['content'] );
		$this->assertStringContainsString( 'draftcraft-faq', $split['faq'] );

		$item = '<p>Body</p><div class="draftcraft-faq-item">not wrapper</div>';
		$no   = DraftCraft_TOC::split_faq_block( $item );
		$this->assertSame( $item, $no['content'] );
		$this->assertSame( '', $no['faq'] );

		$fallback = '<p>Body</p><h2>Frequently Asked Questions</h2><p>Q</p>';
		$fb       = DraftCraft_TOC::split_faq_block( $fallback );
		$this->assertSame( '<p>Body</p>', $fb['content'] );
		$this->assertStringContainsString( 'Frequently Asked Questions', $fb['faq'] );
	}

	public function test_process_content_passthrough_and_single_heading() {
		$html = '<p>Intro.</p><h2>Only One</h2><p>More.</p>';
		$this->assertSame( $html, DraftCraft_TOC::process_content( $html, array( 'toc_enabled' => '0' ) ) );
		$this->assertSame( '', DraftCraft_TOC::process_content( '', array( 'toc_enabled' => '1' ) ) );

		$existing = '<nav class="draftcraft-toc">x</nav><p>Hi</p>';
		$this->assertSame( $existing, DraftCraft_TOC::process_content( $existing, array( 'toc_enabled' => '1' ) ) );

		$one = DraftCraft_TOC::process_content( $html, array( 'toc_enabled' => '1' ) );
		$this->assertStringNotContainsString( 'draftcraft-toc', $one );
		$this->assertStringContainsString( 'id="only-one"', $one );
	}

	public function test_process_content_builds_nav_nests_and_skips_faq() {
		$html  = '<p>Intro.</p><h2>Alpha</h2><h3>Nested</h3><h2>Alpha</h2>';
		$html .= '<section class="draftcraft-faq"><h2>Frequently Asked Questions</h2><h3 class="draftcraft-faq-q">Q?</h3></section>';
		$out   = DraftCraft_TOC::process_content( $html, array( 'toc_enabled' => '1' ) );

		$this->assertStringContainsString( 'class="draftcraft-toc"', $out );
		$this->assertStringContainsString( 'id="alpha"', $out );
		$this->assertStringContainsString( 'id="alpha-2"', $out );
		$this->assertStringContainsString( 'draftcraft-toc__sub', $out );
		$first_p = strpos( $out, '</p>' );
		$toc     = strpos( $out, 'draftcraft-toc' );
		$faq     = strrpos( $out, 'draftcraft-faq' );
		$this->assertGreaterThan( $first_p, $toc );
		$this->assertGreaterThan( $toc, $faq );
		$this->assertStringNotContainsString( 'Frequently Asked Questions</a>', $out );
	}

	public function test_fix_toc_labels_on_display() {
		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => 'x',
			)
		);
		$this->go_to( get_permalink( $post_id ) );

		$nav = '<nav class="draftcraft-toc"><ol><li><a href="#a">1. Secure Your Router</a></li><li><a href="#faq">Frequently Asked Questions</a><ol class="draftcraft-toc__sub"><li><a href="#q">Q?</a></li></ol></li></ol></nav>';
		$out = DraftCraft_TOC::fix_toc_labels_on_display( $nav );
		$this->assertStringContainsString( 'Secure Your Router', $out );
		$this->assertStringNotContainsString( '1. Secure', $out );
		$this->assertStringNotContainsString( 'Frequently Asked Questions', $out );

		$this->assertSame( '<p>No toc</p>', DraftCraft_TOC::fix_toc_labels_on_display( '<p>No toc</p>' ) );
	}
}
