<?php
/**
 * Tests for DraftCraft_Internal_Links.
 *
 * @package DraftCraft
 */

/**
 * Class DraftCraft_Test_Internal_Links
 */
class DraftCraft_Test_Internal_Links extends WP_UnitTestCase {

	public function test_is_internal_href() {
		$this->assertTrue( DraftCraft_Internal_Links::is_internal_href( '' ) );
		$this->assertTrue( DraftCraft_Internal_Links::is_internal_href( '#section' ) );
		$this->assertTrue( DraftCraft_Internal_Links::is_internal_href( '/relative-path' ) );
		$this->assertFalse( DraftCraft_Internal_Links::is_internal_href( '//evil.example/x' ) );
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$this->assertTrue( DraftCraft_Internal_Links::is_internal_href( 'https://' . strtoupper( (string) $host ) . '/post' ) );
		$this->assertFalse( DraftCraft_Internal_Links::is_internal_href( 'https://other.example/post' ) );
	}

	public function test_strip_and_count_links() {
		$home = home_url( '/ok/' );
		$html = '<p><a href="' . esc_url( $home ) . '">in</a> and <a href="https://evil.example/x">out</a> and <a href="#frag">frag</a></p>';
		$out  = DraftCraft_Internal_Links::strip_external_links( $html );
		$this->assertStringContainsString( 'href="' . $home . '"', $out );
		$this->assertStringNotContainsString( 'evil.example', $out );
		$this->assertStringContainsString( 'out', $out );
		$this->assertSame( 1, DraftCraft_Internal_Links::count_internal_links( $out ) );
		$this->assertSame( 'plain', DraftCraft_Internal_Links::strip_external_links( 'plain' ) );
	}

	public function test_score_thresholds() {
		$strict = DraftCraft_Internal_Links::get_score_thresholds( array( 'internal_links_relevance' => 'strict' ) );
		$this->assertSame( 'strict', $strict['key'] );
		$this->assertSame( 28.0, $strict['prompt'] );
		$balanced = DraftCraft_Internal_Links::get_score_thresholds( array( 'internal_links_relevance' => 'nope' ) );
		$this->assertSame( 'balanced', $balanced['key'] );
		$loose = DraftCraft_Internal_Links::get_score_thresholds( array( 'internal_links_relevance' => 'loose' ) );
		$this->assertSame( 10.0, $loose['prompt'] );
	}

	public function test_rank_candidates_order() {
		$candidates = array(
			array(
				'title'        => 'Unrelated Post Title Extra',
				'url'          => home_url( '/u/' ),
				'category_ids' => array( 9 ),
			),
			array(
				'title'        => 'Wireless Router Setup Guide',
				'url'          => home_url( '/r/' ),
				'category_ids' => array( 3 ),
			),
		);
		$ranked = DraftCraft_Internal_Links::rank_candidates(
			$candidates,
			array(
				'category_ids'  => array( 3 ),
				'focus_keyword' => 'router',
				'content'       => 'This article is about a wireless router setup for the home office.',
			)
		);
		$this->assertSame( home_url( '/r/' ), $ranked[0]['url'] );
		$this->assertGreaterThan( $ranked[1]['score'], $ranked[0]['score'] );

		$dropped = DraftCraft_Internal_Links::rank_candidates(
			array(
				array(
					'title' => '',
					'url'   => home_url( '/x/' ),
				),
			)
		);
		$this->assertSame( array(), $dropped );
	}

