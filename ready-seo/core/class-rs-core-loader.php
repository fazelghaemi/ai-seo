<?php
/**
 * Ready Studio SEO Engine - Core Loader
 *
 * v12.3: CRITICAL FIX - Injects the Core API into the Core Settings
 * class constructor to prevent an ArgumentCountError (Fatal Error).
 *
 * @package   ReadyStudio
 * @version   12.3.0
 * @author    Fazel Ghaemi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Final class ReadyStudio_Core_Loader.
 *
 * The main plugin singleton. This class loads all components,
 * instantiates them, and injects dependencies.
 */
final class ReadyStudio_Core_Loader {

	/**
	 * Singleton instance.
	 * @var ReadyStudio_Core_Loader|null
	 */
	private static $instance = null;

	/**
	 * Plugin options.
	 * @var array
	 */
	public $options = [];

	/**
	 * Core API instance (Nexus Brain).
	 * @var ReadyStudio_Core_API
	 */
	public $api;

	/**
	 * Core Data Helper instance.
	 * @var ReadyStudio_Core_Data
	 */
	public $data;

	/**
	 * Core Metabox Shell instance.
	 * @var ReadyStudio_Core_Metabox
	 */
	public $metabox;

	/**
	 * Singleton Instance Accessor.
	 *
	 * @return ReadyStudio_Core_Loader
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private Constructor.
	 * Initializes the plugin by loading components and firing hooks.
	 */
	private function __construct() {
		// 1. Load plugin options immediately
		$this->options = get_option( 'promptseo_ultimate_options', [] );

		// 2. Load Core class files (order is important)
		$this->load_core_files();

		// 3. Instantiate Core components (Dependency Injection)
		$this->init_core_components();
		
		// 4. Load all Modules
		$this->load_modules();

		// 5. Register common hooks
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_core_assets' ] );
	}

	/**
	 * 2. Loads all necessary core files from the /core/ directory.
	 */
	private function load_core_files() {
		$core_files = [
			// Helpers (loaded first)
			'class-rs-core-data.php',      // Data saving helper
			'class-rs-core-api.php',       // The AI Brain (Nexus)

			// Core Features
			'class-rs-core-settings.php',  // Admin settings pages
			'class-rs-core-bulk.php',      // Bulk generator page
			'class-rs-core-metabox.php',   // Metabox shell (Crucial for modules)
		];

		foreach ( $core_files as $file ) {
			$path = RS_SEO_CORE_PATH . $file;
			if ( file_exists( $path ) ) {
				include_once $path;
			} else {
				// Handle missing core file (critical error)
				$this->admin_critical_error( 'فایل هسته ضروری یافت نشد: ' . $file );
			}
		}
	}

	/**
	 * 3. Instantiates the core components and injects dependencies.
	 *
	 * *** THIS FUNCTION CONTAINS THE FIX ***
	 */
	private function init_core_components() {
		// Make API and Data helpers available globally via the loader instance
		if ( class_exists( 'ReadyStudio_Core_Data' ) ) {
			$this->data = new ReadyStudio_Core_Data();
		}
		if ( class_exists( 'ReadyStudio_Core_API' ) ) {
			$this->api = new ReadyStudio_Core_API( $this->options );
		}
		
		// Initialize other core components
		if ( class_exists( 'ReadyStudio_Core_Settings' ) ) {
			// *** FATAL ERROR FIX ***
			// The constructor for Settings (v12.3) now requires
			// both $options AND $api (for the test button).
			// We must pass both dependencies.
			new ReadyStudio_Core_Settings( $this->options, $this->api );
		}
		if ( class_exists( 'ReadyStudio_Core_Bulk' ) ) {
			// Bulk page needs API and Data helpers
			new ReadyStudio_Core_Bulk( $this->api, $this->data );
		}
		if ( class_exists( 'ReadyStudio_Core_Metabox' ) ) {
			// Metabox shell needs Data helper (for the save button)
			$this->metabox = new ReadyStudio_Core_Metabox( $this->data );
		}
	}

	/**
	 * 4. Scans the /modules/ directory and loads all valid modules.
	 *
	 * A valid module is a .php file that starts with 'class-rs-module-'.
	 */
	private function load_modules() {
		// Use glob to find all module files
		foreach ( glob( RS_SEO_MODULES_PATH . 'class-rs-module-*.php' ) as $module_file ) {
			include_once $module_file;
		}

		/**
		 * Fires after all core components are instantiated.
		 * Modules must hook here to be instantiated.
		 *
		 * This allows modules to hook into the Metabox shell
		 * and use the Core API.
		 */
		do_action( 'rs_core_loaded', $this );
	}
	
	/**
	 * 5. Enqueues the *core* admin assets (JS & CSS).
	 * Module assets are enqueued by the modules themselves.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_core_assets( $hook ) {
		// --- A. Load on Plugin Admin Pages (Settings & Bulk) ---
		// `strpos` checks if the hook *contains* 'promptseo_'
		if ( strpos( $hook, 'promptseo_' ) !== false ) {
			
			// 1. Core styles for admin pages (Settings, Bulk)
			wp_enqueue_style(
				'rs-core-style',
				RS_SEO_URL . 'assets/css/style-core.css',
				[], // No dependencies
				RS_SEO_VERSION
			);
			
			// 2. Core JS for admin pages (Settings tabs, Bulk logic, Test Button)
			wp_enqueue_script(
				'rs-admin-core-js',
				RS_SEO_URL . 'assets/js/admin-core.js',
				[ 'jquery' ], // Dependency
				RS_SEO_VERSION,
				true // Load in footer
			);
		}
		
		// --- B. Load on Post Edit Screens (post.php, post-new.php) ---
		if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
			
			// 1. Core (global) styles. Must load first for CSS variables.
			wp_enqueue_style(
				'rs-core-style',
				RS_SEO_URL . 'assets/css/style-core.css',
				[],
				RS_SEO_VERSION
			);
			
			// 2. Base styles for the metabox shell and tabs
			wp_enqueue_style(
				'rs-metabox-style',
				RS_SEO_URL . 'assets/css/style-metabox.css',
				[ 'rs-core-style' ], // Inherit variables from core style
				RS_SEO_VERSION
			);
			
			// 3. Core JS for the metabox (Tab switching, AJAX handling)
			wp_enqueue_script(
				'rs-metabox-core-js',
				RS_SEO_URL . 'assets/js/metabox-core.js',
				[ 'jquery' ],
				RS_SEO_VERSION,
				true // Load in footer
			);
		}
	}

	/**
	 * Displays a critical admin error notice if a core file is missing.
	 *
	 * @param string $message The error message to display.
	 */
	private function admin_critical_error( $message ) {
		add_action( 'admin_notices', function() use ( $message ) {
			echo '<div class="error"><p>';
			echo '<strong>خطای فاجعه‌بار افزونه AI SEO:</strong><br>';
			echo esc_html( $message );
			echo '</p></div>';
		});
	}

} // End final class ReadyStudio_Core_Loader