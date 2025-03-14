<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


class EverXP_Sync {
    public static function init() {

    }

    public static function render_sync_page() {
        if (isset($_POST['sync_data'])) {
            $response = self::sync_data_from_dashboard();

            if ($response['success']) {
                echo '<div class="notice notice-success is-dismissible"><p>Data synced successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error syncing data: ' . esc_html($response['message']) . '</p></div>';
            }
        }

        if (isset($_POST['sync_logs'])) {
            self::sync_logs_manually();
            echo '<div class="notice notice-success is-dismissible"><p>Logs synced successfully!</p></div>';
        }

        if (isset($_POST['clear_logs'])) {
            self::truncate_logs_table();
            echo '<div class="notice notice-warning is-dismissible"><p>Logs table has been cleared successfully!</p></div>';
        }

        global $wpdb;

        // Get counts
        $headings_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}api_endpoint_headings");
        $banks_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}user_banks");
        $logs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}api_user_logs");
        $logs_to_sync_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}api_user_logs WHERE synced = 0");

        // Retrieve the last sync timestamp
        $last_sync = get_option('everxp_last_sync', 'Never synced');

        echo '<style>
            .wp-list-table {
                margin-top: 20px;
                border-collapse: collapse;
                width: 100%;
            }
            .wp-list-table th, .wp-list-table td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            .wp-list-table th {
                background-color: #f1f1f1;
            }
        </style>';

        // Render the page content
        echo '<h1>Sync Data</h1>';
        echo '<p>Click the buttons below to sync data, sync logs, or clear logs.</p>';
        echo '<p><strong>Last Sync:</strong> ' . esc_html($last_sync) . '</p>';

        // Render the table
        echo '<table class="wp-list-table widefat fixed striped" style="width: 80%; margin: 20px 0;">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Data Type</th>';
        echo '<th>Count</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        echo '<tr>';
        echo '<td>Number of Headings Synced</td>';
        echo '<td>' . esc_html($headings_count) . '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td>Number of Banks Synced</td>';
        echo '<td>' . esc_html($banks_count) . '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td>Number of Logs Stored</td>';
        echo '<td>' . esc_html($logs_count) . '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<td>Number of Logs to Sync</strong> (unsynced)</td>';
        echo '<td>' . esc_html($logs_to_sync_count) . '</td>';
        echo '</tr>';
        echo '</tbody>';
        echo '</table>';

        // Buttons: Sync Now, Sync Logs, Clear Logs
        echo '<form method="post">';
        wp_nonce_field('everxp_sync_action', '_everxp_nonce'); // Generate nonce
        echo '<button type="submit" name="sync_data" class="button button-primary">Sync Data</button>';
        echo '&nbsp;';
        echo '<button type="submit" name="sync_logs" class="button button-secondary">Sync Logs</button>';
        echo '&nbsp;';
        echo '<button type="submit" name="clear_logs" class="button button-secondary" onclick="return confirm(\'Are you sure you want to clear all logs? This cannot be undone.\')">Clear Logs</button>';
        echo '</form>';
    }

    /**
     * Function to truncate logs table
     */
    private static function truncate_logs_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'api_user_logs';

        $wpdb->query("TRUNCATE TABLE $table_name"); // Clears all logs and resets auto-increment
    }


    public static function sync_data_from_dashboard() {
        $api_key            = get_option('everxp_api_key');
        $decrypted_api_key = EverXP_Encryption_Helper::decrypt($api_key);
        $domain             = everxp_check_domain();


        if (empty($decrypted_api_key)) {
            return ['success' => false, 'message' => 'API key is missing. Please verify your API key in the settings page.'];
        }

        if (!isset($_POST['_everxp_nonce']) || !wp_verify_nonce($_POST['_everxp_nonce'], 'everxp_sync_action')) {
            wp_die('Unauthorized action.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        // API endpoint for syncing data
        //$url = 'http://localhost/everxp/everxp-api/v2/request';
        $url = 'https://api.everxp.com/v2/request';

        // Make the API request
        $response = wp_remote_get(add_query_arg([
            'domain' => $domain,
            'folder' => 'all',
            'lang' => 'en',
            'limit' => '99999999'
        ], $url), [
            'headers' => [
                'Authorization' => 'Bearer ' . $decrypted_api_key,
            ],
        ]);


        if (is_wp_error($response)) {
            return ['success' => false, 'message' => 'Request failed: ' . $response->get_error_message()];
        }

        $insert_data = json_decode(wp_remote_retrieve_body($response), true);
        // Save the synced data to the WordPress database
        self::everxp_insert_data($insert_data);
        return ['success' => true];


        return ['success' => false, 'message' => $data['message'] ?? 'Unknown error'];
    }

    public static function sync_logs_manually() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        if (!isset($_POST['_everxp_nonce']) || !wp_verify_nonce($_POST['_everxp_nonce'], 'everxp_sync_action')) {
            wp_die('Unauthorized action.');
        }

        if (class_exists('EverXP_Cron')) {
            EverXP_Cron::sync_logs_to_api(); // Manually trigger logs sync
        } else {
            error_log('EverXP_Cron class not found. Unable to sync logs.');
        }
    }


    private static function everxp_insert_data($data) 
    {
        global $wpdb;

        // Define table names
        $table_api_endpoint_headings = $wpdb->prefix . 'api_endpoint_headings';
        $table_user_banks            = $wpdb->prefix . 'user_banks';
        $table_user_bank_headings    = $wpdb->prefix . 'user_bank_headings';

        // Start a transaction for consistency
        $wpdb->query('START TRANSACTION');

        try {
            // Check if api_endpoint_headings exists and is not empty
            if (empty($data['api_endpoint_headings']) || !is_array($data['api_endpoint_headings'])) {
                throw new Exception('Error: Data not found. Please check your API Key and try reconnecting.');
            }

            // Delete old rows directly (skip existence checks for performance)
            $wpdb->query("DELETE FROM $table_api_endpoint_headings");
            $wpdb->query("DELETE FROM $table_user_banks");
            $wpdb->query("DELETE FROM $table_user_bank_headings");

            // Loop through provided data and insert into respective tables
            foreach ($data['api_endpoint_headings'] as $row) {
                // Insert into api_endpoint_headings
                $encrypted_heading = EverXP_Encryption_Helper::encrypt_data($row['name']);
                $wpdb->insert(
                    $table_api_endpoint_headings,
                    [
                        'ID'           => $row['ID'],
                        'user_id'      => $row['user_id'],
                        'name'         => $encrypted_heading,
                        'last_updated' => $row['last_updated'],
                        'active'       => $row['active'],
                        'pattern'      => $row['pattern'],
                        'language'     => $row['language'],
                        'quotes'       => $row['quotes'],
                        'times'        => $row['times'],
                        'cta'          => $row['cta'],
                        'humanitarian' => $row['humanitarian'],
                        'announcement' => $row['announcement'],
                        'length'       => $row['length'],
                        'style'        => $row['style'],
                        'start_date'   => $row['start_date'],
                        'end_date'     => $row['end_date'],
                    ],
                    [
                        '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'
                    ]
                );
            }
            foreach ($data['user_banks'] as $row) {
                // Insert into user_banks
                $wpdb->insert(
                    $table_user_banks,
                    [
                        'id'         => $row['id'], 
                        'user_id'    => $row['user_id'],
                        'name'       => $row['name'], 
                        'active'     => $row['active'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at'],
                        'deleted_at' => $row['deleted_at'],
                    ],
                    [
                        '%d', '%d', '%s', '%d', '%s', '%s', '%s'
                    ]
                );
            }
            foreach ($data['user_bank_headings'] as $row) {
                // Insert into user_bank_headings
                $wpdb->insert(
                    $table_user_bank_headings,
                    [
                        'id'         => $row['id'],
                        'folder_id'  => $row['folder_id'],
                        'user_id'    => $row['user_id'],
                        'heading_id' => $row['heading_id'],
                        'style'      => $row['style'],
                        'active'     => $row['active'],
                        'start_date' => $row['start_date'],
                        'end_date'   => $row['end_date'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at'],
                        'deleted_at' => $row['deleted_at'],
                    ],
                    [
                        '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s'
                    ]
                );
            }

            // Commit the transaction
            $wpdb->query('COMMIT');

        } catch (Exception $e) {
            // Rollback the transaction in case of error
            $wpdb->query('ROLLBACK');
            echo '<div class="error"><p>' . esc_html($e->getMessage()) . '</p></div>';
            error_log('Error during data sync: ' . $e->getMessage());
        }


        if (!empty($data['domains'][0]) && is_array($data['domains'][0])) {
            // Remove empty values and sanitize
            $sanitized_settings = array_map('sanitize_text_field', array_filter($data['domains'][0]));

            // Ensure we only update if sanitized_settings is not empty
            if (!empty($sanitized_settings)) {
                update_option('everxp_domain_settings', wp_json_encode($sanitized_settings));
            } else {
                update_option('everxp_domain_settings', '[]'); // Store an empty JSON array if no valid domains
            }
        } else {
            update_option('everxp_domain_settings', '[]'); // Default to an empty JSON array
        }


        // Update the last sync timestamp in the database
        update_option('everxp_last_sync', current_time('mysql'));

        if ($wpdb->last_error) {
            error_log('Database Insert Error: ' . $wpdb->last_error);
        }
    }


}

EverXP_Sync::init();
