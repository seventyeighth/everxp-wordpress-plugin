<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EverXP_Cron {

    public static function init() {
        add_action('everxp_sync_logs_cron', [self::class, 'sync_logs_to_api']);
    }

    public static function sync_logs_to_api() {
        global $wpdb;

        // Fetch unsynced logs
        $unsynced_logs = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}api_user_logs
            WHERE synced = 0
            LIMIT 100
        ", ARRAY_A);

        if (empty($unsynced_logs)) {
            return; // No logs to sync
        }

        // Prepare data for API
		$api_url           = 'https://api.everxp.com/logs/sync_logs';
		$api_key           = get_option('everxp_api_key');
		$decrypted_api_key = EverXP_Encryption_Helper::decrypt($api_key);
        if (!$decrypted_api_key) {
            error_log('EverXP API Key is missing.');
            return;
        }

        $payload = [
            'api_key' => $decrypted_api_key,
            'logs'    => $unsynced_logs,
        ];

        // Send data to EverXP API
		$response = wp_remote_post($api_url, [
		    'method'      => 'POST',
		    'headers'     => [
		        'Content-Type'  => 'application/json',
		        'Authorization' => 'Bearer ' . $decrypted_api_key,
		    ],
		    'body'        => wp_json_encode($payload),
		    'timeout'     => 30,
		]);

        // Check for errors
        if (is_wp_error($response)) {
            error_log('EverXP Log Sync Error: ' . $response->get_error_message());
            return;
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        if ($decoded_response['status'] === 'success') {
            // Mark logs as synced
            $log_ids = array_map(function ($log) {
                return (int) $log['ID'];
            }, $unsynced_logs);

            $ids_placeholder = implode(',', array_fill(0, count($log_ids), '%d'));
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}api_user_logs
                SET synced = 1
                WHERE ID IN ($ids_placeholder)
            ", $log_ids));
        } else {
            error_log('EverXP Log Sync Failed: ' . $decoded_response['message']);
        }
    }
}

EverXP_Cron::init();
