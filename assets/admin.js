/**
 * DraftCraft — Admin JavaScript
 *
 * @package WP_AutoWriter
 * @author  ArtisansWP
 * @version 1.2.0
 */

/* jshint esversion: 6 */
(function ($) {
	'use strict';

	if (typeof draftCraftData === 'undefined') {
		return; }

	const Data         = draftCraftData;
	const strings      = Data.strings || {};
	const cached       = Data.cachedModels || [];
	const cachedImages = Data.cachedImageModels || [];
	const saved        = Data.savedModel || '';
	const savedImage   = Data.savedImageModel || '';

	// DOM refs.
	const $apiKeyInput            = $( '#draftcraft_api_key' );
	const $toggleKey              = $( '#draftcraft-toggle-key' );
	const $fetchBtn               = $( '#draftcraft-fetch-models' );
	const $fetchBtnLabel          = $( '#draftcraft-fetch-btn-label' );
	const $modelSelect            = $( '#draftcraft_model_dropdown' );
	const $modelsStatus           = $( '#draftcraft-models-status' );
	const $fetchImageBtn          = $( '#draftcraft-fetch-image-models' );
	const $fetchImageBtnLabel     = $( '#draftcraft-fetch-image-btn-label' );
	const $imageModelSelect       = $( '#draftcraft_image_model' );
	const $imageModelsStatus      = $( '#draftcraft-image-models-status' );
	const $imageProvider          = $( '#draftcraft_image_provider' );
	const $imageModelField        = $( '#draftcraft-image-model-field' );
	const $imageSystemPromptField = $( '#draftcraft-image-system-prompt-field' );
	const $unsplashKeyField       = $( '#draftcraft-unsplash-key-field' );
	const $unsplashKeyInput       = $( '#draftcraft_unsplash_access_key' );
	const $triggerBtn             = $( '#draftcraft-manual-trigger' );
	const $triggerIcon            = $( '#draftcraft-trigger-icon' );
	const $triggerLabel           = $( '#draftcraft-trigger-label' );
	const $triggerResult          = $( '#draftcraft-trigger-result' );
	const $scheduleCards          = $( '.draftcraft-schedule-card' );

	// On Load: populate from PHP-injected cache.
	if (cached.length > 0) {
		populateModelDropdown( $modelSelect, cached, saved );
		setModelsStatus(
			$modelsStatus,
			'✓ ' + ( strings.cachedNotice || 'Loaded from cache · Click Refresh to update' ),
			'success'
		);
		$fetchBtnLabel.text( strings.btnRefresh || 'Refresh Models' );
	} else {
		$fetchBtnLabel.text( strings.btnFetch || 'Fetch Models' );
	}

	if (cachedImages.length > 0) {
		populateModelDropdown( $imageModelSelect, cachedImages, savedImage );
		setModelsStatus(
			$imageModelsStatus,
			'✓ ' + ( strings.cachedNotice || 'Loaded from cache · Click Refresh to update' ),
			'success'
		);
		$fetchImageBtnLabel.text( strings.btnRefresh || 'Refresh Models' );
	} else {
		$fetchImageBtnLabel.text( strings.btnFetch || 'Fetch Models' );
	}

	// Image provider conditional fields.
	function syncImageProviderFields()
	{
		const provider = $imageProvider.val();
		if ('openrouter' === provider) {
			$imageModelField.prop( 'hidden', false );
			$imageSystemPromptField.prop( 'hidden', false );
			$unsplashKeyField.prop( 'hidden', true );
		} else if ('unsplash' === provider) {
			$imageModelField.prop( 'hidden', true );
			$imageSystemPromptField.prop( 'hidden', true );
			$unsplashKeyField.prop( 'hidden', false );
		} else {
			$imageModelField.prop( 'hidden', true );
			$imageSystemPromptField.prop( 'hidden', true );
			$unsplashKeyField.prop( 'hidden', true );
		}
	}

	if ($imageProvider.length) {
		syncImageProviderFields();
		$imageProvider.on( 'change', syncImageProviderFields );
	}

	// Smart Internal Links & TOC Conditional Sub-Fields.
	const $internalLinksToggle = $( '#draftcraft_internal_links_enabled' );
	const $internalLinksBody   = $( '#draftcraft-internal-links-body' );
	if ($internalLinksToggle.length) {
		$internalLinksToggle.on(
			'change',
			function () {
				$internalLinksBody.prop( 'hidden', ! $( this ).is( ':checked' ) );
			}
		);
	}

	const $tocToggle = $( '#draftcraft_toc_enabled' );
	const $tocBody   = $( '#draftcraft-toc-body' );
	if ($tocToggle.length) {
		$tocToggle.on(
			'change',
			function () {
				$tocBody.prop( 'hidden', ! $( this ).is( ':checked' ) );
			}
		);
	}

	// System Prompt Character Counter.
	const $promptInput = $( '#draftcraft_system_prompt' );
	const $promptCount = $( '#draftcraft-prompt-count' );
	function updatePromptCount()
	{
		if ( ! $promptInput.length || ! $promptCount.length) {
			return; }

		const len = $promptInput.val().length;
		$promptCount.text( len + ' / 800 chars' );
		if (len > 750) {
			$promptCount.css( { background: 'var(--dc-red-bg)', color: 'var(--dc-red)' } );
		} else {
			$promptCount.css( { background: 'var(--dc-border)', color: 'var(--dc-text-muted)' } );
		}
	}
	if ($promptInput.length) {
		updatePromptCount();
		$promptInput.on( 'input propertychange', updatePromptCount );
	}

	// API Key Toggle.
	$toggleKey.on(
		'click',
		function () {
			const isPassword = 'password' === $apiKeyInput.attr( 'type' );
			$apiKeyInput.attr( 'type', isPassword ? 'text' : 'password' );
			$( this ).find( '.dashicons' )
			.toggleClass( 'dashicons-visibility', ! isPassword )
			.toggleClass( 'dashicons-hidden', isPassword );
		}
	);

	// Clear placeholder mask on focus so user can type a fresh key.
	$apiKeyInput.on(
		'focus',
		function () {
			if ($( this ).val() === '\u2022'.repeat( 16 )) {
				$( this ).val( '' );
			}
		}
	);

	$unsplashKeyInput.on(
		'focus',
		function () {
			if ($( this ).val() === '\u2022'.repeat( 16 )) {
				$( this ).val( '' );
			}
		}
	);

	// Fetch / Refresh Models.
	function fetchModels( type, $btn, $btnLabel, $select, $status, fallbackSaved )
	{
		const currentKey = $apiKeyInput.val().trim();
		if ( ! currentKey || currentKey === '') {
			setModelsStatus( $status, strings.noApiKey || '⚠ Please enter and save your OpenRouter API key first.', 'error' );
			return;
		}

		setModelsStatus( $status, strings.fetching || 'Refreshing models\u2026', 'loading' );
		$btn.prop( 'disabled', true );
		$btn.find( '.dashicons' ).addClass( 'draftcraft-spinning' );

		$.ajax(
			{
				url: Data.ajaxUrl,
				method: 'POST',
				data: {
					action: 'draftcraft_fetch_models',
					nonce: Data.fetchNonce,
					type: type
				},
				success: function ( response ) {
					if (response.success && response.data && response.data.models) {
						populateModelDropdown( $select, response.data.models, $select.val() || fallbackSaved );
						setModelsStatus(
							$status,
							'\u2713 ' + ( strings.modelsLoaded || 'Models refreshed!' ),
							'success'
						);
						$btnLabel.text( strings.btnRefresh || 'Refresh Models' );
					} else {
						const msg = ( response.data && response.data.message )
						|| ( strings.modelsFailed || 'Failed to load models.' );
						setModelsStatus( $status, '\u26a0 ' + msg, 'error' );
					}
				},
				error: function () {
					setModelsStatus( $status, '\u26a0 ' + ( strings.modelsFailed || 'Could not fetch models.' ), 'error' );
				},
				complete: function () {
					$btn.prop( 'disabled', false );
					$btn.find( '.dashicons' ).removeClass( 'draftcraft-spinning' );
				}
			}
		);
	}

	$fetchBtn.on(
		'click',
		function () {
			fetchModels( 'text', $fetchBtn, $fetchBtnLabel, $modelSelect, $modelsStatus, saved );
		}
	);

	$fetchImageBtn.on(
		'click',
		function () {
			fetchModels( 'image', $fetchImageBtn, $fetchImageBtnLabel, $imageModelSelect, $imageModelsStatus, savedImage );
		}
	);

	// Populate Dropdown.
	function populateModelDropdown( $select, models, selectId )
	{
		if ( ! $select.length) {
			return; }

		const prevVal = selectId || $select.val() || '';
		$select.empty();

		if ( ! models.length) {
			$select.append(
				$( '<option>' ).val( '' ).text( strings.noModels || '— No models available —' )
			);
			return;
		}

		// Group by provider prefix (part before "/").
		const groups = {};
		models.forEach(
			function ( m ) {
				const parts    = m.id.split( '/' );
				const provider = parts.length > 1 ? parts[0] : 'Other';
				if ( ! groups[ provider ]) {
					groups[ provider ] = []; }

				groups[ provider ].push( m );
			}
		);

		Object.keys( groups ).sort().forEach(
			function ( provider ) {
				const label  = provider.charAt( 0 ).toUpperCase() + provider.slice( 1 );
				const $group = $( '<optgroup>' ).attr( 'label', label );
				groups[ provider ].forEach(
					function ( m ) {
						const $opt = $( '<option>' ).val( m.id ).text( m.name || m.id );
						if (m.id === prevVal) {
							$opt.prop( 'selected', true ); }

						$group.append( $opt );
					}
				);
				$select.append( $group );
			}
		);

		if (prevVal) {
			$select.val( prevVal ); }
	}

	// Models status text.
	function setModelsStatus( $el, msg, type )
	{
		if ( ! $el.length) {
			return; }

		$el.text( msg ).css(
			'color',
			'success' === type ? 'var(--dc-green)' : 'error' === type ? 'var(--dc-red)' : 'var(--dc-text-muted)'
		);
	}

	// Category Sortable List.
	const $sortableList = $( '#draftcraft-cat-sortable' );
	if ($sortableList.length && $.fn.sortable) {
		$sortableList.sortable(
			{
				handle: '.draftcraft-drag-handle',
				placeholder: 'draftcraft-sort-item ui-sortable-placeholder',
				axis: 'y',
				containment: 'parent',
				tolerance: 'pointer'
			}
		);
	}

	// Toggle sorting badge status (Active / Skip) when checkboxes are changed.
	$( document ).on(
		'change',
		'.draftcraft-sort-check',
		function () {
			const isChecked = $( this ).is( ':checked' );
			const $item     = $( this ).closest( '.draftcraft-sort-item' );
			const $badge    = $item.find( '.draftcraft-sort-badge' );

			if (isChecked) {
				$badge.addClass( 'is-active' ).text( 'Active' );
			} else {
				$badge.removeClass( 'is-active' ).text( 'Skip' );
			}
		}
	);

	// Schedule card highlight.
	$scheduleCards.on(
		'click',
		function () {
			$scheduleCards.removeClass( 'is-selected' );
			$( this ).addClass( 'is-selected' );
		}
	);

	// Manual trigger (Two-Stage Pipeline Tracker).
	let pipelinePollTimer = null;
	let ownRequestActive  = false;

	function setGenerateBusy( message )
	{
		$triggerBtn.prop( 'disabled', true );
		$triggerLabel.text( strings.generating || 'Generating post…' );
		$triggerIcon.addClass( 'draftcraft-spinning' );
		if (message) {
			$triggerResult.removeClass( 'is-success' ).addClass( 'is-error' ).text( message ).show();
		}
	}

	function setGenerateIdle()
	{
		ownRequestActive = false;
		$triggerBtn.prop( 'disabled', false );
		$triggerLabel.text( strings.btnGenerate || 'Generate Post Now' );
		$triggerIcon.removeClass( 'draftcraft-spinning' );
	}

	function stopPipelinePoll()
	{
		if (pipelinePollTimer) {
			clearInterval( pipelinePollTimer );
			pipelinePollTimer = null;
		}
	}

	function startPipelinePoll()
	{
		stopPipelinePoll();
		pipelinePollTimer = setInterval(
			function () {
				$.ajax(
					{
						url: Data.ajaxUrl,
						method: 'POST',
						data: {
							action: 'draftcraft_pipeline_status',
							nonce: Data.nonceStatus
						}
					}
				).done(
					function ( response ) {
						if (response.success && response.data && ! response.data.busy) {
								stopPipelinePoll();
							if ( ! ownRequestActive) {
								setGenerateIdle();
								$triggerResult.removeClass( 'is-success is-error' ).hide().html( '' );
							}
						}
					}
				);
			},
			3000
		);
	}

	function isBusyLockMessage( msg )
	{
		const text = String( msg || '' ).toLowerCase();
		return text.indexOf( 'already running' ) !== -1
			|| text.indexOf( 'already in progress' ) !== -1;
	}

	if (Data.pipelineBusy) {
		setGenerateBusy( strings.alreadyRunning || 'A generation is already in progress. Please wait…' );
		startPipelinePoll();
	}

	function renderFinalResult( title, editUrl )
	{
		const editLink   = editUrl ? '<a href="' + escHtml( editUrl ) + '" target="_blank" rel="noopener">' + escHtml( strings.editPost || 'View / Edit post →' ) + '</a>' : '';
		const titleHtml  = title ? '<div class="draftcraft-gen-title" style="font-size:12px;">' + escHtml( title ) + '</div>' : '';
		const linkHtml   = editLink ? '<div>' + editLink + '</div>' : '';
		const footerHtml = '<div class="draftcraft-gen-footer is-success"><div><span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span> <strong>' + escHtml( strings.success || 'Post created successfully!' ) + '</strong></div>' + titleHtml + linkHtml + '</div>';
		$triggerResult.find( '.draftcraft-gen-footer' ).remove();
		$triggerResult.find( '.draftcraft-pipeline-tracker' ).after( footerHtml );
	}

	$triggerBtn.on(
		'click',
		function ( e ) {
			e.preventDefault();
			e.stopPropagation();
			if ($triggerBtn.prop( 'disabled' )) {
				return; }

			ownRequestActive = true;
			stopPipelinePoll();
			setGenerateBusy( '' );

			const selectedProvider = $imageProvider.length ? $imageProvider.val() : ( Data.imageProvider || 'none' );
			const hasImageTask     = ( selectedProvider && selectedProvider !== 'none' );

			// Build 2-stage visual tracker.
			const contentStepTitle = escHtml( hasImageTask ? ( strings.stepContentTitle || 'Step 1/2: Generating Article & SEO…' ) : 'Generating Article & SEO…' );
			const contentStepDesc  = escHtml( strings.stepContentDesc || 'OpenRouter AI is generating content, headings, and schema…' );
			let trackerHtml        = '<div class="draftcraft-pipeline-tracker">';
			trackerHtml           += '<div class="draftcraft-step draftcraft-step--active" id="draftcraft-step-content"><div class="draftcraft-step-bullet"><span class="dashicons dashicons-update draftcraft-spinning"></span></div><div class="draftcraft-step-content"><strong>' + contentStepTitle + '</strong><span class="draftcraft-step-desc">' + contentStepDesc + '</span></div></div>';

			if (hasImageTask) {
				const imageStepTitle = escHtml( strings.stepImageTitle || 'Step 2/2: Generating Featured Image…' );
				const imageStepDesc  = escHtml( strings.stepImagePending || 'Will generate and attach once article is drafted.' );
				trackerHtml         += '<div class="draftcraft-step draftcraft-step--pending" id="draftcraft-step-image"><div class="draftcraft-step-bullet"><span class="dashicons dashicons-format-image"></span></div><div class="draftcraft-step-content"><strong>' + imageStepTitle + '</strong><span class="draftcraft-step-desc">' + imageStepDesc + '</span></div></div>';
			}

			trackerHtml += '</div>';

			$triggerResult.removeClass( 'is-success is-error' ).html( trackerHtml ).show();

			// Step 1: Generate Post Content.
			$.ajax(
				{
					url: Data.ajaxUrl,
					method: 'POST',
					timeout: 130000,
					data: {
						action: 'draftcraft_manual_trigger',
						nonce: Data.nonceTrigger
					},
					success: function ( response ) {
						if (response.success && response.data) {
							const d          = response.data;
							const postId     = d.post_id;
							const title      = d.title || 'DraftCraft Post';
							const editUrl    = d.edit_url || '';
							const needsImage = d.has_image_task && ( d.image_provider !== 'none' );

							// Mark Step 1 Done.
							$( '#draftcraft-step-content' )
							.removeClass( 'draftcraft-step--active draftcraft-step--pending' )
							.addClass( 'draftcraft-step--done' );
							$( '#draftcraft-step-content .draftcraft-step-bullet' )
							.html( '<span class="dashicons dashicons-yes-alt"></span>' );
							$( '#draftcraft-step-content .draftcraft-step-content strong' )
							.text( ( strings.stepContentDone || '✓ Article Drafted: ' ) + title );
							$( '#draftcraft-step-content .draftcraft-step-desc' )
							.text( strings.stepContentDoneDesc || 'Post content, SEO, and schema saved to WordPress.' );

							if (needsImage && postId) {
								// Activate Step 2.
								const providerName = ( d.image_provider === 'unsplash' ? 'Unsplash API' : 'OpenRouter Model' );
								$( '#draftcraft-step-image' )
								.removeClass( 'draftcraft-step--pending' )
								.addClass( 'draftcraft-step--active' );
								$( '#draftcraft-step-image .draftcraft-step-bullet' )
								.html( '<span class="dashicons dashicons-update draftcraft-spinning"></span>' );
								$( '#draftcraft-step-image .draftcraft-step-content strong' )
								.text( strings.stepImageActive || 'Step 2/2: Generating & Attaching Featured Image…' );
								$( '#draftcraft-step-image .draftcraft-step-desc' )
								.text( ( strings.stepImageConnecting || 'Connecting to ' ) + providerName + '…' );

								// Step 2: Fetch / Generate Image Live.
								$.ajax(
									{
										url: Data.ajaxUrl,
										method: 'POST',
										timeout: 60000,
										data: {
											action: 'draftcraft_generate_image_now',
											nonce: Data.nonceImageGenerate,
											post_id: postId
										},
										success: function ( imgRes ) {
											if (imgRes.success) {
												$( '#draftcraft-step-image' )
												.removeClass( 'draftcraft-step--active draftcraft-step--pending' )
												.addClass( 'draftcraft-step--done' );
												$( '#draftcraft-step-image .draftcraft-step-bullet' )
												.html( '<span class="dashicons dashicons-yes-alt"></span>' );
												$( '#draftcraft-step-image .draftcraft-step-content strong' )
												.text( strings.stepImageDone || '✓ Featured image attached to Media Library!' );
												$( '#draftcraft-step-image .draftcraft-step-desc' )
												.text( strings.stepImageDoneDesc || 'Featured image set as post thumbnail.' );
											} else {
												const warnMsg = ( imgRes.data && imgRes.data.message ) ? imgRes.data.message : 'Image generation skipped.';
												$( '#draftcraft-step-image' )
												.removeClass( 'draftcraft-step--active draftcraft-step--pending' )
												.addClass( 'draftcraft-step--warn' );
												$( '#draftcraft-step-image .draftcraft-step-bullet' )
												.html( '<span class="dashicons dashicons-warning"></span>' );
												$( '#draftcraft-step-image .draftcraft-step-content strong' )
												.text( strings.stepImageWarn || 'Featured image could not be loaded' );
												$( '#draftcraft-step-image .draftcraft-step-desc' )
												.text( warnMsg );
											}//end if

											renderFinalResult( title, editUrl );
											setGenerateIdle();
										},
										error: function () {
											$( '#draftcraft-step-image' )
											.removeClass( 'draftcraft-step--active draftcraft-step--pending' )
											.addClass( 'draftcraft-step--warn' );
											$( '#draftcraft-step-image .draftcraft-step-bullet' )
											.html( '<span class="dashicons dashicons-warning"></span>' );
											$( '#draftcraft-step-image .draftcraft-step-content strong' )
											.text( strings.stepImageWarn || 'Featured image timed out (Post content is saved).' );
											renderFinalResult( title, editUrl );
											setGenerateIdle();
										}
									}
								);
							} else {
								renderFinalResult( title, editUrl );
								setGenerateIdle();
							}//end if

							return;
						}//end if

						const msg = ( response.data && response.data.message )
						|| ( strings.error || 'An error occurred.' );
						$triggerResult.addClass( 'is-error' ).text( '✗ ' + msg ).show();

						if (isBusyLockMessage( msg )) {
							setGenerateBusy( strings.alreadyRunning || msg );
							startPipelinePoll();
							return;
						}

						setGenerateIdle();
					},
					error: function ( xhr, status ) {
						let msg;
						if ('timeout' === status) {
							msg = strings.timeout || 'Request timed out. The API may be slow — try again.';
						} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
							msg = xhr.responseJSON.data.message;
						} else if (xhr.responseJSON && typeof xhr.responseJSON.data === 'string') {
							msg = xhr.responseJSON.data;
						} else {
							msg = strings.error || 'An error occurred. Please try again.';
						}

						$triggerResult.addClass( 'is-error' ).text( '✗ ' + msg ).show();

						if (isBusyLockMessage( msg )) {
							setGenerateBusy( strings.alreadyRunning || msg );
							startPipelinePoll();
							return;
						}

						setGenerateIdle();
					}
				}
			);
		}
	);

	// CSV Keyword Import (AJAX).
	const $csvImportBtn  = $( '#draftcraft-import-csv' );
	const $csvFileInput  = $( '#draftcraft_csv_file' );
	const $csvModeSelect = $( '#draftcraft_csv_mode' );
	const $csvStatus     = $( '#draftcraft-csv-import-status' );
	const $queueBody     = $( '#draftcraft-queue-body' );
	const $queueStatsTxt = $( '#draftcraft-queue-stats-text' );

	$csvImportBtn.on(
		'click',
		function () {
			const fileInput = $csvFileInput.get( 0 );
			if ( ! fileInput || ! fileInput.files || ! fileInput.files.length) {
				$csvStatus.css( 'color', 'var(--dc-red)' ).text( strings.csvNoFile || 'Please choose a CSV file first.' );
				return;
			}

			const formData = new FormData();
			formData.append( 'action', 'draftcraft_import_csv' );
			formData.append( 'nonce', Data.nonceCsvImport || '' );
			formData.append( 'draftcraft_csv_mode', $csvModeSelect.val() || 'append' );
			formData.append( 'draftcraft_csv_file', fileInput.files[0] );

			$csvImportBtn.prop( 'disabled', true );
			$csvStatus.css( 'color', 'var(--dc-text-muted)' ).text( strings.csvImporting || 'Importing keywords…' );

			$.ajax(
				{
					url: Data.ajaxUrl,
					method: 'POST',
					data: formData,
					processData: false,
					contentType: false
				}
			).done(
				function ( response ) {
					if (response && response.success && response.data) {
							const d = response.data;
							$csvStatus.css( 'color', 'var(--dc-green)' ).text( d.message || 'Import complete.' );
						if (d.queue_html && $queueBody.length) {
							$queueBody.html( d.queue_html );
						}

						if ($queueStatsTxt.length) {
							const tpl = strings.queueStats || 'Queue: %1$d pending / %2$d total.';
							$queueStatsTxt.text(
								tpl.replace( '%1$d', String( d.pending || 0 ) )
								.replace( '%2$d', String( d.total || 0 ) )
							);
						}

						$csvFileInput.val( '' );
						return;
					}

					const msg = ( response && response.data && response.data.message ) ? response.data.message : ( strings.error || 'An error occurred.' );
					$csvStatus.css( 'color', 'var(--dc-red)' ).text( msg );
				}
			).fail(
				function ( xhr ) {
					let msg = strings.error || 'An error occurred.';
					if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
							msg = xhr.responseJSON.data.message;
					}

					$csvStatus.css( 'color', 'var(--dc-red)' ).text( msg );
				}
			).always(
				function () {
					$csvImportBtn.prop( 'disabled', false );
				}
			);
		}
	);

	// Unsaved Changes Tracker.
	let isDirty         = false;
	const $saveWidget   = $( '#draftcraft-save-widget' );
	const $saveTag      = $( '#draftcraft-save-tag' );
	const $saveBtn      = $( '#draftcraft-save-btn' );
	const $saveBtnText  = $( '#draftcraft-save-btn-text' );
	const $saveHelpText = $( '#draftcraft-save-help-text' );
	const $unsavedBadge = $( '#draftcraft-unsaved-badge' );
	const $settingsForm = $( '#draftcraft-form' );

	function markDirty()
	{
		if (isDirty) {
			return; }

		isDirty = true;

		if ($saveWidget.length) {
			$saveWidget.addClass( 'has-unsaved' );
		}

		if ($saveTag.length) {
			$saveTag.show();
		}

		if ($saveBtn.length) {
			$saveBtn.addClass( 'is-dirty' );
		}

		if ($saveBtnText.length) {
			$saveBtnText.text( ( strings.saveChanges || 'Save Changes' ) + ' ●' );
		}

		if ($saveHelpText.length) {
			$saveHelpText.text( strings.unsavedNotice || 'You have unsaved changes. Remember to save!' );
		}

		if ($unsavedBadge.length) {
			$unsavedBadge.show();
		}
	}

	// Listen to changes in settings form inputs, selects, textareas.
	$settingsForm.on(
		'input change',
		'input:not(#draftcraft_csv_file), select:not(#draftcraft_csv_mode), textarea',
		function () {
			markDirty();
		}
	);

	// Listen to category sortable reordering.
	if ($sortableList.length) {
		$sortableList.on(
			'sortupdate',
			function () {
				markDirty();
			}
		);
	}

	// Category List Manager (Tabs, Lazy Reveal & Search).
	const $catList        = $( '#draftcraft-cat-sortable' );
	const $catItems       = $catList.find( '.draftcraft-sort-item' );
	const $catSearch      = $( '#draftcraft-cat-search' );
	const $catTabBtns     = $( '.draftcraft-cat-tab-btn' );
	const $totalPill      = $( '#draftcraft-cat-total-pill' );
	const $selectedPill   = $( '#draftcraft-cat-selected-pill' );
	const $catSelectAll   = $( '#draftcraft-cat-select-all' );
	const $catDeselectAll = $( '#draftcraft-cat-deselect-all' );
	const $catEmpty       = $( '#draftcraft-cat-empty' );
	const $catLoadMore    = $( '#draftcraft-cat-load-more' );

	let currentCatTab    = 'all';
	let catVisibleLimit  = 25;
	const CAT_CHUNK_SIZE = 25;

	function refreshCategoryList()
	{
		if ( ! $catItems.length) {
			return; }

		const query       = $catSearch.length ? $catSearch.val().toLowerCase().trim() : '';
		const totalCount  = $catItems.length;
		let selectedCount = 0;
		let visibleCount  = 0;
		let matchedCount  = 0;

		$catItems.each(
			function () {
				const $item     = $( this );
				const isChecked = $item.find( '.draftcraft-sort-check' ).is( ':checked' );
				const labelText = $item.find( '.draftcraft-sort-label' ).text().toLowerCase();
				const $badge    = $item.find( '.draftcraft-sort-badge' );

				if (isChecked) {
						selectedCount++;
						$item.addClass( 'is-selected' );
						$badge.addClass( 'is-active' ).text( 'Active' );
				} else {
					$item.removeClass( 'is-selected' );
					$badge.removeClass( 'is-active' ).text( 'Skip' );
				}

				// Tab filter: 'selected' view hides unchecked.
				if (currentCatTab === 'selected' && ! isChecked) {
					$item.hide();
					return;
				}

				// Search query filter.
				if (query && labelText.indexOf( query ) === -1) {
					$item.hide();
					return;
				}

				matchedCount++;

				// If searching or in 'selected' view, show all matches immediately.
				if (query || currentCatTab === 'selected') {
					$item.show();
					visibleCount++;
				} else {
					// In 'all' view with lazy reveal.
					if (matchedCount <= catVisibleLimit) {
								$item.show();
								visibleCount++;
					} else {
						$item.hide();
					}
				}
			}
		);

		// Update counts in pills.
		if ($totalPill.length) {
			$totalPill.text( totalCount ); }

		if ($selectedPill.length) {
			$selectedPill.text( selectedCount ); }

		// Show/hide empty state.
		if (matchedCount === 0) {
			$catEmpty.show();
		} else {
			$catEmpty.hide();
		}

		// Show/hide loading indicator.
		if (currentCatTab === 'all' && ! query && catVisibleLimit < matchedCount) {
			$catLoadMore.show();
		} else {
			$catLoadMore.hide();
		}
	}

	if ($catList.length) {
		refreshCategoryList();

		// Tab click (All vs Selected).
		$catTabBtns.on(
			'click',
			function (e) {
				e.preventDefault();
				const filter = $( this ).data( 'filter' );
				$catTabBtns.removeClass( 'is-active' );
				$( this ).addClass( 'is-active' );
				currentCatTab   = filter;
				catVisibleLimit = 25;
				$catList.scrollTop( 0 );
				refreshCategoryList();
			}
		);

		// Search input.
		$catSearch.on(
			'input keyup',
			function () {
				catVisibleLimit = 25;
				refreshCategoryList();
			}
		);

		// Scroll event for Infinite Lazy Reveal.
		$catList.on(
			'scroll',
			function () {
				if (currentCatTab !== 'all' || ( $catSearch.val() && $catSearch.val().trim() )) {
					return;
				}

				const scrollTop    = $catList.scrollTop();
				const scrollHeight = $catList[0].scrollHeight;
				const clientHeight = $catList.innerHeight();

				if (scrollTop + clientHeight >= (scrollHeight - 30)) {
					if (catVisibleLimit < $catItems.length) {
						catVisibleLimit += CAT_CHUNK_SIZE;
						refreshCategoryList();
					}
				}
			}
		);

		// Checkbox toggle.
		$catList.on(
			'change',
			'.draftcraft-sort-check',
			function () {
				refreshCategoryList();
				markDirty();
			}
		);

		// Select All (applies to currently visible items).
		$catSelectAll.on(
			'click',
			function (e) {
				e.preventDefault();
				$catItems.filter( ':visible' ).find( '.draftcraft-sort-check' ).prop( 'checked', true );
				refreshCategoryList();
				markDirty();
			}
		);

		// Deselect All (applies to currently visible items).
		$catDeselectAll.on(
			'click',
			function (e) {
				e.preventDefault();
				$catItems.filter( ':visible' ).find( '.draftcraft-sort-check' ).prop( 'checked', false );
				refreshCategoryList();
				markDirty();
			}
		);
	}//end if

	// When saving form, clear dirty flag so beforeunload doesn't prompt.
	$settingsForm.on(
		'submit',
		function () {
			isDirty = false;
		}
	);

	// Also clear dirty flag when save button is clicked directly.
	$saveBtn.on(
		'click',
		function () {
			isDirty = false;
		}
	);

	// Warn if leaving page with unsaved modifications.
	window.addEventListener(
		'beforeunload',
		function ( e ) {
			if (isDirty) {
				e.preventDefault();
				e.returnValue = strings.leaveWarning || 'You have unsaved changes in DraftCraft settings.';
				return e.returnValue;
			}
		}
	);

	// Utility.
	function escHtml( str )
	{
		return $( '<div>' ).text( String( str ) ).html();
	}

	// Toast Notification for Settings Saved.
	const urlParams = new URLSearchParams( window.location.search );
	if (urlParams.has( 'draftcraft_saved' ) && urlParams.get( 'draftcraft_saved' ) === '1') {
		const $toast = $( '#draftcraft-toast' );
		if ($toast.length) {
			// Show toast.
			$toast.addClass( 'is-visible' );

			// Hide after 4 seconds.
			setTimeout(
				function () {
					$toast.removeClass( 'is-visible' );
				},
				4000
			);

			// Clean up URL to prevent toast on refresh.
			let newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?page=draftcraft';
			if (urlParams.has( 'tab' )) {
				newUrl += '&tab=' + urlParams.get( 'tab' );
			}

			window.history.replaceState( {path: newUrl}, '', newUrl );
		}
	}//end if
})( jQuery );
