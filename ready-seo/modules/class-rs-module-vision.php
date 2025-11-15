<?php
/**
 * Module: Visual Tagger & Style Analyzer
 *
 * این ماژول به هسته اصلی متصل شده و قابلیت تحلیل بصری (Gemini Vision)
 * را به افزونه اضافه می‌کند.
 *
 * @version 11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ReadyStudio_Module_Vision {

	/** @var array هسته تنظیمات افزونه */
	private $options;

	/**
	 * Constructor.
	 * قلاب‌های این ماژول را ثبت می‌کند.
	 */
	public function __construct() {
		// دریافت تنظیمات ذخیره شده از هسته
		$this->options = get_option( 'promptseo_ultimate_options' );

		// 1. اضافه کردن CSS اختصاصی این ماژول
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_module_assets' ] );

		// 2. اضافه کردن تب جدید به متاباکس
		add_action( 'rs_metabox_tabs', [ $this, 'add_vision_tab' ] );

		// 3. رندر کردن محتوای تب
		add_action( 'rs_metabox_content', [ $this, 'render_vision_tab_content' ] );

		// 4. ثبت AJAX endpoint
		add_action( 'wp_ajax_pseo_generate_vision', [ $this, 'handle_ajax_generate_vision' ] );
	}

	/**
	 * 1. بارگذاری فایل CSS اختصاصی این ماژول
	 */
	public function enqueue_module_assets( $hook ) {
		if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
			wp_enqueue_style(
				'rs-module-vision-style',
				plugin_dir_url( __FILE__ ) . '../assets/css/vision.css', // مسیر فایل CSS
				[], // وابستگی‌ها
				'11.0' // ورژن
			);
		}
	}

	/**
	 * 2. اضافه کردن تب "تحلیل بصری" به لیست تب‌ها
	 */
	public function add_vision_tab( $tabs ) {
		$tabs['tab-vision'] = 'تحلیل بصری';
		return $tabs;
	}

	/**
	 * 3. رندر کردن HTML داخل تب "تحلیل بصری"
	 */
	public function render_vision_tab_content( $active_tab ) {
		global $post;
		$thumbnail_url = get_the_post_thumbnail_url( $post->ID, 'medium' );
		?>
		<div id="tab-vision" class="rs-tab-content <?php echo $active_tab === 'tab-vision' ? 'active' : ''; ?>">
			
			<div class="rs-vision-panel">
				<!-- نمایش تصویر شاخص -->
				<div class="rs-vision-preview">
					<?php if ( $thumbnail_url ) : ?>
						<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="Preview">
					<?php else : ?>
						<div class="rs-vision-preview-placeholder">
							<span class="dashicons dashicons-format-image"></span>
							<br>
							ابتدا یک تصویر شاخص تنظیم کنید
						</div>
					<?php endif; ?>
				</div>

				<button type="button" id="btn-gen-vision" class="pseo-btn btn-vision" <?php echo $thumbnail_url ? '' : 'disabled'; ?>>
					<span class="dashicons dashicons-visibility"></span>
					شروع تحلیل بصری (Vision)
				</button>
				
				<div id="load-vision" class="spinner-rs" style="display:none;"></div>

				<!-- نتایج -->
				<div id="area-vision-results" style="display:none;">
					<div class="pseo-field style-field">
						<label for="in-art-style">سبک هنری (از تصویر)</label>
						<input type="text" id="in-art-style">
					</div>
					<div class="pseo-field visual-tags-field">
						<label for="in-visual-tags">تگ‌های بصری (از تصویر)</label>
						<input type="text" id="in-visual-tags">
					</div>
					<div class="pseo-field">
						<label for="in-alt-vision">متن Alt پیشنهادی (از تصویر)</label>
						<input type="text" id="in-alt-vision">
					</div>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * 4. مدیریت درخواست AJAX برای تحلیل بصری
	 */
	public function handle_ajax_generate_vision() {
		check_ajax_referer( 'rs_nonce_action', 'nonce' );
		$post_id = intval( $_POST['post_id'] );
		
		if ( ! $post_id ) {
			wp_send_json_error( 'ID پست نامعتبر است.' );
			return;
		}
		
		// فراخوانی مغز هوش مصنوعی (Vision)
		$this->call_gemini_vision( $post_id );
	}

	/**
	 * 5. مغز هوش مصنوعی (Vision)
	 * این تابع تصویر را به Gemini Vision ارسال می‌کند.
	 */
	private function call_gemini_vision( $post_id ) {
		$opts = $this->options;

		// --- 1. دریافت تصویر (Encode Base64) ---
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumbnail_id ) {
			wp_send_json_error( 'تصویر شاخص یافت نشد.' );
			return;
		}
		
		$image_path = get_attached_file( $thumbnail_id );
		if ( ! $image_path || ! file_exists( $image_path ) ) {
			wp_send_json_error( 'فایل تصویر شاخص در سرور یافت نشد.' );
			return;
		}

		$image_data = base64_encode( file_get_contents( $image_path ) );
		$mime_type = get_post_mime_type( $thumbnail_id );

		// --- 2. ساخت پرامپت (Vision) ---
		$system_prompt = "
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

		// --- 3. آماده‌سازی Payload برای ورکر ---
		$worker_url = isset( $opts['worker_url'] ) ? $opts['worker_url'] : '';
		$api_key = isset( $opts['api_key'] ) ? $opts['api_key'] : '';
		// مدل‌های Vision-capable
		$model_name = 'gemini-1.5-flash'; // یا 'gemini-pro-vision'

		if ( empty( $worker_url ) || empty( $api_key ) ) {
			wp_send_json_error( 'تنظیمات API کامل نیست.' );
			return;
		}

		$payload = [
			'action_type' => 'vision', // <-- این به ورکر می‌گوید که با تصویر کار کند
			'api_key' => $api_key,
			'model_name' => $model_name,
			'system_prompt' => $system_prompt,
			'image_data' => $image_data,
			'mime_type' => $mime_type
		];

		// --- 4. ارسال به ورکر ---
		$response = wp_remote_post( $worker_url, [
			'body' => json_encode( $payload ),
			'headers' => [ 'Content-Type' => 'application/json' ],
			'timeout' => 90, // Vision-models might take longer
			'sslverify' => false
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Worker Error: ' . $response->get_error_message() );
			return;
		}
		
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		
		if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$json_data = json_decode( $body['candidates'][0]['content']['parts'][0]['text'], true );
			
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wp_send_json_error( 'AI returned invalid JSON.' );
				return;
			}
			wp_send_json_success( $json_data );

		} else {
			$error_message = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown AI Error';
			wp_send_json_error( 'AI API Error: ' . $error_message );
		}
	}

} // پایان کلاس ReadyStudio_Module_Vision

// --- این ماژول را به هسته متصل کن ---
// هسته افزونه (ReadyStudio_Core_V12) این فایل را include می‌کند
// و این خط، ماژول را فعال می‌سازد.
new ReadyStudio_Module_Vision();
?>