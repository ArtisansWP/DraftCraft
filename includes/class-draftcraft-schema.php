<?php
/**
 * DraftCraft — Auto FAQ Section & FAQPage JSON-LD Schema
 *
 * Extracts FAQs from generated post content, renders accessible HTML,
 * outputs FAQPage JSON-LD, and prevents Rank Math duplicate FAQPage schema.
 *
 * @package DraftCraft
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DraftCraft_Schema
 */
class DraftCraft_Schema {



	/**
	 * Boot frontend hooks.
	 *
	 * @since 1.2.0
	 */
	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'output_faq_jsonld' ), 20 );
		add_filter( 'the_content', array( __CLASS__, 'ensure_faq_at_end' ), 15 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_styles' ) );
		add_action( 'wp', array( __CLASS__, 'prevent_duplicate_faq_schema' ) );
		add_filter( 'rank_math/json_ld', array( __CLASS__, 'strip_rank_math_faqpage' ), 99, 2 );
		add_action( 'save_post_post', array( __CLASS__, 'sync_faqs_from_content' ), 25, 2 );
	}//end init()


	/**
	 * Whether the current singular post has DraftCraft FAQs saved.
	 *
	 * @since  1.2.1
	 * @param  integer $post_id Optional post ID.
	 * @return boolean
	 */
	public static function post_has_faqs( int $post_id = 0 ): bool {
		if ( $post_id < 1 ) {
			$post_id = (int) get_queried_object_id();
		}

		if ( $post_id < 1 ) {
			return false;
		}

		$faqs = get_post_meta( $post_id, '_draftcraft_faqs', true );
		return is_array( $faqs ) && ! empty( $faqs );
	}//end post_has_faqs()


	/**
	 * Soft, theme-friendly FAQ spacing (no forced colors/background).
	 *
	 * @since 1.2.1
	 */
	public static function enqueue_frontend_styles(): void {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id < 1 ) {
			return;
		}

		$has_faqs = self::post_has_faqs( $post_id )
			|| '1' === (string) get_post_meta( $post_id, '_draftcraft_generated', true );

		if ( ! $has_faqs ) {
			return;
		}

		$css = '
			.draftcraft-faq,
			.wp-block-group.draftcraft-faq{
				margin:3em 0 1.5em;
				padding:0;
				border:0;
				background:transparent;
				color:inherit
			}
			.draftcraft-faq > h2,
			.wp-block-group.draftcraft-faq > h2,
			.wp-block-group.draftcraft-faq > .wp-block-heading:first-child h2{
				margin:0 0 1.15em;
				padding:0 0 .65em;
				font-size:1.35em;
				line-height:1.3;
				border-bottom:1px solid currentColor;
				border-bottom-color:color-mix(in srgb,currentColor 28%,transparent)
			}
			.draftcraft-faq-item{
				margin:0;
				padding:1.15em 0;
				border-bottom:1px solid currentColor;
				border-bottom-color:color-mix(in srgb,currentColor 16%,transparent)
			}
			.draftcraft-faq-item:last-child{
				border-bottom:0;
				padding-bottom:0
			}
			.draftcraft-faq-q,
			h3.draftcraft-faq-q,
			.wp-block-heading.draftcraft-faq-q h3{
				margin:1.15em 0 .45em;
				font-size:1.08em;
				font-weight:700;
				line-height:1.35
			}
			.draftcraft-faq-a,
			p.draftcraft-faq-a,
			.wp-block-paragraph.draftcraft-faq-a{
				margin:0 0 .35em;
				line-height:1.65;
				opacity:.88
			}
			.draftcraft-faq-a p{margin:0 0 .55em}
			.draftcraft-faq-a p:last-child{margin-bottom:0}
		';

		wp_register_style( 'draftcraft-faq', false, array(), DRAFTCRAFT_VERSION );
		wp_enqueue_style( 'draftcraft-faq' );
		wp_add_inline_style( 'draftcraft-faq', $css );
	}//end enqueue_frontend_styles()


	/**
	 * Stop Rank Math FAQ block schema when DraftCraft already outputs FAQPage.
	 *
	 * @since 1.2.1
	 */
	public static function prevent_duplicate_faq_schema(): void {
		if ( is_admin() || ! is_singular( 'post' ) || ! self::post_has_faqs() ) {
			return;
		}

		if ( class_exists( '\RankMath\Schema\Block_FAQ' ) ) {
			remove_filter( 'rank_math/schema/block/faq-block', array( \RankMath\Schema\Block_FAQ::get(), 'add_graph' ) );
		}
	}//end prevent_duplicate_faq_schema()


	/**
	 * Remove FAQPage nodes from Rank Math's JSON-LD graph when we own FAQ schema.
	 *
	 * @since  1.2.1
	 * @param  array $data   Rank Math schema graph.
	 * @param  mixed $jsonld Rank Math JsonLD instance.
	 * @return array
	 */
	public static function strip_rank_math_faqpage( $data, $jsonld = null ) {  // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! is_array( $data ) || ! self::post_has_faqs() ) {
			return $data;
		}

		foreach ( $data as $key => $piece ) {
			if ( ! is_array( $piece ) || empty( $piece['@type'] ) ) {
				continue;
			}

			$type   = $piece['@type'];
			$is_faq = ( 'FAQPage' === $type )
				|| ( is_array( $type ) && in_array( 'FAQPage', $type, true ) );
			if ( $is_faq ) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}//end strip_rank_math_faqpage()


	/**
	 * Whether FAQ generation is enabled.
	 *
	 * @since  1.2.0
	 * @param  array $settings Plugin settings.
	 * @return boolean
	 */
	public static function is_enabled( array $settings ): bool {
		return ! empty( $settings['faq_schema_enabled'] ) && '1' === $settings['faq_schema_enabled'];
	}//end is_enabled()


	/**
	 * Extra prompt instructions for FAQ HTML + JSON array.
	 *
	 * @since  1.2.0
	 * @return string
	 */
	public static function get_prompt_instructions(): string {
		return '
Also include:
- Append a FAQ section at the end of "content" HTML with 3–4 questions. Use this structure: <section class="draftcraft-faq" aria-labelledby="draftcraft-faq-heading"><h2 id="draftcraft-faq-heading">Frequently Asked Questions</h2><div class="draftcraft-faq-item"><h3 class="draftcraft-faq-q">Question?</h3><div class="draftcraft-faq-a"><p>Answer.</p></div></div></section>
- "faqs" (array of objects): each with "question" (string) and "answer" (string, plain text). Must match the FAQ section in the content.';
	}//end get_prompt_instructions()


	/**
	 * Sanitize FAQ array from AI payload.
	 *
	 * @since  1.2.0
	 * @param  array $post_data Parsed AI response.
	 * @return array<int, array{question:string,answer:string}>
	 */
	public static function sanitize_faqs( array $post_data ): array {
		$raw  = ( $post_data['faqs'] ?? array() );
		$faqs = array();

		if ( ! is_array( $raw ) ) {
			return array();
		}

		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$q = sanitize_text_field( ( $item['question'] ?? '' ) );
			$a = sanitize_text_field( wp_strip_all_tags( ( $item['answer'] ?? '' ) ) );
			if ( '' !== $q && '' !== $a ) {
				$faqs[] = array(
					'question' => $q,
					'answer'   => $a,
				);
			}

			if ( count( $faqs ) >= 4 ) {
				break;
			}
		}

		return $faqs;
	}//end sanitize_faqs()


	/**
	 * Extract FAQs from HTML if the JSON array was missing.
	 *
	 * @since  1.2.0
	 * @param  string $content HTML content.
	 * @return array<int, array{question:string,answer:string}>
	 */
	public static function extract_faqs_from_html( string $content ): array {
		$faqs    = array();
		$matches = array();

		$matched = preg_match_all(
			'/<h3[^>]*class=["\'][^"\']*draftcraft-faq-q[^"\']*["\'][^>]*>(.*?)<\/h3>\s*<div[^>]*class=["\'][^"\']*draftcraft-faq-a[^"\']*["\'][^>]*>(.*?)<\/div>/is',
			$content,
			$matches,
			PREG_SET_ORDER
		);

		if ( ! $matched ) {
			if ( ! preg_match( '/class=["\'][^"\']*draftcraft-faq[^"\']*["\']/i', $content ) ) {
				return array();
			}

			$matched = preg_match_all(
				'/<h3[^>]*>(.*?)<\/h3>\s*(?:<div[^>]*>)?\s*<p[^>]*>(.*?)<\/p>/is',
				$content,
				$matches,
				PREG_SET_ORDER
			);
			if ( ! $matched ) {
				return array();
			}
		}

		foreach ( $matches as $m ) {
			$q = sanitize_text_field( wp_strip_all_tags( $m[1] ) );
			$a = sanitize_text_field( wp_strip_all_tags( $m[2] ) );
			if ( $q && $a ) {
				$faqs[] = array(
					'question' => $q,
					'answer'   => $a,
				);
			}

			if ( count( $faqs ) >= 4 ) {
				break;
			}
		}

		return $faqs;
	}//end extract_faqs_from_html()


	/**
	 * Persist FAQs as post meta for frontend JSON-LD output.
	 *
	 * @since  1.2.0
	 * @param  integer $post_id   Post ID.
	 * @param  array   $post_data Parsed AI response.
	 * @param  array   $settings  Plugin settings.
	 * @param  string  $content   Final HTML content (for extraction fallback).
	 * @return array Saved FAQs.
	 */
	public static function save_faqs( int $post_id, array $post_data, array $settings, string $content = '' ): array {
		if ( ! self::is_enabled( $settings ) || $post_id < 1 ) {
			return array();
		}

		$faqs = self::sanitize_faqs( $post_data );
		if ( empty( $faqs ) && $content ) {
			$faqs = self::extract_faqs_from_html( $content );
		}

		if ( empty( $faqs ) ) {
			return array();
		}

		update_post_meta( $post_id, '_draftcraft_faqs', $faqs );

		/*
		 * Fires after FAQs are saved to post meta.
		 *
		 * @since 1.2.0
		 * @param int   $post_id Post ID.
		 * @param array $faqs    FAQ array.
		 */
		do_action( 'draftcraft_faqs_saved', $post_id, $faqs );

		return $faqs;
	}//end save_faqs()


	/**
	 * Build FAQPage JSON-LD array.
	 *
	 * @since  1.2.0
	 * @param  array $faqs FAQ items.
	 * @return array
	 */
	public static function build_jsonld( array $faqs ): array {
		$entities = array();
		foreach ( $faqs as $faq ) {
			$q = sanitize_text_field( ( $faq['question'] ?? '' ) );
			$a = sanitize_text_field( ( $faq['answer'] ?? '' ) );
			if ( '' === $q || '' === $a ) {
				continue;
			}

			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $a,
				),
			);
		}

		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
	}//end build_jsonld()


	/**
	 * Build FAQ section HTML from saved Q&A items.
	 *
	 * @since  1.2.1
	 * @param  array $faqs FAQ items.
	 * @return string
	 */
	public static function render_faq_html( array $faqs ): string {
		$items = array();
		foreach ( $faqs as $faq ) {
			$q = sanitize_text_field( ( $faq['question'] ?? '' ) );
			$a = sanitize_text_field( ( $faq['answer'] ?? '' ) );
			if ( '' === $q || '' === $a ) {
				continue;
			}

			$items[] = '<div class="draftcraft-faq-item"><h3 class="draftcraft-faq-q">' . esc_html( $q ) . '</h3><div class="draftcraft-faq-a"><p>' . esc_html( $a ) . '</p></div></div>';
		}

		if ( empty( $items ) ) {
			return '';
		}

		$title = __( 'Frequently Asked Questions', 'draftcraft' );

		return '<section class="draftcraft-faq" aria-labelledby="draftcraft-faq-heading"><h2 id="draftcraft-faq-heading">' . esc_html( $title ) . '</h2>' . implode( '', $items ) . '</section>';
	}//end render_faq_html()


	/**
	 * Ensure the FAQ block is present at the end of post content.
	 *
	 * If the post already contains FAQ markup (including Gutenberg group
	 * blocks), leave it alone so the editor stays editable. Only inject
	 * from meta when the frontend content has no FAQ yet.
	 *
	 * @since  1.2.1
	 * @param  string $content Post content.
	 * @return string
	 */
	public static function ensure_faq_at_end( string $content ): string {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Already has FAQ (section or Gutenberg group) — do not replace with raw HTML.
		if ( false !== stripos( $content, 'draftcraft-faq' ) ) {
			return $content;
		}

		$faqs = get_post_meta( $post_id, '_draftcraft_faqs', true );
		if ( is_array( $faqs ) && ! empty( $faqs ) ) {
			$faq_html = self::render_faq_html( $faqs );
			return '' !== $faq_html ? ( rtrim( $content ) . "\n\n" . $faq_html ) : $content;
		}

		return $content;
	}//end ensure_faq_at_end()


	/**
	 * Keep FAQ schema meta in sync when authors edit FAQ blocks in Gutenberg.
	 *
	 * @since 1.4.0
	 * @param integer $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function sync_faqs_from_content( int $post_id, $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$is_draftcraft = ( '1' === (string) get_post_meta( $post_id, '_draftcraft_generated', true ) )
			|| self::post_has_faqs( $post_id );
		if ( ! $is_draftcraft ) {
			return;
		}

		$content = (string) $post->post_content;
		if ( false === stripos( $content, 'draftcraft-faq' ) ) {
			return;
		}

		// Prefer rendered HTML so block comments do not confuse extractors.
		$html = $content;
		if ( function_exists( 'has_blocks' ) && has_blocks( $content ) && function_exists( 'do_blocks' ) ) {
			$html = do_blocks( $content );
		}

		$faqs = self::extract_faqs_from_html( $html );
		if ( empty( $faqs ) ) {
			$faqs = self::extract_faqs_from_heading_pairs( $html );
		}

		if ( ! empty( $faqs ) ) {
			update_post_meta( $post_id, '_draftcraft_faqs', $faqs );
		}
	}//end sync_faqs_from_content()


	/**
	 * Extract FAQs from Gutenberg-style heading + paragraph pairs inside .draftcraft-faq.
	 *
	 * @since  1.4.0
	 * @param  string $html Rendered HTML.
	 * @return array
	 */
	public static function extract_faqs_from_heading_pairs( string $html ): array {
		$faqs = array();

		if ( ! preg_match( '/<(?:div|section)[^>]*class=["\'][^"\']*\bdraftcraft-faq\b[^"\']*["\'][^>]*>([\s\S]*?)<\/(?:div|section)>/i', $html, $wrap ) ) {
			return array();
		}

		$inner = $wrap[1];
		if ( ! preg_match_all( '/<h3[^>]*>(.*?)<\/h3>\s*(?:<p[^>]*>(.*?)<\/p>)+/is', $inner, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		foreach ( $matches as $m ) {
			$q = trim( wp_strip_all_tags( $m[1] ) );
			$a = trim( wp_strip_all_tags( $m[2] ) );
			if ( '' === $q || '' === $a ) {
				continue;
			}

			if ( preg_match( '/frequently asked questions/i', $q ) ) {
				continue;
			}

			$faqs[] = array(
				'question' => sanitize_text_field( $q ),
				'answer'   => sanitize_text_field( $a ),
			);
			if ( count( $faqs ) >= 4 ) {
				break;
			}
		}

		return $faqs;
	}//end extract_faqs_from_heading_pairs()


	/**
	 * Output FAQPage JSON-LD in wp_head on singular posts.
	 *
	 * Zero CSS/JS. One meta read. Early exit when no FAQs.
	 *
	 * @since 1.2.0
	 */
	public static function output_faq_jsonld(): void {
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$faqs = get_post_meta( $post_id, '_draftcraft_faqs', true );
		if ( ! is_array( $faqs ) || empty( $faqs ) ) {
			return;
		}

		$schema = self::build_jsonld( $faqs );
		if ( empty( $schema['mainEntity'] ) ) {
			return;
		}

		/*
		 * Filter the FAQPage JSON-LD schema before output.
		 *
		 * @since 1.2.0
		 * @param array $schema  Schema array.
		 * @param int   $post_id Post ID.
		 * @param array $faqs    FAQ items.
		 */
		$schema = (array) apply_filters( 'draftcraft_faq_jsonld', $schema, $post_id, $faqs );

		// JSON_HEX_TAG prevents </script> breakout in FAQ text.
		$json = wp_json_encode( $schema, ( JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) );
		if ( ! $json ) {
			return;
		}

		echo '<script type="application/ld+json" class="draftcraft-faq-schema">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD encoded via wp_json_encode + HEX flags.
	}//end output_faq_jsonld()
}//end class
