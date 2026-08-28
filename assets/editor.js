/**
 * DraftCraft — AI Post Assistant (Editor Meta Box)
 *
 * Handles the "Apply AI Changes" button on the post edit screen
 * for DraftCraft-generated posts.
 *
 * @package DraftCraft
 * @version 1.2.0
 */

/* jshint esversion: 6 */
(function ( $ ) {
	'use strict';

	if (typeof draftCraftEditorData === 'undefined') {
		return;
	}

	var Data = draftCraftEditorData;

	// Auto-expand the meta box if it was collapsed on page load.
	var attempts       = 0;
	var expandInterval = setInterval(
		function () {
			var $postbox = $( '#draftcraft_ai_assistant_normal' );
			if ($postbox.length) {
				if ($postbox.hasClass( 'closed' )) {
					$postbox.removeClass( 'closed' );
					$postbox.find( '.handlediv, .postbox-header button, .hndle' )
					.attr( 'aria-expanded', 'true' );
				}

				attempts++;
				if (attempts > 10) {
					clearInterval( expandInterval );
				}
			}
		},
		500
	);

	$( '#draftcraft_apply_ai_edit' ).on(
		'click',
		function ( e ) {
			e.preventDefault();

			var $btn        = $( this );
			var $status     = $( '#draftcraft_ai_edit_status' );
			var instruction = $( '#draftcraft_ai_instruction' ).val().trim();
			var nonce       = $( '#draftcraft_ai_edit_nonce' ).val();

			if ( ! instruction) {
				// translators: alert when no AI instructions are entered.
				window.alert( Data.strings.noInstruction || 'Please enter some instructions for the AI.' );
				return;
			}

			$btn.prop( 'disabled', true )
			.text( Data.strings.applying || 'Applying changes\u2026' );

			$status.removeClass( 'is-error is-success' )
			.css( 'color', '#646970' )
			.text( Data.strings.working || 'AI is rewriting\u2026 Please wait. This can take up to a minute.' )
			.show();

			$.ajax(
				{
					url: ajaxurl,
					method: 'POST',
					timeout: 90000,
					data: {
						action: 'draftcraft_ai_edit_post',
						post_id: Data.postId,
						instruction: instruction,
						nonce: nonce
					},
					success: function ( response ) {
						if (response.success) {
							var updatedTitle   = ( response.data && response.data.title ) ? response.data.title : '';
							var updatedContent = ( response.data && response.data.content ) ? response.data.content : '';

							// 1. Gutenberg (Block Editor) integration.
							if (typeof wp !== 'undefined' && wp.data && typeof wp.data.dispatch === 'function') {
								// Update post title in Gutenberg store.
								if (updatedTitle && wp.data.dispatch( 'core/editor' ) && typeof wp.data.dispatch( 'core/editor' ).editPost === 'function') {
									wp.data.dispatch( 'core/editor' ).editPost( { title: updatedTitle } );
								}

								// Parse updated HTML into Gutenberg blocks and update canvas without reload.
								if (updatedContent && typeof wp.blocks !== 'undefined' && typeof wp.blocks.parse === 'function') {
									var parsedBlocks = wp.blocks.parse( updatedContent );
									if (wp.data.dispatch( 'core/block-editor' ) && typeof wp.data.dispatch( 'core/block-editor' ).resetBlocks === 'function') {
										wp.data.dispatch( 'core/block-editor' ).resetBlocks( parsedBlocks );
									} else if (wp.data.dispatch( 'core/editor' ) && typeof wp.data.dispatch( 'core/editor' ).resetBlocks === 'function') {
										wp.data.dispatch( 'core/editor' ).resetBlocks( parsedBlocks );
									}
								}
							}

							// 2. Classic Editor (TinyMCE / Textarea) integration.
							if ($( '#title' ).length && updatedTitle) {
								$( '#title' ).val( updatedTitle ).trigger( 'change' );
							}
							if (typeof tinyMCE !== 'undefined' && tinyMCE.get( 'content' ) && ! tinyMCE.get( 'content' ).isHidden()) {
								if (updatedContent) {
									tinyMCE.get( 'content' ).setContent( updatedContent );
								}
							} else if ($( '#content' ).length && updatedContent) {
								$( '#content' ).val( updatedContent ).trigger( 'change' );
							}

							// Clear instructions field and show success status.
							$( '#draftcraft_ai_instruction' ).val( '' );
							$status.css( 'color', '#00875a' )
							.text( '\u2713 ' + ( ( response.data && response.data.message ) || Data.strings.success || 'AI changes applied successfully!' ) );

							$btn.prop( 'disabled', false )
							.html(
								'<span class="dashicons dashicons-admin-customizer" style="font-size:16px;width:16px;height:16px;margin:0;"></span> ' + ( Data.strings.btnLabel || 'Apply AI Changes' )
							);
							return;
						}

						var msg = ( response.data && response.data.message ) ? response.data.message : ( Data.strings.error || 'An error occurred.' );
						$status.css( 'color', '#d63638' )
						.text( '\u2717 ' + msg );
						$btn.prop( 'disabled', false )
						.html(
							'<span class="dashicons dashicons-admin-customizer" style="font-size:16px;width:16px;height:16px;margin:0;"></span> ' + ( Data.strings.btnLabel || 'Apply AI Changes' )
						);
					},
					error: function () {
						$status.css( 'color', '#d63638' )
						.text( Data.strings.serverError || 'Could not contact server.' );
						$btn.prop( 'disabled', false )
						.html(
							'<span class="dashicons dashicons-admin-customizer" style="font-size:16px;width:16px;height:16px;margin:0;"></span> ' + ( Data.strings.btnLabel || 'Apply AI Changes' )
						);
					}
				}
			);
		}
	);
}( jQuery ) );
