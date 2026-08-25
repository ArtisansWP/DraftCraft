<?php
/**
 * DraftCraft — Smart Internal Linking Engine
 *
 * Internal linking pipeline:
 * 1) Rank published posts by category + topical overlap
 * 2) Insert contextual in-content links with safe phrase matching
 * 3) Guarantee a minimum via a high-relevance "Related reading" block when needed
 * 4) Strip outbound links whenever internal linking is enabled
 *
 * @package DraftCraft
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DraftCraft_Internal_Links
 */
class DraftCraft_Internal_Links {


	/**
	 * Max candidates to consider.
	 *
	 * @var int
	 */
	const MAX_CANDIDATES = 60;

	/**
	 * Default minimum internal links.
	 *
	 * @var int
	 */
	const MIN_LINKS = 2;

	/**
	 * Default maximum internal links.
	 *
	 * @var int
	 */
	const MAX_LINKS = 3;

	/**
	 * Minimum relevance score to use a candidate for contextual linking.
	 *
	 * @var float
	 */
	const MIN_CONTEXTUAL_SCORE = 22.0;

	/**
	 * Minimum score for related-reading fallback.
	 *
	 * @var float
	 */
	const MIN_RELATED_SCORE = 14.0;

	/**
	 * Minimum score to offer a post to the AI as a link target.
	 *
	 * @var float
	 */
	const MIN_PROMPT_SCORE = 18.0;


	/**
	 * Relevance presets → score thresholds.
	 *
	 * @since  1.2.1
	 * @param  array $settings Plugin settings.
	 * @return array{prompt:float,contextual:float,related:float,key:string}
	 */
	public static function get_score_thresholds( array $settings = array() ): array {
		$key = sanitize_key( (string) ( $settings['internal_links_relevance'] ?? 'balanced' ) );
		if ( ! in_array( $key, array( 'strict', 'balanced', 'loose' ), true ) ) {
			$key = 'balanced';
		}

		$presets = array(
			'strict'   => array(
				'prompt'     => 28.0,
				'contextual' => 30.0,
				'related'    => 20.0,
			),
			'balanced' => array(
				'prompt'     => self::MIN_PROMPT_SCORE,
				'contextual' => self::MIN_CONTEXTUAL_SCORE,
				'related'    => self::MIN_RELATED_SCORE,
			),
			'loose'    => array(
				'prompt'     => 10.0,
				'contextual' => 12.0,
				'related'    => 6.0,
			),
		);

		$thresholds        = $presets[ $key ];
		$thresholds['key'] = $key;

		/*
		 * Filter internal-link score thresholds.
		 *
		 * @since 1.2.1
		 * @param array $thresholds Threshold map.
		 * @param array $settings   Plugin settings.
		 */
		return (array) apply_filters( 'draftcraft_internal_link_thresholds', $thresholds, $settings );
	}//end get_score_thresholds()


	/**
	 * Whether internal linking is enabled.
	 *
	 * @since  1.2.0
	 * @param  array $settings Plugin settings.
	 * @return boolean
	 */
	public static function is_enabled( array $settings ): bool {
		return ! empty( $settings['internal_links_enabled'] ) && '1' === $settings['internal_links_enabled'];
	}//end is_enabled()


