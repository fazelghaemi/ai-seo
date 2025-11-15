<?php
/**
 * Ready Studio SEO Engine - Module: Content
 *
 * This module handles the generation of post content ("Content Writer")
 * and the base image Alt Text (which can be overridden by the Vision module).
 * It adds the "Content" tab to the metabox.
 *
 * @package   ReadyStudio
 * @version   12.0.0
 * @author    Fazel Ghaemi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ReadyStudio_Module_Content {

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
		// This hook is fired by the Core Loader
		add_action( 'rs_core_loaded', [ $this, 'init' ] );
	}

	/**
	 * Initialize the module and inject dependencies.
	 *
	 * @param ReadyStudio_Core_Loader $core_loader The main loader instance.
	 */
	public function init( $core_loader ) {
		// Inject dependencies from the core
		$this->api  = $core_loader->api;
		$this->data = $core_loader->data;

		// --- Register hooks for this module ---

		// 1. Add the "Content" tab to the metabox shell
		add_filter( 'rs_metabox_tabs', [ $this, 'add_tab' ], 20 ); // 20 = second tab

		// 2. Render the content for the "Content" tab
		add_action( 'rs_metabox_content', [ $this, 'render_content' ], 20 );

		// 3. Register the AJAX endpoint for this module's "Generate" button
		add_action( 'wp_ajax_rs_generate_content', [ $this, 'ajax_handle_generate' ] );
	}

	/**
	 * 1. Adds the "Content" tab to the metabox.
	 * Fired by 'rs_metabox_tabs' filter.
	 *
	 * @param array $tabs The array of existing tabs.
	 * @return array The modified array of tabs.
	 */
	public function add_tab( $tabs ) {
		$tabs['tab-content'] = 'محتوا';
		return $tabs;
	}

	/**
	 * 2. Renders the HTML content for the "Content" tab.
	 * Fired by 'rs_metabox_content' action.
	 *
	 * @param string $active_tab_id The ID of the currently active tab.
	 */
	public function render_content( $active_tab_id ) {
		// We use `key($active_tab_id)` if it's an array, or just the string
		$active_id = is_array($active_tab_id) ? key($active_tab_id) : $active_tab_id;
		?>
		<div id="tab-content" class="rs-metabox-content <?php echo ( $active_id === 'tab-content' ) ? 'active' : ''; ?>">

			<div class="cpt-notice">
				(Thin Content) با تولید پاراگراف توصیفی، مشکل محتوای ضعیف را حل کنید.
			</div>

			<!-- Generate Button -->
			<button type="button" id="btn-gen-content" class="pseo-btn btn-magic">
				<span class="dashicons dashicons-edit" style="margin-top:4px; margin-left: 5px;"></span>
				نویسنده هوشمند (پاراگراف)
			</button>

			<!-- Loader -->
			<div id="load-content" class="rs-loader-wrap">
				<div class="spinner-rs"></div>
			</div>

			<!-- Results Area -->
			<div id="area-content" style="display:none;">
				<div class="pseo-field">
					<label for="in-content">متن توصیفی (برای بدنه پست)</label>
					<textarea id="in-content" name="rs_content[content_body]" rows="7"></textarea>
				</div>
				<div class="pseo-field">
					<label for="in-alt">متن جایگزین (Alt Text)</label>
					<input type="text" id="in-alt" name="rs_content[image_alt]">
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * 3. Handles the AJAX request for the "Content Writer" button.
	 * Fired by 'wp_ajax_rs_generate_content' hook.
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
		
		// 2. Build the task prompt
		$task_prompt = self::get_task_prompt();

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
	 * Static helper function to build the task prompt for Content.
	 * This is also used by the Bulk Generator (Core Bulk).
	 *
	 * @return string The formatted task prompt.
	 */
	public static function get_task_prompt() {
		return "
        --- TASK: Generate Content ---
        Instructions:
        1.  **content_body**: Write a 150-word, engaging Persian paragraph. This paragraph should describe the artistic style, mood, and potential uses of the prompt. Make it descriptive and useful for a user browsing a gallery.
        2.  **image_alt**: Write a concise (10-15 words) Persian alt text *based on the prompt content*, describing what the resulting image might look like.
        
        Respond ONLY with the following JSON structure (no markdown):
        {
          \"content_body\": \"...\",
          \"image_alt\": \"...\"
        }
        ";
	}

} // End class ReadyStudio_Module_Content

// Instantiate the module by hooking into the core loader
add_action( 'rs_core_loaded', function( $core_loader ) {
	new ReadyStudio_Module_Content( $core_loader );
} );