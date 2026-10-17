<?php
/**
 * Tests for DraftCraft_SEO.
 *
 * @package DraftCraft
 */

/**
 * Class DraftCraft_Test_SEO
 */
class DraftCraft_Test_SEO extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->reset_detected();
	}

	public function tear_down() {
		$this->reset_detected();
		parent::tear_down();
	}

	private function reset_detected(): void {
		$ref  = new ReflectionClass( DraftCraft_SEO::class );
		$prop = $ref->getProperty( 'detected' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	private function force_plugin( string $slug ): void {
		$ref  = new ReflectionClass( DraftCraft_SEO::class );
		$prop = $ref->getProperty( 'detected' );
		$prop->setAccessible( true );
		$prop->setValue( null, $slug );
	}

	private function words( int $count, string $word = 'lorem' ): string {
		return implode( ' ', array_fill( 0, $count, $word ) );
	}

	public function test_sanitize_seo_fields_truncates_with_ellipsis() {
		$title = str_repeat( 'a', 70 );
		$desc  = str_repeat( 'b', 180 );
		$out   = DraftCraft_SEO::sanitize_seo_fields(
			array(
				'focus_keyword'    => '<b>router</b>',
				'meta_title'       => $title,
				'meta_description' => $desc,
			)
		);
		$this->assertSame( 'router', $out['focus_keyword'] );
		$this->assertSame( 58, draftcraft_strlen( $out['meta_title'] ) );
		$this->assertStringEndsWith( '…', $out['meta_title'] );
		$this->assertSame( 57, draftcraft_strlen( rtrim( $out['meta_title'], '…' ) ) );
		$this->assertSame( 158, draftcraft_strlen( $out['meta_description'] ) );
		$this->assertStringEndsWith( '…', $out['meta_description'] );

		$short = DraftCraft_SEO::sanitize_seo_fields(
			array(
				'focus_keyword'    => 'kw',
				'meta_title'       => 'Short',
				'meta_description' => 'Also short',
			)
		);
		$this->assertSame( 'Short', $short['meta_title'] );
		$this->assertSame( 'Also short', $short['meta_description'] );
	}

	public function test_sentiment_word_boundaries() {
		$this->assertFalse( DraftCraft_SEO::title_has_sentiment( 'A notable tutorial' ) );
		$this->assertTrue( DraftCraft_SEO::title_has_sentiment( 'The best router' ) );
		$this->assertTrue( DraftCraft_SEO::title_has_sentiment( "Don't skip this" ) );
		$this->assertSame( '', DraftCraft_SEO::ensure_title_sentiment( '' ) );
		$this->assertSame( 'The best router', DraftCraft_SEO::ensure_title_sentiment( 'The best router' ) );
		$this->assertSame( 'Essential Plain Title', DraftCraft_SEO::ensure_title_sentiment( 'Plain Title' ) );
	}

	public function test_build_short_slug() {
		$this->assertSame( 'wireless-router', DraftCraft_SEO::build_short_slug( 'A Long Title Here', 'Wireless Router' ) );
		$this->assertSame( 'plain-title', DraftCraft_SEO::build_short_slug( 'Plain Title', '   ' ) );
		$long = 'one-two-three-four-five-six-seven-eight-nine-ten-eleven-twelve';
		$slug = DraftCraft_SEO::build_short_slug( $long, '', 10 );
		$this->assertLessThanOrEqual( 20, strlen( $slug ) );
		$this->assertStringNotContainsString( '--', $slug );
		$trimmed = DraftCraft_SEO::build_short_slug( 'alpha-beta-gamma-delta-epsilon-zeta-eta-theta', '', 25 );
		$this->assertFalse( str_ends_with( $trimmed, '-' ) );
		$this->assertSame( 'short-slug', DraftCraft_SEO::build_short_slug( 'short-slug', '', 60 ) );
	}

	public function test_keyword_matching_and_beginning_window() {
		$html = '<p>The Wireless  Router is great.</p>';
		$this->assertTrue( DraftCraft_SEO::text_has_keyword( $html, 'wireless router' ) );
		$this->assertSame( 1, DraftCraft_SEO::count_keyword_occurrences( $html, 'WIRELESS ROUTER' ) );
		$this->assertSame( 0, DraftCraft_SEO::count_keyword_occurrences( $html, '' ) );
		$this->assertFalse( DraftCraft_SEO::text_has_keyword( '', 'router' ) );

		$under = '<p>' . $this->words( 298 ) . ' router</p>';
		$this->assertTrue( DraftCraft_SEO::keyword_in_beginning( $under, 'router' ) );

		$over = '<p>' . $this->words( 299 ) . ' router</p>';
		$this->assertFalse( DraftCraft_SEO::keyword_in_beginning( $over, 'router' ) );
	}

	public function test_ensure_focus_keyword_does_not_double() {
		$already = '<p>This router guide is clear.</p><p>More.</p>';
		$this->assertSame( $already, DraftCraft_SEO::ensure_focus_keyword_in_content( $already, 'router' ) );

		$missing = '<p>This guide is clear.</p>';
		$updated = DraftCraft_SEO::ensure_focus_keyword_in_content( $missing, 'router' );
		$this->assertSame( 1, DraftCraft_SEO::count_keyword_occurrences( $updated, 'router' ) );
		$this->assertTrue( DraftCraft_SEO::keyword_in_beginning( $updated, 'router' ) );

		$no_p    = 'Just text about networking.';
		$prepend = DraftCraft_SEO::ensure_focus_keyword_in_content( $no_p, 'router' );
		$this->assertStringStartsWith( '<p>This guide explains <strong>router</strong>', $prepend );
	}

	public function test_ensure_keyword_in_subheading() {
		$has = '<p>Intro</p><h2>Router basics</h2><p>Body</p>';
		$this->assertSame( $has, DraftCraft_SEO::ensure_keyword_in_subheading( $has, 'router' ) );

		$rewrite = DraftCraft_SEO::ensure_keyword_in_subheading( '<p>Intro</p><h2>Basics</h2>', 'router' );
		$this->assertStringContainsString( '<h2>router: Basics</h2>', $rewrite );

		$insert = DraftCraft_SEO::ensure_keyword_in_subheading( '<p>Intro</p><p>More</p>', 'router' );
		$this->assertMatchesRegularExpression( '/<\/p>\s*<h2>router: Key Points<\/h2>/', $insert );

		$start = DraftCraft_SEO::ensure_keyword_in_subheading( 'No paragraphs here.', 'router' );
		$this->assertStringStartsWith( '<h2>router: Key Points</h2>', $start );
	}

	public function test_ensure_keyword_density_rules() {
		$faq = '<section class="draftcraft-faq"><h2>Frequently Asked Questions</h2></section>';
		$short = '<p>' . $this->words( 20 ) . '</p>' . $faq;
		$this->assertSame( $short, DraftCraft_SEO::ensure_keyword_density( $short, 'router' ) );

		$enough = '<p>' . $this->words( 80, 'router' ) . '</p>' . $faq;
		$this->assertSame( $enough, DraftCraft_SEO::ensure_keyword_density( $enough, 'router' ) );

		$low  = '<p>' . $this->words( 80 ) . '</p>' . $faq;
		$out  = DraftCraft_SEO::ensure_keyword_density( $low, 'router' );
		$this->assertStringContainsString( 'ideas around <strong>router</strong>', $out );
		$faq_pos  = stripos( $out, 'draftcraft-faq' );
		$para_pos = strpos( $out, 'ideas around' );
		$this->assertNotFalse( $faq_pos );
		$this->assertLessThan( $faq_pos, $para_pos );
	}

	public function test_sync_to_plugin_false_when_disabled_or_invalid_id() {
		$this->force_plugin( 'rank_math' );
		$this->assertFalse(
			DraftCraft_SEO::sync_to_plugin(
				1,
				array( 'focus_keyword' => 'router' ),
				array( 'seo_sync_enabled' => '0' )
			)
		);
		$this->assertFalse(
			DraftCraft_SEO::sync_to_plugin(
				0,
				array( 'focus_keyword' => 'router' ),
				array( 'seo_sync_enabled' => '1' )
			)
		);
	}

	public function test_sync_writes_rank_math_meta_and_flag_when_keyword_empty() {
		$this->force_plugin( 'rank_math' );
		$post_id = self::factory()->post->create();
		$ok      = DraftCraft_SEO::sync_to_plugin(
			$post_id,
			array(
				'focus_keyword'    => '',
				'meta_title'       => 'Best title here',
				'meta_description' => 'A description.',
			),
			array( 'seo_sync_enabled' => '1' )
		);
		$this->assertTrue( $ok );
		$this->assertSame( '', get_post_meta( $post_id, 'rank_math_focus_keyword', true ) );
		$this->assertSame( 'Best title here', get_post_meta( $post_id, 'rank_math_title', true ) );
		$this->assertSame( 'rank_math', get_post_meta( $post_id, '_draftcraft_seo_synced', true ) );
	}

	public function test_sync_writes_yoast_and_aioseo_post_meta() {
		$post_id = self::factory()->post->create();
		$this->force_plugin( 'yoast' );
		DraftCraft_SEO::sync_to_plugin(
			$post_id,
			array(
				'focus_keyword'    => 'router',
				'meta_title'       => 'Best router',
				'meta_description' => 'Desc',
			),
			array( 'seo_sync_enabled' => '1' )
		);
		$this->assertSame( 'router', get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
		$this->assertSame( 'yoast', get_post_meta( $post_id, '_draftcraft_seo_synced', true ) );

		$aioseo_id = self::factory()->post->create();
		$this->force_plugin( 'aioseo' );
		DraftCraft_SEO::sync_to_plugin(
			$aioseo_id,
			array(
				'focus_keyword'    => 'router',
				'meta_title'       => 'Best router',
				'meta_description' => 'Desc',
			),
			array( 'seo_sync_enabled' => '1' )
		);
		$this->assertSame( 'router', get_post_meta( $aioseo_id, '_aioseo_keywords', true ) );
		$this->assertSame( 'aioseo', get_post_meta( $aioseo_id, '_draftcraft_seo_synced', true ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_detect_rank_math_wins() {
		define( 'RANK_MATH_VERSION', '1.0.0' );
		define( 'WPSEO_VERSION', '1.0.0' );
		$this->reset_detected();
		$this->assertSame( 'rank_math', DraftCraft_SEO::detect_active_plugin() );
		$this->assertSame( 'Rank Math', DraftCraft_SEO::get_active_plugin_label() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_detect_yoast() {
		define( 'WPSEO_VERSION', '1.0.0' );
		$this->reset_detected();
		$this->assertSame( 'yoast', DraftCraft_SEO::detect_active_plugin() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_detect_aioseo() {
		define( 'AIOSEO_VERSION', '1.0.0' );
		$this->reset_detected();
		$this->assertSame( 'aioseo', DraftCraft_SEO::detect_active_plugin() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_detect_none() {
		$this->reset_detected();
		$this->assertSame( '', DraftCraft_SEO::detect_active_plugin() );
		$this->assertFalse( DraftCraft_SEO::is_enabled( array( 'seo_sync_enabled' => '1' ) ) );
	}
}
