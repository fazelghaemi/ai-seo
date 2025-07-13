<?php
// ماژول ووکامرس

// افزودن گزینه بسته‌بندی هدیه
if ( ReOptimize_Settings::get_option('gift_wrap_enable') ) {
    add_action('woocommerce_review_order_before_submit', function() {
        $fee = ReOptimize_Settings::get_option('gift_wrap_fee', '20000');
        echo '<div id="gift_wrap_checkbox" style="margin-bottom: 1em;">
            <label>
                <input type="checkbox" name="gift_wrap" value="yes"> مایل هستید محصول به صورت کادویی ارسال شود؟ (' . wc_price($fee) . ')
            </label>
        </div>';
    });

    add_action( 'wp_ajax_update_order_review', function() {
        if (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $post_data);
            if (isset($post_data['gift_wrap'])) {
                WC()->session->set('gift_wrap', true);
            } else {
                WC()->session->set('gift_wrap', false);
            }
        }
    });

    add_action('woocommerce_cart_calculate_fees', function() {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (WC()->session->get('gift_wrap')) {
            $fee = ReOptimize_Settings::get_option('gift_wrap_fee', '20000');
            WC()->cart->add_fee(__('بسته‌بندی هدیه', 'reoptimize'), $fee);
        }
    });

    add_action('woocommerce_checkout_create_order', function($order, $data) {
        if (WC()->session->get('gift_wrap')) {
            $order->update_meta_data('gift_wrap', 'بله، بسته‌بندی هدیه انتخاب شده است.');
            WC()->session->set('gift_wrap', false); // Clear session
        }
    }, 10, 2);
    
    add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
        $gift_wrap = $order->get_meta('gift_wrap');
        if ($gift_wrap) {
            echo '<p><strong>بسته‌بندی هدیه:</strong> ' . esc_html($gift_wrap) . '</p>';
        }
    }, 10, 1);
}

