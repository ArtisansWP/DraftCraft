<?php
/**
 * DraftCraft — SEO & Content Features Tab View
 *
 * @package DraftCraft
 * @since   1.2.1
 *
 * @var array  $settings   Plugin settings array.
 * @var string $active_tab Currently active tab key.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="draftcraft-panel <?php echo 'seo' === $active_tab ? 'is-active' : ''; ?>" id="draftcraft-panel-seo">

	<?php
	if ( class_exists( 'DraftCraft_Bulk', false ) ) {
		DraftCraft_Bulk::render_seo_keywords_section();
		DraftCraft_Bulk::render_queue_table();
	}
	?>

	<div class="draftcraft-section">
		<div class="draftcraft-section-header">
			<h2><?php esc_html_e( 'Content Enhancement Features', 'draftcraft' ); ?></h2>
			<p><?php esc_html_e( 'Configure automated internal linking, FAQ schema generation, and Table of Contents for generated posts.', 'draftcraft' ); ?></p>
		</div>
		<div class="draftcraft-section-body">

			<?php
			// Feature 1: Smart Internal Linking.
			?>
			<div class="draftcraft-feature-card">
				<div class="draftcraft-feature-card-header">
					<div class="draftcraft-feature-card-info">
						<strong>
							<span class="dashicons dashicons-admin-links" style="color:var(--dc-primary);"></span>
							<?php esc_html_e( 'Smart Internal Linking', 'draftcraft' ); ?>
							<span class="draftcraft-tooltip-wrap">
								<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
								<span class="draftcraft-tooltip-bubble">
									<?php esc_html_e( 'Automatically inserts 2–3 contextual links to your existing published posts to boost SEO and keeps visitors on your site by stripping hallucinated external links.', 'draftcraft' ); ?>
								</span>
							</span>
						</strong>
						<p class="draftcraft-description"><?php esc_html_e( 'Contextually link generated articles to your published content while removing unwanted outbound links.', 'draftcraft' ); ?></p>
					</div>
					<label class="draftcraft-switch" for="draftcraft_internal_links_enabled">
						<input type="checkbox" id="draftcraft_internal_links_enabled" name="draftcraft_internal_links_enabled" value="1" <?php checked( '1', $settings['internal_links_enabled'] ); ?>>
						<span class="draftcraft-switch-track"><span class="draftcraft-switch-thumb"></span></span>
					</label>
				</div>
				<div class="draftcraft-feature-card-body" id="draftcraft-internal-links-body"<?php echo '1' !== $settings['internal_links_enabled'] ? ' hidden' : ''; ?>>
					<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px;">
						<div class="draftcraft-field" style="margin-bottom:0;">
							<label class="draftcraft-label" for="draftcraft_internal_links_method">
								<?php esc_html_e( 'Linking Strategy', 'draftcraft' ); ?>
								<span class="draftcraft-tooltip-wrap">
									<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
									<span class="draftcraft-tooltip-bubble">
										<?php esc_html_e( 'Hybrid (Recommended): AI organically places links when naturally relevant, and fallback algorithm steps in only if needed.', 'draftcraft' ); ?>
									</span>
								</span>
							</label>
							<select id="draftcraft_internal_links_method" name="draftcraft_internal_links_method" class="draftcraft-select">
								<option value="hybrid" <?php selected( 'hybrid', $settings['internal_links_method'] ); ?>><?php esc_html_e( 'Hybrid — AI First + Fallback (Recommended)', 'draftcraft' ); ?></option>
								<option value="llm" <?php selected( 'llm', $settings['internal_links_method'] ); ?>><?php esc_html_e( 'AI Only — Let AI place links in prose', 'draftcraft' ); ?></option>
								<option value="local" <?php selected( 'local', $settings['internal_links_method'] ); ?>><?php esc_html_e( 'Deterministic — Algorithmic anchor insertion', 'draftcraft' ); ?></option>
							</select>
						</div>
						<div class="draftcraft-field" style="margin-bottom:0;">
							<label class="draftcraft-label" for="draftcraft_internal_links_relevance">
								<?php esc_html_e( 'Relevance Strictness', 'draftcraft' ); ?>
								<span class="draftcraft-tooltip-wrap">
									<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
									<span class="draftcraft-tooltip-bubble">
										<?php esc_html_e( 'Controls how closely related an existing article must be to qualify as a link candidate.', 'draftcraft' ); ?>
									</span>
								</span>
							</label>
							<select id="draftcraft_internal_links_relevance" name="draftcraft_internal_links_relevance" class="draftcraft-select">
								<option value="strict" <?php selected( 'strict', ( $settings['internal_links_relevance'] ?? 'balanced' ) ); ?>><?php esc_html_e( 'Strict — High relevance bar (fewer links)', 'draftcraft' ); ?></option>
								<option value="balanced" <?php selected( 'balanced', ( $settings['internal_links_relevance'] ?? 'balanced' ) ); ?>><?php esc_html_e( 'Balanced (Recommended)', 'draftcraft' ); ?></option>
								<option value="loose" <?php selected( 'loose', ( $settings['internal_links_relevance'] ?? 'balanced' ) ); ?>><?php esc_html_e( 'Loose — Broad matching (more links)', 'draftcraft' ); ?></option>
							</select>
						</div>
					</div>
				</div>
			</div>

			<?php
			// Feature 2: FAQ & Schema.
			?>
			<div class="draftcraft-feature-card">
				<div class="draftcraft-feature-card-header">
					<div class="draftcraft-feature-card-info">
						<strong>
							<span class="dashicons dashicons-format-chat" style="color:var(--dc-primary);"></span>
							<?php esc_html_e( 'FAQ Generation & Schema Markup', 'draftcraft' ); ?>
							<span class="draftcraft-tooltip-wrap">
								<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
								<span class="draftcraft-tooltip-bubble">
									<?php esc_html_e( 'Adds a structured FAQ accordion to the end of posts and outputs Google-compliant FAQPage JSON-LD schema for rich search results.', 'draftcraft' ); ?>
								</span>
							</span>
						</strong>
						<p class="draftcraft-description"><?php esc_html_e( 'Appends 3–4 high-value questions and answers at the end of every post with valid FAQPage JSON-LD schema.', 'draftcraft' ); ?></p>
					</div>
					<label class="draftcraft-switch" for="draftcraft_faq_schema_enabled">
						<input type="checkbox" id="draftcraft_faq_schema_enabled" name="draftcraft_faq_schema_enabled" value="1" <?php checked( '1', $settings['faq_schema_enabled'] ); ?>>
						<span class="draftcraft-switch-track"><span class="draftcraft-switch-thumb"></span></span>
					</label>
				</div>
			</div>

			<?php
			// Feature 3: Table of Contents.
			?>
			<div class="draftcraft-feature-card">
				<div class="draftcraft-feature-card-header">
					<div class="draftcraft-feature-card-info">
						<strong>
							<span class="dashicons dashicons-list-view" style="color:var(--dc-primary);"></span>
							<?php esc_html_e( 'Automated Table of Contents (TOC)', 'draftcraft' ); ?>
							<span class="draftcraft-tooltip-wrap">
								<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
								<span class="draftcraft-tooltip-bubble">
									<?php esc_html_e( 'Scans H2 and H3 headings to build an interactive, jump-to Table of Contents at the top of the post with smooth scrolling.', 'draftcraft' ); ?>
								</span>
							</span>
						</strong>
						<p class="draftcraft-description"><?php esc_html_e( 'Builds an interactive table of contents from article headings and integrates with Rank Math TOC schema.', 'draftcraft' ); ?></p>
					</div>
					<label class="draftcraft-switch" for="draftcraft_toc_enabled">
						<input type="checkbox" id="draftcraft_toc_enabled" name="draftcraft_toc_enabled" value="1" <?php checked( '1', ( $settings['toc_enabled'] ?? '1' ) ); ?>>
						<span class="draftcraft-switch-track"><span class="draftcraft-switch-thumb"></span></span>
					</label>
				</div>
				<div class="draftcraft-feature-card-body" id="draftcraft-toc-body"<?php echo '1' !== ( $settings['toc_enabled'] ?? '1' ) ? ' hidden' : ''; ?>>
					<div class="draftcraft-field" style="margin-bottom:0; max-width:340px;">
						<label class="draftcraft-label" for="draftcraft_toc_scroll_offset">
							<?php esc_html_e( 'Sticky Header Scroll Offset (px)', 'draftcraft' ); ?>
							<span class="draftcraft-tooltip-wrap">
								<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
								<span class="draftcraft-tooltip-bubble">
									<?php esc_html_e( 'Prevents fixed/sticky theme navigation bars from overlapping headings when a visitor clicks a TOC link (typically 80–120px).', 'draftcraft' ); ?>
								</span>
							</span>
						</label>
						<input
							type="number"
							id="draftcraft_toc_scroll_offset"
							name="draftcraft_toc_scroll_offset"
							class="draftcraft-input"
							min="0"
							max="400"
							step="1"
							value="<?php echo esc_attr( (string) absint( ( $settings['toc_scroll_offset'] ?? 96 ) ) ); ?>"
						/>
						<p class="draftcraft-description" style="margin-top:4px;">
							<?php esc_html_e( 'Default: 96px. Only adjust if your theme uses a sticky header that blocks headings upon jumping.', 'draftcraft' ); ?>
						</p>
					</div>
				</div>
			</div>

		</div>
	</div>

</div><!-- /#draftcraft-panel-seo -->
