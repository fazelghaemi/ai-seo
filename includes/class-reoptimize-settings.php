<?php
/**
 * کلاس مدیریت تنظیمات و ماژول‌ها
 */
class ReOptimize_Settings {

    /**
     * دریافت یک گزینه از تنظیمات
     * @param string $option نام گزینه
     * @param mixed $default مقدار پیش‌فرض
     * @return mixed
     */
    public static function get_option( string $option, $default = false ) {
        $options = get_option( 'reoptimize_settings', [] );
        return isset( $options[$option] ) ? $options[$option] : $default;
    }

    /**
     * بارگذاری تمام ماژول‌های فعال
     */
    public static function load_modules() {
        $modules_dir = REOPTIMIZE_PATH . 'includes/modules/';
        $all_modules = [
            'general.php',
            'security.php',
            'woocommerce.php',
            'comments.php',
            'appearance.php',
        ];

        foreach ($all_modules as $module_file) {
            if (file_exists($modules_dir . $module_file)) {
                require_once $modules_dir . $module_file;
            }
        }
    }
}
