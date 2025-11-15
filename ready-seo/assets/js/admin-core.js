/*
 * Ready Studio SEO Engine - Admin Core JS (v12.0)
 * Handles Settings Page Tabs and Bulk Generation Logic
 */

jQuery(document).ready(function ($) {
	
	// --- 1. Settings Page Tab Switching ---
	var $settingsTabs = $('.rs-tabs .rs-tab-link');
	if ($settingsTabs.length) {
		$settingsTabs.click(function (e) {
			e.preventDefault();
			var targetTab = $(this).data('tab');
			if (!targetTab) return;

			// Update tabs
			$settingsTabs.removeClass('active');
			$(this).addClass('active');

			// Update content
			$('.rs-tab-content').removeClass('active');
			$('#' + targetTab).addClass('active');

			// Update URL hash for deep linking (optional)
			// window.location.hash = targetTab;
		});
		
		// Activate tab from hash on page load
		// if(window.location.hash) {
		// 	$('a[href="' + window.location.hash + '"]').click();
		// }
	}

	// --- 2. Bulk Generator Logic ---
	var $bulkStartBtn = $('#rs-bulk-start');
	if ($bulkStartBtn.length) {
		
		$('#cb-select-all-1').click(function () {
			$('.rs-bulk-check').prop('checked', this.checked);
		});

		$bulkStartBtn.click(function () {
			var selected_ids = [];
			$('.rs-bulk-check:checked').each(function () {
				selected_ids.push($(this).val());
			});

			if (selected_ids.length === 0) {
				alert('حداقل یک پست انتخاب کنید.');
				return;
			}

			var gen_options = {
				do_seo: $('#opt-seo').is(':checked'),
				do_content: $('#opt-content').is(':checked'),
				do_alt: $('#opt-alt').is(':checked'),
				do_slug: $('#opt-slug').is(':checked'),
				strict_mode: $('#opt-strict').is(':checked')
			};

			if (!confirm('آیا از پردازش ' + selected_ids.length + ' پست مطمئن هستید؟')) return;

			var btn = $(this);
			btn.prop('disabled', true);
			var $logBox = $('#rs-log');
			var $progressBar = $('#rs-progress-bar');
			$logBox.slideDown().html('');
			$progressBar.css('width', '0%');

			var total = selected_ids.length;
			var current = 0;
			var nonce = $('#rs_bulk_nonce').val(); // Get nonce from hidden field

			function processNextPost() {
				if (current >= total) {
					$logBox.append('<div style="color:#4caf50; font-weight:bold;">>>> پایان کامل عملیات.</div>');
					btn.prop('disabled', false);
					return;
				}

				var post_id = selected_ids[current];
				var percent = Math.round(((current + 1) / total) * 100);
				$progressBar.css('width', percent + '%');
				$logBox.append('<div><span class="spinner-rs" style="width:12px; height:12px; margin:0 5px 0 0; border-width:2px; vertical-align: middle;"></span> در حال پردازش [#' + post_id + ']...</div>');
				$logBox.scrollTop($logBox[0].scrollHeight);

				$.post(ajaxurl, {
					action: 'rs_bulk_generate',
					post_id: post_id,
					options: gen_options,
					nonce: nonce
				})
				.done(function (res) {
					var symbol = res.success ? '✓' : '✗';
					var color = res.success ? '#81c784' : '#e57373';
					$logBox.append('<div style="color:' + color + '; padding-right:10px;">' + symbol + ' [#' + post_id + '] ' + res.data + '</div>');
					if (res.success) {
						$('#status-' + post_id).html('<span class="status-badge status-done">Done</span>');
					}
				})
				.fail(function () {
					$logBox.append('<div style="color:#e57373; padding-right:10px;">✗ [#' + post_id + '] خطای فاجعه‌بار (سرور).</div>');
				})
				.always(function () {
					current++;
					$logBox.scrollTop($logBox[0].scrollHeight);
					processNextPost(); // Process next post
				});
			}
			
			// Start the loop
			processNextPost();
		});
	}
});