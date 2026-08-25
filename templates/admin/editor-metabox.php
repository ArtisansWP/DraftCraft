<?php
/**
 * DraftCraft — Editor AI Assistant Metabox View
 *
 * @package DraftCraft
 * @since   1.2.1
 *
 * @var WP_Post $post Current post object.
 */

defined( 'ABSPATH' ) || exit;

wp_nonce_field( 'draftcraft_ai_edit', 'draftcraft_ai_edit_nonce' );
?>
<div class="draftcraft-ai-editor-box" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;">
	<p style="margin-top:0; font-size:12px; color:#64748b; line-height:1.45;">
		<?php esc_html_e( 'Enter instructions to refine, expand, or adjust the tone of this post. AI updates the title and body while preserving your structure.', 'draftcraft' ); ?>
	</p>
	<div style="margin-bottom:12px;">
		<textarea id="draftcraft_ai_instruction" 
					rows="4" 
					style="width:100%; border-radius:6px; font-size:13px; padding:10px 12px; border:1px solid #cbd5e1; box-sizing:border-box; background:#f8fafc; color:#0f172a; line-height:1.45; resize:vertical;" 
					placeholder="<?php esc_attr_e( 'e.g. Rewrite the introduction to hook the reader better, make headings punchier, and add a quick takeaways section at the end.', 'draftcraft' ); ?>"></textarea>
	</div>
	<button type="button" 
			id="draftcraft_apply_ai_edit" 
			class="button button-primary button-large" 
			style="width:100%; display:inline-flex; align-items:center; justify-content:center; gap:6px; height:36px; font-weight:600; background:#00875a; border-color:#00704a; box-shadow:none; text-shadow:none;">
		<span class="dashicons dashicons-admin-customizer" style="font-size:16px; width:16px; height:16px; margin:0;"></span>
		<?php esc_html_e( 'Apply AI Refinements', 'draftcraft' ); ?>
	</button>
	<div id="draftcraft_ai_edit_status" style="margin-top:10px; font-size:12px; display:none; line-height:1.4; padding:8px 10px; border-radius:4px;"></div>
</div>
