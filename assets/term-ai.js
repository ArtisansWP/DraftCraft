/**
 * DraftCraft — Term AI Description Generator
 *
 * Injects a "Generate with DraftCraft" button on the taxonomy term
 * edit screen and handles the AJAX description generation request.
 *
 * @package DraftCraft
 * @version 1.2.0
 */

/* jshint esversion: 6 */
(function ( $ ) {
	'use strict';

	if (typeof draftCraftTermData === 'undefined') {
		return;
	}

	var Data = draftCraftTermData;

	function resolveFields()
	{
		var $name = $( '#name' );
		var $slug = $( '#slug' );
		// Rank Math uses wp_editor with this ID; core uses #description.
		var $desc    = $( '#rank_math_description_editor' );
		var editorId = 'rank_math_description_editor';

		if ( ! $desc.length) {
			$desc    = $( '#description' );
			editorId = 'description';
		}

		return { $desc: $desc, $name: $name, $slug: $slug, editorId: editorId };
	}

	function getEditor( editorId )
	{
		if (typeof tinymce === 'undefined') {
			return null;
		}

		return tinymce.get( editorId );
	}

	function getDescription( fields )
	{
		var editor = getEditor( fields.editorId );
		if (editor && ! editor.isHidden()) {
			return ( editor.getContent( { format: 'text' } ) || '' ).trim();
		}

		return ( fields.$desc.val() || '' ).trim();
	}

	function setDescription( fields, text )
	{
		var safe = String( text || '' );
		// Escape plain text into simple HTML paragraphs for TinyMCE.
		var html = $( '<div>' ).text( safe ).html().replace( /\n/g, '<br>' );

		fields.$desc.val( safe ).trigger( 'change' );

		var editor = getEditor( fields.editorId );
		if (editor) {
			editor.setContent( html );
			editor.fire( 'change' );
		}
	}

	function insertButton( fields )
	{
		if ($( '#draftcraft-generate-term-desc' ).length) {
			return true;
		}

		if ( ! fields.$desc.length) {
			return false;
		}

		// Prefer Rank Math / wp.editor wrap, then the description table cell.
		var $anchor = fields.$desc.closest( '.wp-editor-wrap' );
		if ( ! $anchor.length) {
			$anchor = fields.$desc.closest(
				'.rank-math-term-description-wrap td, .term-description-wrap td, td'
			);
		}

		if ( ! $anchor.length) {
			$anchor = fields.$desc;
		}

		var $wrap   = $( '<div class="draftcraft-term-ai" style="margin-top:10px;margin-bottom:8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;"></div>' );
		var btnText = Data.strings.btnLabel || 'Generate with DraftCraft';
		var $btn    = $( '<button type="button" class="button" id="draftcraft-generate-term-desc" style="display:inline-flex;align-items:center;gap:4px;"></button>' )
			.html(
				'<span class="dashicons dashicons-schedule" style="font-size:16px;width:16px;height:16px;line-height:16px;margin:0;"></span><span>' + btnText + '</span>'
			);
		var $status = $( '<span id="draftcraft-term-desc-status" style="font-size:12px;color:#646970;"></span>' );

		$wrap.append( $btn ).append( $status );

		if ($anchor.is( 'td' )) {
			$anchor.append( $wrap );
		} else {
			$anchor.after( $wrap );
		}

		$btn.on(
			'click',
			function () {
				var name     = ( fields.$name.val() || '' ).trim();
				var slug     = ( fields.$slug.val() || '' ).trim();
				var taxonomy = ( $( 'input[name="taxonomy"]' ).val() || '' ).trim();

				if ( ! name) {
					$status.css( 'color', '#d63638' )
					.text( Data.strings.needName || 'Enter a name first.' );
					return;
				}

				if (getDescription( fields )
					&& ! window.confirm( Data.strings.confirm || 'Replace the current description?' )
				) {
					return;
				}

				$btn.prop( 'disabled', true );
				$status.css( 'color', '#646970' )
				.text( Data.strings.generating || 'Generating\u2026' );

				$.ajax(
					{
						url: Data.ajaxUrl,
						method: 'POST',
						timeout: 90000,
						data: {
							action: 'draftcraft_generate_term_desc',
							nonce: Data.nonce,
							name: name,
							slug: slug,
							taxonomy: taxonomy
						},
						success: function ( response ) {
							if (response.success && response.data && response.data.description) {
								setDescription( fields, response.data.description );
								$status.css( 'color', '#00a32a' )
								.text( Data.strings.success || 'Done.' );
							} else {
								var msg = ( response.data && response.data.message )
								|| ( Data.strings.error || 'Failed.' );
								$status.css( 'color', '#d63638' ).text( msg );
							}
						},
						error: function () {
							$status.css( 'color', '#d63638' )
							.text( Data.strings.error || 'Failed.' );
						},
						complete: function () {
							$btn.prop( 'disabled', false );
						}
					}
				);
			}
		);

		return true;
	}

	// Rank Math may init / replace the description field after DOM ready.
	var attempts = 0;
	var timer    = setInterval(
		function () {
			attempts++;
			var fields = resolveFields();
			if (insertButton( fields ) || attempts >= 40) {
					clearInterval( timer );
			}
		},
		250
	);
}( jQuery ) );
