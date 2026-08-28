<?php
/**
 * DraftCraft — Content Settings Tab View
 *
 * @package DraftCraft
 * @since   1.2.1
 *
 * @var array  $settings        Plugin settings array.
 * @var array  $categories      List of term objects.
 * @var string $target_taxonomy Active taxonomy name (e.g. category).
 * @var string $active_tab      Currently active tab key.
 */

defined( 'ABSPATH' ) || exit;

$draftcraft_tax_obj      = get_taxonomy( $target_taxonomy );
$draftcraft_tax_name     = ! empty( $draftcraft_tax_obj->labels->name ) ? $draftcraft_tax_obj->labels->name : __( 'Categories', 'draftcraft' );
$draftcraft_tax_singular = ! empty( $draftcraft_tax_obj->labels->singular_name ) ? $draftcraft_tax_obj->labels->singular_name : __( 'Category', 'draftcraft' );
?>
<div class="draftcraft-panel <?php echo 'content' === $active_tab ? 'is-active' : ''; ?>" id="draftcraft-panel-content">

	<div class="draftcraft-section">
		<div class="draftcraft-section-header">
			<h2><?php esc_html_e( 'Post Publishing & Author Settings', 'draftcraft' ); ?></h2>
			<p><?php esc_html_e( 'Configure author assignment and initial publish state for AI-generated posts.', 'draftcraft' ); ?></p>
		</div>
		<div class="draftcraft-section-body">
			<div class="draftcraft-field-row" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
				<div class="draftcraft-field" style="margin-bottom:0;">
					<label class="draftcraft-label" for="draftcraft_post_status">
						<?php esc_html_e( 'Default Post Status', 'draftcraft' ); ?>
						<span class="draftcraft-tooltip-wrap">
							<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
							<span class="draftcraft-tooltip-bubble">
								<?php esc_html_e( 'Draft: Allows manual editorial review before publishing (Recommended). Publish: Immediately pushes new posts live upon generation.', 'draftcraft' ); ?>
							</span>
						</span>
					</label>
					<select id="draftcraft_post_status" name="draftcraft_post_status" class="draftcraft-select">
						<option value="draft" <?php selected( 'draft', $settings['post_status'] ); ?>>
							<?php esc_html_e( 'Draft — Review before publishing (recommended)', 'draftcraft' ); ?>
						</option>
						<option value="publish" <?php selected( 'publish', $settings['post_status'] ); ?>>
							<?php esc_html_e( 'Published — Go live immediately', 'draftcraft' ); ?>
						</option>
					</select>
				</div>
				<div class="draftcraft-field" style="margin-bottom:0;">
					<label class="draftcraft-label" for="draftcraft_post_author">
						<?php esc_html_e( 'Default Post Author', 'draftcraft' ); ?>
						<span class="draftcraft-tooltip-wrap">
							<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
							<span class="draftcraft-tooltip-bubble">
								<?php esc_html_e( 'Assigns generated articles to a specific author profile. Automatic assigns posts to the active administrator or triggering user.', 'draftcraft' ); ?>
							</span>
						</span>
					</label>
					<?php
					$draftcraft_users = get_users(
						array(
							'role__in' => array(
								'administrator',
								'editor',
								'author',
							),
							'orderby'  => 'display_name',
							'order'    => 'ASC',
							'number'   => 200,
						)
					);
					?>
					<select id="draftcraft_post_author" name="draftcraft_post_author" class="draftcraft-select">
						<option value="0" <?php selected( 0, (int) ( $settings['post_author'] ?? 0 ) ); ?>>
							<?php esc_html_e( 'Automatic — Active Admin / Logged-in User', 'draftcraft' ); ?>
						</option>
						<?php if ( ! empty( $draftcraft_users ) ) : ?>
							<?php foreach ( $draftcraft_users as $draftcraft_u ) : ?>
								<option value="<?php echo esc_attr( $draftcraft_u->ID ); ?>" <?php selected( $draftcraft_u->ID, (int) ( $settings['post_author'] ?? 0 ) ); ?>>
									<?php echo esc_html( $draftcraft_u->display_name . ' (' . $draftcraft_u->user_login . ')' ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>
			</div>
		</div>
	</div>

	<div class="draftcraft-section">
		<div class="draftcraft-section-header">
			<h2><?php echo esc_html( $draftcraft_tax_name ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: taxonomy name (e.g. categories). */
					esc_html__( 'Choose which %s generated posts are assigned to. Enable rotation to cycle through them one by one.', 'draftcraft' ),
					esc_html( strtolower( $draftcraft_tax_name ) )
				);
				?>
			</p>
			<div class="draftcraft-notice-inline" style="margin-bottom:12px; display:flex; align-items:center; gap:8px;">
				<span class="dashicons dashicons-info" style="color:var(--dc-amber); flex-shrink:0;"></span>
				<?php
				printf(
					/* translators: 1: taxonomy plural name, 2: taxonomy singular name. */
					esc_html__( 'Tip: Add detailed descriptions to your %1$s. DraftCraft automatically passes %2$s names and descriptions to the AI so it knows exactly what style/topic of content to write.', 'draftcraft' ),
					esc_html( strtolower( $draftcraft_tax_name ) ),
					esc_html( strtolower( $draftcraft_tax_singular ) )
				);
				?>
			</div>
		</div>
		<div class="draftcraft-section-body">

			<div class="draftcraft-feature-card" style="margin-bottom:16px;">
				<div class="draftcraft-feature-card-header">
					<div class="draftcraft-feature-card-info">
						<strong>
							<span class="dashicons dashicons-controls-repeat" style="color:var(--dc-primary);"></span>
							<?php esc_html_e( 'Category Sequence Rotation', 'draftcraft' ); ?>
							<span class="draftcraft-tooltip-wrap">
								<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
								<span class="draftcraft-tooltip-bubble">
									<?php esc_html_e( 'When active, DraftCraft advances through checked categories in sequence so all your topics get even content distribution.', 'draftcraft' ); ?>
								</span>
							</span>
						</strong>
						<p class="draftcraft-description"><?php esc_html_e( 'Automatically cycles through checked categories in sequence for balanced topic coverage across your site.', 'draftcraft' ); ?></p>
					</div>
					<label class="draftcraft-switch" for="draftcraft_category_rotation" aria-label="<?php esc_attr_e( 'Enable Category Rotation', 'draftcraft' ); ?>">
						<input type="checkbox"
								id="draftcraft_category_rotation"
								name="draftcraft_category_rotation"
								value="1"
								<?php checked( '1', $settings['category_rotation'] ); ?>>
						<span class="draftcraft-switch-track" aria-hidden="true">
							<span class="draftcraft-switch-thumb"></span>
						</span>
					</label>
				</div>

				<?php
				if ( ! empty( $categories ) && ! empty( $settings['categories'] ) && '1' === $settings['category_rotation'] ) :
					$draftcraft_selected  = array_values( array_filter( array_map( 'absint', (array) $settings['categories'] ) ) );
					$draftcraft_rot_order = array_values(
						array_filter(
							array_map( 'absint', (array) ( $settings['category_order'] ?? array() ) ),
							static fn( $id ) => in_array( $id, $draftcraft_selected, true )
						)
					);
					if ( empty( $draftcraft_rot_order ) ) {
						$draftcraft_rot_order = $draftcraft_selected;
					}

					$draftcraft_rot_idx   = ( absint( get_option( 'draftcraft_rotation_index', 0 ) ) % count( $draftcraft_rot_order ) );
					$draftcraft_next_cat  = get_term( $draftcraft_rot_order[ $draftcraft_rot_idx ], $target_taxonomy );
					$draftcraft_after_idx = ( ( $draftcraft_rot_idx + 1 ) % count( $draftcraft_rot_order ) );
					$draftcraft_after_cat = count( $draftcraft_rot_order ) > 1 ? get_term( $draftcraft_rot_order[ $draftcraft_after_idx ], $target_taxonomy ) : null;
					?>
				<div class="draftcraft-feature-card-body">
					<div class="draftcraft-next-cat-preview" style="margin:0;">
						<div class="draftcraft-next-cat-header">
							<span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
							<?php esc_html_e( 'Current Queue Sequence', 'draftcraft' ); ?>
						</div>
						<div class="draftcraft-next-cat-body">
							<div class="draftcraft-next-cat-item is-next">
								<span class="draftcraft-next-cat-badge"><?php esc_html_e( 'Next Post', 'draftcraft' ); ?></span>
								<strong><?php echo $draftcraft_next_cat && ! is_wp_error( $draftcraft_next_cat ) ? esc_html( $draftcraft_next_cat->name ) : '—'; ?></strong>
							</div>
							<?php if ( $draftcraft_after_cat && ! is_wp_error( $draftcraft_after_cat ) ) : ?>
							<div class="draftcraft-next-cat-item">
								<span class="draftcraft-next-cat-badge is-after"><?php esc_html_e( 'Then', 'draftcraft' ); ?></span>
								<span><?php echo esc_html( $draftcraft_after_cat->name ); ?></span>
							</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<div class="draftcraft-feature-card" style="margin-bottom:16px; background:#f8fafc; border-left:3px solid var(--dc-primary);">
				<div class="draftcraft-feature-card-header" style="align-items:center;">
					<div class="draftcraft-feature-card-info">
						<strong>
							<span class="dashicons dashicons-search" style="color:var(--dc-primary);"></span>
							<?php esc_html_e( 'Keyword-Driven Content Queue', 'draftcraft' ); ?>
						</strong>
						<p class="draftcraft-description" style="margin:0;">
							<?php esc_html_e( 'Prefer writing articles around specific target keywords instead of category rotation? Manage your keyword queue in the SEO tab.', 'draftcraft' ); ?>
						</p>
					</div>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'seo', menu_page_url( 'draftcraft', false ) ) ); ?>" class="draftcraft-btn draftcraft-btn--outline draftcraft-tab-switch" data-tab="seo" style="white-space:nowrap; text-decoration:none;">
						<span class="dashicons dashicons-arrow-right-alt"></span>
						<?php esc_html_e( 'Manage Keyword Queue', 'draftcraft' ); ?>
					</a>
				</div>
			</div>

			<div class="draftcraft-field" style="margin-bottom:0;">
				<div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
					<div>
						<label class="draftcraft-label" style="margin-bottom:2px;">
							<?php
							printf(
								/* translators: %s: taxonomy name (e.g. Categories). */
								esc_html__( 'Target %s', 'draftcraft' ),
								esc_html( $draftcraft_tax_name )
							);
							?>
							<span class="draftcraft-tooltip-wrap">
								<span class="dashicons dashicons-editor-help draftcraft-tooltip-icon"></span>
								<span class="draftcraft-tooltip-bubble">
									<?php esc_html_e( 'Select the categories you want DraftCraft to write articles for. Drag rows to change execution order.', 'draftcraft' ); ?>
								</span>
							</span>
						</label>
						<p class="draftcraft-description" style="margin:0;">
							<?php
							printf(
								/* translators: %s: taxonomy name (e.g. categories). */
								esc_html__( 'Check target %s. Drag and drop handles on left to reorder execution hierarchy.', 'draftcraft' ),
								esc_html( strtolower( $draftcraft_tax_name ) )
							);
							?>
						</p>
					</div>
				</div>

				<?php if ( ! empty( $categories ) ) : ?>
				<div class="draftcraft-cat-toolbar">
					<div class="draftcraft-cat-filter-tabs">
						<button type="button" class="draftcraft-cat-tab-btn is-active" data-filter="all">
							<?php esc_html_e( 'All', 'draftcraft' ); ?>
							<span class="draftcraft-cat-pill" id="draftcraft-cat-total-pill"><?php echo count( $categories ); ?></span>
						</button>
						<button type="button" class="draftcraft-cat-tab-btn" data-filter="selected">
							<?php esc_html_e( 'Selected', 'draftcraft' ); ?>
							<span class="draftcraft-cat-pill is-active-pill" id="draftcraft-cat-selected-pill"><?php echo count( (array) ( $settings['categories'] ?? array() ) ); ?></span>
						</button>
					</div>
					<div class="draftcraft-cat-search-wrap">
						<span class="dashicons dashicons-search"></span>
						<input type="search"
								id="draftcraft-cat-search"
								class="draftcraft-cat-search-input"
								placeholder="<?php esc_attr_e( 'Search categories…', 'draftcraft' ); ?>"
								autocomplete="off">
					</div>
					<div class="draftcraft-cat-actions">
						<button type="button" class="draftcraft-cat-action-btn" id="draftcraft-cat-select-all">
							<?php esc_html_e( 'Select All', 'draftcraft' ); ?>
						</button>
						<span class="draftcraft-cat-action-sep">•</span>
						<button type="button" class="draftcraft-cat-action-btn" id="draftcraft-cat-deselect-all">
							<?php esc_html_e( 'Deselect All', 'draftcraft' ); ?>
						</button>
					</div>
				</div>
					<?php
					// Build custom sorted order (Selected items first, then remaining).
					$draftcraft_saved_order = array_map( 'absint', (array) ( $settings['category_order'] ?? array() ) );
					$draftcraft_all_ids     = array_map( static fn( $c ) => $c->term_id, $categories );
					$draftcraft_cats_by_id  = array_column( $categories, null, 'term_id' );
					$draftcraft_ordered_ids = array_merge(
						array_filter( $draftcraft_saved_order, static fn( $id ) => isset( $draftcraft_cats_by_id[ $id ] ) ),
						array_diff( $draftcraft_all_ids, $draftcraft_saved_order )
					);
					?>
				<ul class="draftcraft-sortable-list" id="draftcraft-cat-sortable">
					<?php
					foreach ( $draftcraft_ordered_ids as $draftcraft_cat_id ) :
						$draftcraft_cat = $draftcraft_cats_by_id[ $draftcraft_cat_id ] ?? null;
						if ( ! $draftcraft_cat ) {
							continue;
						}

						$draftcraft_checked = in_array( $draftcraft_cat->term_id, (array) ( $settings['categories'] ?? array() ), true );
						?>
					<li class="draftcraft-sort-item<?php echo $draftcraft_checked ? ' is-selected' : ''; ?>" data-id="<?php echo esc_attr( $draftcraft_cat->term_id ); ?>">
						<span class="draftcraft-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'draftcraft' ); ?>" aria-hidden="true">
							<span class="dashicons dashicons-menu"></span>
						</span>
						<input type="checkbox"
								class="draftcraft-sort-check"
								name="draftcraft_categories[]"
								value="<?php echo esc_attr( $draftcraft_cat->term_id ); ?>"
								id="draftcraft_cat_<?php echo esc_attr( $draftcraft_cat->term_id ); ?>"
								<?php checked( $draftcraft_checked ); ?>>
						<input type="hidden" name="draftcraft_category_order[]" value="<?php echo esc_attr( $draftcraft_cat->term_id ); ?>">
						<?php
						$draftcraft_has_desc = ! empty( $draftcraft_cat->description );
						$draftcraft_edit_url = admin_url( 'term.php?taxonomy=' . $target_taxonomy . '&tag_ID=' . $draftcraft_cat->term_id . '&post_type=post' );
						?>
						<label for="draftcraft_cat_<?php echo esc_attr( $draftcraft_cat->term_id ); ?>" class="draftcraft-sort-label">
							<?php echo esc_html( $draftcraft_cat->name ); ?>
							<span class="draftcraft-checkbox-count"><?php echo absint( $draftcraft_cat->count ); ?></span>
							<?php if ( ! $draftcraft_has_desc ) : ?>
								<a href="<?php echo esc_url( $draftcraft_edit_url ); ?>" class="draftcraft-add-desc-link" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Category has no description. Click to add one for better AI generation.', 'draftcraft' ); ?>">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'Add Description', 'draftcraft' ); ?>
								</a>
							<?php endif; ?>
						</label>
						<span class="draftcraft-sort-badge<?php echo $draftcraft_checked ? ' is-active' : ''; ?>">
							<?php echo $draftcraft_checked ? esc_html__( 'Active', 'draftcraft' ) : esc_html__( 'Skip', 'draftcraft' ); ?>
						</span>
					</li>
					<?php endforeach; ?>
					<li id="draftcraft-cat-empty" class="draftcraft-cat-empty" style="display:none;">
						<span class="dashicons dashicons-search" style="font-size:16px; margin-right:4px;"></span>
						<?php esc_html_e( 'No matching categories found.', 'draftcraft' ); ?>
					</li>
					<li id="draftcraft-cat-load-more" class="draftcraft-cat-load-more" style="display:none;">
						<span class="dashicons dashicons-update draftcraft-spinning"></span>
						<?php esc_html_e( 'Loading more categories…', 'draftcraft' ); ?>
					</li>
				</ul>
				<?php else : ?>
					<p class="draftcraft-notice-inline">
						<?php esc_html_e( 'No categories found. Create categories in Posts → Categories first.', 'draftcraft' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>

</div><!-- /#draftcraft-panel-content -->
