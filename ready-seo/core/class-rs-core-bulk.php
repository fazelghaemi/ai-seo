<?php
/**
 * Ready Studio SEO Engine - Core Bulk Processor
 *
 * v12.5: CRITICAL FIX - Added `class_exists()` checks before calling
 * static methods from modules (SEO, Content, Vision). This prevents
 * a Fatal Error (Class not found) if a module file is missing or
 * hasn't been added to the repo yet.
 *
 * @package   ReadyStudio
 * @version   12.5.0
 * @author    Fazel Ghaemi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ReadyStudio_Core_Bulk {

	/**
	* Core API instance (injected).
	* @var ReadyStudio_Core_API
	*/
	private static $api;

	/**
	* Core Data instance (injected).
	* @var ReadyStudio_Core_Data
	*/
	private static $data;

	/**
	 * Constructor.
	 * Injects dependencies from the Core Loader.
	 *
	 * @param ReadyStudio_Core_API  $api  The Core API instance (Nexus Brain).
	 * @param ReadyStudio_Core_Data $data The Core Data helper instance.
	 */
	public function __construct( $api, $data ) {
		self::$api = $api;
		self::$data = $data;

		// Register the AJAX action for bulk processing
		add_action( 'wp_ajax_rs_bulk_generate', [ $this, 'ajax_handle_bulk_generate' ] );
	}

	/**
	 * Static callback function for the `add_submenu_page` call.
	 * This renders the HTML for the Bulk Generator page.
	 */
	public static function render_page() {
		// Determine which post type to show
		$post_type = isset( $_GET['ptype'] ) ? sanitize_text_field( $_GET['ptype'] ) : 'post';
		$is_prompt_pt = ($post_type === 'prompts'); // Check if it's the special 'prompts' CPT

		// WP_Query args to get the posts
		$args = [
			'post_type'      => $post_type,
			'posts_per_page' => 100, // Show 100 posts per page
			'post_status'    => 'publish', // Only published posts
			'orderby'        => 'modified', // Show most recently modified
			'order'          => 'DESC',
		];
		$query = new WP_Query( $args );

		// Get all public post types for the dropdown selector
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		?>
		<div class="wrap rs-wrap bulk-page" dir="rtl">
			
			<!-- Header: Title and Post Type Selector -->
			<div class="rs-header">
				<h1>
					AI SEO
					<span class="rs-brand-family">از خانواده <strong>Ready Studio</strong></span>
				</h1>
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

			<!-- Control Panel: Checkboxes for options -->
			<div class="rs-control-panel">
				<span class="rs-label-title">عملیات:</span>
				<label class="rs-control-group"><input type="checkbox" id="opt-seo" checked> تولید سئو (SEO)</label>
				<label class="rs-control-group"><input type="checkbox" id="opt-content"> تولید محتوا (Content)</label>
				<label class="rs-control-group"><input type="checkbox" id="opt-alt"> تولید (Alt) تصویر</label>
				
				<div class="rs-divider"></div>
				
				<span class="rs-label-title">تنظیمات:</span>
				<label class="rs-control-group"><input type="checkbox" id="opt-strict" <?php checked( $is_prompt_pt ); ?>> عنوان دقیق (Strict)</label>
				<label class="rs-control-group"><input type="checkbox" id="opt-slug" <?php checked( $is_prompt_pt ); ?>> آپدیت اسلاگ (Slug)</label>
			</div>

			<!-- Action Bar: Start Button -->
			<div class="rs-bulk-controls">
				<button id="rs-bulk-start" class="pseo-btn btn-generate" style="width:auto;">
					<span class="dashicons dashicons-controls-play" style="margin-top:4px; margin-left: 5px;"></span>
					شروع پردازش
				</button>
				<!-- Security nonce for the AJAX request -->
				<?php wp_nonce_field( 'rs_nonce_action', 'rs_bulk_nonce' ); ?>
			</div>
			
			<!-- Progress Bar & Log Console -->
			<div id="rs-progress-bar"></div>
			<div id="rs-log" class="rs-log-box"></div>

			<!-- Posts Table -->
			<table class="wp-list-table widefat fixed striped table-view-list" style="margin: 0;">
				<thead>
					<tr>
						<td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>
						<th scope="col" id="title" class="manage-column column-title column-primary">عنوان</th>
						<th scope="col" id="status" class="manage-column column-status">وضعیت AI</th>
					</tr>
				</thead>
				<tbody id="the-list">
					<?php if ( $query->have_posts() ) : ?>
						<?php while ( $query->have_posts() ) : $query->the_post(); ?>
							<?php
							// Check if the post already has AI data (e.g., a focus keyword)
							$has_seo = get_post_meta( get_the_ID(), 'rank_math_focus_keyword', true );
							?>
							<tr id="post-<?php echo get_the_ID(); ?>">
								<th class="check-column">
									<input type="checkbox" name="post[]" value="<?php echo get_the_ID(); ?>" class="rs-bulk-check">
								</th>
								<td class="column-title column-primary">
									<strong><a href="<?php echo get_edit_post_link(); ?>" target="_blank"><?php the_title(); ?></a></strong>
								</td>
								<td class="column-status" id="status-<?php echo get_the_ID(); ?>">
									<?php if ( $has_seo ) : ?>
										<span class="status-badge status-done">Done</span>
									<?php else : ?>
										<span class="status-badge status-pending">Pending</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endwhile; ?>
					<?php else : ?>
						<tr><td colspan="3">پستی در این پست تایپ یافت نشد.</td></tr>
					<?php endif; wp_reset_postdata(); ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * AJAX handler for processing a *single post* from the bulk queue.
	 *
	 * Fired by 'wp_ajax_rs_bulk_generate' hook from admin-core.js.
	 */
	public function ajax_handle_bulk_generate() {
		
		// Prevent PHP from timing out on long-running bulk tasks.
		@set_time_limit( 0 );
		
		// 1. Security Check
		if ( ! check_ajax_referer( 'rs_nonce_action', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid security token.' );
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
			return;
		}

		// 2. Get Data
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$opts = isset( $_POST['options'] ) ? (array) $_POST['options'] : [];
		
		// Convert JS 'true'/'false' strings to bool
		foreach ($opts as $key => $value) {
			$opts[$key] = ($value === 'true');
		}

		if ( $post_id === 0 ) {
			wp_send_json_error( 'Invalid Post ID.' );
			return;
		}

		$results = []; // To store success messages

		// --- 3. Execute Operations based on options ---

		try {
			// --- SEO Generation ---
			// *** DEFENSIVE CHECK v12.5 ***
			if ( ! empty( $opts['do_seo'] ) && class_exists( 'ReadyStudio_Module_SEO' ) ) {
				$post = get_post( $post_id );
				$content = self::$data->get_content_for_analysis( $post_id );
				
				// Build the task prompt for the API
				$task_prompt = ReadyStudio_Module_SEO::get_task_prompt( $opts );
				
				// Call the Nexus Brain (Core API)
				$response = self::$api->call_gemini_text( $task_prompt, $content, $post->post_title );

				if ( is_wp_error( $response ) ) {
					throw new Exception( 'SEO Error: ' . $response->get_error_message() );
				}
				
				// Save the data
				self::$data->save_seo_meta( $post_id, $response );
				if (isset($response['tags'])) {
					self::$data->save_post_tags( $post_id, $response['tags'] );
				}
				if ( ! empty( $opts['do_slug'] ) && isset($response['latin_name']) ) {
					self::$data->save_prompt_cpt_data( $post_id, $response['latin_name'] );
				}
				$results[] = 'SEO OK';
			} elseif ( ! empty( $opts['do_seo'] ) ) {
				$results[] = 'SEO Skipped (Module not found)';
			}

			// --- Content Generation ---
			// *** DEFENSIVE CHECK v12.5 ***
			if ( ! empty( $opts['do_content'] ) && class_exists( 'ReadyStudio_Module_Content' ) ) {
				$post = get_post( $post_id );
				$content = self::$data->get_content_for_analysis( $post_id );

				$task_prompt = ReadyStudio_Module_Content::get_task_prompt();
				$response = self::$api->call_gemini_text( $task_prompt, $content, $post->post_title );

				if ( is_wp_error( $response ) ) {
					throw new Exception( 'Content Error: ' . $response->get_error_message() );
				}

				if (isset($response['content_body'])) {
					self::$data->save_post_content( $post_id, $response['content_body'] );
				}
				if ( ! empty( $opts['do_alt'] ) && isset($response['image_alt']) ) {
					self::$data->save_image_alt_text( $post_id, $response['image_alt'] );
				}
				$results[] = 'Content OK';
			} elseif ( ! empty( $opts['do_content'] ) ) {
				$results[] = 'Content Skipped (Module not found)';
			}
			
			// --- Alt-Only Generation ---
			// *** DEFENSIVE CHECK v12.5 ***
			elseif ( ! empty( $opts['do_alt'] ) && class_exists( 'ReadyStudio_Module_Vision' ) ) {
				$task_prompt = ReadyStudio_Module_Vision::get_task_prompt();
				$image_data = ReadyStudio_Module_Vision::get_image_data( $post_id );

				if ( is_array( $image_data ) ) {
					$response = self::$api->call_gemini_vision( $task_prompt, $image_data['base64'], $image_data['mime_type'] );
					if ( is_wp_error( $response ) ) {
						throw new Exception( 'Vision Alt Error: ' . $response->get_error_message() );
					}
					if (isset($response['alt_text'])) {
						self::$data->save_image_alt_text( $post_id, $response['alt_text'] );
					}
					$results[] = 'Alt (Vision) OK';
				} else {
					$results[] = 'Alt Skipped (No Image)';
				}
			} elseif ( ! empty( $opts['do_alt'] ) ) {
				$results[] = 'Alt Skipped (Vision Module not found)';
			}

		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
			return;
		}

		if ( empty( $results ) ) {
			wp_send_json_error( 'هیچ عملیاتی انتخاب نشد.' );
			return;
		}

		// 4. Send Success Response
		wp_send_json_success( implode( ', ', $results ) );
	}

} // End class ReadyStudio_Core_Bulk