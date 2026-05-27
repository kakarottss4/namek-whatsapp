<?php
defined('ABSPATH') || exit;

class Namek_WA_WooCommerce {

    public static function init() {
        // Order status hooks — each fires with ($order_id, $order)
        add_action('woocommerce_order_status_pending',    [__CLASS__, 'order_pending'],    10, 2);
        add_action('woocommerce_order_status_processing', [__CLASS__, 'order_processing'], 10, 2);
        add_action('woocommerce_order_status_on-hold',    [__CLASS__, 'order_on_hold'],    10, 2);
        add_action('woocommerce_order_status_completed',  [__CLASS__, 'order_completed'],  10, 2);
        add_action('woocommerce_order_status_cancelled',  [__CLASS__, 'order_cancelled'],  10, 2);
        add_action('woocommerce_order_status_refunded',   [__CLASS__, 'order_refunded'],   10, 2);
        add_action('woocommerce_order_status_failed',     [__CLASS__, 'payment_failed'],   10, 2);

        // Customer-visible note added to order — fires with array ['order_id', 'customer_note']
        add_action('woocommerce_new_customer_note', [__CLASS__, 'order_note'], 10, 1);

        // New customer account
        add_action('woocommerce_created_customer', [__CLASS__, 'new_customer'], 10, 1);

        // Admin alerts
        add_action('woocommerce_checkout_order_created', [__CLASS__, 'new_order_admin'],  10, 1);
        add_action('woocommerce_low_stock',              [__CLASS__, 'low_stock_admin'],   10, 1);

        // Group notifications
        add_action('woocommerce_checkout_order_created', [__CLASS__, 'new_order_group'],  10, 1);
    }

    // ── Internal helpers ────────────────────────────────────────────────────

    private static function is_enabled($key) {
        return get_option("namek_wa_{$key}_enabled") === '1';
    }

    private static function get_template($key) {
        $triggers = Namek_WA_Settings::get_triggers();
        $default  = $triggers[$key]['default'] ?? '';
        return get_option("namek_wa_{$key}_template", $default);
    }

