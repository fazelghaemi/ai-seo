<?php

/**
 * کلاس مدیریت پنل تنظیمات افزونه
 */
final class ReOptimize_Admin {

    private static ?self $instance = null;

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'create_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }
    
    /**
     * افزودن اسکریپت و استایل به صفحه تنظیمات
     */
    public function enqueue_assets( $hook ): void {
        if ( 'toplevel_page_reoptimize-settings' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'reoptimize-admin-styles', REOPTIMIZE_URL . 'assets/css/admin-styles.css', [], REOPTIMIZE_VERSION );
        wp_enqueue_script( 'reoptimize-admin-scripts', REOPTIMIZE_URL . 'assets/js/admin-scripts.js', ['jquery'], REOPTIMIZE_VERSION, true );
    }

    /**
     * ایجاد منوی افزونه
     */
    public function create_admin_menu(): void {
        add_menu_page(
            __( 'ReOptimize', 'reoptimize' ),
            __( 'ReOptimize', 'reoptimize' ),
            'manage_options',
            'reoptimize-settings',
            [ $this, 'render_settings_page' ],
            'data:image/svg+xml;base64,' . base64_encode('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" fill="white"/></svg>'),
            81
        );
    }

    /**
     * ثبت تنظیمات با WordPress Settings API
     */
    public function register_settings(): void {
        register_setting( 'reoptimize_settings_group', 'reoptimize_settings' );

        // بخش بهینه‌سازی عمومی
        add_settings_section( 'reoptimize_general_section', __( 'بهینه‌سازی‌های عمومی', 'reoptimize' ), null, 'reoptimize-settings' );
        add_settings_field( 'remove_wp_block_library_css', __( 'حذف CSS کتابخانه بلوک', 'reoptimize' ), [ $this, 'render_checkbox' ], 'reoptimize-settings', 'reoptimize_general_section', ['id' => 'remove_wp_block_library_css', 'desc' => 'فایل‌های استایل گوتنبرگ را از فرانت‌اند حذف می‌کند.'] );
        add_settings_field( 'disable_emojis', __( 'غیرفعال کردن ایموجی‌ها', 'reoptimize' ), [ $this, 'render_checkbox' ], 'reoptimize-settings', 'reoptimize_general_section', ['id' => 'disable_emojis', 'desc' => 'اسکریپت‌های مربوط به ایموجی‌های وردپرس را برای افزایش سرعت حذف می‌کند.'] );

        // بخش امنیتی
        add_settings_section( 'reoptimize_security_section', __( 'تنظیمات امنیتی', 'reoptimize' ), null, 'reoptimize-settings' );
        add_settings_field( 'close_user_rest_api', __( 'بستن REST API کاربران', 'reoptimize' ), [ $this, 'render_checkbox' ], 'reoptimize-settings', 'reoptimize_security_section', ['id' => 'close_user_rest_api', 'desc' => 'برای جلوگیری از شناسایی نام‌های کاربری، دسترسی به لیست کاربران از طریق REST API را مسدود می‌کند.'] );
        add_settings_field( 'limit_login_attempts', __( 'محدودیت تلاش برای ورود', 'reoptimize' ), [ $this, 'render_checkbox' ], 'reoptimize-settings', 'reoptimize_security_section', ['id' => 'limit_login_attempts', 'desc' => 'پس از 3 بار تلاش ناموفق، ورود کاربر را برای 5 دقیقه مسدود می‌کند.'] );
        
        // بخش ووکامرس
        add_settings_section( 'reoptimize_woocommerce_section', __( 'بهینه‌سازی ووکامرس', 'reoptimize' ), null, 'reoptimize-settings' );
        add_settings_field( 'gift_wrap_enable', __( 'فعال‌سازی بسته‌بندی هدیه', 'reoptimize' ), [ $this, 'render_checkbox' ], 'reoptimize-settings', 'reoptimize_woocommerce_section', ['id' => 'gift_wrap_enable', 'desc' => 'گزینه کادوپیچ را به صفحه پرداخت اضافه می‌کند.'] );
        add_settings_field( 'gift_wrap_fee', __( 'هزینه بسته‌بندی هدیه', 'reoptimize' ), [ $this, 'render_text_input' ], 'reoptimize-settings', 'reoptimize_woocommerce_section', ['id' => 'gift_wrap_fee', 'desc' => 'هزینه را به تومان وارد کنید. مثال: 20000'] );
        
        // بخش دیدگاه‌ها
        add_settings_section( 'reoptimize_comments_section', __( 'مدیریت دیدگاه‌ها', 'reoptimize' ), null, 'reoptimize-settings' );
        add_settings_field( 'limit_comment_length_enable', __( 'محدودیت طول دیدگاه', 'reoptimize' ), [ $this, 'render_checkbox' ], 'reoptimize-settings', 'reoptimize_comments_section', ['id' => 'limit_comment_length_enable'] );
        add_settings_field( 'remove_comment_url_field', __( 'حذف فیلد وب‌سایت', 'reoptimize' ), [ $this, 'render_checkbox' ], 'reoptimize-settings', 'reoptimize_comments_section', ['id' => 'remove_comment_url_field', 'desc' => 'فیلد آدرس وب‌سایت را از فرم دیدگاه‌ها حذف می‌کند.'] );

        // ... سایر بخش‌ها و فیلدها به همین ترتیب اضافه می‌شوند
    }

