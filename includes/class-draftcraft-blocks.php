<?php
/**
 * DraftCraft — Convert AI HTML/Markdown into Gutenberg blocks.
 *
 * @package DraftCraft
 * @since   1.4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class DraftCraft_Blocks
 */
class DraftCraft_Blocks {



	/**
	 * Normalize AI output into Gutenberg block markup for the block editor.
	 *
	 * @since  1.4.0
	 * @param  string $content Raw HTML, Markdown, or mixed content.
	 * @return string
	 */
	public static function normalize_for_editor( string $content ): string {
		$content = trim( $content );
		if ( '' === $content ) {
			return '';
		}

		// Already proper block markup — leave alone.
		if ( self::is_block_markup( $content ) ) {
			return $content;
		}

		if ( self::looks_like_markdown( $content ) ) {
			$content = self::markdown_to_html( $content );
		}

		$content = self::html_to_blocks( $content );

		/*
		 * Filter Gutenberg-ready content after conversion.
		 *
		 * @since 1.4.0
		 * @param string $content Block markup.
		 */
		return (string) apply_filters( 'draftcraft_gutenberg_content', $content );
	}//end normalize_for_editor()


	/**
	 * Whether content is already Gutenberg block markup.
	 *
	 * @since  1.4.0
	 * @param  string $content Content.
	 * @return boolean
	 */
	public static function is_block_markup( string $content ): bool {
		return (bool) preg_match( '/<!--\s*wp:/', $content );
	}//end is_block_markup()


	/**
	 * Heuristic: treat as Markdown when it uses MD syntax without real HTML structure.
	 *
	 * @since  1.4.0
	 * @param  string $content Content.
	 * @return boolean
	 */
	public static function looks_like_markdown( string $content ): bool {
		$has_md_heading   = (bool) preg_match( '/^#{1,6}\s+\S/m', $content );
		$has_html_heading = (bool) preg_match( '/<h[1-6]\b/i', $content );
		$has_md_fence     = (bool) preg_match( '/^```/m', $content );
		$has_md_bold      = (bool) preg_match( '/\*\*[^*\n]+\*\*/', $content );
		$has_p_tags       = (bool) preg_match( '/<p\b/i', $content );

		if ( $has_md_heading && ! $has_html_heading ) {
			return true;
		}

		if ( $has_md_fence && ! preg_match( '/<pre\b/i', $content ) ) {
			return true;
		}

		if ( $has_md_bold && ! $has_p_tags && ! $has_html_heading ) {
			return true;
		}

		return false;
	}//end looks_like_markdown()


	/**
	 * Lightweight Markdown → HTML for common blog patterns.
	 *
	 * @since  1.4.0
	 * @param  string $markdown Markdown text.
	 * @return string
	 */
	public static function markdown_to_html( string $markdown ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", $markdown );
		$text = trim( $text );

		// Fenced code blocks.
		$text = preg_replace_callback(
			'/^```([a-z0-9+#-]*)\s*\n([\s\S]*?)^```/im',
			static function ( $m ) {
				$lang       = sanitize_html_class( strtolower( (string) $m[1] ) );
				$code       = htmlspecialchars( rtrim( $m[2] ), ( ENT_QUOTES | ENT_SUBSTITUTE ), 'UTF-8' );
				$class_attr = $lang ? ' class="language-' . $lang . '"' : '';
				return "\n<pre><code{$class_attr}>{$code}</code></pre>\n";
			},
			$text
		);

		$lines  = explode( "\n", (string) $text );
		$html   = array();
		$buffer = array();
		$list   = null;
		// List element type: ul, ol, or null.
		$flush_p = static function () use ( &$html, &$buffer ) {
			if ( empty( $buffer ) ) {
				return;
			}

			$para   = trim( implode( ' ', $buffer ) );
			$buffer = array();
			if ( '' === $para ) {
				return;
			}

			$html[] = '<p>' . DraftCraft_Blocks::inline_markdown( $para ) . '</p>';
		};

		$flush_list = static function () use ( &$html, &$list ) {
			if ( null === $list ) {
				return;
			}

			$html[] = '</' . $list . '>';
			$list   = null;
		};

		foreach ( $lines as $line ) {
			$trim = trim( $line );

			if ( '' === $trim ) {
				$flush_p();
				$flush_list();
				continue;
			}

			// Already-converted HTML chunks (e.g. code fences).
			if ( preg_match( '/^<(pre|h[1-6]|ul|ol|blockquote|section|div|p)\b/i', $trim ) ) {
				$flush_p();
				$flush_list();
				$html[] = $line;
				continue;
			}

			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $trim, $hm ) ) {
				$flush_p();
				$flush_list();
				$level  = strlen( $hm[1] );
				$html[] = '<h' . $level . '>' . self::inline_markdown( $hm[2] ) . '</h' . $level . '>';
				continue;
			}

