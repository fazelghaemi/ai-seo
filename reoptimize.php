<?php
// FILE: reoptimize.php (فایل اصلی افزونه)
/**
 * Plugin Name:       ReOptimize
 * Plugin URI:        https://readystudio.ir/reoptimize
 * Description:       مجموعه‌ای از ابزارهای بهینه‌سازی و شخصی‌سازی وردپرس برای افزایش سرعت و کارایی سایت. محصولی از ردی استودیو.
 * Version:           1.0.0
 * Author:            ReadyStudio
 * Author URI:        https://readystudio.ir
 * Text Domain:       reoptimize
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

// جلوگیری از دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * کلاس اصلی افزونه ReOptimize
 * @final
 */
final class ReOptimize_Main {

    /**
     * ورژن افزونه
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * نمونه کلاس (Singleton)
     * @var ReOptimize_Main|null
     */
    private static ?self $instance = null;

    /**
     * سازنده کلاس
     */
    private function __construct() {
        $this->define_constants();
        $this->load_dependencies();
        $this->init_plugin();
    }

    /**
     * تعریف ثابت‌های افزونه
     */
    private function define_constants(): void {
        define( 'REOPTIMIZE_VERSION', self::VERSION );
        define( 'REOPTIMIZE_FILE', __FILE__ );
        define( 'REOPTIMIZE_PATH', plugin_dir_path( REOPTIMIZE_FILE ) );
        define( 'REOPTIMIZE_URL', plugin_dir_url( REOPTIMIZE_FILE ) );
    }

    /**
     * بارگذاری فایل‌های مورد نیاز
     */
    private function load_dependencies(): void {
        require_once REOPTIMIZE_PATH . 'includes/class-reoptimize-settings.php';
        require_once REOPTIMIZE_PATH . 'includes/class-reoptimize-admin.php';
        
        // بارگذاری ماژول‌ها
        ReOptimize_Settings::load_modules();
    }

    /**
     * راه‌اندازی افزونه
     */
    private function init_plugin(): void {
        ReOptimize_Admin::get_instance();
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
    }

    /**
     * بارگذاری فایل ترجمه
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'reoptimize', false, dirname( plugin_basename( REOPTIMIZE_FILE ) ) . '/languages' );
    }

    /**
     * متد Singleton برای دریافت نمونه کلاس
     * @return ReOptimize_Main
     */
    public static function get_instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

/**
 * اجرای افزونه
 * @return ReOptimize_Main
 */
function reoptimize_run(): ReOptimize_Main {
    return ReOptimize_Main::get_instance();
}

// استارت!
reoptimize_run();