    private static function order_vars(WC_Abstract_Order $order) {
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = $item->get_name() . ' ×' . $item->get_quantity();
        }
        return [
            '{name}'         => $order->get_billing_first_name(),
            '{full_name}'    => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            '{order_id}'     => $order->get_order_number(),
            '{order_total}'  => html_entity_decode(strip_tags(wc_price($order->get_total()))),
            '{order_status}' => wc_get_order_status_name($order->get_status()),
            '{product_list}' => implode(', ', $items),
            '{site_name}'    => get_bloginfo('name'),
            '{note}'         => '',   // filled in only by order_note handler
            '{product_name}' => '',
            '{stock_qty}'    => '',
        ];
    }

    private static function render($template, $vars) {
        return strtr($template, $vars);
    }

    private static function send_customer(WC_Abstract_Order $order, $key) {
        if (!self::is_enabled($key)) return;
        $phone = $order->get_billing_phone();
        if (!$phone) return;
        $message = self::render(self::get_template($key), self::order_vars($order));
        $result  = Namek_WA_API::send($phone, $message);
        if (!$result['success']) {
            Namek_WA_Logger::log($key, $order->get_id(), $phone, $result['error'] ?? 'Unknown error');
        }
    }

    private static function send_admin($key, $vars = [], $order_id = 0) {
        if (!self::is_enabled($key)) return;
        $phone = get_option('namek_wa_admin_phone');
        if (!$phone) return;
        $vars['{site_name}'] = get_bloginfo('name');
        $message = self::render(self::get_template($key), $vars);
        $result  = Namek_WA_API::send($phone, $message);
        if (!$result['success']) {
            Namek_WA_Logger::log($key, $order_id, $phone, $result['error'] ?? 'Unknown error');
        }
    }

    // ── Order status handlers ────────────────────────────────────────────────

    public static function order_pending($order_id, $order = null) {
        if (!$order) $order = wc_get_order($order_id);
        self::send_customer($order, 'order_pending');
    }

    public static function order_processing($order_id, $order = null) {
        if (!$order) $order = wc_get_order($order_id);
        self::send_customer($order, 'order_processing');
    }

    public static function order_on_hold($order_id, $order = null) {
        if (!$order) $order = wc_get_order($order_id);
        self::send_customer($order, 'order_on_hold');
    }

    public static function order_completed($order_id, $order = null) {
        if (!$order) $order = wc_get_order($order_id);
        self::send_customer($order, 'order_completed');
    }

    public static function order_cancelled($order_id, $order = null) {
        if (!$order) $order = wc_get_order($order_id);
        self::send_customer($order, 'order_cancelled');
    }

    public static function order_refunded($order_id, $order = null) {
        if (!$order) $order = wc_get_order($order_id);
        self::send_customer($order, 'order_refunded');
    }

    public static function payment_failed($order_id, $order = null) {
        if (!$order) $order = wc_get_order($order_id);
        self::send_customer($order, 'payment_failed');
    }

    // ── Order note ───────────────────────────────────────────────────────────

    public static function order_note($data) {
        if (!self::is_enabled('order_note')) return;

        $order_id  = $data['order_id']      ?? 0;
        $note_text = $data['customer_note'] ?? '';

        if (!$order_id || !$note_text) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $phone = $order->get_billing_phone();
        if (!$phone) return;

        $vars          = self::order_vars($order);
        $vars['{note}'] = $note_text;
        $message = self::render(self::get_template('order_note'), $vars);
        $result  = Namek_WA_API::send($phone, $message);
        if (!$result['success']) {
            Namek_WA_Logger::log('order_note', $order_id, $phone, $result['error'] ?? 'Unknown error');
        }
    }

    // ── New customer registration ─────────────────────────────────────────────

    public static function new_customer($customer_id) {
        if (!self::is_enabled('new_customer')) return;

        $phone = get_user_meta($customer_id, 'billing_phone', true);
        if (!$phone) return;

        $user = get_userdata($customer_id);
        if (!$user) return;

        $name = $user->first_name ?: $user->display_name;
        $vars = [
            '{name}'      => $name,
            '{full_name}' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->display_name,
            '{site_name}' => get_bloginfo('name'),
            '{order_id}'  => '', '{order_total}' => '', '{order_status}' => '',
            '{product_list}' => '', '{product_name}' => '', '{stock_qty}' => '', '{note}' => '',
        ];
        $message = self::render(self::get_template('new_customer'), $vars);
        $result  = Namek_WA_API::send($phone, $message);
        if (!$result['success']) {
            Namek_WA_Logger::log('new_customer', 0, $phone, $result['error'] ?? 'Unknown error');
        }
    }

    // ── Admin: new order alert ────────────────────────────────────────────────

    public static function new_order_admin(WC_Order $order) {
        if (!self::is_enabled('new_order_admin')) return;
        $phone = get_option('namek_wa_admin_phone');
        if (!$phone) return;

        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = $item->get_name() . ' ×' . $item->get_quantity();
        }

        $vars = [
            '{order_id}'     => $order->get_order_number(),
            '{full_name}'    => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            '{order_total}'  => html_entity_decode(strip_tags(wc_price($order->get_total()))),
            '{product_list}' => implode(', ', $items),
            '{name}'         => $order->get_billing_first_name(),
            '{order_status}' => wc_get_order_status_name($order->get_status()),
            '{product_name}' => '', '{stock_qty}' => '', '{note}' => '',
        ];
        self::send_admin('new_order_admin', $vars, $order->get_id());
    }

    // ── Admin: low stock alert ────────────────────────────────────────────────

    public static function low_stock_admin(WC_Product $product) {
        $vars = [
            '{product_name}' => $product->get_name(),
            '{stock_qty}'    => (string) $product->get_stock_quantity(),
            '{name}'         => '', '{full_name}' => '', '{order_id}' => '',
            '{order_total}'  => '', '{order_status}' => '', '{product_list}' => '', '{note}' => '',
        ];
        self::send_admin('low_stock_admin', $vars);
    }

    // ── Group: new order notification ────────────────────────────────────────

    public static function new_order_group(WC_Order $order) {
        if (!self::is_enabled('new_order_group')) return;
        $group_id = get_option('namek_wa_group_id');
        if (!$group_id) return;

        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = $item->get_name() . ' ×' . $item->get_quantity();
        }
        $vars = [
            '{order_id}'     => $order->get_order_number(),
            '{full_name}'    => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            '{name}'         => $order->get_billing_first_name(),
            '{order_total}'  => html_entity_decode(strip_tags(wc_price($order->get_total()))),
            '{product_list}' => implode(', ', $items),
            '{order_status}' => wc_get_order_status_name($order->get_status()),
            '{site_name}'    => get_bloginfo('name'),
            '{note}' => '', '{product_name}' => '', '{stock_qty}' => '',
        ];
        $message = self::render(self::get_template('new_order_group'), $vars);
        $result  = Namek_WA_API::send_group($group_id, $message);
        if (!$result['success']) {
            Namek_WA_Logger::log('new_order_group', $order->get_id(), null, $result['error'] ?? 'Unknown error');
        }
    }
}