	/**
	 * English stopwords / weak anchors that must never become single-word links.
	 *
	 * @since  1.2.1
	 * @return string[]
	 */
	public static function stopwords(): array {
		static $words = null;
		if ( null !== $words ) {
			return $words;
		}

		$words = array(
			'a',
			'an',
			'the',
			'and',
			'or',
			'but',
			'if',
			'then',
			'else',
			'when',
			'at',
			'by',
			'for',
			'with',
			'about',
			'against',
			'between',
			'into',
			'through',
			'during',
			'before',
			'after',
			'above',
			'below',
			'to',
			'from',
			'up',
			'down',
			'in',
			'out',
			'on',
			'off',
			'over',
			'under',
			'again',
			'further',
			'once',
			'here',
			'there',
			'all',
			'any',
			'both',
			'each',
			'few',
			'more',
			'most',
			'other',
			'some',
			'such',
			'no',
			'nor',
			'not',
			'only',
			'own',
			'same',
			'so',
			'than',
			'too',
			'very',
			'can',
			'will',
			'just',
			'don',
			'should',
			'now',
			'also',
			'into',
			'your',
			'you',
			'our',
			'their',
			'this',
			'that',
			'these',
			'those',
			'what',
			'which',
			'who',
			'whom',
			'how',
			'why',
			'is',
			'are',
			'was',
			'were',
			'be',
			'been',
			'been',
			'have',
			'has',
			'had',
			'do',
			'does',
			'did',
			'of',
			'as',
			'it',
			'its',
			'we',
			'they',
			'he',
			'she',
			'his',
			'her',
			'guide',
			'guides',
			'tips',
			'tip',
			'best',
			'top',
			'new',
			'free',
			'easy',
			'ways',
			'way',
			'things',
			'thing',
			'ideas',
			'idea',
			'post',
			'posts',
			'blog',
			'article',
			'articles',
			'complete',
			'ultimate',
			'essential',
			'simple',
			'powerful',
			'effective',
			'important',
			'using',
			'make',
			'made',
			'get',
			'got',
			'like',
			'need',
			'know',
			'learn',
			'help',
			'use',
		);

		/*
		 * Filter internal-link stopwords.
		 *
		 * @since 1.2.1
		 * @param string[] $words Stopwords.
		 */
		$words = array_values( array_unique( array_map( 'strtolower', (array) apply_filters( 'draftcraft_internal_link_stopwords', $words ) ) ) );
		return $words;
	}//end stopwords()


	/**
	 * Fetch published posts for linking.
	 *
	 * @since  1.2.0
	 * @param  string  $post_type  Target post type.
	 * @param  integer $exclude_id Post ID to exclude.
	 * @return array<int, array{id:int,title:string,url:string,categories:string,category_ids:int[]}>
	 */
	public static function get_link_candidates( string $post_type = 'post', int $exclude_id = 0 ): array {
		$cache_key = 'draftcraft_link_candidates_v2_' . md5( $post_type );
		$cached    = get_transient( $cache_key );

		if ( ! is_array( $cached ) ) {
			$query = new WP_Query(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'publish',
					'posts_per_page'         => self::MAX_CANDIDATES,
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => true,
					'fields'                 => 'ids',
				)
			);

			$cached = array();
			foreach ( $query->posts as $pid ) {
				$pid   = absint( $pid );
				$cats  = array();
				$ids   = array();
				$terms = get_the_terms( $pid, self::resolve_taxonomy( $post_type ) );
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$cats[] = $term->name;
						$ids[]  = (int) $term->term_id;
					}
				}

