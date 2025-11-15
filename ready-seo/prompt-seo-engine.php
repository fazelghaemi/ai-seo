<?php
/**
 * Plugin Name:       AI SEO
 * Plugin URI:        https://github.com/fazelghaemi/ai-seo
 * Description:       The ultimate AI-powered SEO & Content Engine for WordPress, featuring a modular architecture, AI Brain, and direct integration with Google Gemini via Cloudflare Workers. A Ready Studio product.
 * Version:           12.1.0
 * Author:            Ready Studio (Fazel Ghaemi)
 * Author URI:        https://readystudio.ir
 * Requires PHP:      7.4
 * Text Domain:       ai-seo
 * Domain Path:       /lang
 *
 * @package           AISEO
 * @author            Fazel Ghaemi
 * @copyright         2024 Ready Studio
 */

// Security Check: Prevent direct access
defined( 'ABSPATH' ) or die( 'Unauthorized Access.' );

/**
 * Version 12.1.0 (Branding Update)
 *
 * This file is the main plugin bootloader.
 * Its *only* responsibility is to define constants,
 * load the Core Loader, and instantiate the plugin.
 */

// --- 1. Define Core Constants ---

if ( ! defined( 'RS_SEO_VERSION' ) ) {
	/**
	 * Plugin version.
	 */
	define( 'RS_SEO_VERSION', '12.1.0' );
}

if ( ! defined( 'RS_SEO_PATH' ) ) {
	/**
	 * The absolute file path to the plugin's root directory.
	 * Example: /var/www/html/wp-content/plugins/ready-seo/
	 */
	define( 'RS_SEO_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'RS_SEO_URL' ) ) {
	/**
	 * The URL to the plugin's root directory.
	 * Example: http://example.com/wp-content/plugins/ready-seo/
	 */
	define( 'RS_SEO_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'RS_SEO_CORE_PATH' ) ) {
	/**
	 * Path to the core classes directory.
	 */
	define( 'RS_SEO_CORE_PATH', RS_SEO_PATH . 'core/' );
}

if ( ! defined( 'RS_SEO_MODULES_PATH' ) ) {
	/**
	 * Path to the modules directory.
	 */
	define( 'RS_SEO_MODULES_PATH', RS_SEO_PATH . 'modules/' );
}

// --- 2. Load the Core Loader ---

// Check if the Core Loader class file exists before trying to include it.
$core_loader_file = RS_SEO_CORE_PATH . 'class-rs-core-loader.php';

if ( ! file_exists( $core_loader_file ) ) {
	// Show a fatal error in admin if the core loader is missing.
	if ( is_admin() ) {
		add_action( 'admin_notices', function() {
			echo '<div class="error"><p>';
			echo '<strong>خطای فاجعه‌بار افزونه AI SEO:</strong> فایل بارگذار هسته (Core Loader) یافت نشد.';
			echo '<br><code>' . esc_html( $core_loader_file ) . '</code>';
			echo '</p></div>';
		});
	}
	return; // Stop execution.
}

// Load the Core Loader
require_once $core_loader_file;

// --- 3. Initialize the Plugin ---

/**
 * The main function to run the plugin.
 * This function fires on the 'plugins_loaded' hook and instantiates the singleton Core Loader.
 */
function rs_seo_run_plugin() {
	// Get the singleton instance of the Core Loader
	ReadyStudio_Core_Loader::get_instance();
}

// Hook to 'plugins_loaded' to ensure all WP functions are available.
add_action( 'plugins_loaded', 'rs_seo_run_plugin' );

// --- 4. Activation/Deactivation Hooks ---

/**
 * (Optional) Runs on plugin deactivation.
 * Can be used for cleanup.
 */
register_deactivation_hook( __FILE__, function() {
	// Example: flush rewrite rules or remove scheduled tasks
	flush_rewrite_rules();
} );

/**
 * (Optional) Runs on plugin activation.
 */
register_activation_hook( __FILE__, function() {
	// Example: set default options
	if ( get_option( 'promptseo_ultimate_options' ) === false ) {
		// Set default options
		$default_options = [
			'model_name' => 'gemini-2.0-flash',
			//... other defaults
		];
		update_option( 'promptseo_ultimate_options', $default_options );
	}
	flush_rewrite_rules();
} );

// --- End of Bootloader ---