    /**
     * رندر کردن یک فیلد چک‌باکس
     */
    public function render_checkbox( array $args ): void {
        $option = ReOptimize_Settings::get_option( $args['id'] );
        echo '<label for="' . esc_attr($args['id']) . '">';
        echo '<input type="checkbox" id="' . esc_attr($args['id']) . '" name="reoptimize_settings[' . esc_attr($args['id']) . ']" value="1" ' . checked( 1, $option, false ) . '/>';
        if (isset($args['desc'])) {
            echo ' ' . esc_html($args['desc']);
        }
        echo '</label>';
    }

    /**
     * رندر کردن یک فیلد متنی
     */
    public function render_text_input( array $args ): void {
        $option = ReOptimize_Settings::get_option( $args['id'] );
        echo '<input type="text" id="' . esc_attr($args['id']) . '" name="reoptimize_settings[' . esc_attr($args['id']) . ']" value="' . esc_attr( $option ) . '" class="regular-text" />';
        if (isset($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
    }

    /**
     * رندر کردن صفحه تنظیمات
     */
    public function render_settings_page(): void {
        ?>
        <div class="wrap reoptimize-wrap">
            <div class="reoptimize-header">
                <div class="reoptimize-logo">
                    <img src="<?php echo REOPTIMIZE_URL . 'assets/images/reoptimize-logo.png'; ?>" alt="ReOptimize Logo">
                    <h1><?php esc_html_e( 'تنظیمات ReOptimize', 'reoptimize' ); ?></h1>
                </div>
                <p><?php esc_html_e( 'محصولی از ردی استودیو', 'reoptimize' ); ?></p>
            </div>

            <div class="reoptimize-content">
                <nav class="reoptimize-nav">
                    <ul>
                        <li><a href="#tab-general" class="nav-tab nav-tab-active"><?php _e('عمومی', 'reoptimize'); ?></a></li>
                        <li><a href="#tab-security" class="nav-tab"><?php _e('امنیتی', 'reoptimize'); ?></a></li>
                        <li><a href="#tab-woocommerce" class="nav-tab"><?php _e('ووکامرس', 'reoptimize'); ?></a></li>
                        <li><a href="#tab-comments" class="nav-tab"><?php _e('دیدگاه‌ها', 'reoptimize'); ?></a></li>
                        <!-- More tabs here -->
                    </ul>
                </nav>
                <div class="reoptimize-tabs">
                    <form method="post" action="options.php">
                        <?php settings_fields( 'reoptimize_settings_group' ); ?>
                        
                        <div id="tab-general" class="tab-content active">
                            <?php do_settings_sections( 'reoptimize_general_section' ); ?>
                        </div>
                        <div id="tab-security" class="tab-content">
                             <?php do_settings_sections( 'reoptimize_security_section' ); ?>
                        </div>
                        <div id="tab-woocommerce" class="tab-content">
                            <?php do_settings_sections( 'reoptimize_woocommerce_section' ); ?>
                        </div>
                        <div id="tab-comments" class="tab-content">
                            <?php do_settings_sections( 'reoptimize_comments_section' ); ?>
                        </div>

                        <?php submit_button(); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public static function get_instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
