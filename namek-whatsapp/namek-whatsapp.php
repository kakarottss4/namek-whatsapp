<?php
/**
 * Plugin Name: Namek WhatsApp
 * Plugin URI:  https://namek.co.in
 * Description: Send WhatsApp notifications automatically for WooCommerce events via Namek WhatsApp Services.
 * Version:     1.1.0
 * Author:      Namek
 * Author URI:  https://namek.co.in
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * Text Domain: namek-whatsapp
 */

/**
 * Copyright (c) 2026 Namek. All rights reserved.
 *
 * This plugin is proprietary software licensed exclusively to authorised
 * Namek clients. Unauthorised copying, distribution, or modification
 * is strictly prohibited. For licensing enquiries contact hello@namek.co.in
 */

defined('ABSPATH') || exit;

define('NAMEK_WA_VERSION',     '1.1.0');
define('NAMEK_WA_PATH',        plugin_dir_path(__FILE__));
define('NAMEK_WA_URL',         plugin_dir_url(__FILE__));
define('NAMEK_WA_GITHUB_REPO', 'kakarottss4/namek-whatsapp');

require_once NAMEK_WA_PATH . 'includes/class-api.php';
require_once NAMEK_WA_PATH . 'includes/class-updater.php';
require_once NAMEK_WA_PATH . 'includes/class-settings.php';
require_once NAMEK_WA_PATH . 'includes/modules/class-woocommerce.php';

// Auto-updater — checks GitHub releases for newer versions
(new Namek_WA_Updater(__FILE__, NAMEK_WA_GITHUB_REPO))->init();

add_action('plugins_loaded', function () {
    Namek_WA_Settings::init();
    if (class_exists('WooCommerce')) {
        Namek_WA_WooCommerce::init();
    }
});

// AJAX: test send from settings page
add_action('wp_ajax_namek_wa_test_send', function () {
    check_ajax_referer('namek_wa_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $phone   = sanitize_text_field($_POST['phone']   ?? '');
    $message = sanitize_textarea_field($_POST['message'] ?? '');

    if (!$phone || !$message) {
        wp_send_json_error('Phone and message are required.');
    }

    $result = Namek_WA_API::send($phone, $message);
    if ($result['success']) {
        wp_send_json_success('Message sent!');
    } else {
        wp_send_json_error($result['error'] ?? 'Send failed.');
    }
});
