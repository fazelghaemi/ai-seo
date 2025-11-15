<?php
/**
 * Ready Studio SEO Engine - Core Settings
 *
 * v12.6: CRITICAL FIX - Removed the 'Robots.txt' submenu registration.
 * This was causing a fatal error because the module class was not
 * yet loaded. The module itself (class-rs-module-robots.php)
 * is correctly responsible for registering its own menu page.
 *
 * @package   ReadyStudio
 * @version   12.6.0
 * @author    Fazel Ghaemi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ReadyStudio_Core_Settings {

	/**
	 * Plugin options.
	 * @var array
	 */
	private $options;

	/**
	 * Core API instance (injected).
	 * @var ReadyStudio_Core_API
	 */
	private $api;

	/**
	 * Constructor.
	 * Hooks into WordPress admin menu and init actions.
	 *
	 * @param array $options The plugin's options (injected by Loader).
	 * @param ReadyStudio_Core_API $api The Core API instance (injected by Loader).
	 */
	public function __construct( $options, $api ) {
		$this->options = $options;
		$this->api = $api; // Store the injected API instance

		// Hook to create admin menus
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		
		// Hook to register settings fields
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		
		// Hook to add custom CSS for the admin menu icon
		add_action( 'admin_head', [ $this, 'admin_menu_styles' ] );

		// Hook for the test connection button
		add_action( 'wp_ajax_rs_test_connection', [ $this, 'ajax_handle_test_connection' ] );
	}

	/**
	 * Registers the main admin menu and sub-menu pages.
	 *
	 * Fired by 'admin_menu' hook.
	 */
	public function register_menus() {
		// Main Menu Page (Dashboard / Settings)
		add_menu_page(
			'AI SEO',               // Page Title (Browser tab title)
			'Ready Studio',         // Menu Title (What user sees in menu)
			'manage_options',       // Capability
			'promptseo_dashboard',  // Menu Slug (Main page)
			[ $this, 'render_dashboard_page' ], // Callback function
			'none',                 // Icon (We set this to 'none' and add SVG via CSS)
			99                      // Position
		);

		// 1. "Settings" submenu
		add_submenu_page(
			'promptseo_dashboard',
			'تنظیمات',              // Page Title
			'تنظیمات',              // Menu Title
			'manage_options',       // Capability
			'promptseo_dashboard',  // Menu Slug (matches parent)
			[ $this, 'render_dashboard_page' ] // Same callback
		);

		// 2. "Bulk Generator" submenu
		add_submenu_page(
			'promptseo_dashboard',
			'تولید انبوه',          // Page Title
			'تولید انبوه',          // Menu Title
			'manage_options',       // Capability
			'promptseo_bulk',       // Menu Slug (unique)
			[ 'ReadyStudio_Core_Bulk', 'render_page' ] // Static callback
		);
		
		// 3. *** REMOVED in v12.6 ***
		// The 'Robots.txt' module now handles its own menu registration.
	}

	/**
	 * Registers the plugin's settings group and fields.
	 *
	 * Fired by 'admin_init' hook.
	 */
	public function register_settings() {
		// Register one single options group for the entire plugin
		register_setting(
			'promptseo_opts_group',           // Option group name
			'promptseo_ultimate_options',     // Option name in wp_options
			[ $this, 'sanitize_options' ]     // Sanitization callback
		);

		// --- Section 1: API Settings ---
		add_settings_section(
			'rs_api_section',                 // ID
			'تنظیمات اتصال (API)',           // Title
			null,                             // Callback (no description)
			'promptseo_dashboard'             // Page slug
		);

		add_settings_field(
			'worker_url',
			'آدرس ورکر (Cloudflare)',
			[ $this, 'render_field_text' ],
			'promptseo_dashboard',
			'rs_api_section',
			[ 'id' => 'worker_url', 'type' => 'url', 'placeholder' => 'https://...' ]
		);

		add_settings_field(
			'api_key',
			'کلید API (Gemini)',
			[ $this, 'render_field_text' ],
			'promptseo_dashboard',
			'rs_api_section',
			[ 'id' => 'api_key', 'type' => 'password' ]
		);
		
		add_settings_field(
			'model_name',
			'مدل هوش مصنوعی (متن)',
			[ $this, 'render_field_select' ],
			'promptseo_dashboard',
			'rs_api_section',
			[
				'id' => 'model_name',
				'options' => [
					'gemini-2.0-flash' => 'Gemini 2.0 Flash (پرسرعت)',
					'gemini-1.5-pro'   => 'Gemini 1.5 Pro (دقیق)',
				],
				'default' => 'gemini-2.0-flash',
			]
		);

		// --- Section 2: AI Brain Settings ---
		add_settings_section(
			'rs_brain_section',
			'مغز هوش مصنوعی (AI Brain)',
			null,
			'promptseo_dashboard_brain' // A different 'page' slug for this tab
		);

		add_settings_field(
			'site_knowledge_base',
			'دانش پایه (Knowledge Base)',
			[ $this, 'render_field_textarea' ],
			'promptseo_dashboard_brain',
			'rs_brain_section',
			[ 
				'id' => 'site_knowledge_base',
				'desc' => 'اطلاعات کلی در مورد سایت، برند، مخاطبان و سبک نگارش خود را اینجا وارد کنید. (مثال: ما readyprompt.ir هستیم، یک مرجع پرامپت فارسی...)'
			]
		);

		add_settings_field(
			'custom_system_prompt',
			'پرامپت سفارشی (Custom Rules)',
			[ $this, 'render_field_textarea' ],
			'promptseo_dashboard_brain',
			'rs_brain_section',
			[ 
				'id' => 'custom_system_prompt',
				'desc' => 'قوانین اجباری که AI باید رعایت کند. (مثال: همیشه در توضیحات متا از هشتگ #ReadyPrompt استفاده کن.)'
			]
		);
		
		// *** REMOVED in v12.6 ***
		// Robots.txt settings are now registered by the module itself.
	}

	/**
	 * Renders the main settings page HTML wrapper with tabs.
	 */
	public function render_dashboard_page() {
		// Get active tab from URL, default to 'api'
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'api';
		?>
		<div class="wrap rs-wrap settings-page" dir="rtl">
			
			<div class="rs-header">
				<h1>
					AI SEO
					<span class="rs-brand-family">از خانواده <strong>Ready Studio</strong></span>
				</h1>
			</div>

			<!-- Tab Navigation -->
			<nav class="rs-tabs">
				<a href="#" class="rs-tab-link <?php echo $active_tab == 'api' ? 'active' : ''; ?>" data-tab="tab-api">
					<span class="dashicons dashicons-admin-links" style="margin-left: 5px;"></span>
					اتصال (API)
				</a>
				<a href="#" class="rs-tab-link <?php echo $active_tab == 'brain' ? 'active' : ''; ?>" data-tab="tab-brain">
					<span class="dashicons dashicons-database" style="margin-left: 5px;"></span>
					مغز هوش مصنوعی (AI Brain)
				</a>
			</nav>

			<form method="post" action="options.php" style="margin:0;">
				<?php settings_fields( 'promptseo_opts_group' ); ?>
				
				<?php wp_nonce_field( 'rs_nonce_action', 'rs_test_nonce' ); ?>

				<!-- Tab 1: API Settings Content -->
				<div id="tab-api" class="rs-tab-content <?php echo $active_tab == 'api' ? 'active' : ''; ?>">
					<table class="form-table">
						<?php do_settings_fields( 'promptseo_dashboard', 'rs_api_section' ); ?>
						
						<tr valign="top">
							<th scope="row">تست اتصال</th>
							<td>
								<button type="button" id="rs-test-connection-btn" class="pseo-btn btn-primary" style="width: auto; padding: 10px 24px;">
									<span class="dashicons dashicons-transfer" style="margin-top:4px; margin-left: 5px;"></span>
									تست ارتباط با ورکر
								</button>
								<div id="rs-test-connection-result" style="margin-top: 10px; font-weight: 600; font-size: 13px;"></div>
							</td>
						</tr>
					</table>
				</div>

				<!-- Tab 2: AI Brain Settings Content -->
				<div id="tab-brain" class="rs-tab-content <?php echo $active_tab == 'brain' ? 'active' : ''; ?>">
					<table class="form-table">
						<?php do_settings_fields( 'promptseo_dashboard_brain', 'rs_brain_section' ); ?>
					</table>
				</div>

				<?php submit_button( 'ذخیره تمام تنظیمات', 'primary pseo-btn btn-generate' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * AJAX handler for the Test Connection button.
	 */
	public function ajax_handle_test_connection() {
		if ( ! isset( $_POST['rs_test_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['rs_test_nonce'] ), 'rs_nonce_action' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid security token (Nonce failed).' ] );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
			return;
		}
		$worker_url = isset( $_POST['worker_url'] ) ? esc_url_raw( $_POST['worker_url'] ) : '';
		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';

		if ( ! $this->api || ! method_exists( $this->api, 'test_connection' ) ) {
			wp_send_json_error( [ 'message' => 'خطای داخلی: نمونه API یا تابع تست بارگذاری نشده است.' ] );
			return;
		}
		$response = $this->api->test_connection( $worker_url, $api_key );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => $response->get_error_message() ] );
		} else {
			wp_send_json_success( [ 'message' => $response['message'] ] );
		}
	}

	/**
	 * Injects CSS into the admin head to apply the custom SVG menu icon.
	 */
	public function admin_menu_styles() {
		$icon_url = RS_SEO_URL . 'assets/logo/aiseo-logo.svg';
		$menu_slug = 'promptseo_dashboard';
		$menu_id = '#toplevel_page_' . $menu_slug;
		?>
		<style type="text/css">
			<?php echo esc_html( $menu_id ); ?> .wp-menu-image {
				background-color: #a0a5aa;
				-webkit-mask: url('<?php echo esc_url( $icon_url ); ?>') no-repeat center center;
				mask: url('<?php echo esc_url( $icon_url ); ?>') no-repeat center center;
				-webkit-mask-size: 18px 18px;
				mask-size: 18px 18px;
				background-size: 18px 18px;
				background-position: center center;
			}
			<?php echo esc_html( $menu_id ); ?>:hover .wp-menu-image,
			<?php echo esc_html( $menu_id ); ?>.wp-has-current-submenu .wp-menu-image,
			<?php echo esc_html( $menu_id ); ?>.current .wp-menu-image {
				background-color: var(--rs-accent, #01ada1);
			}
			<?php echo esc_html( $menu_id ); ?> .wp-menu-image::before {
				content: '' !important;
			}
		</style>
		<?php
	}

	// --- Field Renderer Callbacks ---

	public function render_field_text( $args ) {
		$id = esc_attr( $args['id'] );
		$type = isset( $args['type'] ) ? esc_attr( $args['type'] ) : 'text';
		$placeholder = isset( $args['placeholder'] ) ? esc_attr( $args['placeholder'] ) : '';
		$value = isset( $this->options[$id] ) ? esc_attr( $this->options[$id] ) : '';
		
		echo "<div class='pseo-field'>";
		echo "<input type='{$type}' id='field-{$id}' name='promptseo_ultimate_options[{$id}]' value='{$value}' class='regular-text' placeholder='{$placeholder}'>";
		echo "</div>";
	}

	public function render_field_select( $args ) {
		$id = esc_attr( $args['id'] );
		$options = $args['options'];
		$default = $args['default'];
		$current_val = isset( $this->options[$id] ) ? $this->options[$id] : $default;
		
		echo "<div class='pseo-field'>";
		echo "<select id='field-{$id}' name='promptseo_ultimate_options[{$id}]'>";
		foreach ( $options as $value => $label ) {
			echo "<option value='" . esc_attr( $value ) . "' " . selected( $current_val, $value, false ) . ">" . esc_html( $label ) . "</option>";
		}
		echo "</select>";
		echo "</div>";
	}

	public function render_field_textarea( $args ) {
		// Default to main options group
		$option_name = isset( $args['option_name'] ) ? esc_attr( $args['option_name'] ) : 'promptseo_ultimate_options';
		// Load the correct options array
		$options = ( $option_name === 'promptseo_ultimate_options' ) ? $this->options : get_option( $option_name, [] );
		
		$id = esc_attr( $args['id'] );
		$desc = esc_html( $args['desc'] );
		$class = isset( $args['class'] ) ? esc_attr( $args['class'] ) : 'ai-brain-textarea';
		$value = isset( $options[$id] ) ? esc_textarea( $options[$id] ) : '';

		echo "<div class='pseo-field'>";
		echo "<textarea id='field-{$id}' name='{$option_name}[{$id}]' class='{$class}'>{$value}</textarea>";
		echo "<p class='ai-brain-desc'>{$desc}</p>";
		echo "</div>";
	}

	/**
	 * Renders a toggle switch.
	 */
	public function render_field_toggle( $args ) {
		$option_name = esc_attr( $args['option_name'] );
		$options = get_option( $option_name, [] );
		$id = esc_attr( $args['id'] );
		$desc = esc_html( $args['desc'] );
		$checked = isset( $options[$id] ) ? checked( $options[$id], 'on', false ) : '';
		
		echo "<div class 'pseo-field'>";
		// Note: The 'rs-toggle-switch' class is defined in style-core.css
		echo "<label for='field-{$id}' class='rs-toggle-switch'>";
		echo "<input type='checkbox' id='field-{$id}' name='{$option_name}[{$id}]' {$checked} onchange='this.form.submit()'>";
		echo "<span class='rs-toggle-slider'></span>";
		echo "</label>";
		echo "<p class='description' style='display: inline-block; margin-right: 10px;'>{$desc}</p>";
		echo "</div>";
	}

	/**
	 * Sanitizes the main options array.
	 */
	public function sanitize_options( $input ) {
		$output = get_option( 'promptseo_ultimate_options', [] );

		if ( isset( $input['worker_url'] ) ) {
			$output['worker_url'] = esc_url_raw( $input['worker_url'] );
		}
		if ( isset( $input['api_key'] ) ) {
			if ( ! empty( $input['api_key'] ) ) {
				$output['api_key'] = sanitize_text_field( $input['api_key'] );
			}
		}
		if ( isset( $input['model_name'] ) ) {
			$output['model_name'] = sanitize_text_field( $input['model_name'] );
		}
		if ( isset( $input['site_knowledge_base'] ) ) {
			$output['site_knowledge_base'] = wp_kses_post( $input['site_knowledge_base'] );
		}
		if ( isset( $input['custom_system_prompt'] ) ) {
			$output['custom_system_prompt'] = wp_kses_post( $input['custom_system_prompt'] );
		}
		
		return $output;
	}

	/**
	 * Sanitizes the robots.txt options array.
	 * This is now correctly registered by the module itself.
	 */
	// public function sanitize_robots_options( $input ) { ... } 
	// This function is now correctly located in class-rs-module-robots.php

} // End class ReadyStudio_Core_Settings