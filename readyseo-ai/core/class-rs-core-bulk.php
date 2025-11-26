<?php
/**
 * Ready Studio SEO Engine - Core Bulk Processor
 *
 * v15.0: MASTERPIECE EDITION
 * - Enhanced error reporting (catches specific PHP errors).
 * - Implemented unlimited execution time `set_time_limit(0)`.
 * - Added strict checks for module existence.
 *
 * @package   ReadyStudio
 * @version   15.0.0
 * @author    Fazel Ghaemi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ReadyStudio_Core_Bulk {

	private static $api;
	private static $data;

	public function __construct( $api, $data ) {
		self::$api = $api;
		self::$data = $data;
		add_action( 'wp_ajax_rs_bulk_generate', [ $this, 'ajax_handle_bulk_generate' ] );
	}

	public static function render_page() {
		$post_type = isset( $_GET['ptype'] ) ? sanitize_text_field( $_GET['ptype'] ) : 'post';
		$is_prompt_pt = ($post_type === 'prompts');
		$args = [
			'post_type'      => $post_type,
			'posts_per_page' => 100,
			'post_status'    => 'publish',
			'orderby'        => 'modified',
			'order'          => 'DESC',
		];
		$query = new WP_Query( $args );
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		?>
		<div class="wrap rs-wrap bulk-page" dir="rtl">
			<div class="rs-header">
				<h1>AI SEO <span class="rs-brand-family">از خانواده <strong>Ready Studio</strong></span></h1>
				<form method="get" style="margin:0;">
					<input type="hidden" name="page" value="promptseo_bulk">
					<select name="ptype" onchange="this.form.submit()">
						<?php foreach ( $post_types as $pt_slug => $pt_obj ) : ?>
							<option value="<?php echo esc_attr( $pt_slug ); ?>" <?php selected( $post_type, $pt_slug ); ?>><?php echo esc_html( $pt_obj->label ); ?></option>
						<?php endforeach; ?>
					</select>
				</form>
			</div>

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

			<div class="rs-bulk-controls">
				<button id="rs-bulk-start" class="pseo-btn btn-generate" style="width:auto;"><span class="dashicons dashicons-controls-play" style="margin-top:4px;"></span> شروع پردازش</button>
				<?php wp_nonce_field( 'rs_nonce_action', 'rs_bulk_nonce' ); ?>
			</div>
			<div id="rs-progress-bar"></div>
			<div id="rs-log" class="rs-log-box"></div>

			<table class="wp-list-table widefat fixed striped table-view-list" style="margin: 0;">
				<thead><tr><td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td><th>عنوان</th><th>وضعیت</th></tr></thead>
				<tbody>
					<?php if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post(); 
						$has_seo = get_post_meta( get_the_ID(), 'rank_math_focus_keyword', true ); ?>
						<tr id="post-<?php echo get_the_ID(); ?>">
							<th class="check-column"><input type="checkbox" name="post[]" value="<?php echo get_the_ID(); ?>" class="rs-bulk-check"></th>
							<td><strong><a href="<?php echo get_edit_post_link(); ?>" target="_blank"><?php the_title(); ?></a></strong></td>
							<td id="status-<?php echo get_the_ID(); ?>"><?php echo $has_seo ? '<span class="status-badge status-done">انجام شده</span>' : '<span class="status-badge status-pending">در انتظار</span>'; ?></td>
						</tr>
					<?php endwhile; else : ?><tr><td colspan="3">پستی یافت نشد.</td></tr><?php endif; wp_reset_postdata(); ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function ajax_handle_bulk_generate() {
		@set_time_limit( 0 ); // Prevent timeout
		@ignore_user_abort( true );

		if ( ! check_ajax_referer( 'rs_nonce_action', 'nonce', false ) ) { wp_send_json_error( 'Invalid security token.' ); return; }
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); return; }

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$opts = isset( $_POST['options'] ) ? (array) $_POST['options'] : [];
		foreach ($opts as $key => $value) { $opts[$key] = ($value === 'true'); }

		if ( $post_id === 0 ) { wp_send_json_error( 'Invalid Post ID.' ); return; }
		if ( empty( self::$api ) ) { wp_send_json_error( 'API Error: AI Brain not initialized.' ); return; }

		$results = [];

		try {
			// --- SEO ---
			if ( ! empty( $opts['do_seo'] ) ) {
				if ( ! class_exists( 'ReadyStudio_Module_SEO' ) ) throw new Exception( 'ماژول SEO نصب نیست.' );
				$content = self::$data->get_content_for_analysis( $post_id );
				$task_prompt = ReadyStudio_Module_SEO::get_task_prompt( $opts );
				$response = self::$api->call_gemini_text( $task_prompt, $content, get_the_title($post_id) );

				if ( is_wp_error( $response ) ) throw new Exception( 'SEO: ' . $response->get_error_message() );
				
				self::$data->save_seo_meta( $post_id, $response ); // This updates Title
				if(isset($response['tags'])) self::$data->save_post_tags( $post_id, $response['tags'] );
				if ( ! empty( $opts['do_slug'] ) && isset($response['latin_name']) ) self::$data->save_prompt_cpt_data( $post_id, $response['latin_name'] );
				$results[] = 'SEO OK';
			}

			// --- Content ---
			if ( ! empty( $opts['do_content'] ) ) {
				if ( ! class_exists( 'ReadyStudio_Module_Content' ) ) throw new Exception( 'ماژول محتوا نصب نیست.' );
				$content = self::$data->get_content_for_analysis( $post_id );
				$task_prompt = ReadyStudio_Module_Content::get_task_prompt();
				$response = self::$api->call_gemini_text( $task_prompt, $content, get_the_title($post_id) );

				if ( is_wp_error( $response ) ) throw new Exception( 'Content: ' . $response->get_error_message() );

				if(isset($response['content_body'])) self::$data->save_post_content( $post_id, $response['content_body'] );
				if ( ! empty( $opts['do_alt'] ) && isset($response['image_alt']) ) self::$data->save_image_alt_text( $post_id, $response['image_alt'] );
				$results[] = 'Content OK';
			}
			
			// --- Alt Only (Vision) ---
			elseif ( ! empty( $opts['do_alt'] ) && class_exists( 'ReadyStudio_Module_Vision' ) ) {
				$task_prompt = ReadyStudio_Module_Vision::get_task_prompt();
				$image_data = ReadyStudio_Module_Vision::get_image_data( $post_id );

				if ( is_array( $image_data ) ) {
					$response = self::$api->call_gemini_vision( $task_prompt, $image_data['base64'], $image_data['mime_type'] );
					if ( is_wp_error( $response ) ) throw new Exception( 'Vision: ' . $response->get_error_message() );
					if(isset($response['alt_text'])) {
						self::$data->save_image_alt_text( $post_id, $response['alt_text'] );
						$results[] = 'Alt (Vision) OK';
					}
				} else {
					$results[] = 'Alt Skipped (No Image)';
				}
			}

		} catch ( Throwable $e ) {
			// Catch ANY PHP error (Fatal or Exception)
			wp_send_json_error( 'خطای پردازش: ' . $e->getMessage() . ' (خط ' . $e->getLine() . ')' );
			return;
		}

		if ( empty( $results ) ) { wp_send_json_error( 'هیچ عملیاتی انجام نشد.' ); return; }

		wp_send_json_success( implode( ', ', $results ) );
	}
}