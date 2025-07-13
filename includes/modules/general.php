<?php
// ماژول بهینه‌سازی‌های عمومی

// حذف CSS کتابخانه بلوک گوتنبرگ
if ( ReOptimize_Settings::get_option('remove_wp_block_library_css') ) {
    add_action( 'wp_enqueue_scripts', function(){
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );
        wp_dequeue_style( 'wc-blocks-style' );
    }, 100 );
}

// غیرفعال کردن ایموجی‌ها
if ( ReOptimize_Settings::get_option('disable_emojis') ) {
    add_action( 'init', function() {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
        remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
        remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
        add_filter( 'tiny_mce_plugins', function ( $plugins ) {
            if ( is_array( $plugins ) ) {
                return array_diff( $plugins, ['wpemoji'] );
            } else {
                return [];
            }
        });
        add_filter( 'wp_resource_hints', function ( $urls, $relation_type ) {
            if ( 'dns-prefetch' == $relation_type ) {
                $emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/15.0.3/svg/' );
                $urls = array_diff( $urls, [$emoji_svg_url] );
            }
            return $urls;
        }, 10, 2 );
    });
}