			if ( preg_match( '/^[-*]\s+(.+)$/', $trim, $lm ) ) {
				$flush_p();
				if ( 'ul' !== $list ) {
					$flush_list();
					$html[] = '<ul>';
					$list   = 'ul';
				}

				$html[] = '<li>' . self::inline_markdown( $lm[1] ) . '</li>';
				continue;
			}

			if ( preg_match( '/^\d+\.\s+(.+)$/', $trim, $om ) ) {
				$flush_p();
				if ( 'ol' !== $list ) {
					$flush_list();
					$html[] = '<ol>';
					$list   = 'ol';
				}

				$html[] = '<li>' . self::inline_markdown( $om[1] ) . '</li>';
				continue;
			}

			if ( preg_match( '/^>\s?(.*)$/', $trim, $qm ) ) {
				$flush_p();
				$flush_list();
				$html[] = '<blockquote><p>' . self::inline_markdown( $qm[1] ) . '</p></blockquote>';
				continue;
			}

			$buffer[] = $trim;
		}//end foreach

		$flush_p();
		$flush_list();

		return implode( "\n", $html );
	}//end markdown_to_html()


	/**
	 * Inline Markdown (bold/italic/code/links).
	 *
	 * @since  1.4.0
	 * @param  string $text Text.
	 * @return string
	 */
	public static function inline_markdown( string $text ): string {
		$text = htmlspecialchars( $text, ( ENT_QUOTES | ENT_SUBSTITUTE ), 'UTF-8' );
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
		$text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text );
		$text = preg_replace( '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2">$1</a>', $text );
		return $text;
	}//end inline_markdown()


	/**
	 * Merge consecutive &lt;p&gt;&lt;code&gt;…&lt;/code&gt;&lt;/p&gt; runs into a real &lt;pre&gt;&lt;code&gt; block.
	 *
	 * AI often emits one code chip per line; Gutenberg then shows green inline chips
	 * instead of a unified code panel.
	 *
	 * @since  1.4.0
	 * @param  string $html HTML.
	 * @return string
	 */
	public static function coalesce_code_paragraphs( string $html ): string {
		// Two or more consecutive code-only paragraphs → <pre><code>.
		$html = preg_replace_callback(
			'/(?:<p(?:\s[^>]*)?>\s*<code(?:\s[^>]*)?>[\s\S]*?<\/code>\s*<\/p>\s*){2,}/i',
			static function ( $m ) {
				if ( ! preg_match_all( '/<p(?:\s[^>]*)?>\s*<code(?:\s[^>]*)?>([\s\S]*?)<\/code>\s*<\/p>/i', $m[0], $parts ) ) {
					return $m[0];
				}

				$lines = array();
				foreach ( $parts[1] as $inner ) {
					$lines[] = html_entity_decode( wp_strip_all_tags( $inner ), ( ENT_QUOTES | ENT_HTML5 ), 'UTF-8' );
				}

				$text = implode( "\n", $lines );
				return '<pre><code>' . esc_html( $text ) . '</code></pre>' . "\n";
			},
			$html
		);

		// Single code-only paragraph that already contains newlines or looks like a snippet.
		$html = preg_replace_callback(
			'/<p(?:\s[^>]*)?>\s*<code(?:\s[^>]*)?>([\s\S]*?)<\/code>\s*<\/p>/i',
			static function ( $m ) {
				$raw = html_entity_decode( wp_strip_all_tags( $m[1] ), ( ENT_QUOTES | ENT_HTML5 ), 'UTF-8' );
				if ( false === strpos( $raw, "\n" ) && strlen( $raw ) < 48 && ! preg_match( '/[{};$]|function\s|=>|::/', $raw ) ) {
					return $m[0];
				}

				return '<pre><code>' . esc_html( $raw ) . '</code></pre>' . "\n";
			},
			(string) $html
		);

		return (string) $html;
	}//end coalesce_code_paragraphs()


	/**
	 * Convert an HTML fragment into Gutenberg block comment markup.
	 *
	 * @since  1.4.0
	 * @param  string $html HTML.
	 * @return string
	 */
	public static function html_to_blocks( string $html ): string {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}

		if ( self::is_block_markup( $html ) ) {
			return $html;
		}

		$html = self::coalesce_code_paragraphs( $html );

		$wrapped = '<div id="draftcraft-root">' . $html . '</div>';
		$prev    = libxml_use_internal_errors( true );
		$dom     = new DOMDocument( '1.0', 'UTF-8' );
		$loaded  = $dom->loadHTML(
			'<?xml encoding="utf-8" ?>' . $wrapped,
			( LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD )
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( ! $loaded ) {
			return self::block( 'core/html', array(), $html );
		}

		$root = $dom->getElementById( 'draftcraft-root' );
		if ( ! $root ) {
			return self::block( 'core/html', array(), $html );
		}

		$parts = array();
     // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMDocument property.
		foreach ( iterator_to_array( $root->childNodes ) as $node ) {
			$parts[] = self::node_to_blocks( $node, $dom );
		}

		$out = trim( implode( "\n\n", array_filter( $parts ) ) );
		return '' !== $out ? $out : self::block( 'core/html', array(), $html );
	}//end html_to_blocks()


	/**
	 * Convert one DOM node into block markup.
	 *
	 * @since  1.4.0
	 * @param  DOMNode     $node Node.
	 * @param  DOMDocument $dom  Document.
	 * @return string
	 */
	private static function node_to_blocks( $node, DOMDocument $dom ): string {
     // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMNode property.
		if ( XML_TEXT_NODE === $node->nodeType ) {
      // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMNode property.
			$text = trim( ( $node->nodeValue ?? '' ) );
			if ( '' === $text ) {
				return '';
			}

			return self::block( 'core/paragraph', array(), '<p>' . esc_html( $text ) . '</p>' );
		}

     // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMNode property.
		if ( XML_ELEMENT_NODE !== $node->nodeType || ! $node instanceof DOMElement ) {
			return '';
		}

		$el = $node;
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMElement property.
		$tag  = strtolower( $el->tagName );
		$html = $dom->saveHTML( $el );

		// DraftCraft FAQ → editable group of headings/paragraphs.
		if ( self::element_has_class( $el, 'draftcraft-faq' ) ) {
			return self::faq_element_to_blocks( $el, $dom );
		}

		// TOC → group (still editable list).
		if ( self::element_has_class( $el, 'draftcraft-toc' ) ) {
			$inner = self::children_to_blocks( $el, $dom );
			return self::block(
				'core/group',
				array(
					'className' => 'draftcraft-toc',
					'layout'    => array( 'type' => 'constrained' ),
				),
				'<div class="wp-block-group draftcraft-toc">' . $inner . '</div>'
			);
		}

		switch ( $tag ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$level = (int) substr( $tag, 1 );
				return self::block( 'core/heading', array( 'level' => $level ), $html );

			case 'p':
				return self::block( 'core/paragraph', array(), $html );

			case 'ul':
			case 'ol':
				return self::block(
					'core/list',
					'ol' === $tag ? array( 'ordered' => true ) : array(),
					$html
				);

			case 'blockquote':
				return self::block( 'core/quote', array(), $html );

			case 'pre':
				$code = $el->getElementsByTagName( 'code' )->item( 0 );
       // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMElement property.
				$code_html = $code ? $dom->saveHTML( $code ) : '<code>' . esc_html( $el->textContent ) . '</code>';
				return self::block( 'core/code', array(), '<pre class="wp-block-code">' . $code_html . '</pre>' );

			case 'figure':
				return self::block( 'core/html', array(), $html );

			case 'img':
				return self::block( 'core/html', array(), $html );

			case 'hr':
				return self::block( 'core/separator', array(), '<hr class="wp-block-separator has-alpha-channel-opacity"/>' );

			case 'table':
				return self::block( 'core/table', array(), '<figure class="wp-block-table">' . $html . '</figure>' );

			case 'section':
			case 'div':
			case 'article':
			case 'nav':
			case 'aside':
				// Unwrap generic containers; keep meaningful class wrappers as groups.
				$class_name = trim( (string) $el->getAttribute( 'class' ) );
				if ( '' !== $class_name && ! preg_match( '/^(align|wp-)/', $class_name ) ) {
					$inner = self::children_to_blocks( $el, $dom );
					return self::block(
						'core/group',
						array(
							'className' => $class_name,
							'layout'    => array( 'type' => 'constrained' ),
						),
						'<div class="wp-block-group ' . esc_attr( $class_name ) . '">' . $inner . '</div>'
					);
				}
				return self::children_to_blocks( $el, $dom );

			default:
				// Skip empty wrappers / scripts.
				if ( in_array( $tag, array( 'script', 'style', 'link', 'meta' ), true ) ) {
					return '';
				}

				if ( '' === trim( wp_strip_all_tags( $html ) ) && ! preg_match( '/<(img|hr|br)\b/i', $html ) ) {
					return '';
				}
				return self::block( 'core/html', array(), $html );
		}//end switch
	}//end node_to_blocks()


	/**
	 * Convert FAQ section/div into editable Gutenberg blocks.
	 *
	 * @since  1.4.0
	 * @param  DOMElement  $el  FAQ element.
	 * @param  DOMDocument $dom Document.
	 * @return string
	 */
	private static function faq_element_to_blocks( DOMElement $el, DOMDocument $dom ): string {
		$inner_parts = array();

     // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMElement property.
		foreach ( iterator_to_array( $el->childNodes ) as $child ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMNode property.
			if ( XML_ELEMENT_NODE !== $child->nodeType || ! $child instanceof DOMElement ) {
				continue;
			}

			$child_el = $child;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMElement property.
			$ctag = strtolower( $child_el->tagName );

			if ( in_array( $ctag, array( 'h1', 'h2', 'h3', 'h4' ), true ) ) {
				$level         = (int) substr( $ctag, 1 );
				$inner_parts[] = self::block(
					'core/heading',
					array( 'level' => $level ),
					$dom->saveHTML( $child_el )
				);
				continue;
			}

			if ( self::element_has_class( $child_el, 'draftcraft-faq-item' ) ) {
				$q = null;
				$a = null;
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMElement property.
				foreach ( iterator_to_array( $child_el->childNodes ) as $item_child ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMNode property.
					if ( XML_ELEMENT_NODE !== $item_child->nodeType || ! $item_child instanceof DOMElement ) {
						continue;
					}

					$item_el = $item_child;
        // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMElement property.
					if ( self::element_has_class( $item_el, 'draftcraft-faq-q' ) || preg_match( '/^h[1-6]$/', strtolower( $item_el->tagName ) ) ) {
						$q = $item_el;
					}

					if ( self::element_has_class( $item_el, 'draftcraft-faq-a' ) ) {
						$a = $item_el;
					}
				}

				if ( $q ) {
        // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMElement property.
					$q_tag  = strtolower( $q->tagName );
					$level  = preg_match( '/^h([1-6])$/', $q_tag, $lm ) ? (int) $lm[1] : 3;
					$q_html = $dom->saveHTML( $q );
					// Normalize to h3 for consistency in editor.
					if ( 'h3' !== $q_tag ) {
         // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMElement property.
						$q_text = esc_html( trim( wp_strip_all_tags( $q->textContent ) ) );
						$q_html = '<h3 class="draftcraft-faq-q">' . $q_text . '</h3>';
						$level  = 3;
					}

					$inner_parts[] = self::block(
						'core/heading',
						array(
							'level'     => $level,
							'className' => 'draftcraft-faq-q',
						),
						$q_html
					);
				}//end if

				if ( $a ) {
					$paras = $a->getElementsByTagName( 'p' );
        // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMNodeList property.
					if ( $paras->length > 0 ) {
						foreach ( $paras as $p ) {
							$inner_parts[] = self::block( 'core/paragraph', array( 'className' => 'draftcraft-faq-a' ), $dom->saveHTML( $p ) );
						}
					} else {
         // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMElement property.
						$text = trim( wp_strip_all_tags( $a->textContent ) );
						if ( '' !== $text ) {
							$inner_parts[] = self::block(
								'core/paragraph',
								array( 'className' => 'draftcraft-faq-a' ),
								'<p class="draftcraft-faq-a">' . esc_html( $text ) . '</p>'
							);
						}
					}
				}

				continue;
			}//end if

			$inner_parts[] = self::node_to_blocks( $child_el, $dom );
		}//end foreach

		$inner = trim( implode( "\n\n", array_filter( $inner_parts ) ) );
		return self::block(
			'core/group',
			array(
				'className' => 'draftcraft-faq',
				'layout'    => array( 'type' => 'constrained' ),
			),
			'<div class="wp-block-group draftcraft-faq">' . $inner . '</div>'
		);
	}//end faq_element_to_blocks()


	/**
	 * Convert element children to blocks.
	 *
	 * @since  1.4.0
	 * @param  DOMElement  $el  Parent.
	 * @param  DOMDocument $dom Document.
	 * @return string
	 */
	private static function children_to_blocks( DOMElement $el, DOMDocument $dom ): string {
		$parts = array();
     // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP DOMElement property.
		foreach ( iterator_to_array( $el->childNodes ) as $child ) {
			$parts[] = self::node_to_blocks( $child, $dom );
		}

		return trim( implode( "\n\n", array_filter( $parts ) ) );
	}//end children_to_blocks()


	/**
	 * Check if DOM element has a given CSS class.
	 *
	 * @since  1.4.0
	 * @param  DOMElement $el         Element.
	 * @param  string     $class_name Class name.
	 * @return boolean
	 */
	private static function element_has_class( DOMElement $el, string $class_name ): bool {
		$classes = preg_split( '/\s+/', trim( (string) $el->getAttribute( 'class' ) ) );
		return is_array( $classes ) && in_array( $class_name, $classes, true );
	}//end element_has_class()


	/**
	 * Serialize a core block.
	 *
	 * @since  1.4.0
	 * @param  string               $name       Block name (e.g. core/paragraph).
	 * @param  array<string, mixed> $attributes Attributes.
	 * @param  string               $inner_html Inner HTML.
	 * @return string
	 */
	private static function block( string $name, array $attributes, string $inner_html ): string {
		if ( function_exists( 'get_comment_delimited_block_content' ) ) {
			return get_comment_delimited_block_content( $name, $attributes, $inner_html );
		}

		$block_name = preg_replace( '/^core\//', '', $name );
		$attr_json  = ! empty( $attributes ) ? ' ' . wp_json_encode( $attributes, ( JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) : '';

		return "<!-- wp:{$block_name}{$attr_json} -->\n{$inner_html}\n<!-- /wp:{$block_name} -->";
	}//end block()


	/**
	 * Build FAQ Gutenberg blocks from Q&A meta (for save pipeline).
	 *
	 * @since  1.4.0
	 * @param  array $faqs FAQ items.
	 * @return string
	 */
	public static function render_faq_blocks( array $faqs ): string {
		if ( empty( $faqs ) ) {
			return '';
		}

		// Reuse HTML renderer then convert — keeps copy consistent.
		if ( ! class_exists( 'DraftCraft_Schema' ) ) {
			return '';
		}

		$html = DraftCraft_Schema::render_faq_html( $faqs );
		if ( '' === $html ) {
			return '';
		}

		return self::html_to_blocks( $html );
	}//end render_faq_blocks()
}//end class
