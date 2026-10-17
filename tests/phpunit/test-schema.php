<?php
/**
 * Tests for DraftCraft_Schema.
 *
 * @package DraftCraft
 */

/**
 * Class DraftCraft_Test_Schema
 */
class DraftCraft_Test_Schema extends WP_UnitTestCase {

	public function test_sanitize_faqs_skips_invalid_and_caps_at_four() {
		$out = DraftCraft_Schema::sanitize_faqs(
			array(
				'faqs' => array(
					'nope',
					array(
						'question' => '',
						'answer'   => 'A',
					),
					array(
						'question' => 'Q1',
						'answer'   => '<b>A1</b>',
					),
					array(
						'question' => 'Q2',
						'answer'   => 'A2',
					),
					array(
						'question' => 'Q3',
						'answer'   => 'A3',
					),
					array(
						'question' => 'Q4',
						'answer'   => 'A4',
					),
					array(
						'question' => 'Q5',
						'answer'   => 'A5',
					),
				),
			)
		);
		$this->assertCount( 4, $out );
		$this->assertSame( 'Q1', $out[0]['question'] );
		$this->assertSame( 'A1', $out[0]['answer'] );
		$this->assertSame( 'Q4', $out[3]['question'] );
	}

	public function test_extract_faqs_from_html() {
		$html = '<section class="draftcraft-faq"><h3 class="draftcraft-faq-q">Q1?</h3><div class="draftcraft-faq-a"><p>A1</p></div><h3 class="draftcraft-faq-q">Q2?</h3><div class="draftcraft-faq-a"><p>A2</p></div></section>';
		$faqs = DraftCraft_Schema::extract_faqs_from_html( $html );
		$this->assertCount( 2, $faqs );
		$this->assertSame( 'Q1?', $faqs[0]['question'] );
		$this->assertSame( 'A1', $faqs[0]['answer'] );

		$this->assertSame( array(), DraftCraft_Schema::extract_faqs_from_html( '<p>No faq classes</p>' ) );
	}

	public function test_extract_faqs_from_heading_pairs() {
		$html = '<div class="draftcraft-faq"><h2>Frequently Asked Questions</h2><h3>Q1?</h3><p>A1</p><h3>Q2?</h3><p>A2</p></div>';
		$faqs = DraftCraft_Schema::extract_faqs_from_heading_pairs( $html );
		$this->assertCount( 2, $faqs );
		$this->assertSame( 'Q1?', $faqs[0]['question'] );
	}

	public function test_build_jsonld() {
		$schema = DraftCraft_Schema::build_jsonld(
			array(
				array(
					'question' => 'Q1?',
					'answer'   => 'A1',
				),
				array(
					'question' => '',
					'answer'   => 'skip',
				),
			)
		);
		$this->assertSame( 'https://schema.org', $schema['@context'] );
		$this->assertSame( 'FAQPage', $schema['@type'] );
		$this->assertCount( 1, $schema['mainEntity'] );
		$this->assertSame( 'Question', $schema['mainEntity'][0]['@type'] );
		$this->assertSame( 'Q1?', $schema['mainEntity'][0]['name'] );
		$this->assertSame( 'A1', $schema['mainEntity'][0]['acceptedAnswer']['text'] );
	}

	public function test_render_faq_html() {
		$this->assertSame( '', DraftCraft_Schema::render_faq_html( array() ) );
		$html = DraftCraft_Schema::render_faq_html(
			array(
				array(
					'question' => 'Tom & Jerry?',
					'answer'   => 'It is <b>fine</b>.',
				),
			)
		);
		$this->assertStringContainsString( 'section class="draftcraft-faq"', $html );
		$this->assertStringContainsString( 'Tom &amp; Jerry?', $html );
		$this->assertStringNotContainsString( '<b>fine</b>', $html );
	}

	public function test_save_faqs_guards_and_persists() {
		$this->assertSame( array(), DraftCraft_Schema::save_faqs( 1, array(), array( 'faq_schema_enabled' => '0' ) ) );
		$this->assertSame( array(), DraftCraft_Schema::save_faqs( 0, array( 'faqs' => array( array( 'question' => 'Q', 'answer' => 'A' ) ) ), array( 'faq_schema_enabled' => '1' ) ) );

		$post_id = self::factory()->post->create();
		$saved   = DraftCraft_Schema::save_faqs(
			$post_id,
			array(
				'faqs' => array(
					array(
						'question' => 'Q?',
						'answer'   => 'A',
					),
				),
			),
			array( 'faq_schema_enabled' => '1' )
		);
		$this->assertCount( 1, $saved );
		$this->assertSame( $saved, get_post_meta( $post_id, '_draftcraft_faqs', true ) );
	}

	public function test_ensure_faq_at_end_on_singular_loop() {
		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '<p>Article body</p>',
			)
		);
		update_post_meta(
			$post_id,
			'_draftcraft_faqs',
			array(
				array(
					'question' => 'Q?',
					'answer'   => 'A',
				),
			)
		);

		$this->go_to( get_permalink( $post_id ) );
		$this->assertTrue( have_posts() );
		the_post();
		$out = DraftCraft_Schema::ensure_faq_at_end( get_post( $post_id )->post_content );
		$this->assertStringContainsString( 'draftcraft-faq', $out );
		$this->assertStringContainsString( 'Article body', $out );

		$already = DraftCraft_Schema::ensure_faq_at_end( '<p>Body</p><section class="draftcraft-faq">kept</section>' );
		$this->assertStringContainsString( 'kept', $already );
		$this->assertSame( 1, substr_count( $already, 'draftcraft-faq' ) );
	}

	public function test_ensure_faq_at_end_skips_outside_loop() {
		$content = '<p>Body</p>';
		$this->assertSame( $content, DraftCraft_Schema::ensure_faq_at_end( $content ) );
	}
}
