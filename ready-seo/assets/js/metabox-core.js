/*
 * Ready Studio SEO Engine - Metabox Core JS (v12.0)
 * Handles Metabox Tab Switching & ALL Module AJAX
 *
 * This script controls the entire user experience within the
 * post editor metabox, connecting all modules to their
 * respective AJAX handlers.
 */
jQuery(document).ready(function ($) {
	// Find the main metabox app container
	var $app = $('#promptseo-app');
	if (!$app.length) {
		// If the metabox isn't on this page, do nothing.
		return; 
	}

	// --- Global Vars ---
	var post_id = $('#post_ID').val(); // Get the current post ID
	var nonce = $('#rs_nonce_field').val(); // Get the security nonce
	var busy = false; // Prevents multiple simultaneous AJAX requests

	// --- 1. Tab Switching Logic ---
	var $tabs = $('#rs-metabox-tabs .rs-tab-link');
	$tabs.click(function () {
		if (busy) return; // Don't switch tabs while a process is running
		
		var tab_id = $(this).data('tab'); // Get target tab ID (e.g., 'tab-seo')
		
		// Update tab links visual state
		$tabs.removeClass('active');
		$(this).addClass('active');

		// Show/Hide tab content panels
		$('.rs-metabox-content').removeClass('active');
		$('#' + tab_id).addClass('active');
	});

	// --- 2. SEO Module AJAX ---
	// Fired when "Generate SEO" is clicked
	$app.on('click', '#btn-gen-seo', function() {
		if (busy) return;
		// Set UI to busy state for the SEO tab
		setBusy(true, '#load-seo', '#area-seo', this);

		$.post(ajaxurl, {
			action: 'rs_generate_seo', // AJAX action for SEO Module
			post_id: post_id,
			nonce: nonce
		})
		.done(function (res) {
			if (res.success) {
				// Success: Populate fields with AI data
				$('#area-seo').show();
				$('#in-kw').val(res.data.keyword);
				$('#in-title').val(res.data.title);
				$('#in-desc').val(res.data.description);
				$('#in-tags').val(Array.isArray(res.data.tags) ? res.data.tags.join(',') : res.data.tags);
				
				// Handle specific CPT field
				if (res.data.latin_name && $('#in-latin-name').length) {
					$('#in-latin-name').val(res.data.latin_name);
				}
			} else { 
				// Handle AI or WP error
				alert('Error (SEO): ' + res.data); 
			}
		})
		.fail(function () { 
			// Handle server/network error
			alert('Network error. Please try again.'); 
		})
		.always(function () { 
			// Always reset UI state
			setBusy(false, '#load-seo'); 
		});
	});

	// --- 3. Content Module AJAX ---
	// Fired when "Content Writer" is clicked
	$app.on('click', '#btn-gen-content', function() {
		if (busy) return;
		setBusy(true, '#load-content', '#area-content', this);

		$.post(ajaxurl, {
			action: 'rs_generate_content', // AJAX action for Content Module
			post_id: post_id,
			nonce: nonce
		})
		.done(function (res) {
			if (res.success) {
				$('#area-content').show();
				$('#in-content').val(res.data.content_body);
				$('#in-alt').val(res.data.image_alt);
			} else { alert('Error (Content): ' + res.data); }
		})
		.fail(function () { alert('Network error.'); })
		.always(function () { setBusy(false, '#load-content'); });
	});

	// --- 4. Vision Module AJAX ---
	// Fired when "Visual Analysis" is clicked
	$app.on('click', '#btn-gen-vision', function() {
		if (busy) return;
		setBusy(true, '#load-vision', '#area-vision-results', this);

		$.post(ajaxurl, {
			action: 'rs_generate_vision', // AJAX action for Vision Module
			post_id: post_id,
			nonce: nonce
		})
		.done(function (res) {
			if (res.success) {
				$('#area-vision-results').show();
				$('#in-art-style').val(res.data.art_style);
				$('#in-visual-tags').val(Array.isArray(res.data.visual_tags) ? res.data.visual_tags.join(',') : res.data.visual_tags);
				$('#in-alt-vision').val(res.data.alt_text);
				
				// --- BONUS: Auto-fill other tabs ---
				// Fill Alt text in Content tab
				$('#in-alt').val(res.data.alt_text);
				// Append visual tags to SEO tags if SEO tags are empty
				var $seoTags = $('#in-tags');
				if ($seoTags.val() === '') {
					$seoTags.val(res.data.visual_tags.join(','));
				}
			} else { alert('Error (Vision): ' + res.data); }
		})
		.fail(function () { alert('Network error.'); })
		.always(function () { setBusy(false, '#load-vision'); });
	});

	// --- 5. Save All Data ---
	// Fired when "Save All Changes" is clicked
	$app.on('click', '#btn-save-all', function() {
		if (busy) return;
		var btn = $(this);
		btn.text('در حال ذخیره...').prop('disabled', true);
		busy = true;
		
		// Collect data from ALL tabs
		var data_to_save = {
			// SEO Tab
			title: $('#in-title').val(),
			description: $('#in-desc').val(),
			keyword: $('#in-kw').val(),
			tags: $('#in-tags').val(),
			latin_name: $('#in-latin-name').val(),
			
			// Content Tab
			content_body: $('#in-content').val(),
			image_alt: $('#in-alt').val(),
			
			// Vision Tab (if it has better data)
			vision_alt: $('#in-alt-vision').val()
		};
		
		// Prioritize Vision Alt text if it exists, as it's more accurate
		if (data_to_save.vision_alt) {
			data_to_save.image_alt = data_to_save.vision_alt;
		}

		// Sync RankMath UI live for immediate feedback
		if($('input[name="rank_math_focus_keyword"]').length) {
			$('input[name="rank_math_focus_keyword"]').val(data_to_save.keyword);
		}

		$.post(ajaxurl, {
			action: 'rs_save_all_data', // Core AJAX action for saving
			post_id: post_id,
			nonce: nonce,
			seo_data: data_to_save
		})
		.done(function (res) {
			if (res.success) {
				btn.text('ذخیره شد!').css('background', 'var(--g-green)');
				setTimeout(function(){ 
					btn.text('ذخیره تمام تغییرات').css('background', ''); // Reset button
				}, 2000);
			} else {
				alert('Save Error: ' + res.data);
				btn.text('خطا! مجدد تلاش کنید');
			}
		})
		.fail(function () { alert('Network error. Save failed.'); })
		.always(function () {
			btn.prop('disabled', false);
			busy = false;
		});
	});

	// --- Helper Function ---
	/**
	 * Manages the UI loading state.
	 * @param {boolean} isBusy - Whether the UI should be busy.
	 * @param {string} loader - The CSS selector for the loader.
	 * @param {string} [area] - (Optional) The CSS selector for the results area to hide.
	 * @param {HTMLElement} [btn] - (Optional) The button element to disable.
	 */
	function setBusy(isBusy, loader, area, btn) {
		busy = isBusy;
		if (isBusy) {
			$(loader).show();
			if (area) $(area).hide();
			if (btn) $(btn).prop('disabled', true);
		} else {
			$(loader).hide();
			if (btn) $(btn).prop('disabled', false);
		}
	}
});