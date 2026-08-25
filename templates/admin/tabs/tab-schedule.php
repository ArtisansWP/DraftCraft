<?php
/**
 * DraftCraft — Automation & Schedule Tab View
 *
 * @package DraftCraft
 * @since   1.2.1
 *
 * @var array    $settings   Plugin settings array.
 * @var int|bool $next_cron  Timestamp of next cron execution or false.
 * @var string   $active_tab Currently active tab key.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="draftcraft-panel <?php echo 'schedule' === $active_tab ? 'is-active' : ''; ?>" id="draftcraft-panel-schedule">

	<div class="draftcraft-section">
		<div class="draftcraft-section-header">
			<h2><?php esc_html_e( 'Automation', 'draftcraft' ); ?></h2>
			<p><?php esc_html_e( 'DraftCraft uses WordPress Cron to run on your chosen schedule. For best reliability, configure a server-level cron job to visit your site periodically.', 'draftcraft' ); ?></p>
		</div>
		<div class="draftcraft-section-body">

			<div class="draftcraft-field draftcraft-field--toggle">
				<div class="draftcraft-toggle-row">
					<div class="draftcraft-toggle-info">
						<strong>
							<?php esc_html_e( 'Enable Automated Post Generation', 'draftcraft' ); ?>
							<span class="draftcraft-tooltip-wrap">
								<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
								<span class="draftcraft-tooltip-bubble">
									<?php esc_html_e( 'When active, WordPress Cron runs in the background on your chosen interval to draft or publish posts automatically.', 'draftcraft' ); ?>
								</span>
							</span>
						</strong>
						<p class="draftcraft-description"><?php esc_html_e( 'Turn the scheduled content engine on or off. Disabling clears the scheduled task immediately.', 'draftcraft' ); ?></p>
					</div>
					<label class="draftcraft-switch" for="draftcraft_automation_on" aria-label="<?php esc_attr_e( 'Enable automation', 'draftcraft' ); ?>">
						<input type="checkbox"
								id="draftcraft_automation_on"
								name="draftcraft_automation_on"
								value="1"
								<?php checked( '1', $settings['automation_on'] ); ?>>
						<span class="draftcraft-switch-track" aria-hidden="true">
							<span class="draftcraft-switch-thumb"></span>
						</span>
					</label>
				</div>
			</div>

			<div class="draftcraft-field">
				<label class="draftcraft-label">
					<?php esc_html_e( 'Generation Frequency', 'draftcraft' ); ?>
					<span class="draftcraft-tooltip-wrap">
						<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
						<span class="draftcraft-tooltip-bubble">
							<?php esc_html_e( 'Select how often DraftCraft generates an article. Daily is recommended for steady, organic site growth.', 'draftcraft' ); ?>
						</span>
					</span>
				</label>
				<div class="draftcraft-schedule-grid">
					<?php foreach ( draftcraft_get_schedule_options() as $draftcraft_value => $draftcraft_data ) : ?>
						<label class="draftcraft-schedule-card <?php echo $draftcraft_value === $settings['schedule'] ? 'is-selected' : ''; ?>">
							<input type="radio"
									name="draftcraft_schedule"
									value="<?php echo esc_attr( $draftcraft_value ); ?>"
									<?php checked( $draftcraft_value, $settings['schedule'] ); ?>>
							<span class="draftcraft-schedule-icon" aria-hidden="true"><?php echo esc_html( $draftcraft_data['icon'] ); ?></span>
							<span class="draftcraft-schedule-label"><?php echo esc_html( $draftcraft_data['label'] ); ?></span>
							<span class="draftcraft-schedule-sub"><?php echo esc_html( $draftcraft_data['sub'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( $next_cron ) : ?>
				<div class="draftcraft-cron-info" style="margin-bottom:16px;">
					<span class="dashicons dashicons-clock"></span>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: human-readable time until next cron run (HTML strong tag allowed). */
							__( 'Next generation scheduled in %s.', 'draftcraft' ),
							'<strong>' . esc_html( human_time_diff( time(), $next_cron ) ) . '</strong>'
						)
					);
					?>
				</div>
			<?php endif; ?>

			<?php
			// Compute next generation target details.
			$draftcraft_pending_kws  = 0;
			$draftcraft_next_kw_name = '';
			if ( class_exists( 'DraftCraft_Bulk', false ) ) {
				$draftcraft_q             = DraftCraft_Bulk::get_queue();
				$draftcraft_pending_items = array_values( array_filter( $draftcraft_q, static fn( $r ) => ( $r['status'] ?? 'pending' ) === 'pending' ) );
				$draftcraft_pending_kws   = count( $draftcraft_pending_items );
				if ( $draftcraft_pending_kws > 0 ) {
					$draftcraft_next_kw_name = ( $draftcraft_pending_items[0]['keyword'] ?? '' );
				}
			}

			$draftcraft_target_source = '';
			if ( $draftcraft_pending_kws > 0 && '1' === ( $settings['bulk_queue_priority'] ?? '1' ) ) {
				$draftcraft_target_source = sprintf(
					/* translators: %s: keyword. */
					__( 'Keyword Queue ("%s")', 'draftcraft' ),
					$draftcraft_next_kw_name
				);
			} elseif ( '1' === ( $settings['category_rotation'] ?? '0' ) ) {
				$draftcraft_order = draftcraft_resolve_rotation_order( $settings );
				if ( ! empty( $draftcraft_order ) ) {
					$draftcraft_idx           = ( absint( get_option( 'draftcraft_rotation_index', 0 ) ) % count( $draftcraft_order ) );
					$draftcraft_term          = get_term( $draftcraft_order[ $draftcraft_idx ], draftcraft_get_target_taxonomy() );
					$draftcraft_target_source = sprintf(
						/* translators: %s: category name. */
						__( 'Category Rotation (%s)', 'draftcraft' ),
						( $draftcraft_term && ! is_wp_error( $draftcraft_term ) ) ? $draftcraft_term->name : '—'
					);
				}
			}

			if ( empty( $draftcraft_target_source ) ) {
				$draftcraft_target_source = __( 'Configured Categories', 'draftcraft' );
			}

			$draftcraft_author_name = __( 'Automatic (Admin)', 'draftcraft' );
			if ( ! empty( $settings['post_author'] ) ) {
				$draftcraft_u = get_userdata( (int) $settings['post_author'] );
				if ( $draftcraft_u ) {
					$draftcraft_author_name = $draftcraft_u->display_name;
				}
			}

			$draftcraft_active_model = draftcraft_get_active_model( $settings );
			?>
			<div class="draftcraft-feature-card" style="margin-top:0;">
				<div class="draftcraft-feature-card-header">
					<div class="draftcraft-feature-card-info">
						<strong>
							<span class="dashicons dashicons-visibility" style="color:var(--dc-primary);"></span>
							<?php esc_html_e( 'Next Generation Plan', 'draftcraft' ); ?>
						</strong>
						<p class="draftcraft-description"><?php esc_html_e( 'Summary of how DraftCraft will execute the next scheduled run.', 'draftcraft' ); ?></p>
					</div>
				</div>
				<div class="draftcraft-feature-card-body">
					<ul class="draftcraft-status-list" style="margin:0;">
						<li class="draftcraft-status-row">
							<span class="draftcraft-status-label"><?php esc_html_e( 'Topic Source', 'draftcraft' ); ?></span>
							<span class="draftcraft-pill draftcraft-pill--ok"><?php echo esc_html( $draftcraft_target_source ); ?></span>
						</li>
						<li class="draftcraft-status-row">
							<span class="draftcraft-status-label"><?php esc_html_e( 'Initial Status', 'draftcraft' ); ?></span>
							<span class="draftcraft-pill draftcraft-pill--info"><?php echo esc_html( ucfirst( $settings['post_status'] ) ); ?></span>
						</li>
						<li class="draftcraft-status-row">
							<span class="draftcraft-status-label"><?php esc_html_e( 'Assigned Author', 'draftcraft' ); ?></span>
							<span class="draftcraft-pill draftcraft-pill--info"><?php echo esc_html( $draftcraft_author_name ); ?></span>
						</li>
						<li class="draftcraft-status-row">
							<span class="draftcraft-status-label"><?php esc_html_e( 'Active Model', 'draftcraft' ); ?></span>
							<span class="draftcraft-pill draftcraft-pill--info"><?php echo esc_html( ! empty( $draftcraft_active_model ) ? $draftcraft_active_model : __( 'Not set', 'draftcraft' ) ); ?></span>
						</li>
					</ul>
				</div>
			</div>

		</div>
	</div>

</div><!-- /#draftcraft-panel-schedule -->