	public function test_build_prompt_context() {
		$this->assertSame( '', DraftCraft_Internal_Links::build_prompt_context( array() ) );
		$weak = DraftCraft_Internal_Links::build_prompt_context(
			array(
				array(
					'id'    => 1,
					'title' => 'T',
					'url'   => home_url( '/t/' ),
					'score' => 1,
				),
			),
			array( 'internal_links_relevance' => 'balanced' )
		);
		$this->assertStringContainsString( 'Do NOT invent links', $weak );

		$rows = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$rows[] = array(
				'id'    => $i,
				'title' => 'Post ' . $i,
				'url'   => home_url( '/p' . $i . '/' ),
				'score' => 40,
			);
		}
		$ctx = DraftCraft_Internal_Links::build_prompt_context( $rows, array( 'internal_links_relevance' => 'balanced' ) );
		$this->assertSame( 10, substr_count( $ctx, 'ID:' ) );
		$this->assertStringContainsString( 'at most 2', $ctx );
		$loose = DraftCraft_Internal_Links::build_prompt_context( $rows, array( 'internal_links_relevance' => 'loose' ) );
		$this->assertStringContainsString( 'at most 3', $loose );
	}

	public function test_prompt_instructions_local_forbids_anchors() {
		$local = DraftCraft_Internal_Links::get_prompt_instructions( array( 'internal_links_method' => 'local' ) );
		$this->assertStringContainsString( 'Do NOT include any hyperlinks', $local );
	}

	public function test_inject_contextual_links() {
		$url     = home_url( '/wireless-router-setup-guide/' );
		$phrase  = 'Wireless Router Setup';
		$content = '<p>Learn Wireless Router Setup today.</p>';
		$cands   = array(
			array(
				'title' => 'Wireless Router Setup Guide',
				'url'   => $url,
				'score' => 40,
			),
		);

		$empty = DraftCraft_Internal_Links::inject_contextual_links( '', $cands, 1, 1, 22 );
		$this->assertSame( 0, $empty['inserted'] );

		$already = '<p><a href="' . $url . '">x</a> and also another <a href="' . home_url( '/b/' ) . '">y</a></p>';
		$skip    = DraftCraft_Internal_Links::inject_contextual_links( $already, $cands, 2, 3, 22 );
		$this->assertSame( 0, $skip['inserted'] );

		$low = DraftCraft_Internal_Links::inject_contextual_links( $content, array( array_merge( $cands[0], array( 'score' => 1 ) ) ), 1, 1, 22 );
		$this->assertSame( 0, $low['inserted'] );

		$dup_url = DraftCraft_Internal_Links::inject_contextual_links( '<p>' . $phrase . ' at ' . $url . '</p>', $cands, 1, 1, 22 );
		$this->assertSame( 0, $dup_url['inserted'] );

		$ok = DraftCraft_Internal_Links::inject_contextual_links( $content, $cands, 1, 1, 22 );
		$this->assertSame( 1, $ok['inserted'] );
		$this->assertSame( 1, substr_count( strtolower( $ok['content'] ), strtolower( $url ) ) );

		$inside_href = '<p><a href="https://evil.example/?q=Wireless Router Setup">click</a> plus Wireless Router Setup later.</p>';
		$safe        = DraftCraft_Internal_Links::inject_contextual_links( $inside_href, $cands, 1, 1, 22 );
		$this->assertSame( 1, $safe['inserted'] );
		$this->assertStringContainsString( 'q=Wireless Router Setup', $safe['content'] );
	}

	public function test_append_related_reading() {
		$faq = '<section class="draftcraft-faq"><h2>Frequently Asked Questions</h2></section>';
		$url1 = home_url( '/one/' );
		$url2 = home_url( '/two/' );
		$url3 = home_url( '/three/' );
		$cands = array(
			array(
				'title' => 'One Post',
				'url'   => $url1,
				'score' => 40,
			),
			array(
				'title' => 'Two Post',
				'url'   => $url2,
				'score' => 40,
			),
			array(
				'title' => 'Three Post',
				'url'   => $url3,
				'score' => 40,
			),
		);
		$none = DraftCraft_Internal_Links::append_related_reading( '<p>Body</p>', $cands, 0, 14 );
		$this->assertSame( 0, $none['inserted'] );

		$out = DraftCraft_Internal_Links::append_related_reading( '<p>Body</p>' . $faq, $cands, 5, 14 );
		$this->assertSame( 2, $out['inserted'] );
		$rel = strpos( $out['content'], 'draftcraft-related-reading' );
		$faq_pos = strpos( $out['content'], 'draftcraft-faq' );
		$this->assertLessThan( $faq_pos, $rel );
	}

	public function test_process_content_methods() {
		$external = '<p>See <a href="https://evil.example/x">here</a></p>';
		$disabled = DraftCraft_Internal_Links::process_content( $external, array( 'internal_links_enabled' => '0' ) );
		$this->assertSame( $external, $disabled );

		$stripped = DraftCraft_Internal_Links::process_content( $external, array( 'internal_links_enabled' => '1', 'internal_links_method' => 'hybrid' ) );
		$this->assertStringNotContainsString( 'evil.example', $stripped );

		$home = home_url( '/kept/' );
		$html = '<p>See <a href="' . $home . '">Kept</a> and Wireless Router Setup too.</p>';
		$hybrid = DraftCraft_Internal_Links::process_content(
			$html,
			array(
				'internal_links_enabled' => '1',
				'internal_links_method'  => 'hybrid',
			)
		);
		$this->assertStringContainsString( $home, $hybrid );
	}
}
