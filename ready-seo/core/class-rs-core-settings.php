<?php
/**
 * Ready Studio SEO Engine - Core Settings
 *
 * This class handles the creation of the admin menu pages
 * and the registration and display of all plugin settings,
 * including API keys and the AI Brain.
 *
 * @package   ReadyStudio
 * @version   12.0.0
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
	 * Constructor.
	 * Hooks into WordPress admin menu and init actions.
	 *
	 * @param array $options The plugin's options (injected by Loader).
	 */
	public function __construct( $options ) {
		$this->options = $options;

		// Hook to create admin menus
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		
		// Hook to register settings fields
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Registers the main admin menu and sub-menu pages.
	 *
	 * Fired by 'admin_menu' hook.
	 */
	public function register_menus() {
		// Main Menu Page (Dashboard / Settings)
		add_menu_page(
			'Ready Studio',         // Page Title
			'Ready Studio',         // Menu Title
			'manage_options',       // Capability
			'promptseo_dashboard',  // Menu Slug (Main page)
			[ $this, 'render_dashboard_page' ], // Callback function
			'dashicons-art',        // Icon (related to prompts/art)
			99                      // Position
		);

		// Sub-Menu Page (Bulk Generator)
		// This will be handled by the ReadyStudio_Core_Bulk class
		// We add it here to ensure menu order
		add_submenu_page(
			'promptseo_dashboard',
			'تولید انبوه',          // Page Title
			'تولید انبوه',          // Menu Title
			'manage_options',       // Capability
			'promptseo_bulk',       // Menu Slug
			[ 'ReadyStudio_Core_Bulk', 'render_page' ] // Static callback
		);
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
			'promptseo_dashboard_brain' // A different 'page' slug for the tab
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
				<h1>Ready Studio Nexus Core <span style="font-size:12px; color:#888;">v12.0</span></h1>
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

				<!-- Tab 1: API Settings Content -->
				<div id="tab-api" class="rs-tab-content <?php echo $active_tab == 'api' ? 'active' : ''; ?>">
					<table class="form-table">
						<?php do_settings_fields( 'promptseo_dashboard', 'rs_api_section' ); ?>
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

	// --- Field Renderer Callbacks ---

	/**
	 * Renders a standard text, url, or password input field.
	 *
	 * @param array $args Arguments for the field.
	 */
	public function render_field_text( $args ) {
		$id = esc_attr( $args['id'] );
		$type = isset( $args['type'] ) ? esc_attr( $args['type'] ) : 'text';
		$placeholder = isset( $args['placeholder'] ) ? esc_attr( $args['placeholder'] ) : '';
		$value = isset( $this->options[$id] ) ? esc_attr( $this->options[$id] ) : '';
		
		echo "<div class='pseo-field'>";
		echo "<input type='{$type}' id='{$id}' name='promptseo_ultimate_options[{$id}]' value='{$value}' class='regular-text' placeholder='{$placeholder}'>";
		echo "</div>";
	}

	/**
	 * Renders a select (dropdown) field.
	 *
	 * @param array $args Arguments for the field.
	 */
	public function render_field_select( $args ) {
		$id = esc_attr( $args['id'] );
		$options = $args['options'];
		$default = $args['default'];
		$current_val = isset( $this->options[$id] ) ? $this->options[$id] : $default;
		
		echo "<div class='pseo-field'>";
		echo "<select id='{$id}' name='promptseo_ultimate_options[{$id}]'>";
		foreach ( $options as $value => $label ) {
			echo "<option value='" . esc_attr( $value ) . "' " . selected( $current_val, $value, false ) . ">" . esc_html( $label ) . "</option>";
		}
		echo "</select>";
		echo "</div>";
	}

	/**
	 * Renders a textarea field (for AI Brain).
	 *
	 * @param array $args Arguments for the field.
	 */
	public function render_field_textarea( $args ) {
		$id = esc_attr( $args['id'] );
		$desc = esc_html( $args['desc'] );
		$value = isset( $this->options[$id] ) ? esc_textarea( $this->options[$id] ) : '';

		echo "<div class='pseo-field'>";
		echo "<textarea id='{$id}' name='promptseo_ultimate_options[{$id}]' class='ai-brain-textarea'>{$value}</textarea>";
		echo "<p class='ai-brain-desc'>{$desc}</p>";
		echo "</div>";
	}

	/**
	 * Sanitizes the options array before saving to DB.
	 *
	 * @param array $input The raw input from the settings form.
	 * @return array The sanitized array.
	 */
	public function sanitize_options( $input ) {
		$output = [];

		if ( isset( $input['worker_url'] ) ) {
			$output['worker_url'] = esc_url_raw( $input['worker_url'] );
		}
		if ( isset( $input['api_key'] ) ) {
			// Basic sanitization. API keys can have weird chars.
			$output['api_key'] = sanitize_text_field( $input['api_key'] );
		}
		if ( isset( $input['model_name'] ) ) {
			$output['model_name'] = sanitize_text_field( $input['model_name'] );
		}
		if ( isset( $input['site_knowledge_base'] ) ) {
			// Allow more tags/text for knowledge base
			$output['site_knowledge_base'] = wp_kses_post( $input['site_knowledge_base'] );
		}
		if ( isset( $input['custom_system_prompt'] ) ) {
			$output['custom_system_prompt'] = wp_kses_post( $input['custom_system_prompt'] );
		}
		
		return $output;
	}

} // End class ReadyStudio_Core_Settings