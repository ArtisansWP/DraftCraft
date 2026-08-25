<?php
/**
 * DraftCraft — Auto Table of Contents
 *
 * Builds a TOC from H2/H3 headings in generated content, adds heading IDs,
 * and registers DraftCraft with Rank Math's TOC plugin detection.
 *
 * @package DraftCraft
 * @since   1.2.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DraftCraft_TOC
 */
class DraftCraft_TOC {



	/**
	 * Boot hooks.
	 *
	 * @since 1.2.1
	 */
	public static function init(): void {
		add_filter( 'rank_math/researches/toc_plugins', array( __CLASS__, 'register_rank_math_toc_plugin' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_styles' ) );
		add_filter( 'the_content', array( __CLASS__, 'fix_toc_labels_on_display' ), 12 );
	}//end init()


	/**
	 * Whether TOC generation is enabled.
	 *
	 * @since  1.2.1
	 * @param  array $settings Plugin settings.
	 * @return boolean
	 */
	public static function is_enabled( array $settings ): bool {
		return ! empty( $settings['toc_enabled'] ) && '1' === $settings['toc_enabled'];
	}//end is_enabled()


	/**
	 * Tell Rank Math that DraftCraft provides Table of Contents.
	 *
	 * Rank Math does not scan HTML for a TOC — it checks for known plugins.
	 *
	 * @since  1.2.1
	 * @param  array $plugins Map of plugin-file => label.
	 * @return array
	 */
	public static function register_rank_math_toc_plugin( array $plugins ): array {
		$settings = function_exists( 'draftcraft_get_settings' ) ? draftcraft_get_settings() : array();
		if ( ! self::is_enabled( $settings ) ) {
			return $plugins;
		}

		$plugins['draftcraft/draftcraft.php'] = 'DraftCraft';
		return $plugins;
	}//end register_rank_math_toc_plugin()


	/**
	 * Light frontend styles for the TOC block + scroll offset for sticky headers.
	 *
	 * @since 1.2.1
	 */
	public static function enqueue_frontend_styles(): void {
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$settings = function_exists( 'draftcraft_get_settings' ) ? draftcraft_get_settings() : array();
		if ( ! self::is_enabled( $settings ) ) {
			return;
		}

		$offset = isset( $settings['toc_scroll_offset'] ) ? absint( $settings['toc_scroll_offset'] ) : 96;
		if ( $offset > 400 ) {
			$offset = 400;
		}

		// Transparent box — colors inherit from the theme.
		$css = '
			.draftcraft-toc,
			.wp-block-group.draftcraft-toc{margin:1.5em 0 2em;padding:0;border:0;background:transparent}
			.draftcraft-toc__title{margin:0 0 .75em;font-size:1.1em;font-weight:600}
			.draftcraft-toc ol{margin:0;padding-left:1.25em}
			.draftcraft-toc li{margin:.35em 0}
			.draftcraft-toc a{text-decoration:none}
			.draftcraft-toc a:hover,.draftcraft-toc a:focus{text-decoration:underline}
			.draftcraft-toc .draftcraft-toc__sub{margin-top:.25em;padding-left:1.1em}
		';

		if ( $offset > 0 ) {
			$css .= sprintf(
				'
				:root{--draftcraft-toc-offset:%1$dpx}
				.single-post :is(h2,h3,h4)[id],
				.prose-wp :is(h2,h3,h4)[id],
				.entry-content :is(h2,h3,h4)[id],
				.wp-block-post-content :is(h2,h3,h4)[id]{
					scroll-margin-top:var(--draftcraft-toc-offset)
				}
				',
				$offset
			);
		}

		wp_register_style( 'draftcraft-toc', false, array(), DRAFTCRAFT_VERSION );
		wp_enqueue_style( 'draftcraft-toc' );
		wp_add_inline_style( 'draftcraft-toc', $css );

		if ( $offset > 0 ) {
			wp_register_script( 'draftcraft-toc-offset', false, array(), DRAFTCRAFT_VERSION, true );
			wp_enqueue_script( 'draftcraft-toc-offset' );
			$offset_js = sprintf(
				'(function(){var o=%d;function go(id){if(!id)return;var el=document.getElementById(id);if(!el)return;var y=el.getBoundingClientRect().top+window.pageYOffset-o;window.scrollTo({top:Math.max(0,y),behavior:"smooth"});}document.addEventListener("click",function(e){var a=e.target.closest(".draftcraft-toc a[href^=\"#\"],.wp-block-group.draftcraft-toc a[href^=\"#\"]");if(!a)return;var href=a.getAttribute("href")||"";if(href.charAt(0)!=="#")return;var id=decodeURIComponent(href.slice(1));if(!document.getElementById(id))return;e.preventDefault();history.pushState(null,"",href);go(id);},true);if(location.hash){requestAnimationFrame(function(){go(decodeURIComponent(location.hash.slice(1)));});}})();',
				(int) $offset
			);
			wp_add_inline_script( 'draftcraft-toc-offset', $offset_js );
		}
	}//end enqueue_frontend_styles()


	/**
	 * Inject a TOC into HTML content and ensure heading IDs exist.
	 *
	 * FAQ blocks are excluded from the TOC and always appended at the end.
	 *
	 * @since  1.2.1
	 * @param  string $content  Post HTML.
	 * @param  array  $settings Plugin settings.
	 * @return string
	 */
	public static function process_content( string $content, array $settings ): string {
		if ( ! self::is_enabled( $settings ) || '' === trim( $content ) ) {
			return $content;
		}

		if ( false !== stripos( $content, 'draftcraft-toc' ) ) {
			return $content;
		}

		// Keep FAQ out of the TOC and pin it to the end of the post.
		$split   = self::split_faq_block( $content );
		$content = $split['content'];
		$faq     = $split['faq'];

		$headings = array();
		$used_ids = array();

		$updated = preg_replace_callback(
			'/<h([23])(\s[^>]*)?>(.*?)<\/h\1>/is',
			static function ( $m ) use ( &$headings, &$used_ids ) {
				$level = (int) $m[1];
				$attrs = ( $m[2] ?? '' );
				$inner = $m[3];
				$text  = trim( wp_strip_all_tags( $inner ) );

				if ( '' === $text ) {
					return $m[0];
				}

				// Skip any leftover FAQ headings (unwrapped / malformed markup).
				if ( DraftCraft_TOC::is_faq_heading( $text, $attrs ) ) {
					return $m[0];
				}

				$id = '';
				if ( preg_match( '/\sid=(["\'])(.*?)\1/i', $attrs, $id_m ) ) {
					$id = sanitize_title( $id_m[2] );
				}

				if ( '' === $id ) {
					$id = sanitize_title( $text );
				}

				if ( '' === $id ) {
					$id = 'section-' . ( count( $headings ) + 1 );
				}

				$base = $id;
				$n    = 2;
				while ( isset( $used_ids[ $id ] ) ) {
					$id = $base . '-' . $n;
					$n++;
				}

				$used_ids[ $id ] = true;

				$headings[] = array(
					'level' => $level,
					'id'    => $id,
					'text'  => $text,
				);

				if ( preg_match( '/\sid=(["\']).*?\1/i', $attrs ) ) {
					$attrs = preg_replace( '/\sid=(["\']).*?\1/i', ' id="' . esc_attr( $id ) . '"', $attrs );
				} else {
					$attrs .= ' id="' . esc_attr( $id ) . '"';
				}

				return '<h' . $level . $attrs . '>' . $inner . '</h' . $level . '>';
			},
			$content
		);

		if ( null === $updated ) {
			return $content . $faq;
		}

		if ( count( $headings ) < 2 ) {
			return $updated . $faq;
		}

		$toc = self::build_toc_html( $headings );

		/*
		 * Filter the generated TOC HTML.
		 *
		 * @since 1.2.1
		 * @param string $toc      TOC markup.
		 * @param array  $headings Heading data.
		 * @param string $content  Content with heading IDs.
		 */
		$toc = (string) apply_filters( 'draftcraft_toc_html', $toc, $headings, $updated );

		if ( preg_match( '/<\/p>/i', $updated, $m, PREG_OFFSET_CAPTURE ) ) {
			$pos     = ( $m[0][1] + strlen( $m[0][0] ) );
			$updated = substr( $updated, 0, $pos ) . "\n" . $toc . "\n" . substr( $updated, $pos );
		} else {
			$updated = $toc . "\n" . $updated;
		}

		$faq_suffix = ! empty( $faq ) ? "\n" . $faq : '';
		return $updated . $faq_suffix;
	}//end process_content()


	/**
	 * Pull the FAQ block out of content so it can be re-appended at the end.
	 *
	 * @since  1.2.1
	 * @param  string $content Post HTML.
	 * @return array{content: string, faq: string}
	 */
	public static function split_faq_block( string $content ): array {
		// Match class "draftcraft-faq" only — not draftcraft-faq-item / -q / -a.
		if ( preg_match_all( '/<(div|section)[^>]*class=["\']([^"\']*)["\'][^>]*>/i', $content, $all, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $all[0] as $i => $tag_m ) {
				$classes = preg_split( '/\s+/', trim( $all[2][ $i ][0] ) );
				if ( ! is_array( $classes ) || ! in_array( 'draftcraft-faq', $classes, true ) ) {
					continue;
				}

				$tag   = strtolower( $all[1][ $i ][0] );
				$start = (int) $tag_m[1];
				$end   = self::find_matching_tag_end( $content, $start, $tag );
				if ( $end > $start ) {
					$faq  = substr( $content, $start, ( $end - $start ) );
					$body = substr( $content, 0, $start ) . substr( $content, $end );
					return array(
						'content' => trim( $body ),
						'faq'     => trim( $faq ),
					);
				}
			}
		}

		// Fallback when AI omitted the draftcraft-faq wrapper.
		if ( preg_match( '/<h2[^>]*>\s*Frequently Asked Questions\s*<\/h2>.*$/is', $content, $m, PREG_OFFSET_CAPTURE ) ) {
			$start = (int) $m[0][1];
			return array(
				'content' => trim( substr( $content, 0, $start ) ),
				'faq'     => trim( substr( $content, $start ) ),
			);
		}

		return array(
			'content' => $content,
			'faq'     => '',
		);
	}//end split_faq_block()


	/**
	 * Find the end offset (exclusive) of a matching element starting at $start.
	 *
	 * @since  1.2.1
	 * @param  string  $html  Full HTML.
	 * @param  integer $start Offset of opening tag.
	 * @param  string  $tag   Tag name (div|section).
	 * @return integer
	 */
	private static function find_matching_tag_end( string $html, int $start, string $tag = 'div' ): int {
		$tag   = strtolower( $tag );
		$len   = strlen( $html );
		$depth = 0;
		$pos   = $start;
		$open  = '/<\/?' . preg_quote( $tag, '/' ) . '\b[^>]*>/i';

		while ( $pos < $len ) {
			if ( ! preg_match( $open, $html, $tm, PREG_OFFSET_CAPTURE, $pos ) ) {
				break;
			}

			$found  = $tm[0][0];
			$tagpos = (int) $tm[0][1];

			if ( 0 === strncasecmp( $found, '</', 2 ) ) {
				--$depth;
				$pos = ( $tagpos + strlen( $found ) );
				if ( 0 === $depth ) {
					return $pos;
				}

				continue;
			}

			if ( preg_match( '/\/>\s*$/', $found ) ) {
				$pos = ( $tagpos + strlen( $found ) );
				continue;
			}

			++$depth;
			$pos = ( $tagpos + strlen( $found ) );
		}//end while

		return $len;
	}//end find_matching_tag_end()


	/**
	 * Whether a heading belongs to the FAQ section.
	 *
	 * @since  1.2.1
	 * @param  string $text  Heading text.
	 * @param  string $attrs Heading attributes.
	 * @return boolean
	 */
	public static function is_faq_heading( string $text, string $attrs = '' ): bool {
		if ( preg_match( '/\bdraftcraft-faq-q\b/i', $attrs ) ) {
			return true;
		}

		return (bool) preg_match( '/^frequently asked questions$/i', trim( $text ) );
	}//end is_faq_heading()


	/**
	 * Build nested TOC list HTML from headings.
	 *
	 * @since  1.2.1
	 * @param  array $headings Heading rows.
	 * @return string
	 */
	private static function build_toc_html( array $headings ): string {
		$title = __( 'Table of Contents', 'draftcraft' );

		/*
		 * Filter the TOC title.
		 *
		 * @since 1.2.1
		 * @param string $title TOC heading text.
		 */
		$title = (string) apply_filters( 'draftcraft_toc_title', $title );

		$html  = '<nav class="draftcraft-toc" aria-label="' . esc_attr( $title ) . '">';
		$html .= '<p class="draftcraft-toc__title"><strong>' . esc_html( $title ) . '</strong></p>';
		$html .= '<ol class="draftcraft-toc__list">';

		$i     = 0;
		$count = count( $headings );
		while ( $i < $count ) {
			$h = $headings[ $i ];
			if ( 2 === (int) $h['level'] ) {
				$html .= '<li><a href="#' . esc_attr( $h['id'] ) . '">' . esc_html( self::toc_label( $h['text'] ) ) . '</a>';
				$subs  = array();
				$j     = ( $i + 1 );
				while ( $j < $count && 3 === (int) $headings[ $j ]['level'] ) {
					$subs[] = $headings[ $j ];
					++$j;
				}

				if ( $subs ) {
					$html .= '<ol class="draftcraft-toc__sub">';
					foreach ( $subs as $sub ) {
						$html .= '<li><a href="#' . esc_attr( $sub['id'] ) . '">' . esc_html( self::toc_label( $sub['text'] ) ) . '</a></li>';
					}

					$html .= '</ol>';
				}

				$html .= '</li>';
				$i     = $j;
				continue;
			}//end if

			$html .= '<li><a href="#' . esc_attr( $h['id'] ) . '">' . esc_html( self::toc_label( $h['text'] ) ) . '</a></li>';
			++$i;
		}//end while

		$html .= '</ol></nav>';
		return $html;
	}//end build_toc_html()


	/**
	 * Strip leading numerals from TOC labels so <ol> markers are not duplicated.
	 *
	 * For example: "1. Secure Your Router" → "Secure Your Router"
	 *
	 * @since  1.2.1
	 * @param  string $text Heading text.
	 * @return string
	 */
	public static function toc_label( string $text ): string {
		$cleaned = preg_replace( '/^\s*\d+[\.\)\:\-]\s+/u', '', $text );
		$cleaned = is_string( $cleaned ) ? trim( $cleaned ) : $text;
		return '' !== $cleaned ? $cleaned : $text;
	}//end toc_label()


	/**
	 * Normalize already-saved TOC markup on the frontend.
	 *
	 * - Strip duplicated numerals from labels
	 * - Remove FAQ entries from the TOC
	 *
	 * FAQ placement is handled by DraftCraft_Schema::ensure_faq_at_end().
	 *
	 * @since  1.2.1
	 * @param  string $content Post content.
	 * @return string
	 */
	public static function fix_toc_labels_on_display( string $content ): string {
		if ( ! is_singular( 'post' ) || false === stripos( $content, 'draftcraft-toc' ) ) {
			return $content;
		}

		$updated = preg_replace_callback(
			'/(<nav[^>]*class="[^"]*draftcraft-toc[^"]*"[^>]*>)(.*?)(<\/nav>)/is',
			static function ( $m ) {
				$inner = $m[2];

				// Drop "Frequently Asked Questions" + its nested question links.
				$inner = preg_replace(
					'/<li>\s*<a[^>]*>\s*Frequently Asked Questions\s*<\/a>(?:\s*<ol class="draftcraft-toc__sub">.*?<\/ol>)?\s*<\/li>/is',
					'',
					$inner
				);

				$inner = preg_replace_callback(
					'/(<a\s[^>]*>)(.*?)(<\/a>)/is',
					static function ( $a ) {
						$text  = wp_strip_all_tags( $a[2] );
						$label = DraftCraft_TOC::toc_label( $text );
						return $a[1] . esc_html( $label ) . $a[3];
					},
					is_string( $inner ) ? $inner : $m[2]
				);

				return $m[1] . $inner . $m[3];
			},
			$content
		);

		return is_string( $updated ) ? $updated : $content;
	}//end fix_toc_labels_on_display()
}//end class