				$cached[] = array(
					'id'           => $pid,
					'title'        => get_the_title( $pid ),
					'url'          => get_permalink( $pid ),
					'categories'   => implode( ', ', $cats ),
					'category_ids' => $ids,
				);
			}

			set_transient( $cache_key, $cached, ( 10 * MINUTE_IN_SECONDS ) );
		}//end if

		/*
		 * Filter the internal link candidate list.
		 *
		 * @since 1.2.0
		 * @param array  $cached    Candidate posts.
		 * @param string $post_type Post type.
		 */
		$cached = (array) apply_filters( 'draftcraft_link_candidates', $cached, $post_type );

		if ( $exclude_id > 0 ) {
			$cached = array_values(
				array_filter(
					$cached,
					static fn( $p ) => (int) ( $p['id'] ?? 0 ) !== $exclude_id
				)
			);
		}

		return $cached;
	}//end get_link_candidates()


	/**
	 * Resolve taxonomy for posts.
	 *
	 * @since  1.2.0
	 * @param  string $post_type Post type.
	 * @return string
	 */
	private static function resolve_taxonomy( string $post_type ): string {
		if ( 'post' === $post_type && function_exists( 'draftcraft_get_target_taxonomy' ) ) {
			return draftcraft_get_target_taxonomy();
		}

		$taxonomies = get_object_taxonomies( $post_type );
		return ! empty( $taxonomies ) ? (string) reset( $taxonomies ) : 'category';
	}//end resolve_taxonomy()


	/**
	 * Clear candidate cache when publish state changes.
	 *
	 * @since 1.2.0
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function maybe_bust_cache( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			delete_transient( 'draftcraft_link_candidates_v2_' . md5( $post->post_type ) );
			// Legacy key from earlier versions.
			delete_transient( 'draftcraft_link_candidates_' . md5( $post->post_type ) );
		}
	}//end maybe_bust_cache()


	/**
	 * Score and sort candidates for the current article context.
	 *
	 * @since  1.2.1
	 * @param  array $candidates Candidates.
	 * @param  array $context    {
	 *     Context array for ranking.
	 *
	 *     @type int[]  $category_ids  Target category IDs.
	 *     @type string $content       HTML or plain content.
	 *     @type string $focus_keyword Focus keyword.
	 * }
	 * @return array Ranked candidates with `score` key.
	 */
	public static function rank_candidates( array $candidates, array $context = array() ): array {
		$target_cats    = array_map( 'intval', (array) ( $context['category_ids'] ?? array() ) );
		$keyword        = strtolower( trim( (string) ( $context['focus_keyword'] ?? '' ) ) );
		$content        = strtolower( wp_strip_all_tags( (string) ( $context['content'] ?? '' ) ) );
		$content_map    = array_fill_keys( self::tokenize( $content ), true );
		$keyword_tokens = self::tokenize( $keyword );

		$ranked = array();
		foreach ( $candidates as $i => $candidate ) {
			$title = trim( (string) ( $candidate['title'] ?? '' ) );
			$url   = (string) ( $candidate['url'] ?? '' );
			if ( '' === $title || '' === $url ) {
				continue;
			}

			$score     = 0.0;
			$cand_cats = array_map( 'intval', (array) ( $candidate['category_ids'] ?? array() ) );

			// Same-category boost (strongest signal).
			$shared = array_intersect( $target_cats, $cand_cats );
			if ( $shared ) {
				$score += ( 40 + ( count( $shared ) * 12 ) );
			}

			$title_tokens = self::tokenize( $title );
			if ( empty( $title_tokens ) ) {
				continue;
			}

			// Title tokens present in the article body.
			$overlap = 0;
			foreach ( $title_tokens as $token ) {
				if ( isset( $content_map[ $token ] ) ) {
					++$overlap;
					$score += ( strlen( $token ) >= 7 ) ? 6 : 3.5;
				}
			}

			if ( $overlap >= 2 ) {
				$score += 8;
			}

			// Focus keyword overlap with candidate title.
			foreach ( $keyword_tokens as $token ) {
				if ( in_array( $token, $title_tokens, true ) ) {
					$score += 10;
				}
			}

			// Slight recency preference (list is newest-first) — keep tiny so it
			// cannot outweigh topical relevance.
			$score += max( 0, ( 2 - ( $i * 0.03 ) ) );

			$candidate['score']        = round( $score, 2 );
			$candidate['title_tokens'] = $title_tokens;
			$ranked[]                  = $candidate;
		}//end foreach

		usort(
			$ranked,
			static function ( $a, $b ) {
				return ( $b['score'] <=> $a['score'] );
			}
		);

		/*
		 * Filter ranked internal-link candidates.
		 *
		 * @since 1.2.1
		 * @param array $ranked  Ranked candidates.
		 * @param array $context Ranking context.
		 */
		return (array) apply_filters( 'draftcraft_ranked_link_candidates', $ranked, $context );
	}//end rank_candidates()


	/**
	 * Tokenize text into meaningful lowercase words.
	 *
	 * @since  1.2.1
	 * @param  string $text Text.
	 * @return string[]
	 */
	private static function tokenize( string $text ): array {
		$text  = strtolower( $text );
		$text  = ( preg_replace( '/[^a-z0-9\s\-]/i', ' ', $text ) ?? $text );
		$parts = preg_split( '/[\s\-]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$stop = array_fill_keys( self::stopwords(), true );
		$out  = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( strlen( $part ) < 4 || isset( $stop[ $part ] ) ) {
				continue;
			}

			$out[ $part ] = true;
		}

		return array_keys( $out );
	}//end tokenize()


	/**
	 * Build LLM context from the best-ranked candidates.
	 *
	 * @since  1.2.0
	 * @param  array $candidates Ranked or raw candidates.
	 * @param  array $settings   Optional plugin settings (relevance threshold).
	 * @return string
	 */
	public static function build_prompt_context( array $candidates, array $settings = array() ): string {
		if ( empty( $candidates ) ) {
			return '';
		}

		$thresholds = self::get_score_thresholds( $settings );
		$min_prompt = (float) ( $thresholds['prompt'] ?? self::MIN_PROMPT_SCORE );

		// Only offer clearly related posts — prevents "random" AI links.
		$strong = array_values(
			array_filter(
				$candidates,
				static fn( $c ) => (float) ( $c['score'] ?? 0 ) >= $min_prompt
			)
		);

		if ( empty( $strong ) ) {
			return 'No strongly related published posts were found for internal linking. Do NOT invent links and do NOT add filler hyperlinks. Write the article without internal links; DraftCraft may add a related-reading link later only if relevance is high.';
		}

		$lines = array();
		foreach ( array_slice( $strong, 0, 10 ) as $c ) {
			$lines[] = sprintf(
				'- ID:%d | "%s" | %s | cats:[%s] | relevance:%s',
				(int) ( $c['id'] ?? 0 ),
				( $c['title'] ?? '' ),
				( $c['url'] ?? '' ),
				( $c['categories'] ?? '' ),
				isset( $c['score'] ) ? (string) $c['score'] : 'n/a'
			);
		}

		$max_links = ( 'loose' === ( $thresholds['key'] ?? '' ) ) ? 3 : 2;

		return "High-relevance published posts for optional internal linking (use at most {$max_links}, only if a natural fit):\n" . implode( "\n", $lines ) . "\n\nLinking rules: Add 0–{$max_links} contextual hyperlinks using ONLY URLs from this list. Skip linking entirely if nothing is a clear topical match. Exact format: <a href=\"URL\" title=\"Post Title\">natural anchor text</a>. Never invent URLs, never link to external websites, and never force a weak/random link.";
	}//end build_prompt_context()


	/**
	 * Prompt instructions for the active linking method.
	 *
	 * @since  1.2.0
	 * @param  array $settings Plugin settings.
	 * @return string
	 */
	public static function get_prompt_instructions( array $settings = array() ): string {
		$method = ( $settings['internal_links_method'] ?? 'hybrid' );

		if ( 'local' === $method ) {
			return 'Do NOT include any hyperlinks (<a href>) in the content HTML. DraftCraft will insert high-relevance internal links after generation. Never link to external websites.';
		}

		return 'Internal links (optional): use only high-relevance posts from Context, at most 2. Format: <a href="URL" title="Title">anchor</a>. If no post is a clear topical match, include ZERO links — quality over quota. Never invent URLs and never link to external websites.';
	}//end get_prompt_instructions()


	/**
	 * Whether an href is on this site (or a page fragment).
	 *
	 * @since  1.2.1
	 * @param  string $href URL.
	 * @return boolean
	 */
	public static function is_internal_href( string $href ): bool {
		$href = trim( html_entity_decode( $href, ( ENT_QUOTES | ENT_HTML5 ) ) );
		if ( '' === $href ) {
			return true;
		}

		if ( isset( $href[0] ) && '#' === $href[0] ) {
			return true;
		}

		if ( 0 === stripos( $href, '/' ) && 0 !== stripos( $href, '//' ) ) {
			return true;
		}

		$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$href_host = wp_parse_url( $href, PHP_URL_HOST );
		if ( empty( $href_host ) ) {
			return true;
		}

		return is_string( $home_host )
			&& is_string( $href_host )
			&& strtolower( $home_host ) === strtolower( $href_host );
	}//end is_internal_href()


	/**
	 * Strip outbound links; keep anchor text.
	 *
	 * @since  1.2.1
	 * @param  string $content HTML.
	 * @return string
	 */
	public static function strip_external_links( string $content ): string {
		if ( '' === $content || false === stripos( $content, '<a' ) ) {
			return $content;
		}

		$updated = preg_replace_callback(
			'/<a\s([^>]*?)>(.*?)<\/a>/is',
			static function ( $m ) {
				$href = '';
				if ( preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/i', $m[1], $hm ) ) {
					$href = $hm[2];
				}

				if ( DraftCraft_Internal_Links::is_internal_href( $href ) ) {
					return $m[0];
				}

				return $m[2];
			},
			$content
		);

		return is_string( $updated ) ? $updated : $content;
	}//end strip_external_links()


	/**
	 * Count non-fragment internal links.
	 *
	 * @since  1.2.1
	 * @param  string $content HTML.
	 * @return integer
	 */
	public static function count_internal_links( string $content ): int {
		if ( ! preg_match_all( '/<a\s([^>]*?)>/i', $content, $matches, PREG_SET_ORDER ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $matches as $m ) {
			$href = '';
			if ( preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/i', $m[1], $hm ) ) {
				$href = $hm[2];
			}

			if ( '' === $href || ( isset( $href[0] ) && '#' === $href[0] ) ) {
				continue;
			}

			if ( self::is_internal_href( $href ) ) {
				++$count;
			}
		}

		return $count;
	}//end count_internal_links()


	/**
	 * Build safe anchor phrases from a candidate title.
	 *
	 * @since  1.2.1
	 * @param  string $title Title.
	 * @return string[]
	 */
	private static function build_anchor_phrases( string $title ): array {
		$title = trim( preg_replace( '/\s+/u', ' ', $title ) ?? $title );
		if ( '' === $title ) {
			return array();
		}

		$raw_words = preg_split( '/\s+/', $title );
		$raw_words = is_array( $raw_words ) ? array_values( array_filter( array_map( 'trim', $raw_words ) ) ) : array();
		$stop      = array_fill_keys( self::stopwords(), true );
		$phrases   = array();

		if ( count( $raw_words ) >= 3 ) {
			$phrases[] = implode( ' ', array_slice( $raw_words, 0, 4 ) );
			$phrases[] = implode( ' ', array_slice( $raw_words, 0, 3 ) );
		}

		if ( count( $raw_words ) >= 2 ) {
			$bigrams = array();
			for ( $i = 0, $n = ( count( $raw_words ) - 1 ); $i < $n; $i++ ) {
				$a = strtolower( ( preg_replace( '/[^a-z0-9]/i', '', $raw_words[ $i ] ) ?? '' ) );
				$b = strtolower( ( preg_replace( '/[^a-z0-9]/i', '', $raw_words[ ( $i + 1 ) ] ) ?? '' ) );
				if ( strlen( $a ) < 4 || strlen( $b ) < 4 || isset( $stop[ $a ] ) || isset( $stop[ $b ] ) ) {
					continue;
				}

				$bigrams[] = $raw_words[ $i ] . ' ' . $raw_words[ ( $i + 1 ) ];
			}

			$phrases = array_merge( $phrases, array_slice( $bigrams, 0, 3 ) );
		}

		// Full title last if reasonably specific.
		$significant = 0;
		foreach ( $raw_words as $w ) {
			$n = strtolower( ( preg_replace( '/[^a-z0-9]/i', '', $w ) ?? '' ) );
			if ( strlen( $n ) >= 4 && ! isset( $stop[ $n ] ) ) {
				++$significant;
			}
		}

		if ( $significant >= 2 ) {
			$phrases[] = $title;
		}

		// Single tokens only if highly specific.
		foreach ( $raw_words as $w ) {
			$clean = strtolower( ( preg_replace( '/[^a-z0-9]/i', '', $w ) ?? '' ) );
			if ( strlen( $clean ) >= 7 && ! isset( $stop[ $clean ] ) ) {
				$phrases[] = $w;
			}
		}

		$phrases = array_values( array_unique( array_filter( array_map( 'trim', $phrases ) ) ) );

		// Prefer longer phrases first.
		usort(
			$phrases,
			static function ( $a, $b ) {
				return ( strlen( $b ) <=> strlen( $a ) );
			}
		);

		return $phrases;
	}//end build_anchor_phrases()


	/**
	 * Insert contextual links using ranked candidates.
	 *
	 * @since  1.2.0
	 * @param  string  $content    HTML.
	 * @param  array   $candidates Ranked candidates.
	 * @param  integer $min_links  Minimum.
	 * @param  integer $max_links  Maximum.
	 * @param  float   $min_score  Minimum candidate score.
	 * @return array{content:string,inserted:int}
	 */
	public static function inject_contextual_links( string $content, array $candidates, int $min_links = self::MIN_LINKS, int $max_links = self::MAX_LINKS, float $min_score = self::MIN_CONTEXTUAL_SCORE ): array {
		if ( '' === $content || empty( $candidates ) ) {
			return array(
				'content'  => $content,
				'inserted' => 0,
			);
		}

		$existing = self::count_internal_links( $content );
		if ( $existing >= $min_links ) {
			return array(
				'content'  => $content,
				'inserted' => 0,
			);
		}

		$needed    = min( $max_links, max( 0, ( $max_links - $existing ) ) );
		$inserted  = 0;
		$used_urls = array();

		foreach ( $candidates as $candidate ) {
			if ( $inserted >= $needed ) {
				break;
			}

			$score = (float) ( $candidate['score'] ?? 0 );
			if ( $score < $min_score ) {
				continue;
			}

			$title = trim( (string) ( $candidate['title'] ?? '' ) );
			$url   = esc_url( (string) ( $candidate['url'] ?? '' ) );
			if ( '' === $title || '' === $url || isset( $used_urls[ $url ] ) ) {
				continue;
			}

			if ( false !== stripos( $content, $url ) ) {
				continue;
			}

			foreach ( self::build_anchor_phrases( $title ) as $phrase ) {
				if ( strlen( $phrase ) < 5 ) {
					continue;
				}

				// Avoid matching inside tags or existing anchors.
				$pattern = '/(?![^<]*>|[^<>]*<\/a>)(' . preg_quote( $phrase, '/' ) . ')/iu';
				$link    = sprintf(
					'<a href="%s" title="%s">$1</a>',
					esc_url( $url ),
					esc_attr( $title )
				);

				$new = preg_replace( $pattern, $link, $content, 1, $count );
				if ( $count > 0 && is_string( $new ) ) {
					$content           = $new;
					$used_urls[ $url ] = true;
					++$inserted;
					break;
				}
			}//end foreach
		}//end foreach

		return array(
			'content'  => $content,
			'inserted' => $inserted,
		);
	}//end inject_contextual_links()


	/**
	 * Append a compact related-reading block from top remaining candidates.
	 *
	 * @since  1.2.1
	 * @param  string  $content    HTML.
	 * @param  array   $candidates Ranked candidates.
	 * @param  integer $needed     Links still needed.
	 * @param  float   $min_score  Minimum candidate score.
	 * @return array{content:string,inserted:int}
	 */
	public static function append_related_reading( string $content, array $candidates, int $needed, float $min_score = self::MIN_RELATED_SCORE ): array {
		$needed = max( 0, min( 2, $needed ) );
		// Keep the block focused and tight.
		if ( $needed < 1 || empty( $candidates ) ) {
			return array(
				'content'  => $content,
				'inserted' => 0,
			);
		}

		$links = array();
		foreach ( $candidates as $candidate ) {
			if ( count( $links ) >= $needed ) {
				break;
			}

			if ( (float) ( $candidate['score'] ?? 0 ) < $min_score ) {
				continue;
			}

			$title = trim( (string) ( $candidate['title'] ?? '' ) );
			$url   = esc_url( (string) ( $candidate['url'] ?? '' ) );
			if ( '' === $title || '' === $url ) {
				continue;
			}

			if ( false !== stripos( $content, $url ) ) {
				continue;
			}

			$links[] = '<a href="' . esc_url( $url ) . '" title="' . esc_attr( $title ) . '">' . esc_html( $title ) . '</a>';
		}//end foreach

		if ( empty( $links ) ) {
			return array(
				'content'  => $content,
				'inserted' => 0,
			);
		}

		$paragraph = '<p class="draftcraft-related-reading"><strong>' . esc_html__( 'Related reading:', 'draftcraft' ) . '</strong> ' . implode( ' · ', $links ) . '</p>';

		$split = class_exists( 'DraftCraft_TOC' ) ? DraftCraft_TOC::split_faq_block( $content ) : array(
			'content' => $content,
			'faq'     => '',
		);

		$faq_suffix = ! empty( $split['faq'] ) ? "\n\n" . $split['faq'] : '';
		$content    = rtrim( $split['content'] ) . "\n\n" . $paragraph . $faq_suffix;

		return array(
			'content'  => $content,
			'inserted' => count( $links ),
		);
	}//end append_related_reading()


	/**
	 * Full linking pipeline.
	 *
	 * @since  1.2.0
	 * @param  string  $content  HTML content.
	 * @param  array   $settings Plugin settings.
	 * @param  integer $post_id  Current post ID (0 before insert).
	 * @param  array   $context  Optional: category_ids, focus_keyword.
	 * @return string
	 */
	public static function process_content( string $content, array $settings, int $post_id = 0, array $context = array() ): string {
		if ( ! self::is_enabled( $settings ) || '' === trim( $content ) ) {
			return $content;
		}

		$content = self::strip_external_links( $content );

		$candidates = self::get_link_candidates( 'post', $post_id );
		if ( empty( $candidates ) ) {
			return $content;
		}

		$context['content'] = ( $context['content'] ?? $content );
		$ranked             = self::rank_candidates( $candidates, $context );
		$method             = ( $settings['internal_links_method'] ?? 'hybrid' );
		$thresholds         = self::get_score_thresholds( $settings );

		$before              = self::count_internal_links( $content );
		$inserted_contextual = 0;
		$inserted_related    = 0;

		// Local: always inject. Hybrid/AI: only fill a true gap (AI added nothing).
		$run_local  = ( 'local' === $method );
		$run_safety = in_array( $method, array( 'hybrid', 'llm' ), true ) && 0 === $before;

		if ( $run_local || $run_safety ) {
			$min = $run_local ? self::MIN_LINKS : 1;
			$max = $run_local ? self::MAX_LINKS : 2;

			$result              = self::inject_contextual_links(
				$content,
				$ranked,
				$min,
				$max,
				(float) ( $thresholds['contextual'] ?? self::MIN_CONTEXTUAL_SCORE )
			);
			$content             = $result['content'];
			$inserted_contextual = (int) $result['inserted'];

			$have = self::count_internal_links( $content );
			if ( $have < $min ) {
				$related          = self::append_related_reading(
					$content,
					$ranked,
					( $min - $have ),
					(float) ( $thresholds['related'] ?? self::MIN_RELATED_SCORE )
				);
				$content          = $related['content'];
				$inserted_related = (int) $related['inserted'];
			}
		}//end if

		/*
		 * Filter content after the full internal-linking pipeline.
		 *
		 * @since 1.2.1
		 * @param string $content             HTML.
		 * @param array  $meta                Insert stats.
		 * @param array  $ranked              Ranked candidates.
		 * @param array  $settings            Settings.
		 */
		return (string) apply_filters(
			'draftcraft_after_internal_links',
			$content,
			array(
				'method'               => $method,
				'contextual_inserted'  => $inserted_contextual,
				'related_inserted'     => $inserted_related,
				'total_internal_links' => self::count_internal_links( $content ),
			),
			$ranked,
			$settings
		);
	}//end process_content()
}//end class
