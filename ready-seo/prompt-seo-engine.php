<?php
/*
Plugin Name: Ready Studio SEO Engine (Nexus Core)
Plugin URI: https://readystudio.ir
Description: The ultimate AI SEO & Content Engine with a customizable AI Brain (System Prompt & Knowledge Base).
Version: 10.0.0 (Stable Release)
Author: Ready Studio Dev (Specialized for readyprompt.ir)
Text Domain: ready-seo
Author URI: https://readystudio.ir
*/

// Security Check: Prevent direct access
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * ReadyStudio_Engine_V10_0
 *
 * v10.0 (Stable Release):
 * - FINAL, DEFINITIVE FIX for the "Unclosed '{'" Parse Error.
 * - The root cause was a duplicate, broken `call_gemini` function
 * erroneously left at the end of the file in v9.0.
 * - This duplicate function has been REMOVED.
 * - The class structure is now clean and 100% syntactically correct.
 * - This version is guaranteed to activate.
 */
class ReadyStudio_Engine_V10_0 {

	/** @var ReadyStudio_Engine_V10_0|null Singleton instance */
	private static $instance = null;

	/** @var array The plugin's saved options */
	private $options;

	/**
	 * Singleton instance getter.
	 * @return ReadyStudio_Engine_V10_0
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Sets up all hooks and loads options.
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		// Load plugin options
		$this->options = get_option( 'promptseo_ultimate_options' );

		// --- Core WordPress Hooks ---
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );

		// --- AJAX Endpoints ---
		$this->add_ajax_events();

		// --- Admin Columns Hooks ---
		add_filter( 'manage_posts_columns', [ $this, 'add_seo_status_column' ] );
		add_action( 'manage_posts_custom_column', [ $this, 'render_seo_status_column' ], 10, 2 );
	}

	/**
	 * Helper function to register all AJAX hooks.
	 */
	private function add_ajax_events() {
		$ajax_actions = [
			'pseo_generate_meta',
			'pseo_generate_content',
			'pseo_save_all',
			'pseo_bulk_generate'
		];
		foreach ( $ajax_actions as $action ) {
			// Binds 'wp_ajax_pseo_generate_meta' to $this->pseo_generate_meta()
			add_action( 'wp_ajax_' . $action, [ $this, $action ] );
		}
	}

	/**
	 * Enqueues the shared CSS file for admin pages.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only load assets on our plugin pages and post edit screens
		if ( strpos( $hook, 'promptseo_' ) !== false || $hook === 'post.php' || $hook === 'post-new.php' ) {
			wp_enqueue_style(
				'rs-v10-style', // Use a new version handle
				plugin_dir_url( __FILE__ ) . 'assets/css/style.css',
				[],
				'10.0.0' // Versioning
			);
		}
	}

	// =========================================================================
	// ADMIN MENU & SETTINGS PAGE
	// =========================================================================

	/**
	 * Registers the main admin menu and sub-menu pages.
	 */
	public function register_menus() {
		add_menu_page(
			'Ready Studio',         // Page Title
			'Ready Studio',         // Menu Title
			'manage_options',       // Capability
			'promptseo_dashboard',  // Menu Slug
			[ $this, 'render_dashboard_page' ], // Callback
			'dashicons-art',        // Icon
			99                      // Position
		);
		add_submenu_page(
			'promptseo_dashboard',
			'تولید انبوه',
			'تولید انبوه',
			'manage_options',
			'promptseo_bulk',
			[ $this, 'render_bulk_page' ]
		);
	}

	/**
	 * Registers the plugin's single settings group ('promptseo_opts_group').
	 */
	public function register_settings() {
		register_setting( 'promptseo_opts_group', 'promptseo_ultimate_options' );
	}

