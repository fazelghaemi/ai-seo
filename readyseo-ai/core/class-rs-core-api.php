<?php
/**
 * Ready Studio SEO Engine - Core API (The Nexus Brain)
 *
 * v14.1: CRITICAL VISION FIX
 * - The `call_gemini_vision` function was hard-coding 'gemini-1.5-flash'.
 * - It is now updated to correctly read the new 'model_name_vision'
 * setting (e.g., 'gemini-2.5-flash-preview-09-2025') from the options.
 *
 * @package   ReadyStudio
 * @version   14.1.0
 * @author    Fazel Ghaemi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ReadyStudio_Core_API {

	/**
	 * Plugin options array (injected from Loader).
	 * @var array
	 */
	private $options;

	/**
	 * Cloudflare Worker URL.
	 * @var string
	 */
	private $worker_url;

	/**
	 * Gemini API Key.
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 * Sets up the API client with options.
	 *
	 * @param array $options The plugin's options.
	 */
	public function __construct( $options ) {
		$this->options = $options;
		
		// Load critical API settings from options
		$this->worker_url = isset( $options['worker_url'] ) ? $options['worker_url'] : '';
		$this->api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
	}

	/**
	 * Checks if the API is configured and ready to make calls.
	 *
	 * @return bool|WP_Error True if configured, WP_Error otherwise.
	 */
	private function is_configured() {
		if ( empty( $this->worker_url ) || empty( $this->api_key ) ) {
			return new WP_Error(
				'api_not_configured',
				'تنظیمات API (آدرس ورکر یا کلید API) در پنل Ready Studio ثبت نشده است.'
			);
		}
		return true;
	}

	/**
	 * Builds the shared "System Prompt" using the AI Brain settings.
	 *
	 * @return string The formatted system prompt.
	 */
	private function get_system_prompt_base() {
		$knowledge_base = isset( $this->options['site_knowledge_base'] ) ? $this->options['site_knowledge_base'] : '';
		$custom_prompt = isset( $this->options['custom_system_prompt'] ) ? $this->options['custom_system_prompt'] : '';

		$knowledge_injection = ! empty( $knowledge_base ) ? "--- KNOWLEDGE BASE (Use this context):\n{$knowledge_base}\n---" : "";
		$custom_rule_injection = ! empty( $custom_prompt ) ? "--- CUSTOM RULES (Must Follow):\n{$custom_prompt}\n---" : "";

		// This base prompt is prepended to all specific task prompts
		return "
        Act as an Expert SEO Specialist and Content Creator for readyprompt.ir.
        Your responses must be in Persian (فارسی).
        {$knowledge_injection}
        {$custom_rule_injection}
        ";
	}

	/**
	 * Main function for all TEXT-based generation (SEO, Content).
	 *
	 * @param string $task_prompt The specific instructions for this task (e.g., "Generate SEO meta...").
	 * @param string $content     The post content for analysis.
	 * @param string $title       The post title.
	 * @return array|WP_Error     The JSON-decoded response from AI or a WP_Error on failure.
	 */
	public function call_gemini_text( $task_prompt, $content, $title ) {
		$config_check = $this->is_configured();
		if ( is_wp_error( $config_check ) ) {
			return $config_check;
		}

		// Read the TEXT model from settings
		$model_name = isset( $this->options['model_name'] ) ? $this->options['model_name'] : 'gemini-2.0-flash';
		
		// 1. Build the full prompt
		$system_base = $this->get_system_prompt_base();
		$clean_content = mb_substr( wp_strip_all_tags( $content ), 0, 4000 ); // Limit token usage

		$full_user_prompt = "
        {$system_base}
        
        --- CONTENT TO ANALYZE ---
        Title: {$title}
        Body: {$clean_content}

        --- TASK ---
        {$task_prompt}
        ";

		// 2. Prepare payload for the *worker* (Text format)
		$payload = [
			'action_type'  => 'text', // Tell worker this is a text job
			'api_key'      => $this->api_key,
			'model_name'   => $model_name,
			'contents'     => [
				[
					'role'  => 'user',
					'parts' => [ [ 'text' => $full_user_prompt ] ],
				],
			],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
			],
		];

		// 3. Send to Cloudflare Worker
		$response = $this->send_request( $this->worker_url, $payload, 60 ); // 60s timeout

		// 4. Handle response
		if ( is_wp_error( $response ) ) {
			return $response; // Return WP_Error (e.g., "Worker Error: 500")
		}

		// 5. Parse the AI's JSON response
		if ( isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$json_data = json_decode( $response['candidates'][0]['content']['parts'][0]['text'], true );
			
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $json_data; // Success! Return the associative array
			} else {
				// AI returned malformed JSON
				return new WP_Error( 'ai_json_error', 'پاسخ نامعتبر JSON از هوش مصنوعی. AI Response: ' . $response['candidates'][0]['content']['parts'][0]['text'] );
			}
		} else {
			// AI returned an error (e.g., safety settings, 404)
			$error_message = isset( $response['error']['message'] ) ? $response['error']['message'] : 'خطای ناشناخته از Gemini API';
			return new WP_Error( 'ai_api_error', 'AI API Error: ' . $error_message );
		}
	}

	/**
	 * Main function for all IMAGE-based analysis (Vision).
	 *
	 * @param string $task_prompt The specific instructions for this vision task.
	 * @param string $image_base64 Base64 encoded image data.
	 * @param string $mime_type    The MIME type of the image (e.g., 'image/jpeg').
	 * @return array|WP_Error     The JSON-decoded response from AI or a WP_Error on failure.
	 */
	public function call_gemini_vision( $task_prompt, $image_base64, $mime_type ) {
		$config_check = $this->is_configured();
		if ( is_wp_error( $config_check ) ) {
			return $config_check;
		}

		// --- *** CRITICAL FIX (v14.1) *** ---
		// Read the *VISION* model from the new settings dropdown.
		// Fallback to the new 2.5 flash model if not set.
		$model_name = isset( $this->options['model_name_vision'] ) 
			? $this->options['model_name_vision'] 
			: 'gemini-2.5-flash-preview-09-2025';
		// --- End Fix ---

		// 1. Prepare payload for the *worker* (Vision format)
		$payload = [
			'action_type'   => 'vision', // Tell worker this is a VISION job
			'api_key'       => $this->api_key,
			'model_name'    => $model_name, // Use the correct vision model
			'system_prompt' => $task_prompt, // The prompt is sent separately in this format
			'image_data'    => $image_base64,
			'mime_type'     => $mime_type,
		];

		// 2. Send to Cloudflare Worker
		// Vision tasks can take longer, so we use a 90-second timeout.
		$response = $this->send_request( $this->worker_url, $payload, 90 );

		// 3. Handle response
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// 4. Parse the AI's JSON response
		if ( isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$json_data = json_decode( $response['candidates'][0]['content']['parts'][0]['text'], true );
			
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $json_data; // Success!
			} else {
				return new WP_Error( 'ai_json_error', 'پاسخ نامعتبر JSON از هوش مصنوعی.' );
			}
		} else {
			$error_message = isset( $response['error']['message'] ) ? $response['error']['message'] : 'خطای ناشناخته از Gemini API';
			return new WP_Error( 'ai_api_error', 'AI API Error: ' . $error_message );
		}
	}

	/**
	 * Public function to test the connection using *unsaved* credentials.
	 *
	 * @param string $worker_url The Worker URL from the form.
	 * @param string $api_key    The API Key from the form.
	 * @return array|WP_Error    Success message or WP_Error.
	 */
	public function test_connection( $worker_url, $api_key ) {
		if ( empty( $worker_url ) || empty( $api_key ) ) {
			return new WP_Error( 'missing_credentials', 'لطفا آدرس ورکر و کلید API را وارد کنید.' );
		}
		
		$model_name = 'gemini-2.0-flash'; // Use flash for a fast test
		
		// 1. Build a simple test prompt
		$system_prompt = "--- TASK ---
        You are a connection test.
        Respond ONLY with the following JSON (no markdown):
        { \"message\": \"Test Successful\" }
        ";

		// 2. Prepare payload
		$payload = [
			'action_type'  => 'text',
			'api_key'      => $api_key,
			'model_name'   => $model_name,
			'contents'     => [
				[ 'role' => 'user', 'parts' => [ [ 'text' => $system_prompt ] ] ],
			],
			'generationConfig' => [ 'responseMimeType' => 'application/json' ],
		];

		// 3. Send to Cloudflare Worker (using provided, unsaved URL)
		// Use a short timeout for the test
		$response = $this->send_request( $worker_url, $payload, 20 ); // 20s timeout

		// 4. Handle response
		if ( is_wp_error( $response ) ) {
			return $response; // Return WP_Error (e.g., "Worker Error: 500")
		}

		// 5. Parse the AI's JSON response
		if ( isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$json_data = json_decode( $response['candidates'][0]['content']['parts'][0]['text'], true );
			
			if ( isset( $json_data['message'] ) && $json_data['message'] === 'Test Successful' ) {
				return [ 'message' => 'ارتباط موفقیت‌آمیز بود!' ]; // Success!
			} else {
				return new WP_Error( 'ai_test_failed', 'پاسخ تست نامعتبر بود.' );
			}
		} else {
			$error_message = isset( $response['error']['message'] ) ? $response['error']['message'] : 'خطای ناشناخته در تست API';
			return new WP_Error( 'ai_api_error', 'AI API Error: ' . $error_message );
		}
	}

	/**
	 * A private helper function to send wp_remote_post requests.
	 *
	 * @param string $url     The URL to post to (our Worker).
	 * @param array  $payload The body of the request.
	 * @param int    $timeout Timeout in seconds.
	 * @return array|WP_Error Decoded JSON response or WP_Error.
	 */
	private function send_request( $url, $payload, $timeout = 60 ) {
		$args = [
			'body'        => json_encode( $payload ),
			'headers'     => [ 'Content-Type' => 'application/json' ],
			'timeout'     => $timeout,
			'sslverify'   => false, // Often needed for local/dev, but can be true in production
			'data_format' => 'body',
		];

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			// Network-level error (e.g., cURL error 7, 28)
			return new WP_Error( 'worker_network_error', 'Worker Error: ' . $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $data === null ) {
			// The worker returned non-JSON or empty response
			return new WP_Error( 'worker_response_error', 'پاسخ نامعتبر از ورکر دریافت شد.' );
		}
		
		// Check for the standardized error object
		if ( isset( $data['error']['message'] ) ) {
			// This is a standardized error from our worker OR Gemini
			return new WP_Error( 'worker_internal_error', 'Error: ' . $data['error']['message'] );
		}

		// Success! Return the full, decoded response from Gemini
		return $data;
	}

} // End class ReadyStudio_Core_API