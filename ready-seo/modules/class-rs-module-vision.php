<?php
/**
 * Ready Studio SEO Engine - Module: Vision
 *
 * This module adds visual analysis capabilities (Gemini Vision).
 * It "looks" at the featured image to generate highly accurate
 * alt text, art style tags, and visual keywords.
 *
 * @package   ReadyStudio
 * @version   12.0.0
 * @author    Fazel Ghaemi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ReadyStudio_Module_Vision {

	/**
	 * Core API instance (injected).
	 * @var ReadyStudio_Core_API
	 */
	private $api;

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
		$this->api = $core_loader->api;
		// Note: We don't need $this->data here, as this module
		// only generates data. The Core Metabox save button handles saving.

		// --- Register hooks for this module ---

		// 1. Add this module's CSS to the post edit screen
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_module_assets' ] );

		// 2. Add the "Vision" tab to the metabox shell
		add_filter( 'rs_metabox_tabs', [ $this, 'add_tab' ], 30 ); // 30 = third tab

		// 3. Render the content for the "Vision" tab
		add_action( 'rs_metabox_content', [ $this, 'render_content' ], 30 );

		// 4. Register the AJAX endpoint for this module's "Generate" button
		add_action( 'wp_ajax_rs_generate_vision', [ $this, 'ajax_handle_generate' ] );
	}

	/**
	 * 1. Enqueues the CSS stylesheet specific to this module.
	 * Fired by 'admin_enqueue_scripts'.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_module_assets( $hook ) {
		if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
			wp_enqueue_style(
				'rs-module-vision-style',
				RS_SEO_URL . 'assets/css/module-vision.css',
				[ 'rs-metabox-style' ], // Depends on metabox styles
				RS_SEO_VERSION
			);
		}
	}

	/**
	 * 2. Adds the "Vision" tab to the metabox.
	 * Fired by 'rs_metabox_tabs' filter.
	 *
	 * @param array $tabs The array of existing tabs.
	 * @return array The modified array of tabs.
	 */
	public function add_tab( $tabs ) {
		$tabs['tab-vision'] = 'تحلیل بصری';
		return $tabs;
	}

	/**
	 * 3. Renders the HTML content for the "Vision" tab.
	 * Fired by 'rs_metabox_content' action.
	 *
	 * @param string $active_tab_id The ID of the currently active tab.
	 */
	public function render_content( $active_tab_id ) {
		global $post;
		$thumbnail_url = get_the_post_thumbnail_url( $post->ID, 'medium' );
		
		// We use `key($active_tab_id)` if it's an array, or just the string
		$active_id = is_array($active_tab_id) ? key($active_tab_id) : $active_tab_id;
		?>
		<div id="tab-vision" class="rs-metabox-content <?php echo ( $active_id === 'tab-vision' ) ? 'active' : ''; ?>">
			
			<div class="rs-vision-panel">
				
				<!-- Featured Image Preview Box -->
				<div class="rs-vision-preview">
					<?php if ( $thumbnail_url ) : ?>
						<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="Image Preview">
					<?php else : ?>
						<div class="rs-vision-preview-placeholder">
							<span class="dashicons dashicons-format-image"></span>
							<br>
							ابتدا یک تصویر شاخص تنظیم کنید
						</div>
					<?php endif; ?>
				</div>

				<!-- Generate Button -->
				<button type="button" id="btn-gen-vision" class="pseo-btn btn-vision" <?php disabled( ! $thumbnail_url ); ?>>
					<span class="dashicons dashicons-visibility" style="margin-top:4px; margin-left: 5px;"></span>
					شروع تحلیل بصری (Vision)
				</button>
				
				<!-- Loader -->
				<div id="load-vision" class="rs-loader-wrap">
					<div class="spinner-rs"></div>
				</div>

				<!-- Results Area -->
				<div id="area-vision-results" class="rs-vision-results" style="display:none;">
					<div class="pseo-field style-field">
						<label for="in-art-style">سبک هنری (از تصویر)</label>
						<input type="text" id="in-art-style" name="rs_vision[art_style]">
					</div>
					<div class="pseo-field visual-tags-field">
						<label for="in-visual-tags">تگ‌های بصری (از تصویر)</label>
						<input type="text" id="in-visual-tags" name="rs_vision[visual_tags]">
					</div>
					<div class="pseo-field">
						<label for="in-alt-vision">متن Alt پیشنهادی (از تصویر)</label>
						<input type="text" id="in-alt-vision" name="rs_vision[vision_alt]">
					</div>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * 4. Handles the AJAX request for the "Visual Analysis" button.
	 * Fired by 'wp_ajax_rs_generate_vision' hook.
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

		// 1. Get Image Data (Base64)
		$image_data = self::get_image_data( $post_id );
		if ( is_wp_error( $image_data ) ) {
			wp_send_json_error( $image_data->get_error_message() );
			return;
		}

		// 2. Build the task prompt
		$task_prompt = self::get_task_prompt();

		// 3. Call the Core API (Nexus Brain) - Vision function
		$response = $this->api->call_gemini_vision(
			$task_prompt,
			$image_data['base64'],
			$image_data['mime_type']
		);

		// 4. Send response back to JavaScript
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		} else {
			wp_send_json_success( $response );
		}
	}

	/**
	 * Static helper to get the task prompt for Vision.
	 * Also used by Bulk Generator.
	 *
	 * @return string The task prompt.
	 */
	public static function get_task_prompt() {
		return "
        You are a world-class Art Director and SEO specialist.
        Analyze this image and return ONLY a JSON object.
        Your analysis must be in Persian (فارسی).
        
        Tasks:
        1.  **art_style**: Analyze the visual style (e.g., 'Photorealistic, Macro, Cinematic Lighting').
        2.  **visual_tags**: Extract 5-7 main keywords from the image (e.g., 'gold coin, wood, treasure').
        3.  **alt_text**: Write a perfect, descriptive Alt Text (max 15 words).
        
        Respond ONLY with the following JSON structure (no markdown):
        {
          \"art_style\": \"...\",
          \"visual_tags\": [\"...\", \"...\"],
          \"alt_text\": \"...\"
        }
        ";
	}

	/**
	 * Static helper to get the Base64 data of the featured image.
	 * Also used by Bulk Generator.
	 *
	 * @param int $post_id The post ID.
	 * @return array|WP_Error Array of [base64, mime_type] or WP_Error.
	 */
	public static function get_image_data( $post_id ) {
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumbnail_id ) {
			return new WP_Error( 'no_thumbnail', 'تصویر شاخص یافت نشد.' );
		}
		
		$image_path = get_attached_file( $thumbnail_id );
		if ( ! $image_path || ! file_exists( $image_path ) ) {
			return new WP_Error( 'file_not_found', 'فایل تصویر شاخص در سرور یافت نشد.' );
		}

		$image_data = base64_encode( file_get_contents( $image_path ) );
		$mime_type = get_post_mime_type( $thumbnail_id );

		if ( empty( $image_data ) || empty( $mime_type ) ) {
			return new WP_Error( 'read_error', 'خطا در خواندن فایل تصویر.' );
		}

		return [
			'base64'    => $image_data,
			'mime_type' => $mime_type,
		];
	}

} // End class ReadyStudio_Module_Vision

// Instantiate the module by hooking into the core loader
add_action( 'rs_core_loaded', function( $core_loader ) {
	new ReadyStudio_Module_Vision( $core_loader );
} );