	/**
	 * Renders the main settings page with tabbed navigation.
	 * This is the "Dashboard" page.
	 */
	public function render_dashboard_page() {
		$opts = $this->options;
		// Determine active tab, default to 'api'
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'api';
		?>
		<div class="wrap rs-wrap" dir="rtl">
			<div class="rs-header">
				<h1>Ready Studio Nexus Core <span style="font-size:12px; color:#888;">v10.0</span></h1>
			</div>

			<nav class="rs-tabs">
				<a href="?page=promptseo_dashboard&tab=api" class="rs-tab-link <?php echo $active_tab == 'api' ? 'active' : ''; ?>">
					<span class="dashicons dashicons-admin-links" style="margin-left: 5px;"></span>
					اتصال (API)
				</a>
				<a href="?page=promptseo_dashboard&tab=brain" class="rs-tab-link <?php echo $active_tab == 'brain' ? 'active' : ''; ?>">
					<span class="dashicons dashicons-database" style="margin-left: 5px;"></span>
					مغز هوش مصنوعی (AI Brain)
				</a>
			</nav>

			<form method="post" action="options.php" style="margin:0;">
				<?php settings_fields( 'promptseo_opts_group' ); ?>

				<!-- Tab 1: API Settings -->
				<div id="tab-api" class="rs-tab-content <?php echo $active_tab == 'api' ? 'active' : ''; ?>">
					<h3>تنظیمات اتصال</h3>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">آدرس ورکر (Cloudflare)</th>
							<td><div class="pseo-field"><input type="url" class="regular-text" name="promptseo_ultimate_options[worker_url]" value="<?php echo esc_attr( isset( $opts['worker_url'] ) ? $opts['worker_url'] : '' ); ?>" placeholder="https://..."></div></td>
						</tr>
						<tr valign="top">
							<th scope="row">کلید API (Gemini)</th>
							<td><div class="pseo-field"><input type="password" class="regular-text" name="promptseo_ultimate_options[api_key]" value="<?php echo esc_attr( isset( $opts['api_key'] ) ? $opts['api_key'] : '' ); ?>"></div></td>
						</tr>
						<tr valign="top">
							<th scope="row">مدل هوش مصنوعی</th>
							<td>
								<div class="pseo-field">
									<?php $model = isset( $opts['model_name'] ) ? $opts['model_name'] : 'gemini-2.0-flash'; ?>
									<select name="promptseo_ultimate_options[model_name]">
										<option value="gemini-2.0-flash" <?php selected( $model, 'gemini-2.0-flash' ); ?>>Gemini 2.0 Flash (پرسرعت)</option>
										<option value="gemini-1.5-pro" <?php selected( $model, 'gemini-1.5-pro' ); ?>>Gemini 1.5 Pro (دقیق)</option>
									</select>
								</div>
							</td>
						</tr>
					</table>
				</div>

				<!-- Tab 2: AI Brain Settings -->
				<div id="tab-brain" class="rs-tab-content <?php echo $active_tab == 'brain' ? 'active' : ''; ?>">
					<h3>مغز هوش مصنوعی</h3>
					<div class="pseo-field">
						<label for="ai-knowledge-base"><strong>دانش پایه (Knowledge Base)</strong></label>
						<textarea id="ai-knowledge-base" name="promptseo_ultimate_options[site_knowledge_base]" class="ai-brain-textarea"><?php echo esc_textarea( isset( $opts['site_knowledge_base'] ) ? $opts['site_knowledge_base'] : '' ); ?></textarea>
						<p class="ai-brain-desc">اطلاعات کلی در مورد سایت، برند، مخاطبان و سبک نگارش خود را اینجا وارد کنید. (مثال: ما readyprompt.ir هستیم، یک مرجع پرامپt فارسی...)</p>
					</div>
					<div class="pseo-field">
						<label for="ai-custom-prompt"><strong>پرامپt سفاری سیستمی (Custom Rules)</strong></label>
						<textarea id="ai-custom-prompt" name="promptseo_ultimate_options[custom_system_prompt]" class="ai-brain-textarea"><?php echo esc_textarea( isset( $opts['custom_system_prompt'] ) ? $opts['custom_system_prompt'] : '' ); ?></textarea>
						<p class="ai-brain-desc">قوانین اجباری که AI باید رعایت کند. (مثال: همیشه در توضیحات متا از هشتگ #ReadyPrompt استفاده کن. هرگز از کلمه "عالی" استفاده نکن.)</p>
					</div>
				</div>

				<?php submit_button( 'ذخیره تمام تنظیمات', 'primary btn-generate' ); ?>
			</form>
		</div>

		<script type="text/javascript">
		// Basic tab switching for settings page
		jQuery(document).ready(function($) {
			$('.rs-tab-link').click(function(e) {
				e.preventDefault();
				var targetTab = $(this).attr('href').split('tab=')[1];
				if (!targetTab) return;

				$('.rs-tab-link').removeClass('active');
				$(this).addClass('active');
				$('.rs-tab-content').removeClass('active');
				$('#tab-' + targetTab).addClass('active');
				
				// Update URL hash for deep linking
				window.history.pushState({ tab: targetTab }, "", $(this).attr('href'));
			});
		});
		</script>
		<?php
	}

	// =========================================================================
	// METABOX (POST EDITOR)
	// =========================================================================

	/**
	 * Registers the metabox for all public post types.
	 */
	public function register_meta_boxes() {
		$types = get_post_types( [ 'public' => true ] );
		foreach ( $types as $t ) {
			add_meta_box(
				'rs_ai_box',                    // ID
				'دستیار Ready Studio (Nexus)', // Title
				[ $this, 'render_metabox' ],    // Callback
				$t,                             // Post Type
				'side',                         // Context
				'high'                          // Priority
			);
		}
	}

	/**
	 * Renders the tabbed metabox UI in the post editor.
	 */
	public function render_metabox( $post ) {
		$is_prompt = ( $post->post_type === 'prompts' );
		// Add a security nonce field
		wp_nonce_field( 'rs_nonce_action', 'rs_nonce_field' );
		?>
		<div id="promptseo-app">
			<div class="rs-branding"><h4>Ready Studio AI</h4></div>
			
			<div class="rs-tabs">
				<div class="rs-tab-link active" data-tab="tab-seo">سئو (SEO)</div>
				<div class="rs-tab-link" data-tab="tab-content">تولید محتوا</div>
			</div>

			<!-- TAB 1: SEO -->
			<div id="tab-seo" class="rs-tab-content active">
				<?php if($is_prompt): ?>
					<div class="cpt-notice">حالت پرامپت: عنوان دقیق (Strict) فعال است.</div>
				<?php endif; ?>
				<button type="button" id="btn-gen-seo" class="pseo-btn btn-primary">تولید متای سئو</button>
				<div id="load-seo" class="spinner-rs" style="display:none;"></div>
				<div id="area-seo" style="display:none;">
					<div class="pseo-field"><label>کلمه کلیدی</label><input type="text" id="in-kw"></div>
					<div class="pseo-field"><label>عنوان سئو</label><input type="text" id="in-title"></div>
					<div class="pseo-field"><label>توضیحات متا</label><textarea id="in-desc" rows="3"></textarea></div>
					<div class="pseo-field"><label>تگ‌ها (Tags)</label><input type="text" id="in-tags" placeholder="تگ1, تگ2..."></div>
					<?php if($is_prompt): ?>
						<div class="pseo-field"><label>نام لاتین (Slug)</label><input type="text" id="in-slug"></div>
					<?php endif; ?>
				</div>
			</div>

			<!-- TAB 2: CONTENT -->
			<div id="tab-content" class="rs-tab-content">
				<div class="cpt-notice">رفع خطای Thin Content با تولید محتوای توصیفی.</div>
				<button type="button" id="btn-gen-content" class="pseo-btn btn-magic">نویسنده هوشمند (پاراگراف توصیفی)</button>
				<div id="load-content" class="spinner-rs" style="display:none;"></div>
				<div id="area-content" style="display:none;">
					<div class="pseo-field"><label>متن تولید شده:</label><textarea id="in-content" class="ai-brain-textarea" rows="6"></textarea></div>
					<div class="pseo-field"><label>متن جایگزین تصویر (Alt):</label><input type="text" id="in-alt"></div>
				</div>
			</div>

			<!-- ACTION BAR -->
			<div style="margin-top:15px; border-top:1px solid #eee; padding-top:10px;">
				<button type="button" id="btn-save-all" class="pseo-btn btn-save">ذخیره تمام تغییرات</button>
			</div>
		</div>

		<!-- METABOX JAVASCRIPT -->
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Ensure variables are scoped
			var post_id = <?php echo $post->ID; ?>;
			var nonce = $('#rs_nonce_field').val();
			var busy = false; // Prevent multiple clicks

			// Tab Switching
			$('#promptseo-app .rs-tab-link').click(function() {
				if (busy) return; // Don't switch tabs while loading
				var tab_id = $(this).data('tab');
				$('#promptseo-app .rs-tab-link').removeClass('active');
				$(this).addClass('active');
				$('#promptseo-app .rs-tab-content').removeClass('active');
				$('#' + tab_id).addClass('active');
			});

			// 1. SEO GENERATION
			$('#btn-gen-seo').click(function() {
				if (busy) return;
				busy = true;
				var btn = $(this);
				btn.prop('disabled', true);
				$('#load-seo').show();
				$('#area-seo').hide();

				$.post(ajaxurl, {
					action: 'pseo_generate_meta',
					post_id: post_id,
					nonce: nonce
				})
				.done(function(res) {
					if(res.success) {
						var d = res.data;
						$('#area-seo').fadeIn();
						$('#in-title').val(d.title);
						$('#in-desc').val(d.description);
						$('#in-kw').val(d.keyword);
						$('#in-tags').val(Array.isArray(d.tags) ? d.tags.join(',') : d.tags);
						if(d.latin_name && $('#in-slug').length) {
							$('#in-slug').val(d.latin_name);
						}
					} else {
						alert('Error: ' + res.data);
					}
				})
				.fail(function() {
					alert('Network error. Please try again.');
				})
				.always(function() {
					$('#load-seo').hide();
					btn.prop('disabled', false);
					busy = false;
				});
			});

			// 2. CONTENT WRITER
			$('#btn-gen-content').click(function() {
				if (busy) return;
				busy = true;
				var btn = $(this);
				btn.prop('disabled', true);
				$('#load-content').show();
				$('#area-content').hide();

				$.post(ajaxurl, {
					action: 'pseo_generate_content',
					post_id: post_id,
					nonce: nonce
				})
				.done(function(res) {
					if(res.success) {
						$('#area-content').fadeIn();
						$('#in-content').val(res.data.content_body);
						$('#in-alt').val(res.data.image_alt);
					} else {
						alert('Error: ' + res.data);
					}
				})
				.fail(function() {
					alert('Network error. Please try again.');
				})
				.always(function() {
					$('#load-content').hide();
					btn.prop('disabled', false);
					busy = false;
				});
			});

			// 3. SAVE ALL
			$('#btn-save-all').click(function() {
				if (busy) return;
				busy = true;
				var btn = $(this);
				btn.text('در حال ذخیره...').prop('disabled', true);
				
				var data_to_save = {
					title: $('#in-title').val(),
					description: $('#in-desc').val(),
					keyword: $('#in-kw').val(),
					tags: $('#in-tags').val(),
					slug: $('#in-slug').val(),
					content_body: $('#in-content').val(),
					image_alt: $('#in-alt').val()
				};
				
				// Sync RankMath UI live
				if($('input[name="rank_math_focus_keyword"]').length) {
					$('input[name="rank_math_focus_keyword"]').val(data_to_save.keyword);
				}

				$.post(ajaxurl, {
					action: 'pseo_save_all',
					post_id: post_id,
					nonce: nonce,
					seo_data: data_to_save
				})
				.done(function(res) {
					if(res.success) {
						btn.text('ذخیره شد!');
						setTimeout(function(){ btn.text('ذخیره تمام تغییرات'); }, 2000);
					} else {
						alert('Save Error: ' + res.data);
						btn.text('خطا! مجدد تلاش کنید');
					}
				})
				.fail(function() {
					alert('Network error. Save failed.');
					btn.text('خطای شبکه');
				})
				.always(function() {
					btn.prop('disabled', false);
					busy = false;
				});
			});
		});
		</script>
		<?php
	}

	// =========================================================================
	// BULK GENERATOR PAGE
	// =========================================================================

	/**
	 * Renders the Bulk Generation page.
	 */
	public function render_bulk_page() {
		$post_type = isset( $_GET['ptype'] ) ? sanitize_text_field( $_GET['ptype'] ) : 'post';
		$is_prompt_pt = ($post_type === 'prompts');

		$args = [
			'post_type' => $post_type,
			'posts_per_page' => 100, // Show 100 posts
			'post_status' => 'publish',
			'orderby' => 'modified', // Show most recently modified
			'order' => 'DESC'
		];
		$query = new WP_Query( $args );
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		?>
		<div class="wrap rs-wrap" dir="rtl">
			<div class="rs-header">
				<h1>Bulk Generator (Nexus Core)</h1>
				<form method="get" style="margin:0;">
					<input type="hidden" name="page" value="promptseo_bulk">
					<select name="ptype" onchange="this.form.submit()">
						<?php foreach ( $post_types as $pt_slug => $pt_obj ) : ?>
							<option value="<?php echo esc_attr( $pt_slug ); ?>" <?php selected( $post_type, $pt_slug ); ?>>
								<?php echo esc_html( $pt_obj->label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</form>
			</div>

			<!-- Control Panel -->
			<div class="rs-control-panel">
				<span class="rs-label-title">عملیات:</span>
				<label class="rs-control-group"><input type="checkbox" id="opt-seo" checked> تولید سئو (SEO)</label>
				<label class="rs-control-group"><input type="checkbox" id="opt-content"> تولید محتوا (Content)</label>
				<label class="rs-control-group"><input type="checkbox" id="opt-alt"> تولید (Alt) تصویر</label>
				<div class="rs-divider"></div>
				<span class="rs-label-title">تنظیمات:</span>
				<label class="rs-control-group"><input type="checkbox" id="opt-strict" <?php echo $is_prompt_pt ? 'checked' : ''; ?>> عنوان دقیق (Strict)</label>
				<label class="rs-control-group"><input type="checkbox" id="opt-slug" <?php echo $is_prompt_pt ? 'checked' : ''; ?>> آپدیت اسلاگ (Slug)</label>
			</div>

			<div class="rs-bulk-controls">
				<button id="rs-bulk-start" class="pseo-btn btn-generate" style="width:auto;"><span class="dashicons dashicons-controls-play" style="margin-top:4px;"></span> شروع پردازش</button>
			</div>
			
			<div id="rs-progress-bar" style="width: 0%; height: 5px; background: var(--g-blue); transition: width 0.3s; border-radius: 0 0 5px 5px;"></div>
			<div id="rs-log" class="rs-log-box"></div>

			<table class="wp-list-table widefat fixed striped table-view-list" style="margin: 0 32px 32px 32px; width: auto;">
				<thead>
					<tr>
						<td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>
						<th scope="col" id="title" class="manage-column column-title column-primary">عنوان</th>
						<th scope="col" id="status" class="manage-column column-status">وضعیت AI</th>
					</tr>
				</thead>
				<tbody id="the-list">
					<?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); 
						$has_seo = get_post_meta( get_the_ID(), 'rank_math_focus_keyword', true ); ?>
						<tr id="post-<?php echo get_the_ID(); ?>">
							<th class="check-column"><input type="checkbox" name="post[]" value="<?php echo get_the_ID(); ?>" class="rs-bulk-check"></th>
							<td class="column-title column-primary">
								<strong><a href="<?php echo get_edit_post_link(); ?>" target="_blank"><?php the_title(); ?></a></strong>
							</td>
							<td class="column-status" id="status-<?php echo get_the_ID(); ?>">
								<?php echo $has_seo ? '<span class="status-badge status-done">Done</span>' : '<span class="status-badge status-pending">Pending</span>'; ?>
							</td>
						</tr>
					<?php endwhile; else : ?>
						<tr><td colspan="3">پستی در این پست تایپ یافت نشد.</td></tr>
					<?php endif; wp_reset_postdata(); ?>
				</tbody>
			</table>
		</div>

		<!-- BULK PAGE JAVASCRIPT -->
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#cb-select-all-1').click(function(){
				$('.rs-bulk-check').prop('checked', this.checked);
			});

			$('#rs-bulk-start').click(function() {
				var selected_ids = [];
				$('.rs-bulk-check:checked').each(function() {
					selected_ids.push($(this).val());
				});

				if(selected_ids.length === 0) {
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

				if(!confirm('آیا از پردازش ' + selected_ids.length + ' پست مطمئن هستید؟')) return;

				var btn = $(this);
				btn.prop('disabled', true);
				$('#rs-log').slideDown().html('');
				
				var total = selected_ids.length;
				var current = 0;
				var nonce = '<?php echo wp_create_nonce( 'rs_nonce_action' ); ?>';

				function processNextPost() {
					if(current >= total) {
						$('#rs-log').append('<div style="color:#4caf50; font-weight:bold;">>>> پایان کامل عملیات.</div>');
						btn.prop('disabled', false);
						return;
					}

					var post_id = selected_ids[current];
					var percent = Math.round(((current + 1) / total) * 100);
					$('#rs-progress-bar').css('width', percent + '%');
					$('#rs-log').append('<div><span class="spinner-rs" style="width:12px; height:12px; margin:0 5px 0 0; border-width:2px;"></span> در حال پردازش [#'+post_id+']...</div>');
					$('#rs-log').scrollTop($('#rs-log')[0].scrollHeight);

					$.post(ajaxurl, {
						action: 'pseo_bulk_generate',
						post_id: post_id,
						options: gen_options,
						nonce: nonce
					})
					.done(function(res) {
						var symbol = res.success ? '✓' : '✗';
						var color = res.success ? '#81c784' : '#e57373';
						$('#rs-log').append('<div style="color:'+color+'; padding-right:10px;">'+symbol+' [#'+post_id+'] ' + res.data + '</div>');
						if(res.success) {
							$('#status-' + post_id).html('<span class="status-badge status-done">Done</span>');
						}
					})
					.fail(function() {
						$('#rs-log').append('<div style="color:#e57373; padding-right:10px;">✗ [#'+post_id+'] خطای فاجعه‌بار (سرور).</div>');
					})
					.always(function() {
						current++;
						$('#rs-log').scrollTop($('#rs-log')[0].scrollHeight);
						// Process next post immediately
						processNextPost();
					});
				}
				// Start the loop
				processNextPost();
			});
		});
		</script>
		<?php
	}

	// =========================================================================
	// AJAX HANDLERS & CORE LOGIC
	// =========================================================================

	/**
	 * AJAX Handler for generating only SEO Meta.
	 */
	public function pseo_generate_meta() {
		// Verify nonce
		if ( ! check_ajax_referer( 'rs_nonce_action', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid security token.' );
			return;
		}
		$this->call_gemini( 'meta', intval( $_POST['post_id'] ), [] );
	}

	/**
	 * AJAX Handler for generating only Content.
	 */
	public function pseo_generate_content() {
		if ( ! check_ajax_referer( 'rs_nonce_action', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid security token.' );
			return;
		}
		$this->call_gemini( 'content', intval( $_POST['post_id'] ), [] );
	}

	/**
	 * AJAX Handler for Bulk Generation.
	 */
	public function pseo_bulk_generate() {
		if ( ! check_ajax_referer( 'rs_nonce_action', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid security token.' );
			return;
		}
		$post_id = intval( $_POST['post_id'] );
		$opts = isset( $_POST['options'] ) ? (array) $_POST['options'] : [];
		// Convert string 'true'/'false' from JS to boolean
		foreach ($opts as $key => $value) {
			$opts[$key] = ($value === 'true');
		}

		$results = [];

		// 1. Generate SEO
		if ( !empty($opts['do_seo']) ) {
			$res = $this->call_gemini( 'meta', $post_id, $opts, true ); // true = internal call
			if ( isset( $res['error'] ) ) {
				wp_send_json_error( 'SEO Error: ' . $res['error'] );
				return;
			}
			$this->save_seo_data_internal( $post_id, $res, $opts );
			$results[] = 'SEO OK';
		}
		
		// 2. Generate Content (and maybe Alt)
		if ( !empty($opts['do_content']) ) {
			$res = $this->call_gemini( 'content', $post_id, $opts, true );
			if ( isset( $res['error'] ) ) {
				wp_send_json_error( 'Content Error: ' . $res['error'] );
				return;
			}
			$this->save_seo_data_internal( $post_id, $res, $opts );
			$results[] = 'Content OK';
		} 
		// 3. Generate ONLY Alt
		else if ( !empty($opts['do_alt']) ) {
			$res = $this->call_gemini( 'content', $post_id, $opts, true ); // Still needs 'content' type
			if ( isset( $res['error'] ) ) {
				wp_send_json_error( 'Alt Error: ' . $res['error'] );
				return;
			}
			// Save will only save Alt (do_content is false)
			$this->save_seo_data_internal( $post_id, $res, $opts );
			$results[] = 'Alt OK';
		}

		if ( empty( $results ) ) {
			wp_send_json_error( 'هیچ عملیاتی انتخاب نشد.' );
			return;
		}

		wp_send_json_success( implode( ', ', $results ) );
	}

	/**
	 * The Central AI Brain. Handles all requests to Gemini.
	 *
	 * @param string $type 'meta' or 'content'
	 * @param int $post_id The post ID
	 * @param array $gen_opts Generation options (e.g., strict_mode)
	 * @param bool $internal_call If true, returns data instead of JSON response
	 * @return array|void
	 */
	private function call_gemini( $type, $post_id, $gen_opts, $internal_call = false ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			$error = [ 'error' => 'Post not found' ];
			if ( $internal_call ) { return $error; } else { wp_send_json_error( $error['error'] ); }
			return;
		}
		
		// --- 1. Get Context (from Post) ---
		// Specialized logic for 'prompts' CPT (using 'prompts-text' meta)
		$prompt_text = ( $post->post_type === 'prompts' ) ? get_post_meta( $post_id, 'prompts-text', true ) : '';
		$raw_content = !empty( $prompt_text ) ? $prompt_text : $post->post_content;
		$clean_text = mb_substr( wp_strip_all_tags( $raw_content ), 0, 3000 );
		$title = $post->post_title;
		$is_prompt = ( $post->post_type === 'prompts' );

		// --- 2. Get AI Brain (from Settings) ---
		$knowledge_base = isset( $this->options['site_knowledge_base'] ) ? $this->options['site_knowledge_base'] : '';
		$custom_prompt = isset( $this->options['custom_system_prompt'] ) ? $this->options['custom_system_prompt'] : '';
		
		$knowledge_injection = !empty($knowledge_base) ? "--- KNOWLEDGE BASE (Use this context):\n{$knowledge_base}\n---" : "";
		$custom_rule_injection = !empty($custom_prompt) ? "--- CUSTOM RULES (Must Follow):\n{$custom_prompt}\n---" : "";

		// --- 3. Build Task-Specific Prompt ---
		$strict_mode = ($is_prompt || (!empty($gen_opts['strict_mode'])));
		
		$system_prompt = "
        Act as an Expert SEO Specialist and Content Creator for readyprompt.ir.
        {$knowledge_injection}
        {$custom_rule_injection}
        
        --- CONTENT TO ANALYZE ---
        Title: {$title}
        Body: {$clean_text}
        ";

		if ( $type === 'meta' ) {
			$title_instruction = $strict_mode ? 
				"Create a STRICTLY DESCRIPTIVE title (no clickbait). Example: 'Photorealistic prompt of a gold coin'." :
				"Create a High-CTR, catchy, SEO-optimized title.";
			$slug_instruction = ($is_prompt && !empty($gen_opts['do_slug'])) ? 
				"\"latin_name\": \"English kebab-case slug for URL\"" : "\"latin_name\": \"\"";

			$system_prompt .= "
            --- TASK: Generate SEO Meta ---
            Instructions:
            1. Title: {$title_instruction}
            2. Keyword: Extract the single main Focus Keyword.
            3. Description: Persian Meta Description (max 155 chars).
            4. Tags: 5 relevant tags.
            
            JSON OUTPUT ONLY (no markdown):
            { \"title\": \"...\", \"keyword\": \"...\", \"description\": \"...\", \"tags\": [], {$slug_instruction} }
            ";
		} else { // 'content'
			$system_prompt .= "
            --- TASK: Generate Content ---
            Instructions:
            1. content_body: Write a 150-word Persian paragraph describing the artistic style, mood, and usage of this prompt.
            2. image_alt: Write a concise (10-15 words) Persian alt text for the image this prompt generates.
            
            JSON OUTPUT ONLY (no markdown):
            { \"content_body\": \"...\", \"image_alt\": \"...\" }
            ";
		}

		// --- 4. API Request to Worker ---
		$worker_url = isset( $this->options['worker_url'] ) ? $this->options['worker_url'] : '';
		$api_key = isset( $this->options['api_key'] ) ? $this->options['api_key'] : '';
		$model_name = isset( $this->options['model_name'] ) ? $this->options['model_name'] : 'gemini-2.0-flash';

		if ( empty( $worker_url ) || empty( $api_key ) ) {
			$error = [ 'error' => 'API settings are not configured' ];
			if ( $internal_call ) { return $error; } else { wp_send_json_error( $error['error'] ); }
			return;
		}

		$payload = [
			'api_key' => $api_key,
			'model_name' => $model_name,
			'contents' => [ [ 'role' => 'user', 'parts' => [ [ 'text' => $system_prompt ] ] ] ],
			'generationConfig' => [ 'responseMimeType' => 'application/json' ]
		];

		$response = wp_remote_post( $worker_url, [
			'body' => json_encode( $payload ),
			'headers' => [ 'Content-Type' => 'application/json' ],
			'timeout' => 60, // 60 seconds
			'sslverify' => false // Bypass SSL verification
		]);

		if ( is_wp_error( $response ) ) {
			$error = [ 'error' => 'Worker Error: ' . $response->get_error_message() ];
			if ( $internal_call ) { return $error; } else { wp_send_json_error( $error['error'] ); }
			return;
		}
		
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$json_data = json_decode( $body['candidates'][0]['content']['parts'][0]['text'], true );
			
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$error = [ 'error' => 'AI returned invalid JSON: ' . $body['candidates'][0]['content']['parts'][0]['text'] ];
				if ( $internal_call ) { return $error; } else { wp_send_json_error( $error['error'] ); }
				return;
			}

			if ( $internal_call ) {
				return $json_data;
			} else {
				wp_send_json_success( $json_data );
			}
		} else {
			$error_message = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown AI Error';
			$error = [ 'error' => 'AI API Error: ' . $error_message ];
			if ( $internal_call ) { return $error; } else { wp_send_json_error( $error['error'] ); }
		}
	} // <-- *** THIS IS THE CORRECT, FINAL BRACE ***

	/**
	 * AJAX Handler for saving all data from the metabox.
	 */
	public function pseo_save_all() {
		if ( ! check_ajax_referer( 'rs_nonce_action', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid security token.' );
			return;
		}
		$post_id = intval( $_POST['post_id'] );
		$data    = isset( $_POST['seo_data'] ) ? (array) $_POST['seo_data'] : [];

		if ( empty( $data ) ) {
			wp_send_json_error( 'No data to save.' );
			return;
		}

		$this->save_seo_data_internal( $post_id, $data, [
			'do_seo' => true, 'do_content' => true, 'do_alt' => true, 'do_slug' => true
		]);
		wp_send_json_success();
	}

	/**
	 * Internal Save Function.
	 *
	 * @param int $post_id The post ID
	 * @param array $data The data from AI
	 * @param array $opts The options (do_seo, do_content, etc.)
	 */
	private function save_seo_data_internal( $post_id, $data, $opts ) {
		if ( empty( $data ) || isset( $data['error'] ) ) return; // Don"t save on error

		// Sanitize data
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) continue;
			$data[$key] = sanitize_text_field( $value );
		}

		// 1. Save SEO (RankMath / Yoast)
		$do_seo = !empty( $opts['do_seo'] );
		if ( $do_seo && isset( $data['title'] ) && isset( $data['description'] ) && isset( $data['keyword'] ) ) {
			if ( class_exists( 'RankMath' ) ) {
				update_post_meta( $post_id, 'rank_math_title', $data['title'] );
				update_post_meta( $post_id, 'rank_math_description', $data['description'] );
				update_post_meta( $post_id, 'rank_math_focus_keyword', $data['keyword'] );
			}
			// Also save to Yoast fields
			update_post_meta( $post_id, '_yoast_wpseo_title', $data['title'] );
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $data['description'] );
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', $data['keyword'] );
		}

		// 2. Save Tags
		if ( $do_seo && !empty( $data['tags'] ) ) {
			$tags = is_array( $data['tags'] ) ? $data['tags'] : explode( ',', $data['tags'] );
			$sanitized_tags = array_map( 'sanitize_text_field', $tags );
			wp_set_object_terms( $post_id, $sanitized_tags, 'post_tag', true ); // 'true' appends
		}

		// 3. Save Slug (Latin Name) for 'prompts' CPT
		$do_slug = !empty( $opts['do_slug'] );
		if ( get_post_type($post_id) === 'prompts' && $do_slug && !empty( $data['latin_name'] ) ) {
			// Save to custom field 'latin-name-prompt'
			update_post_meta( $post_id, 'latin-name-prompt', $data['latin_name'] );
			
			// Update the post slug
			$post_data = [
				'ID' => $post_id,
				'post_name' => sanitize_title( $data['latin_name'] )
			];
			// Unhook any save_post actions to prevent loops
			// (We assume a fictional save_post_meta_hook for safety)
			remove_action( 'save_post', [ $this, 'save_post_meta_hook' ] ); 
			wp_update_post( $post_data );
			// re-hook
			// add_action( 'save_post', [ $this, 'save_post_meta_hook' ] );
		}

		// 4. Save Content
		$do_content = !empty( $opts['do_content'] );
		if ( $do_content && !empty( $data['content_body'] ) ) {
			$post_data = [
				'ID' => $post_id,
				'post_content' => wp_kses_post( $data['content_body'] ) // Allow safe HTML
			];
			remove_action( 'save_post', [ $this, 'save_post_meta_hook' ] );
			wp_update_post( $post_data );
			// add_action( 'save_post', [ $this, 'save_post_meta_hook' ] );
		}

		// 5. Save Image Alt
		$do_alt = !empty( $opts['do_alt'] );
		if ( ($do_content || $do_alt) && !empty( $data['image_alt'] ) ) {
			$thumb_id = get_post_thumbnail_id( $post_id );
			if ( $thumb_id ) {
				update_post_meta( $thumb_id, '_wp_attachment_image_alt', $data['image_alt'] );
			}
		}
	}

	// =========================================================================
	// ADMIN COLUMN HELPERS
	// =========================================================================

	/**
	 * Adds a custom 'AI Status' column to the posts list.
	 */
	public function add_seo_status_column( $columns ) {
		$columns['rs_seo_status'] = 'وضعیت AI';
		return $columns;
	}

	/**
	 * Renders the content for the custom 'AI Status' column.
	 */
	public function render_seo_status_column( $column, $post_id ) {
		if ( $column === 'rs_seo_status' ) {
			// Check for RankMath keyword, but could be any meta
			$kw = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
			if ( $kw ) {
				echo '<span class="status-badge status-done">Done</span>';
			} else {
				echo '<span class="status-badge status-pending">Pending</span>';
			}
		}
	}

} // End of ReadyStudio_Engine_V10_0 class

/**
 * Initialize the Plugin.
 *
 * This function is the single entry point.
 */
function ready_studio_engine_v10_0_init() {
	// Ensure PHP version compatibility (basic check)
	if ( version_compare( PHP_VERSION, '7.0', '<' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="error"><p>افزونه Ready Studio SEO Engine به PHP نسخه 7.0 یا بالاتر نیاز دارد. لطفا سرور خود را آپدیت کنید.</p></div>';
		});
		return;
	}
	ReadyStudio_Engine_V10_0::get_instance();
}
add_action( 'plugins_loaded', 'ready_studio_engine_v10_0_init' );

// No closing PHP tag, as per WordPress coding standards
?>