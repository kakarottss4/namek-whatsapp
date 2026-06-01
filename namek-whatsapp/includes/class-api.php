<?php
defined('ABSPATH') || exit;

class Namek_WA_API {

    /**
     * Send a WhatsApp message via the Namek API.
     * Returns ['success' => true] or ['success' => false, 'error' => string].
     */
    public static function send($phone, $message, $image_url = null) {
        $endpoint = get_option('namek_wa_endpoint', '');
        $api_key  = get_option('namek_wa_api_key', '');

        if (!$endpoint || !$api_key) {
            return ['success' => false, 'error' => 'Namek API not configured. Set endpoint and API key in settings.'];
        }

        $phone = self::format_phone($phone);
        if (!$phone) {
            return ['success' => false, 'error' => 'Invalid or missing phone number.'];
        }

        $url = $endpoint;

        $payload = ['phone' => $phone, 'message' => $message];
        if ($image_url) {
            $payload['image_url'] = $image_url;
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 15,
            'sslverify' => false, // allows local/self-signed certs during development
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message() . " (URL: {$url})"];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            return ['success' => true];
        }

        $api_error = $body['error'] ?? null;
        return ['success' => false, 'error' => ($api_error ? $api_error . ' — ' : '') . "HTTP {$code} → {$url}"];
    }

    /**
     * Send a WhatsApp message to a group via the Namek API.
     * Returns ['success' => true] or ['success' => false, 'error' => string].
     */
    public static function send_group($group_id, $message) {
        $url     = get_option('namek_wa_group_endpoint', '');
        $api_key = get_option('namek_wa_api_key', '');

        if (!$url || !$api_key) {
            return ['success' => false, 'error' => 'Group endpoint not configured. Set the Group Message URL in settings.'];
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'      => wp_json_encode(['group_id' => $group_id, 'message' => $message]),
            'timeout'   => 15,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message() . " (URL: {$url})"];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            return ['success' => true];
        }

        $api_error = $body['error'] ?? null;
        return ['success' => false, 'error' => ($api_error ? $api_error . ' — ' : '') . "HTTP {$code} → {$url}"];
    }

    /**
     * Normalise a phone number to E.164-style digits only (no +).
     * - Strips all non-digit characters
     * - Prepends country code if number is exactly 10 digits
     * - Strips a leading 0 then prepends country code if 11 digits starting with 0
     * - Returns null if the result is obviously invalid (< 10 digits)
     */
    public static function format_phone($phone) {
        $digits = preg_replace('/\D/', '', $phone);

        if (!$digits || strlen($digits) < 10) {
            return null;
        }

        $country_code = ltrim(get_option('namek_wa_country_code', '91') ?: '91', '+0');

        if (strlen($digits) === 10) {
            return $country_code . $digits;
        }

        // Leading 0 + 10 digits → strip 0 and prepend country code
        if ($digits[0] === '0' && strlen($digits) === 11) {
            return $country_code . substr($digits, 1);
        }

        // Already has country code (12+ digits) — use as-is
        return $digits;
    }
}
