<?php
/**
 * DraftCraft — SEO Plugin Integration Sync
 *
 * Auto-detects Rank Math, Yoast SEO, or AIOSEO and writes AI-generated
 * focus keyword, meta title, and meta description into the active plugin.
 *
 * @package DraftCraft
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DraftCraft_SEO
 */
class DraftCraft_SEO {


	/**
	 * Cached active plugin slug for the request.
	 *
	 * @var string|null
	 */
	private static $detected = null;


	/**
	 * Detect which supported SEO plugin is active.
	 *
	 * @since  1.2.0
	 * @return string One of: 'rank_math', 'yoast', 'aioseo', or ''.
	 */
	public static function detect_active_plugin(): string {
		if ( null !== self::$detected ) {
			return self::$detected;
		}

		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath', false ) ) {
			self::$detected = 'rank_math';
		} elseif ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options', false ) ) {
			self::$detected = 'yoast';
		} elseif ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			self::$detected = 'aioseo';
		} else {
			self::$detected = '';
		}

		return self::$detected;
	}//end detect_active_plugin()


	/**
	 * Human-readable label for the active SEO plugin.
	 *
	 * @since  1.2.0
	 * @return string
	 */
	public static function get_active_plugin_label(): string {
		$map  = array(
			'rank_math' => 'Rank Math',
			'yoast'     => 'Yoast SEO',
			'aioseo'    => 'All in One SEO',
		);
		$slug = self::detect_active_plugin();
		return ( $map[ $slug ] ?? __( 'None detected', 'draftcraft' ) );
	}//end get_active_plugin_label()


	/**
	 * Whether SEO sync is enabled in settings and a plugin is available.
	 *
	 * @since  1.2.0
	 * @param  array $settings Plugin settings.
	 * @return boolean
	 */
	public static function is_enabled( array $settings ): bool {
		return ! empty( $settings['seo_sync_enabled'] ) && '1' === $settings['seo_sync_enabled']
			&& '' !== self::detect_active_plugin();
	}//end is_enabled()


	/**
	 * Sanitize SEO fields from the AI JSON payload.
	 *
	 * @since  1.2.0
	 * @param  array $post_data Parsed AI response.
	 * @return array{focus_keyword:string,meta_title:string,meta_description:string}
	 */
	public static function sanitize_seo_fields( array $post_data ): array {
		$keyword = sanitize_text_field( ( $post_data['focus_keyword'] ?? '' ) );
		$title   = sanitize_text_field( ( $post_data['meta_title'] ?? '' ) );
		$desc    = sanitize_text_field( ( $post_data['meta_description'] ?? '' ) );

		$len = function_exists( 'draftcraft_strlen' ) ? 'draftcraft_strlen' : 'strlen';
		$sub = function_exists( 'draftcraft_substr' ) ? 'draftcraft_substr' : 'substr';

		if ( $len( $title ) > 60 ) {
			$title = $sub( $title, 0, 57 ) . '…';
		}

		if ( $len( $desc ) > 160 ) {
			$desc = $sub( $desc, 0, 157 ) . '…';
		}

		return array(
			'focus_keyword'    => $keyword,
			'meta_title'       => $title,
			'meta_description' => $desc,
		);
	}//end sanitize_seo_fields()


	/**
	 * Write SEO meta into the active SEO plugin's post meta keys.
	 *
	 * @since  1.2.0
	 * @param  integer $post_id  Post ID.
	 * @param  array   $seo_data Sanitized SEO fields.
	 * @param  array   $settings Plugin settings.
	 * @return boolean True if meta was written.
	 */
	public static function sync_to_plugin( int $post_id, array $seo_data, array $settings ): bool {
		if ( ! self::is_enabled( $settings ) || $post_id < 1 ) {
			return false;
		}

		$seo_data = self::sanitize_seo_fields( $seo_data );
		$plugin   = self::detect_active_plugin();

		/*
		 * Fires before SEO meta is written.
		 *
		 * @since 1.2.0
		 * @param int    $post_id  Post ID.
		 * @param array  $seo_data Sanitized SEO fields.
		 * @param string $plugin   Active SEO plugin slug.
		 */
		do_action( 'draftcraft_before_seo_sync', $post_id, $seo_data, $plugin );

		$written = false;

		switch ( $plugin ) {
			case 'rank_math':
				if ( $seo_data['focus_keyword'] ) {
					update_post_meta( $post_id, 'rank_math_focus_keyword', $seo_data['focus_keyword'] );
				}

				if ( $seo_data['meta_title'] ) {
					update_post_meta( $post_id, 'rank_math_title', $seo_data['meta_title'] );
				}

				if ( $seo_data['meta_description'] ) {
					update_post_meta( $post_id, 'rank_math_description', $seo_data['meta_description'] );
				}

				$written = true;
				break;

			case 'yoast':
				if ( $seo_data['focus_keyword'] ) {
					update_post_meta( $post_id, '_yoast_wpseo_focuskw', $seo_data['focus_keyword'] );
				}

				if ( $seo_data['meta_title'] ) {
					update_post_meta( $post_id, '_yoast_wpseo_title', $seo_data['meta_title'] );
				}

				if ( $seo_data['meta_description'] ) {
					update_post_meta( $post_id, '_yoast_wpseo_metadesc', $seo_data['meta_description'] );
				}

				$written = true;
				break;

			case 'aioseo':
				if ( $seo_data['focus_keyword'] ) {
					update_post_meta( $post_id, '_aioseo_keywords', $seo_data['focus_keyword'] );
				}

				if ( $seo_data['meta_title'] ) {
					update_post_meta( $post_id, '_aioseo_title', $seo_data['meta_title'] );
				}

				if ( $seo_data['meta_description'] ) {
					update_post_meta( $post_id, '_aioseo_description', $seo_data['meta_description'] );
				}

				self::sync_aioseo_table( $post_id, $seo_data );
				$written = true;
				break;
		}//end switch

		if ( $written ) {
			update_post_meta( $post_id, '_draftcraft_seo_synced', $plugin );
			update_post_meta( $post_id, '_draftcraft_focus_keyword', $seo_data['focus_keyword'] );
		}

		/*
		 * Fires after SEO meta is written.
		 *
		 * @since 1.2.0
		 * @param int    $post_id  Post ID.
		 * @param array  $seo_data Sanitized SEO fields.
		 * @param string $plugin   Active SEO plugin slug.
		 */
		do_action( 'draftcraft_after_seo_sync', $post_id, $seo_data, $plugin );

		return $written;
	}//end sync_to_plugin()


	/**
	 * Best-effort update of AIOSEO posts table (if present).
	 * Table existence is cached for 12 hours to avoid SHOW TABLES on every run.
	 *
	 * @since 1.2.0
	 * @param integer $post_id  Post ID.
	 * @param array   $seo_data SEO fields.
	 */
	private static function sync_aioseo_table( int $post_id, array $seo_data ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_posts';

		$cache_key = 'draftcraft_aioseo_table';
		$exists    = get_transient( $cache_key );

		if ( false === $exists ) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			$exists = ( $found === $table ) ? '1' : '0';
			set_transient( $cache_key, $exists, ( 12 * HOUR_IN_SECONDS ) );
		}

		if ( '1' !== $exists ) {
			return;
		}

		$keyphrases = wp_json_encode(
			array(
				'focus' => array(
					'keyphrase' => $seo_data['focus_keyword'],
					'score'     => 0,
				),
			)
		);

		$data = array(
			'title'       => $seo_data['meta_title'],
			'description' => $seo_data['meta_description'],
			'keyphrases'  => $keyphrases,
			'updated'     => current_time( 'mysql' ),
		);

		// Prefer $wpdb->update / insert (PCP-safe) over a raw SELECT with a dynamic table name.
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update( $table, $data, array( 'post_id' => $post_id ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );

		if ( false === $updated ) {
			return;
		}

		if ( $updated > 0 ) {
			return;
		}

		// No row updated: either missing, or values already identical — try insert.
		$data['post_id'] = $post_id;
		$data['created'] = current_time( 'mysql' );
     // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $table, $data );
	}//end sync_aioseo_table()


	/**
	 * Extra prompt instructions for SEO JSON keys.
	 *
	 * @since  1.2.0
	 * @return string
	 */
	public static function get_prompt_instructions(): string {
		return '
Also include these SEO keys in the JSON object:
- "focus_keyword" (string): primary SEO keyword phrase for the post.
- "meta_title" (string): SEO title under 60 characters; must include the focus keyword AND a positive/negative sentiment word (e.g. best, easy, proven, essential, ultimate, complete, simple, powerful, effective, avoid, stop, without).
- "meta_description" (string): meta description under 160 characters; must include the focus keyword.
CRITICAL focus keyword placement (exact phrase, case-insensitive):
1. The post title MUST include a sentiment word (best, easy, essential, ultimate, complete, proven, simple, powerful, effective, avoid, etc.).
2. The focus_keyword MUST appear in the first paragraph — ideally the first sentence.
3. The focus_keyword MUST appear in at least one H2 or H3 subheading.
4. The focus_keyword MUST appear naturally about 3–4 times total (~1% density). Never stuff; stay under 2% density.
5. Keep the post title concise so the URL slug stays under ~75 characters.
Write at least 650 words of body content so SEO plugins (e.g. Rank Math) pass the minimum length check.';
	}//end get_prompt_instructions()


	/**
	 * Rank Math–oriented content + title optimisation.
	 *
	 * @since  1.2.1
	 * @param  string $content HTML content.
	 * @param  string $keyword Focus keyword.
	 * @param  string $title   Post title.
	 * @return array{content:string,title:string}
	 */
	public static function optimize_for_rank_math( string $content, string $keyword, string $title ): array {
		$keyword = sanitize_text_field( $keyword );
		$title   = sanitize_text_field( $title );

		if ( '' !== $keyword && '' !== trim( $content ) ) {
			$content = self::ensure_focus_keyword_in_content( $content, $keyword );
			$content = self::ensure_keyword_in_subheading( $content, $keyword );
			$content = self::ensure_keyword_density( $content, $keyword );
		}

		if ( '' !== $title ) {
			$title = self::ensure_title_sentiment( $title );
		}

		return array(
			'content' => $content,
			'title'   => $title,
		);
	}//end optimize_for_rank_math()


	/**
	 * Build a short permalink slug (Rank Math prefers shorter URLs).
	 *
	 * @since  1.2.1
	 * @param  string  $title   Post title.
	 * @param  string  $keyword Focus keyword.
	 * @param  integer $max_len Max slug length. Default 60.
	 * @return string
	 */
	public static function build_short_slug( string $title, string $keyword = '', int $max_len = 60 ): string {
		$source = '' !== trim( $keyword ) ? $keyword : $title;
		$slug   = sanitize_title( $source );
		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}

		$max_len = max( 20, $max_len );
		if ( strlen( $slug ) <= $max_len ) {
			return $slug;
		}

		$slug = substr( $slug, 0, $max_len );
		$slug = rtrim( $slug, '-' );
		// Avoid cutting mid-token awkwardly when possible.
		if ( false !== strpos( $slug, '-' ) ) {
			$trimmed_slug = preg_replace( '/-[^-]*$/', '', $slug );
			$slug         = ! empty( $trimmed_slug ) ? $trimmed_slug : $slug;
		}

		return ! empty( $slug ) ? $slug : sanitize_title( $title );
	}//end build_short_slug()


	/**
	 * Sentiment words Rank Math commonly accepts.
	 *
	 * @since  1.2.1
	 * @return string[]
	 */
	public static function sentiment_words(): array {
		return array(
			'best',
			'worst',
			'ultimate',
			'easy',
			'easiest',
			'amazing',
			'incredible',
			'useful',
			'great',
			'proven',
			'essential',
			'complete',
			'simple',
			'powerful',
			'effective',
			'free',
			'new',
			'top',
			'perfect',
			'awesome',
			'excellent',
			'outstanding',
			'remarkable',
			'important',
			'critical',
			'quick',
			'fast',
			'smart',
			'better',
			'strong',
			'avoid',
			'stop',
			'without',
			'never',
			'dont',
			"don't",
			'no',
			'wrong',
			'mistake',
			'danger',
			'guide',
			'tips',
			'tricks',
			'secrets',
			'hacks',
		);
	}//end sentiment_words()


	/**
	 * Whether a title already contains a sentiment word.
	 *
	 * @since  1.2.1
	 * @param  string $title Title.
	 * @return boolean
	 */
	public static function title_has_sentiment( string $title ): bool {
		$norm = self::normalize_for_keyword_match( $title );
		foreach ( self::sentiment_words() as $word ) {
			$word = self::normalize_for_keyword_match( $word );
			if ( '' === $word ) {
				continue;
			}

			if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/u', $norm ) ) {
				return true;
			}
		}

		return false;
	}//end title_has_sentiment()


	/**
	 * Ensure the post title includes a sentiment word for Rank Math.
	 *
	 * @since  1.2.1
	 * @param  string $title Title.
	 * @return string
	 */
	public static function ensure_title_sentiment( string $title ): string {
		$title = trim( $title );
		if ( '' === $title || self::title_has_sentiment( $title ) ) {
			return $title;
		}

		// Prefer "Essential …" — reads naturally for how-to / guide posts.
		return 'Essential ' . $title;
	}//end ensure_title_sentiment()


	/**
	 * Count case-insensitive occurrences of the keyword phrase in HTML text.
	 *
	 * @since  1.2.1
	 * @param  string $content HTML.
	 * @param  string $keyword Focus keyword.
	 * @return integer
	 */
	public static function count_keyword_occurrences( string $content, string $keyword ): int {
		$plain   = self::normalize_for_keyword_match( $content );
		$keyword = self::normalize_for_keyword_match( $keyword );
		if ( '' === $plain || '' === $keyword ) {
			return 0;
		}

		return substr_count( $plain, $keyword );
	}//end count_keyword_occurrences()


	/**
	 * Ensure at least one H2/H3 contains the focus keyword.
	 *
	 * @since  1.2.1
	 * @param  string $content HTML.
	 * @param  string $keyword Focus keyword.
	 * @return string
	 */
	public static function ensure_keyword_in_subheading( string $content, string $keyword ): string {
		if ( preg_match_all( '/<h([2-4])(\s[^>]*)?>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				if ( self::text_has_keyword( $m[3], $keyword ) ) {
					return $content;
				}
			}

			// Rewrite the first H2/H3 to lead with the keyword.
			$updated = preg_replace_callback(
				'/<h([2-4])(\s[^>]*)?>(.*?)<\/h\1>/is',
				static function ( $m ) use ( $keyword ) {
					$level = $m[1];
					$attrs = ( $m[2] ?? '' );
					$inner = wp_strip_all_tags( $m[3] );
					$lead  = esc_html( $keyword );
					$rest  = trim( $inner );
					$new   = $rest ? $lead . ': ' . esc_html( $rest ) : $lead;
					return '<h' . $level . $attrs . '>' . $new . '</h' . $level . '>';
				},
				$content,
				1
			);

			return is_string( $updated ) ? $updated : $content;
		}//end if

		// No subheadings — insert one after the first paragraph.
		$heading = '<h2>' . esc_html( $keyword ) . ': Key Points</h2>';
		if ( preg_match( '/<\/p>/i', $content, $m, PREG_OFFSET_CAPTURE ) ) {
			$pos = ( $m[0][1] + strlen( $m[0][0] ) );
			return substr( $content, 0, $pos ) . "\n" . $heading . "\n" . substr( $content, $pos );
		}

		return $heading . "\n" . $content;
	}//end ensure_keyword_in_subheading()


	/**
	 * Nudge keyword density toward ~1% — never push into Rank Math "too high" territory.
	 *
	 * Rank Math typically warns above ~2.5%. We stop adding once density is healthy.
	 *
	 * @since  1.2.1
	 * @param  string $content HTML.
	 * @param  string $keyword Focus keyword.
	 * @return string
	 */
	public static function ensure_keyword_density( string $content, string $keyword ): string {
		$plain      = wp_strip_all_tags( $content );
		$words      = preg_split( '/\s+/u', trim( $plain ), -1, PREG_SPLIT_NO_EMPTY );
		$word_count = is_array( $words ) ? count( $words ) : 0;
		if ( $word_count < 50 ) {
			return $content;
		}

		$count   = self::count_keyword_occurrences( $content, $keyword );
		$density = ( ( $count * 100 ) / $word_count );

		// Already enough (or too much) — do not add more.
		if ( $count >= 3 || $density >= 1.0 ) {
			return $content;
		}

		// Aim for ~1%, capped at 4 total occurrences.
		$target = min( 4, max( 2, (int) round( $word_count * 0.01 ) ) );
		if ( $count >= $target ) {
			return $content;
		}

		// Add at most one gentle paragraph to avoid stacking with opening/H2 injects.
		$lead  = esc_html( $keyword );
		$extra = '<p>As you apply these ideas around <strong>' . $lead . '</strong>, stay consistent and measure what works.</p>';

		$split = class_exists( 'DraftCraft_TOC' ) ? DraftCraft_TOC::split_faq_block( $content ) : array(
			'content' => $content,
			'faq'     => '',
		);
		$body  = rtrim( $split['content'] ) . "\n" . $extra;
		return $body . ( $split['faq'] ? "\n" . $split['faq'] : '' );
	}//end ensure_keyword_density()


	/**
	 * Whether the focus keyword appears in plain text (case-insensitive).
	 *
	 * @since  1.2.1
	 * @param  string $haystack Plain text.
	 * @param  string $keyword  Focus keyword.
	 * @return boolean
	 */
	public static function text_has_keyword( string $haystack, string $keyword ): bool {
		$haystack = self::normalize_for_keyword_match( $haystack );
		$keyword  = self::normalize_for_keyword_match( $keyword );
		if ( '' === $haystack || '' === $keyword ) {
			return false;
		}

		return false !== ( function_exists( 'mb_strpos' ) ? mb_strpos( $haystack, $keyword ) : strpos( $haystack, $keyword ) );
	}//end text_has_keyword()


	/**
	 * Normalize text for keyword matching.
	 *
	 * @since  1.2.1
	 * @param  string $text Text.
	 * @return string
	 */
	private static function normalize_for_keyword_match( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ( ENT_QUOTES | ENT_HTML5 ), 'UTF-8' );
		$text = ( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
		$text = trim( $text );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}//end normalize_for_keyword_match()


	/**
	 * Whether the keyword appears in the first 10% of content words (Rank Math rule).
	 *
	 * Under 300 words, Rank Math checks the whole content.
	 *
	 * @since  1.2.1
	 * @param  string $content HTML content.
	 * @param  string $keyword Focus keyword.
	 * @return boolean
	 */
	public static function keyword_in_beginning( string $content, string $keyword ): bool {
		$plain   = self::normalize_for_keyword_match( $content );
		$keyword = self::normalize_for_keyword_match( $keyword );
		if ( '' === $plain || '' === $keyword ) {
			return false;
		}

		$words = preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) || empty( $words ) ) {
			return false;
		}

		$total = count( $words );
		$limit = ( $total < 300 ) ? $total : max( 1, (int) ceil( $total * 0.10 ) );
		$start = implode( ' ', array_slice( $words, 0, $limit ) );

		return false !== ( function_exists( 'mb_strpos' ) ? mb_strpos( $start, $keyword ) : strpos( $start, $keyword ) );
	}//end keyword_in_beginning()


	/**
	 * Ensure focus keyword exists in content and in the opening section.
	 *
	 * Fixes Rank Math: "Focus Keyword doesn't appear in the content" and
	 * "Focus Keyword doesn't appear at the beginning of your content."
	 *
	 * @since  1.2.1
	 * @param  string $content HTML content.
	 * @param  string $keyword Focus keyword.
	 * @return string
	 */
	public static function ensure_focus_keyword_in_content( string $content, string $keyword ): string {
		$keyword = sanitize_text_field( $keyword );
		if ( '' === $keyword || '' === trim( $content ) ) {
			return $content;
		}

		$in_content   = self::text_has_keyword( $content, $keyword );
		$in_beginning = self::keyword_in_beginning( $content, $keyword );

		if ( $in_content && $in_beginning ) {
			return $content;
		}

		$updated = self::inject_keyword_into_opening_paragraph( $content, $keyword );
		if ( ! self::text_has_keyword( $updated, $keyword ) || ! self::keyword_in_beginning( $updated, $keyword ) ) {
			$updated = self::prepend_keyword_intro( $updated, $keyword );
		}

		/*
		 * Filter content after focus-keyword enforcement.
		 *
		 * @since 1.2.1
		 * @param string $updated Modified HTML.
		 * @param string $keyword Focus keyword.
		 * @param string $content Original HTML.
		 */
		return (string) apply_filters( 'draftcraft_after_focus_keyword_ensure', $updated, $keyword, $content );
	}//end ensure_focus_keyword_in_content()


	/**
	 * Weave the focus keyword into the first non-empty paragraph.
	 *
	 * @since  1.2.1
	 * @param  string $content HTML.
	 * @param  string $keyword Focus keyword.
	 * @return string
	 */
	private static function inject_keyword_into_opening_paragraph( string $content, string $keyword ): string {
		$done = false;

		$updated = preg_replace_callback(
			'/<p(\s[^>]*)?>(.*?)<\/p>/is',
			static function ( $m ) use ( $keyword, &$done ) {
				if ( $done ) {
					return $m[0];
				}

				$attrs = ( $m[1] ?? '' );
				$inner = $m[2];
				$text  = trim( wp_strip_all_tags( $inner ) );

				if ( '' === $text ) {
					return $m[0];
				}

				$done = true;
				$lead = esc_html( $keyword );

				// Already in this paragraph — leave as-is (avoids doubling the phrase).
				if ( DraftCraft_SEO::text_has_keyword( $text, $keyword ) ) {
					return $m[0];
				}

				return '<p' . $attrs . '><strong>' . $lead . '</strong> is the focus of this guide. ' . $inner . '</p>';
			},
			$content,
			1
		);

		return is_string( $updated ) ? $updated : $content;
	}//end inject_keyword_into_opening_paragraph()


	/**
	 * Prepend a short intro paragraph that contains the focus keyword.
	 *
	 * @since  1.2.1
	 * @param  string $content HTML.
	 * @param  string $keyword Focus keyword.
	 * @return string
	 */
	private static function prepend_keyword_intro( string $content, string $keyword ): string {
		$lead  = esc_html( $keyword );
		$intro = '<p>This guide explains <strong>' . $lead . '</strong> in practical terms so you can apply it with confidence.</p>' . "\n";
		return $intro . ltrim( $content );
	}//end prepend_keyword_intro()
}//end class
