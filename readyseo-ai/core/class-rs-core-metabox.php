<?php
/**
 * Ready Studio SEO Engine - Core Metabox Shell
 *
 * This class creates the main metabox container (the "shell")
 * in the post editor. Modules hook into this shell to add their tabs and content.
 * It also handles the "Save All" button and its AJAX action.
 *
 * @package   ReadyStudio
 * @version   12.0.0
 * @author    Fazel Ghaemi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ReadyStudio_Core_Metabox {

	/**
	 * Core Data instance (injected).
	 * @var ReadyStudio_Core_Data
	 */
	private $data;

	/**
	 * Constructor.
	 * Injects dependencies and registers hooks.
	 *
	 * @param ReadyStudio_Core_Data $data The Core Data helper instance.
	 */
	public function __construct( $data ) {
		$this->data = $data;

		// 1. Hook to create the metabox on all public post types
		add_action( 'add_meta_boxes', [ $this, 'register_metabox' ] );

		// 2. Register the global "Save All" AJAX endpoint
		add_action( 'wp_ajax_rs_save_all_data', [ $this, 'ajax_handle_save_all' ] );
	}

	/**
	 * 1. Registers the main metabox shell.
	 * Fired by 'add_meta_boxes' hook.
	 */
	public function register_metabox() {
		// Get all public post types (post, page, product, prompts, etc.)
		$post_types = get_post_types( [ 'public' => true ] );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'rs_ai_box',                    // ID
				'دستیار Ready Studio (Nexus)', // Title
				[ $this, 'render_metabox_shell' ], // Callback
				$post_type,                     // Post Type
				'side',                         // Context (sidebar)
				'high'                          // Priority
			);
		}
	}

	/**
	 * 2. Renders the HTML for the metabox *shell*.
	 * Modules will hook into 'rs_metabox_tabs' and 'rs_metabox_content'.
	 *
	 * @param WP_Post $post The current post object.
	 */
	public function render_metabox_shell( $post ) {
		// Security nonce field for all metabox AJAX actions
		// This is picked up by metabox-core.js
		wp_nonce_field( 'rs_nonce_action', 'rs_nonce_field' );
		?>
		<div id="promptseo-app">
			
			<!-- Header Branding -->
			<div class="rs-branding">
				<h4>Ready Studio AI</h4>
			</div>
			
			<!-- Tab Navigation -->
			<nav id="rs-metabox-tabs" class="rs-tabs">
				<?php
				/**
				 * Hook for modules to add their tab links.
				 * @hooked ReadyStudio_Module_SEO->add_tab()
				 * @hooked ReadyStudio_Module_Content->add_tab()
				 * @hooked ReadyStudio_Module_Vision->add_tab()
				 *
				 * @param array $tabs An array of tabs [ 'tab-id' => 'Tab Title' ]
				 */
				$tabs = apply_filters( 'rs_metabox_tabs', [] );
				
				$active_set = false;
				foreach ( $tabs as $id => $title ) {
					// Make the first tab active by default
					$class = ( ! $active_set ) ? 'rs-tab-link active' : 'rs-tab-link';
					echo '<div class="' . esc_attr( $class ) . '" data-tab="' . esc_attr( $id ) . '">';
					echo esc_html( $title );
					echo '</div>';
					$active_set = true;
				}
				?>
			</nav>

			<!-- Tab Content Wrapper -->
			<div class="rs-metabox-content-wrapper">
				<?php
				/**
				 * Hook for modules to add their tab content panels.
				 * @hooked ReadyStudio_Module_SEO->render_content()
				 * @hooked ReadyStudio_Module_Content->render_content()
				 * @hooked ReadyStudio_Module_Vision->render_content()
				 *
				 * @param string $active_tab_id (Modules should check this)
				 */
				do_action( 'rs_metabox_content', key( $tabs ) ); // Pass the ID of the first tab
				?>
			</div>

			<!-- Global Action Bar -->
			<div class="rs-metabox-actions">
				<button type="button" id="btn-save-all" class="pseo-btn btn-save">
					<span class="dashicons dashicons-yes-alt" style="margin-top:4px; margin-left: 5px;"></span>
					ذخیره تمام تغییرات
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * 3. AJAX handler for the "Save All" button.
	 *
	 * Fired by 'wp_ajax_rs_save_all_data' hook from metabox-core.js.
	 */
	public function ajax_handle_save_all() {
		// 1. Security Check
		if ( ! check_ajax_referer( 'rs_nonce_action', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid security token.' );
			return;
		}

		// 2. Get Data
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$data = isset( $_POST['seo_data'] ) ? (array) $_POST['seo_data'] : [];

		if ( $post_id === 0 || empty( $data ) ) {
			wp_send_json_error( 'اطلاعات ناقص (No Post ID or Data).' );
			return;
		}
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Permission denied.' );
			return;
		}
		
		// 3. Sanitize Data (basic sanitization, Core_Data does more)
		$sanitized_data = [];
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				// We don't expect arrays here, but good to check
				$sanitized_data[ sanitize_key( $key ) ] = array_map( 'sanitize_text_field', $value );
			} else {
				// Allow HTML in content_body, sanitize others
				if ( $key === 'content_body' ) {
					$sanitized_data[ sanitize_key( $key ) ] = wp_kses_post( $value );
				} else {
					$sanitized_data[ sanitize_key( $key ) ] = sanitize_text_field( $value );
				}
			}
		}

		// 4. Pass sanitized data to the Core Data helper for saving
		try {
			$this->data->save_all_metabox_data( $post_id, $sanitized_data );
		} catch ( Exception $e ) {
			wp_send_json_error( 'خطا در ذخیره‌سازی: ' . $e->getMessage() );
			return;
		}

		// 5. Send Success Response
		wp_send_json_success( 'داده‌ها با موفقیت ذخیره شدند.' );
	}

} // End class ReadyStudio_Core_Metabox