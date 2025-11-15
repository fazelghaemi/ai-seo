/*
 * Ready Studio SEO Engine - Metabox Core JS (v12.0)
 * Handles Metabox Tab Switching & ALL Module AJAX
 */
jQuery(document).ready(function ($) {
	var $app = $('#promptseo-app');
	if (!$app.length) return; // Exit if metabox not found

	// --- Global Vars ---
	var post_id = $('#post_ID').val();
	var nonce = $('#rs_nonce_field').val();
	var busy = false; // Prevent multiple clicks

	// --- 1. Tab Switching ---
	var $tabs = $('#rs-metabox-tabs .rs-tab-link');
	$tabs.click(function () {
		if (busy) return;
		var tab_id = $(this).data('tab');
		
		$tabs.removeClass('active');
		$(this).addClass('active');

		$('.rs-metabox-content').removeClass('active');
		$('#' + tab_id).addClass('active');
	});

	// --- 2. SEO Module AJAX ---
	$app.on('click', '#btn-gen-seo', function() {
		if (busy) return;
		setBusy(true, '#load-seo', '#area-seo', this);

		$.post(ajaxurl, {
			action: 'rs_generate_seo',
			post_id: post_id,
			nonce: nonce
		})
		.done(function (res) {
			if (res.success) {
				$('#area-seo').show();
				$('#in-kw').val(res.data.keyword);
				$('#in-title').val(res.data.title);
				$('#in-desc').val(res.data.description);
				$('#in-tags').val(Array.isArray(res.data.tags) ? res.data.tags.join(',') : res.data.tags);
				if (res.data.latin_name && $('#in-latin-name').length) {
					$('#in-latin-name').val(res.data.latin_name);
				}
			} else { alert('Error: ' + res.data); }
		})
		.fail(function () { alert('Network error.'); })
		.always(function () { setBusy(false, '#load-seo'); });
	});

	// --- 3. Content Module AJAX ---
	$app.on('click', '#btn-gen-content', function() {
		if (busy) return;
		setBusy(true, '#load-content', '#area-content', this);

		$.post(ajaxurl, {
			action: 'rs_generate_content',
			post_id: post_id,
			nonce: nonce
		})
		.done(function (res) {
			if (res.success) {
				$('#area-content').show();
				$('#in-content').val(res.data.content_body);
				$('#in-alt').val(res.data.image_alt);
			} else { alert('Error: ' + res.data); }
		})
		.fail(function () { alert('Network error.'); })
		.always(function () { setBusy(false, '#load-content'); });
	});

	// --- 4. Vision Module AJAX ---
	$app.on('click', '#btn-gen-vision', function() {
		if (busy) return;
		setBusy(true, '#load-vision', '#area-vision-results', this);

		$.post(ajaxurl, {
			action: 'rs_generate_vision',
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
				// Append visual tags to SEO tags
				var seoTags = $('#in-tags');
				if (seoTags.val() === '') {
					seoTags.val(res.data.visual_tags.join(','));
				}
			} else { alert('Error: ' + res.data); }
		})
		.fail(function () { alert('Network error.'); })
		.always(function () { setBusy(false, '#load-vision'); });
	});

	// --- 5. Save All Data ---
	$app.on('click', '#btn-save-all', function() {
		if (busy) return;
		var btn = $(this);
		btn.text('در حال ذخیره...').prop('disabled', true);
		busy = true;
		
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
		
		// Prioritize Vision Alt text if it exists
		if (data_to_save.vision_alt) {
			data_to_save.image_alt = data_to_save.vision_alt;
		}

		// Sync RankMath UI live
		if($('input[name="rank_math_focus_keyword"]').length) {
			$('input[name="rank_math_focus_keyword"]').val(data_to_save.keyword);
		}

		$.post(ajaxurl, {
			action: 'rs_save_all_data',
			post_id: post_id,
			nonce: nonce,
			seo_data: data_to_save
		})
		.done(function (res) {
			if (res.success) {
				btn.text('ذخیره شد!').css('background', 'var(--g-green)');
				setTimeout(function(){ 
					btn.text('ذخیره تمام تغییرات').css('background', ''); 
				}, 2000);
			} else {
				alert('Save Error: ' + res.data);
				btn.text('خطا! مجدد تلاش کنید');
			}
		})
		.fail(function () { alert('Network error.'); })
		.always(function () {
			btn.prop('disabled', false);
			busy = false;
		});
	});

	// --- Helper Function ---
	function setBusy(isBusy, loader, area, btn) {
		busy = isBusy;
		if (isBusy) {
			$(loader).show();
			$(area).hide();
			if (btn) $(btn).prop('disabled', true);
		} else {
			$(loader).hide();
			if (btn) $(btn).prop('disabled', false);
		}
	}
});