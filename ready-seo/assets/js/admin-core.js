/*
 * Ready Studio SEO Engine - Admin Core JS (v12.5 - Log Fix)
 *
 * v12.5: Made log messages in bulk generator clearer and more precise.
 * v12.3: Added AJAX handler for "Test Connection" button.
 */

jQuery(document).ready(function ($) {
	
	// --- 1. Settings Page Tab Switching ---
	var $settingsPage = $('.rs-wrap.settings-page'); // Target only settings page
	if ($settingsPage.length) {
		var $settingsTabs = $settingsPage.find('.rs-tabs .rs-tab-link');
		
		$settingsTabs.click(function (e) {
			e.preventDefault();
			var targetTab = $(this).data('tab'); // Get tab ID from data-tab attribute
			if (!targetTab) return;

			// Update tabs
			$settingsTabs.removeClass('active');
			$(this).addClass('active');

			// Update content
			$settingsPage.find('.rs-tab-content').removeClass('active');
			$('#' + targetTab).addClass('active');

			// Update URL for deep linking
			var newUrl = window.location.href.split('?')[0] + '?page=promptseo_dashboard&tab=' + targetTab;
			window.history.pushState({ path: newUrl }, '', newUrl);
		});
		
		// Activate tab from URL parameter on page load
		var urlParams = new URLSearchParams(window.location.search);
		var activeTab = urlParams.get('tab') || 'api'; // Default to 'api'
		$settingsPage.find('.rs-tab-link[data-tab="' + activeTab + '"]').click();
		
		// --- Test Connection AJAX Handler ---
		$('#rs-test-connection-btn').click(function() {
			var $btn = $(this);
			var $resultDiv = $('#rs-test-connection-result');
			var nonce = $('#rs_test_nonce').val();

			// Get *current* (unsaved) values from form fields
			var worker_url = $('#field-worker_url').val();
			var api_key = $('#field-api_key').val();

			// Set loading state
			$btn.prop('disabled', true).text('در حال تست...');
			$resultDiv.css('color', 'var(--g-text-light)').text('در حال ارسال درخواست تست به ورکر...');

			$.post(ajaxurl, {
				action: 'rs_test_connection',
				worker_url: worker_url,
				api_key: api_key,
				rs_test_nonce: nonce // Send the nonce
			})
			.done(function(res) {
				if (res.success) {
					// Success (Green)
					$resultDiv.css('color', 'var(--g-green)').text('✓ ' + res.data.message);
				} else {
					// Failure (Red)
					$resultDiv.css('color', 'var(--g-red)').text('✗ خطا: ' + res.data.message);
				}
			})
			.fail(function() {
				// Server Error (Red)
				$resultDiv.css('color', 'var(--g-red)').text('✗ خطای سرور. (AJAX Fail)');
			})
			.always(function() {
				// Reset button
				$btn.prop('disabled', false).text('تست ارتباط با ورکر');
			});
		});
	}

	// --- 2. Bulk Generator Logic (LOGGING IMPROVED) ---
	var $bulkPage = $('.rs-wrap.bulk-page'); // Target only bulk page
	if ($bulkPage.length) {
		
		var $bulkStartBtn = $('#rs-bulk-start');
		var $logBox = $('#rs-log');
		var $progressBar = $('#rs-progress-bar');
		var nonce = $('#rs_bulk_nonce').val(); // Get nonce from hidden field

		// Select/Deselect all posts
		$('#cb-select-all-1').click(function () {
			$('.rs-bulk-check').prop('checked', this.checked);
		});

		// Start button click handler
		$bulkStartBtn.click(function () {
			var selected_ids = [];
			$('.rs-bulk-check:checked').each(function () {
				selected_ids.push($(this).val());
			});

			if (selected_ids.length === 0) {
				alert('حداقل یک پست انتخاب کنید.');
				return;
			}

			// Get all selected options from checkboxes
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
			$logBox.slideDown().html('<div>[INFO] شروع عملیات...</div>'); // Clear log
			$progressBar.css('width', '0%');

			var total = selected_ids.length;
			var current = 0;

			// --- Recursive function to process posts one by one ---
			function processNextPost() {
				if (current >= total) {
					// --- All posts processed ---
					$logBox.append('<div style="color:#4caf50; font-weight:bold; padding-top: 10px;">>>> تمام آیتم‌های صف پردازش شدند.</div>');
					btn.prop('disabled', false);
					$logBox.scrollTop($logBox[0].scrollHeight);
					return;
				}

				var post_id = selected_ids[current];
				var percent = Math.round(((current + 1) / total) * 100);
				
				// Update UI for this post
				$progressBar.css('width', percent + '%');
				$logBox.append('<div><span class="spinner-rs" style="width:12px; height:12px; margin:0 5px 0 0; border-width:2px; vertical-align: middle; display: inline-block;"></span>[#' + post_id + '] در حال ارسال درخواست به AI... (' + (current+1) + '/' + total + ')</div>');
				$logBox.scrollTop($logBox[0].scrollHeight);

				// Send AJAX request for the current post
				$.post(ajaxurl, {
					action: 'rs_bulk_generate',
					post_id: post_id,
					options: gen_options,
					nonce: nonce
				})
				.done(function (res) {
					// --- Request successful ---
					var symbol = res.success ? '✓' : '✗';
					var color = res.success ? '#81c784' : '#e57373'; // Green or Red
					var message = res.data; // Get message from PHP
					
					$logBox.append('<div style="color:' + color + '; padding-right:10px;">' + symbol + ' [#' + post_id + '] ' + message + '</div>');
					
					if (res.success) {
						$('#status-' + post_id).html('<span class="status-badge status-done">انجام شد</span>');
					} else {
						$('#status-' + post_id).html('<span class="status-badge status-error" style="background-color: #f2dede; color: #a94442;">خطا</span>');
					}
				})
				.fail(function (xhr) {
					// --- Request failed (Server error) ---
					var errorMsg = xhr.status === 500 ? 'خطای 500 سرور (PHP Fatal Error)' : 'خطای شبکه';
					$logBox.append('<div style="color:#e57373; padding-right:10px;">✗ [#' + post_id + '] خطای فاجعه‌بار: ' + errorMsg + '. (پردازش متوقف شد)</div>');
					// Stop the loop on a fatal error
					btn.prop('disabled', false);
				})
				.always(function () {
					// --- Move to the next post ---
					current++;
					$logBox.scrollTop($logBox[0].scrollHeight);
					
					// Only continue if the previous request didn't fail fatally
					if (xhr.status === 500) {
						// Don't continue
					} else {
						processNextPost();
					}
				});
			}
			
			// Start the processing loop
			processNextPost();
		});
	}
});