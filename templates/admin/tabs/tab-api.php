<?php
/**
 * DraftCraft — API & Model Settings Tab View
 *
 * @package DraftCraft
 * @since   1.2.1
 *
 * @var array  $settings   Plugin settings array.
 * @var string $masked_key Masked API key representation.
 * @var string $active_tab Currently active tab key.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="draftcraft-panel <?php echo 'api' === $active_tab ? 'is-active' : ''; ?>" id="draftcraft-panel-api">

	<?php
	// Section 1: OpenRouter API Connection.
	?>
	<div class="draftcraft-section">
		<div class="draftcraft-section-header">
			<h2><?php esc_html_e( 'OpenRouter API Connection', 'draftcraft' ); ?></h2>
			<p><?php esc_html_e( 'Connect your OpenRouter account to access 200+ top-tier AI language and image models.', 'draftcraft' ); ?></p>
		</div>
		<div class="draftcraft-section-body">
			<div class="draftcraft-field">
				<label class="draftcraft-label" for="draftcraft_api_key">
					<?php esc_html_e( 'API Key', 'draftcraft' ); ?>
					<span class="draftcraft-required">*</span>
					<span class="draftcraft-tooltip-wrap">
						<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
						<span class="draftcraft-tooltip-bubble">
							<?php esc_html_e( 'Your OpenRouter key is encrypted in your WordPress database and is never exposed in plain text.', 'draftcraft' ); ?>
						</span>
					</span>
				</label>
				<div class="draftcraft-input-addon">
					<input type="password"
							id="draftcraft_api_key"
							name="draftcraft_api_key"
							value="<?php echo esc_attr( $masked_key ); ?>"
							placeholder="sk-or-v1-…"
							autocomplete="new-password"
							class="draftcraft-input draftcraft-input--full"
							spellcheck="false">
					<button type="button" class="draftcraft-addon-btn" id="draftcraft-toggle-key"
							aria-label="<?php esc_attr_e( 'Toggle key visibility', 'draftcraft' ); ?>">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					</button>
				</div>
				<p class="draftcraft-description">
					<?php
					// phpcs:disable PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Informational link to obtain user API key.
					echo wp_kses_post(
						sprintf(
							/* translators: %s: link to OpenRouter keys page. */
							__( 'Get your OpenRouter API key at %s.', 'draftcraft' ),
							'<a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer" style="font-weight:500;">openrouter.ai/keys ↗</a>'
						)
					);
					// phpcs:enable PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
					?>
				</p>
			</div>
		</div>
	</div>

	<?php
	// Section 2: AI Content Writing & Model.
	?>
	<div class="draftcraft-section">
		<div class="draftcraft-section-header">
			<h2><?php esc_html_e( 'Content Generation Engine', 'draftcraft' ); ?></h2>
			<p><?php esc_html_e( 'Choose the primary LLM for drafting articles and define the unique brand voice and tone.', 'draftcraft' ); ?></p>
		</div>
		<div class="draftcraft-section-body">
			<div class="draftcraft-field">
				<label class="draftcraft-label" for="draftcraft_model_dropdown">
					<?php esc_html_e( 'Content Model', 'draftcraft' ); ?>
					<span class="draftcraft-required">*</span>
					<span class="draftcraft-tooltip-wrap">
						<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
						<span class="draftcraft-tooltip-bubble">
							<?php esc_html_e( 'Select the primary AI model used to write posts. Click "Fetch Models" to retrieve the latest live model catalog from OpenRouter.', 'draftcraft' ); ?>
						</span>
					</span>
				</label>
				<div class="draftcraft-input-addon">
					<select id="draftcraft_model_dropdown"
							name="draftcraft_model_dropdown"
							class="draftcraft-select draftcraft-input--full">
						<?php if ( ! empty( $settings['model_dropdown'] ) ) : ?>
							<option value="<?php echo esc_attr( $settings['model_dropdown'] ); ?>" selected>
								<?php echo esc_html( $settings['model_dropdown'] ); ?>
							</option>
						<?php else : ?>
							<option value=""><?php esc_html_e( '— Click "Fetch Models" to load —', 'draftcraft' ); ?></option>
						<?php endif; ?>
					</select>
					<button type="button" class="draftcraft-btn draftcraft-btn--secondary" id="draftcraft-fetch-models">
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<span id="draftcraft-fetch-btn-label"><?php esc_html_e( 'Fetch Models', 'draftcraft' ); ?></span>
					</button>
				</div>
				<p class="draftcraft-description" id="draftcraft-models-status" role="status" aria-live="polite"></p>
			</div>

			<div class="draftcraft-field" style="margin-top:18px;">
				<label class="draftcraft-label" for="draftcraft_system_prompt">
					<?php esc_html_e( 'Writing Style & Persona (System Prompt)', 'draftcraft' ); ?>
					<span class="draftcraft-tooltip-wrap">
						<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
						<span class="draftcraft-tooltip-bubble">
							<?php esc_html_e( 'Define your brand tone, target audience, phrasing preferences, and words to avoid. SEO structure and schema are handled automatically.', 'draftcraft' ); ?>
						</span>
					</span>
				</label>
				<textarea id="draftcraft_system_prompt"
							name="draftcraft_system_prompt"
							rows="6"
							class="draftcraft-textarea draftcraft-input--full"
							placeholder="<?php esc_attr_e( 'e.g. Write for beginners in a friendly, practical tone. Prefer short paragraphs and real-world examples. Avoid hype and filler phrases like "In today\'s fast-paced world".', 'draftcraft' ); ?>"><?php echo esc_textarea( $settings['system_prompt'] ); ?></textarea>
				<div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
					<p class="draftcraft-description" style="margin:0;">
						<?php esc_html_e( 'Guide voice, tone, audience, and phrasing only. Do not add JSON or SEO formatting rules here — DraftCraft handles those internally.', 'draftcraft' ); ?>
					</p>
					<span id="draftcraft-prompt-count" class="draftcraft-cat-pill" style="margin-left:8px; font-weight:500;"></span>
				</div>
			</div>
		</div>
	</div>

	<?php
	// Section 3: Featured Media Generation.
	?>
	<div class="draftcraft-section">
		<div class="draftcraft-section-header">
			<h2><?php esc_html_e( 'Featured Media Generator', 'draftcraft' ); ?></h2>
			<p><?php esc_html_e( 'Automatically source and attach relevant high-quality featured images to every generated post.', 'draftcraft' ); ?></p>
		</div>
		<div class="draftcraft-section-body">

			<div class="draftcraft-feature-card">
				<div class="draftcraft-field" style="margin-bottom:0;">
					<label class="draftcraft-label" for="draftcraft_image_provider">
						<?php esc_html_e( 'Featured Image Source', 'draftcraft' ); ?>
						<span class="draftcraft-tooltip-wrap">
							<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
							<span class="draftcraft-tooltip-bubble">
								<?php esc_html_e( 'Choose whether to generate AI images via OpenRouter (Flux Pro/DALL-E) or fetch free photos from Unsplash based on post topic.', 'draftcraft' ); ?>
							</span>
						</span>
					</label>
					<select id="draftcraft_image_provider" name="draftcraft_image_provider" class="draftcraft-select" style="max-width:320px;">
						<option value="none" <?php selected( 'none', $settings['image_provider'] ); ?>><?php esc_html_e( 'Disabled — No automated images', 'draftcraft' ); ?></option>
						<option value="openrouter" <?php selected( 'openrouter', $settings['image_provider'] ); ?>><?php esc_html_e( 'OpenRouter — AI Generated Images (Flux/DALL-E)', 'draftcraft' ); ?></option>
						<option value="unsplash" <?php selected( 'unsplash', $settings['image_provider'] ); ?>><?php esc_html_e( 'Unsplash — Curated Photography API', 'draftcraft' ); ?></option>
					</select>
				</div>

				<?php
				// Sub-box: OpenRouter AI Image Controls.
				?>
				<div id="draftcraft-image-model-field" class="draftcraft-feature-card-body" <?php echo 'openrouter' !== $settings['image_provider'] ? 'hidden' : ''; ?>>
					<div class="draftcraft-field">
						<label class="draftcraft-label" for="draftcraft_image_model">
							<?php esc_html_e( 'Image Generation Model', 'draftcraft' ); ?>
							<span class="draftcraft-tooltip-wrap">
								<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
								<span class="draftcraft-tooltip-bubble">
									<?php esc_html_e( 'Select the image diffusion model (e.g. Flux 1.1 Pro, Recraft, SDXL) used to create post featured images.', 'draftcraft' ); ?>
								</span>
							</span>
						</label>
						<div class="draftcraft-input-addon">
							<select id="draftcraft_image_model"
									name="draftcraft_image_model"
									class="draftcraft-select draftcraft-input--full">
								<?php if ( ! empty( $settings['image_model'] ) ) : ?>
									<option value="<?php echo esc_attr( $settings['image_model'] ); ?>" selected>
										<?php echo esc_html( $settings['image_model'] ); ?>
									</option>
								<?php else : ?>
									<option value=""><?php esc_html_e( '— Click "Fetch Models" to load —', 'draftcraft' ); ?></option>
								<?php endif; ?>
							</select>
							<button type="button" class="draftcraft-btn draftcraft-btn--secondary" id="draftcraft-fetch-image-models">
								<span class="dashicons dashicons-update" aria-hidden="true"></span>
								<span id="draftcraft-fetch-image-btn-label"><?php esc_html_e( 'Fetch Models', 'draftcraft' ); ?></span>
							</button>
						</div>
						<p class="draftcraft-description" id="draftcraft-image-models-status" role="status" aria-live="polite"></p>
					</div>

					<div class="draftcraft-field" style="margin-bottom:0; margin-top:14px;">
						<label class="draftcraft-label" for="draftcraft_image_system_prompt">
							<?php esc_html_e( 'Image Visual Style Prompt', 'draftcraft' ); ?>
							<span class="draftcraft-tooltip-wrap">
								<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
								<span class="draftcraft-tooltip-bubble">
									<?php esc_html_e( 'Guidelines passed to the image model (e.g. realistic editorial style, natural lighting, 16:9 landscape, no text/watermarks).', 'draftcraft' ); ?>
								</span>
							</span>
						</label>
						<textarea id="draftcraft_image_system_prompt"
									name="draftcraft_image_system_prompt"
									rows="4"
									class="draftcraft-textarea draftcraft-input--full"
									placeholder="<?php esc_attr_e( 'e.g. Clean editorial photography, natural light, realistic scenes, 16:9 landscape. No text, logos, or watermarks.', 'draftcraft' ); ?>"><?php echo esc_textarea( ( $settings['image_system_prompt'] ?? '' ) ); ?></textarea>
					</div>
				</div>

				<?php
				// Sub-box: Unsplash Key.
				?>
				<div id="draftcraft-unsplash-key-field" class="draftcraft-feature-card-body" <?php echo 'unsplash' !== $settings['image_provider'] ? 'hidden' : ''; ?>>
					<div class="draftcraft-field" style="margin-bottom:0;">
						<label class="draftcraft-label" for="draftcraft_unsplash_access_key">
							<?php esc_html_e( 'Unsplash API Access Key', 'draftcraft' ); ?>
							<span class="draftcraft-tooltip-wrap">
								<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
								<span class="draftcraft-tooltip-bubble">
									<?php esc_html_e( 'Free developer key from Unsplash. Used to search and download high-resolution editorial photos matching your post topic.', 'draftcraft' ); ?>
								</span>
							</span>
						</label>
						<?php
						$draftcraft_masked_unsplash = ! empty( $settings['unsplash_access_key'] ) ? str_repeat( '•', 16 ) : '';
						?>
						<input type="password" id="draftcraft_unsplash_access_key" name="draftcraft_unsplash_access_key"
								class="draftcraft-input draftcraft-input--full"
								value="<?php echo esc_attr( $draftcraft_masked_unsplash ); ?>"
								autocomplete="new-password"
								placeholder="<?php esc_attr_e( 'Enter Unsplash Access Key…', 'draftcraft' ); ?>">
						<p class="draftcraft-description">
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: %s: Unsplash developers link. */
									__( 'Obtain a free developer key at %s.', 'draftcraft' ),
									'<a href="https://unsplash.com/developers" target="_blank" rel="noopener noreferrer" style="font-weight:500;">unsplash.com/developers ↗</a>'
								)
							);
							?>
						</p>
					</div>
				</div>
			</div>

		</div>
	</div>

</div><!-- /#draftcraft-panel-api -->
