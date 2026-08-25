<?php
/**
 * DraftCraft — Admin Settings Sidebar View
 *
 * @package DraftCraft
 * @since   1.2.1
 *
 * @var array         $settings  Plugin settings array.
 * @var int|bool      $next_cron Next cron timestamp or false.
 * @var WP_Term|null  $rot_term  Next category rotation term or null.
 */

defined( 'ABSPATH' ) || exit;

$draftcraft_pipeline_busy = (bool) get_transient( 'draftcraft_pipeline_lock' );
?>
<aside class="draftcraft-sidebar" aria-label="<?php esc_attr_e( 'Plugin sidebar', 'draftcraft' ); ?>">

	<?php
	// ─ Save Changes Metabox ─
	?>
	<div class="draftcraft-widget draftcraft-widget--save" id="draftcraft-save-widget">
		<div class="draftcraft-widget-header">
			<span class="dashicons dashicons-saved" aria-hidden="true"></span>
			<h3><?php esc_html_e( 'Save Settings', 'draftcraft' ); ?></h3>
			<span class="draftcraft-widget-unsaved-tag" id="draftcraft-save-tag" style="display:none;"><?php esc_html_e( 'Unsaved', 'draftcraft' ); ?></span>
		</div>
		<div class="draftcraft-widget-body">
			<p id="draftcraft-save-help-text"><?php esc_html_e( 'Apply changes across all panels.', 'draftcraft' ); ?></p>
			<button type="submit" form="draftcraft-form" name="draftcraft_save" value="1" id="draftcraft-save-btn" class="draftcraft-btn draftcraft-btn--primary draftcraft-btn--full">
				<span id="draftcraft-save-btn-text"><?php esc_html_e( 'Save Changes', 'draftcraft' ); ?></span>
			</button>
		</div>
	</div>

	<?php
	// ─ Generate Now ─
	?>
	<div class="draftcraft-widget draftcraft-widget--generate">
		<div class="draftcraft-widget-header">
			<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
			<h3><?php esc_html_e( 'Quick Generate', 'draftcraft' ); ?></h3>
		</div>
		<div class="draftcraft-widget-body">
			<p style="margin-bottom:8px;"><?php esc_html_e( 'Run the pipeline immediately without waiting for the next scheduled event.', 'draftcraft' ); ?></p>
			<?php
			$draftcraft_sb_target = '';
			if ( class_exists( 'DraftCraft_Bulk', false ) ) {
				$draftcraft_sb_queue   = DraftCraft_Bulk::get_queue();
				$draftcraft_sb_pending = array_values( array_filter( $draftcraft_sb_queue, static fn( $r ) => ( $r['status'] ?? 'pending' ) === 'pending' ) );
				if ( ! empty( $draftcraft_sb_pending ) && '1' === ( $settings['bulk_queue_priority'] ?? '1' ) ) {
					$draftcraft_sb_target = sprintf(
						/* translators: %s: next target keyword */
						__( 'Next: "%s" (Keyword Queue)', 'draftcraft' ),
						$draftcraft_sb_pending[0]['keyword']
					);
				}
			}

			if ( empty( $draftcraft_sb_target ) && $rot_term && ! is_wp_error( $rot_term ) ) {
				$draftcraft_sb_target = sprintf(
					/* translators: %s: next target category name */
					__( 'Next: %s (Rotation)', 'draftcraft' ),
					$rot_term->name
				);
			}
			?>
			<?php if ( ! empty( $draftcraft_sb_target ) ) : ?>
				<div style="font-size:11.5px; color:var(--dc-primary); background:var(--dc-primary-light); padding:4px 8px; border-radius:var(--dc-radius-sm); border:1px solid var(--dc-primary-border); margin-bottom:10px; display:flex; align-items:center; gap:5px;">
					<span class="dashicons dashicons-arrow-right-alt" style="font-size:13px; width:13px; height:13px;"></span>
					<span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html( $draftcraft_sb_target ); ?></span>
				</div>
			<?php endif; ?>
			<button type="button" id="draftcraft-manual-trigger" class="draftcraft-btn draftcraft-btn--generate draftcraft-btn--full"<?php echo $draftcraft_pipeline_busy ? ' disabled' : ''; ?>>
				<span class="dashicons dashicons-update<?php echo $draftcraft_pipeline_busy ? ' draftcraft-spinning' : ''; ?>" id="draftcraft-trigger-icon" aria-hidden="true"></span>
				<span id="draftcraft-trigger-label"><?php echo $draftcraft_pipeline_busy ? esc_html__( 'Generating post…', 'draftcraft' ) : esc_html__( 'Generate Post Now', 'draftcraft' ); ?></span>
			</button>
			<div id="draftcraft-trigger-result" class="draftcraft-generate-result<?php echo $draftcraft_pipeline_busy ? ' is-error' : ''; ?>" role="status" aria-live="polite"<?php echo $draftcraft_pipeline_busy ? ' style="display:block"' : ''; ?>>
			<?php
			echo $draftcraft_pipeline_busy ? esc_html__( 'A generation is already in progress. Please wait…', 'draftcraft' ) : '';
			?>
			</div>
		</div>
	</div>

	<?php
	// ─ Plugin Status ─
	?>
	<div class="draftcraft-widget">
		<div class="draftcraft-widget-header">
			<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
			<h3><?php esc_html_e( 'Status', 'draftcraft' ); ?></h3>
		</div>
		<div class="draftcraft-widget-body">
			<ul class="draftcraft-status-list">
				<?php
				$draftcraft_status_rows = array(
					array(
						'label' => __( 'Version', 'draftcraft' ),
						'value' => 'v' . DRAFTCRAFT_VERSION,
						'type'  => 'info',
					),
					array(
						'label' => __( 'API Key', 'draftcraft' ),
						'value' => ! empty( $settings['api_key'] ) ? __( 'Configured', 'draftcraft' ) : __( 'Not set', 'draftcraft' ),
						'type'  => ! empty( $settings['api_key'] ) ? 'ok' : 'warn',
					),
					array(
						'label' => __( 'Model', 'draftcraft' ),
						'value' => ! empty( draftcraft_get_active_model( $settings ) ) ? draftcraft_get_active_model( $settings ) : __( 'Not set', 'draftcraft' ),
						'type'  => ! empty( draftcraft_get_active_model( $settings ) ) ? 'ok' : 'warn',
					),
					array(
						'label' => __( 'Automation', 'draftcraft' ),
						'value' => $next_cron ? __( 'Scheduled', 'draftcraft' ) : __( 'Off', 'draftcraft' ),
						'type'  => $next_cron ? 'ok' : 'off',
					),
					array(
						'label' => __( 'Post Status', 'draftcraft' ),
						'value' => ucfirst( $settings['post_status'] ),
						'type'  => 'info',
					),
					array(
						'label' => __( 'SEO Sync', 'draftcraft' ),
						'value' => DraftCraft_SEO::is_enabled( $settings ) ? DraftCraft_SEO::get_active_plugin_label() : __( 'Off', 'draftcraft' ),
						'type'  => DraftCraft_SEO::is_enabled( $settings ) ? 'ok' : 'off',
					),
				);

				if ( class_exists( 'DraftCraft_Bulk', false ) ) {
					$draftcraft_pending_count = count( array_filter( DraftCraft_Bulk::get_queue(), static fn( $r ) => ( $r['status'] ?? 'pending' ) === 'pending' ) );
					if ( $draftcraft_pending_count > 0 ) {
						$draftcraft_status_rows[] = array(
							'label' => __( 'Keyword Queue', 'draftcraft' ),
							'value' => (string) $draftcraft_pending_count . ' pending',
							'type'  => 'info',
						);
					}
				}

				if ( $next_cron ) {
					$draftcraft_status_rows[] = array(
						'label' => __( 'Next Run', 'draftcraft' ),
						'value' => human_time_diff( time(), $next_cron ),
						'type'  => 'info',
					);
				}

				if ( $rot_term && ! is_wp_error( $rot_term ) ) {
					$draftcraft_status_rows[] = array(
						'label' => __( 'Next Category', 'draftcraft' ),
						'value' => $rot_term->name,
						'type'  => 'info',
					);
				}

				foreach ( $draftcraft_status_rows as $draftcraft_row ) :
					?>
					<li class="draftcraft-status-row">
						<span class="draftcraft-status-label"><?php echo esc_html( $draftcraft_row['label'] ); ?></span>
						<span class="draftcraft-pill draftcraft-pill--<?php echo esc_attr( $draftcraft_row['type'] ); ?>">
							<?php echo esc_html( $draftcraft_row['value'] ); ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>

	<?php
	// ─ Support & Info ─
	?>
	<div class="draftcraft-widget draftcraft-widget--muted">
		<div class="draftcraft-widget-header">
			<span class="dashicons dashicons-sos" aria-hidden="true"></span>
			<h3><?php esc_html_e( 'Support', 'draftcraft' ); ?></h3>
		</div>
		<div class="draftcraft-widget-body">
			<ul class="draftcraft-link-list">
				<li><a href="https://artisanswp.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'ArtisansWP Website', 'draftcraft' ); ?></a></li>
				<?php // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Informational link to OpenRouter docs. ?>
				<li><a href="https://openrouter.ai/docs" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'OpenRouter Docs', 'draftcraft' ); ?></a></li>
			</ul>
			<p class="draftcraft-developer-note">
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: developer name. */
						__( 'Built with ♥ by %s', 'draftcraft' ),
						'<a href="https://artisanswp.com" target="_blank" rel="noopener noreferrer">ArtisansWP</a>'
					)
				);
				?>
			</p>
		</div>
	</div>

</aside><!-- /.draftcraft-sidebar -->
