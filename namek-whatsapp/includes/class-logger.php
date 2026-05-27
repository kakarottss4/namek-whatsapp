<?php
defined('ABSPATH') || exit;

class Namek_WA_Logger {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'namek_wa_log';
    }

    public static function maybe_create_table() {
        if (get_option('namek_wa_db_version') !== '1.0') {
            self::create_table();
            update_option('namek_wa_db_version', '1.0');
        }
    }

    private static function create_table() {
        global $wpdb;
        $table   = self::table();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            trigger_key varchar(50) NOT NULL DEFAULT '',
            order_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            phone varchar(30) DEFAULT NULL,
            error_message text NOT NULL,
            attempted_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY attempted_at (attempted_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function log($trigger_key, $order_id, $phone, $error_message) {
        global $wpdb;
        $wpdb->insert(
            self::table(),
            [
                'trigger_key'   => (string) $trigger_key,
                'order_id'      => (int) $order_id,
                'phone'         => $phone ?: null,
                'error_message' => (string) $error_message,
                'attempted_at'  => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%s']
        );
    }

    public static function get_recent($limit = 30) {
        global $wpdb;
        $table = self::table();
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY attempted_at DESC LIMIT %d", (int) $limit)
        );
    }

    public static function clear() {
        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . self::table());
    }

    public static function prune($days = 30) {
        global $wpdb;
        $table = self::table();
        $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE attempted_at < DATE_SUB(NOW(), INTERVAL %d DAY)", (int) $days)
        );
    }
}
