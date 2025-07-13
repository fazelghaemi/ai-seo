<?php
// ماژول امنیتی

// بستن Endpoints کاربران در REST API
if ( ReOptimize_Settings::get_option('close_user_rest_api') ) {
    add_filter('rest_endpoints', function( $endpoints ) {
        if ( isset( $endpoints['/wp/v2/users'] ) ) {
            unset( $endpoints['/wp/v2/users'] );
        }
        if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
            unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
        }
        return $endpoints;
    });
}

// محدودیت تلاش برای ورود
if ( ReOptimize_Settings::get_option('limit_login_attempts') ) {
    add_filter('authenticate', function ($user, $username, $password) {
        if (get_transient('limit_login_' . $username)) {
            return new WP_Error('too_many_attempts', 'تعداد تلاش‌های ورود بیش از حد. لطفاً بعداً دوباره تلاش کنید.');
        }
        return $user;
    }, 30, 3);

    add_action('wp_login_failed', function ($username) {
        $transient_name = 'limit_login_' . $username;
        $attempts = (int) get_transient('login_attempts_' . $username);
        $attempts++;

        if ($attempts >= 3) {
            set_transient($transient_name, true, 5 * MINUTE_IN_SECONDS); // 5 دقیقه
            delete_transient('login_attempts_' . $username);
        } else {
            set_transient('login_attempts_' . $username, $attempts, 5 * MINUTE_IN_SECONDS);
        }
    });

    add_action('wp_login', function ($user_login, $user) {
        delete_transient('login_attempts_' . $user_login);
    }, 10, 2);
}

