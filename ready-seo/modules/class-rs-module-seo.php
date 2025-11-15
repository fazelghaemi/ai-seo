<?php
/**
 * Ready Studio SEO Engine - Module: SEO
 *
 * This module handles all core SEO meta generation:
 * - Title, Description, Keywords, Tags
 * - CPT-specific fields (Latin Name)
 * It adds the "SEO" tab to the metabox.
 *
 * @package   ReadyStudio
 * @version   12.0.0
 * @author    Fazel Ghaemi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ReadyStudio_Module_SEO {

	/**
	 * Core API instance (injected).
	 * @var ReadyStudio_Core_API
	 */
	private $api;

	/**
	 * Core Data instance (injected).
	 * @var ReadyStudio_Core_Data
	 */
	private $data;

	/**
	 * Constructor.
	 * Hooks into the Core Loader.
	 */
	public function __construct() {
		// This hook is fired by the Core Loader (class-rs-core-loader.php)
		// It passes the loader instance, which contains API and Data helpers.
		add_action( 'rs_core_loaded', [ $this, 'init' ] );
	}

	/**
	 * Initialize the module.
	 * We receive the core loader instance and store the API/Data helpers.
	 *
	 * @param ReadyStudio_Core_Loader $core_loader The main loader instance.
	 */
	public function init( $core_loader ) {
		// Inject dependencies from the core
		$this->api  = $core_loader->api;
		$this->data = $core_loader->data;

		// --- Register hooks for this module ---

		// 1. Add the "SEO" tab to the metabox shell
		add_filter( 'rs_metabox_tabs', [ $this, 'add_tab' ], 10 ); // 10 = first tab

		// 2. Render the content for the "SEO" tab
		add_action( 'rs_metabox_content', [ $this, 'render_content' ], 10 );

		// 3. Register the AJAX endpoint for this module's "Generate" button
		add_action( 'wp_ajax_rs_generate_seo', [ $this, 'ajax_handle_generate' ] );
	}

	/**
	 * 1. Adds the "SEO" tab to the metabox.
	 * Fired by 'rs_metabox_tabs' filter.
	 *
	 * @param array $tabs The array of existing tabs.
	 * @return array The modified array of tabs.
	 */
	public function add_tab( $tabs ) {
		// 'tab-seo' is the ID, 'سئو (SEO)' is the display title.
		// We add it at the beginning of the array.
		$seo_tab = [ 'tab-seo' => 'سئو (SEO)' ];
		$tabs = $seo_tab + $tabs; // Add to the front
		return $tabs;
	}

	/**
	 * 2. Renders the HTML content for the "SEO" tab.
	 * Fired by 'rs_metabox_content' action.
	 *
	 * @param string $active_tab_id The ID of the currently active tab.
	 */
	public function render_content( $active_tab_id ) {
		global $post;
		$is_prompt_cpt = ( get_post_type( $post ) === 'prompts' );
		
		// We use `key($active_tab_id)` if it's an array, or just the string
		$active_id = is_array($active_tab_id) ? key($active_tab_id) : $active_tab_id;
		?>
		<div id="tab-seo" class="rs-metabox-content <?php echo ( $active_id === 'tab-seo' ) ? 'active' : ''; ?>">

			<?php if ( $is_prompt_cpt ) : ?>
				<!-- Show a special notice if it's the 'prompts' CPT -->
				<div class="cpt-notice">
					حالت پرامپت: عنوان دقیق (Strict) و اسلاگ لاتین فعال است.
				</div>
			<?php endif; ?>

			<!-- Generate Button -->
			<button type="button" id="btn-gen-seo" class="pseo-btn btn-primary">
				<span class="dashicons dashicons-admin-settings" style="margin-top:4px; margin-left: 5px;"></span>
				تولید متای سئو
			</button>

			<!-- Loader -->
			<div id="load-seo" class="rs-loader-wrap">
				<div class="spinner-rs"></div>
			</div>

			<!-- Results Area -->
			<div id="area-seo" style="display:none;">
				<div class="pseo-field">
					<label for="in-kw">کلمه کلیدی کانونی</label>
					<input type="text" id="in-kw" name="rs_seo[keyword]">
				</div>
				<div class="pseo-field">
					<label for="in-title">عنوان سئو</label>
					<input type="text" id="in-title" name="rs_seo[title]">
				</div>
				<div class="pseo-field">
					<label for="in-desc">توضیحات متا</label>
					<textarea id="in-desc" name="rs_seo[description]" rows="3"></textarea>
				</div>
				<div class="pseo-field">
					<label for="in-tags">تگ‌ها (Tags)</label>
					<input type="text" id="in-tags" name="rs_seo[tags]" placeholder="تگ1, تگ2, ...">
				</div>
				
				<?php if ( $is_prompt_cpt ) : ?>
					<!-- Special field only for 'prompts' CPT -->
					<div class="pseo-field">
						<label for="in-latin-name">نام لاتین (برای اسلاگ)</label>
						<input type="text" id="in-latin-name" name="rs_seo[latin_name]">
					</div>
				<?php endif; ?>
			</div>

		</div>
		<?php
	}

	/**
	 * 3. Handles the AJAX request for the "Generate SEO" button.
	 * Fired by 'wp_ajax_rs_generate_seo' hook.
	 */
	public function ajax_handle_generate() {
		// Security checks
		if ( ! check_ajax_referer( 'rs_nonce_action', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid security token.' );
			return;
		}
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Permission denied.' );
			return;
		}

		// 1. Get post data
		$post = get_post( $post_id );
		$content = $this->data->get_content_for_analysis( $post_id );
		$is_prompt_cpt = ( $post->post_type === 'prompts' );
		
		// 2. Build the task prompt
		$task_prompt = self::get_task_prompt( [ 'strict_mode' => $is_prompt_cpt, 'do_slug' => $is_prompt_cpt ] );

		// 3. Call the Core API (Nexus Brain)
		$response = $this->api->call_gemini_text( $task_prompt, $content, $post->post_title );

		// 4. Send response back to JavaScript
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		} else {
			wp_send_json_success( $response );
		}
	}

	/**
	 * Static helper function to build the task prompt for SEO.
	 * This is also used by the Bulk Generator (Core Bulk).
	 *
	 * @param array $opts Options like 'strict_mode' and 'do_slug'.
	 * @return string The formatted task prompt.
	 */
	public static function get_task_prompt( $opts = [] ) {
		$is_strict = ! empty( $opts['strict_mode'] );
		$do_slug = ! empty( $opts['do_slug'] );

		$title_instruction = $is_strict ?
			"Create a STRICTLY DESCRIPTIVE title (no clickbait). Example: 'Photorealistic prompt of a gold coin'." :
			"Create a High-CTR, catchy, SEO-optimized title.";
		
		$slug_instruction = $do_slug ?
			"\"latin_name\": \"English kebab-case slug for URL (e.g., gold-coin-on-wood)\"" :
			"\"latin_name\": \"\"";

		return "
        --- TASK: Generate SEO Meta ---
        Instructions:
        1. Title: {$title_instruction}
        2. Keyword: Extract the single main Focus Keyword from the content (must be in Persian).
        3. Description: Write a compelling Persian Meta Description (max 155 chars).
        4. Tags: Generate 5 relevant Persian tags.
        
        Respond ONLY with the following JSON structure (no markdown):
        {
          \"title\": \"...\",
          \"keyword\": \"...\",
          \"description\": \"...\",
          \"tags\": [\"تگ۱\", \"تگ۲\", \"تگ۳\", \"تگ۴\", \"تگ۵\"],
          {$slug_instruction}
        }
        ";
	}

} // End class ReadyStudio_Module_SEO

// Instantiate the module by hooking into the core loader
add_action( 'rs_core_loaded', function( $core_loader ) {
	new ReadyStudio_Module_SEO( $core_loader );
} );