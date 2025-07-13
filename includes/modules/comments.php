<?php
// ماژول دیدگاه‌ها

// محدودیت طول دیدگاه
if ( ReOptimize_Settings::get_option('limit_comment_length_enable') ) {
    add_filter( 'preprocess_comment', function( $comment ) {
        $length = mb_strlen( trim( $comment['comment_content'] ), 'UTF-8' );
        $min_length = 50;
        $max_length = 6000;

        if ( $length > $max_length ) {
            wp_die( 'دیدگاه خیلی طولانی است. حداکثر ' . $max_length . ' کاراکتر مجاز است.' );
        }
        if ( $length < $min_length ) {
            wp_die( 'دیدگاه خیلی کوتاه است. حداقل ' . $min_length . ' کاراکتر باید باشد.' );
        }
        return $comment;
    });
}

// حذف فیلد وب‌سایت از فرم دیدگاه
if ( ReOptimize_Settings::get_option('remove_comment_url_field') ) {
    add_filter('comment_form_default_fields', function( $fields ) {
        unset( $fields['url'] );
        return $fields;
    });
}
