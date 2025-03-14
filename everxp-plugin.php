<?php
/*
Plugin Name: EverXP API Plugin
Description: Provides API integration with shortcodes, Elementor widgets, and database sync.
Version: 1.6
Author: Accessily LTD
*/

defined('ABSPATH') || exit;

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/class-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-sync.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-everxp-cron.php';
require_once plugin_dir_path(__FILE__) . 'includes/encryption-helper.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-everxp-tracker.php';



// Initialize features
add_action('plugins_loaded', function () {
    EverXP_API::init();
    EverXP_Shortcodes::init();
    EverXP_Sync::init();
    EverXP_Settings::init();
    EverXP_Tracker::init();
});


function everxp_create_custom_tables() {
    global $wpdb;

    // Character set and collation for the tables
    $charset_collate = $wpdb->get_charset_collate();

    // SQL for api_endpoint_headings
    $table_api_endpoint_headings = $wpdb->prefix . 'api_endpoint_headings';
    $sql_api_endpoint_headings = "CREATE TABLE $table_api_endpoint_headings (
        ID int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL DEFAULT '1',
        name TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        last_updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        active int(11) NOT NULL DEFAULT '1' COMMENT '1=>active 2=>inactive',
        pattern varchar(50) NOT NULL,
        language varchar(100) NOT NULL,
        quotes varchar(200) NOT NULL,
        times varchar(200) NOT NULL,
        cta varchar(50) NOT NULL,
        humanitarian varchar(50) NOT NULL,
        announcement varchar(500) NOT NULL,
        length int(11) NOT NULL,
        style varchar(100) NOT NULL DEFAULT '1',
        start_date date DEFAULT NULL,
        end_date date DEFAULT NULL,
        PRIMARY KEY  (ID)
    ) $charset_collate;";

    // SQL for user_bank_headings
    $table_user_bank_headings = $wpdb->prefix . 'user_bank_headings';
    $sql_user_bank_headings = "CREATE TABLE $table_user_bank_headings (
        id int(11) NOT NULL AUTO_INCREMENT,
        folder_id int(11) NOT NULL,
        user_id int(11) NOT NULL,
        heading_id int(11) NOT NULL,
        style int(11) DEFAULT NULL,
        active tinyint(1) NOT NULL DEFAULT '1',
        start_date date DEFAULT NULL,
        end_date date DEFAULT NULL,
        created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at date DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY folder_id (folder_id),
        KEY user_id (user_id),
        KEY heading_id (heading_id)
    ) $charset_collate;";

    // SQL for user_banks
    $table_user_banks = $wpdb->prefix . 'user_banks';
    $sql_user_banks = "CREATE TABLE $table_user_banks (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        name varchar(255) NOT NULL,
        active tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = Active, 2 = Inactive',
        created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at date DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id)
    ) $charset_collate;";


    // SQL for api_user_logs
    $table_api_user_logs = $wpdb->prefix . 'api_user_logs';
    $sql_api_user_logs = "CREATE TABLE IF NOT EXISTS $table_api_user_logs (
        ID int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        endpoint_id int(11) NOT NULL,
        data_id int(11) NOT NULL,
        timestamp int(11) NOT NULL,
        user_identifier varchar(100) DEFAULT NULL,
        event_type varchar(50) NOT NULL,
        event_data JSON DEFAULT NULL, -- Matches dashboard DB structure
        referrer_url varchar(255) DEFAULT NULL,
        utm_parameters JSON DEFAULT NULL, -- Matches dashboard DB structure
        synced tinyint(1) DEFAULT 0, -- Marks if data has been synced to API
        PRIMARY KEY (ID)
    ) $charset_collate;";


    // Execute the queries using dbDelta
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_api_endpoint_headings);
    dbDelta($sql_user_bank_headings);
    dbDelta($sql_user_banks);
    dbDelta($sql_api_user_logs);
}


function everxp_add_foreign_keys() {
    global $wpdb;

    $table_user_bank_headings = $wpdb->prefix . 'user_bank_headings';
    $table_user_banks = $wpdb->prefix . 'user_banks';
    $table_api_endpoint_headings = $wpdb->prefix . 'api_endpoint_headings';

    // Add foreign keys
    $wpdb->query("ALTER TABLE $table_user_bank_headings 
        ADD CONSTRAINT user_bank_headings_ibfk_1 FOREIGN KEY (folder_id) REFERENCES $table_user_banks (id) ON DELETE CASCADE");

    $wpdb->query("ALTER TABLE $table_user_bank_headings 
        ADD CONSTRAINT user_bank_headings_ibfk_2 FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}tbl_users (userId) ON DELETE CASCADE");

    $wpdb->query("ALTER TABLE $table_user_bank_headings 
        ADD CONSTRAINT user_bank_headings_ibfk_3 FOREIGN KEY (heading_id) REFERENCES $table_api_endpoint_headings (ID) ON DELETE CASCADE");

    $wpdb->query("ALTER TABLE $table_user_banks 
        ADD CONSTRAINT user_banks_ibfk_1 FOREIGN KEY (user_id) REFERENCES {$wpdb->prefix}tbl_users (userId) ON DELETE CASCADE ON UPDATE CASCADE");
}


/**
 * Migration script to update 'api_user_logs' table in WordPress database.
 */
function everxp_migrate_api_user_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'api_user_logs';

    // Get the current columns in the table
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name", ARRAY_A);
    $existing_columns = array_column($columns, 'Field');

    // Define required columns with their SQL statements
    $missing_columns = [];

    if (!in_array('event_type', $existing_columns)) {
        $missing_columns[] = "ADD COLUMN event_type VARCHAR(50) NOT NULL";
    }
    if (!in_array('event_data', $existing_columns)) {
        $missing_columns[] = "ADD COLUMN event_data JSON DEFAULT NULL";
    }
    if (!in_array('referrer_url', $existing_columns)) {
        $missing_columns[] = "ADD COLUMN referrer_url VARCHAR(255) DEFAULT NULL";
    }
    if (!in_array('utm_parameters', $existing_columns)) {
        $missing_columns[] = "ADD COLUMN utm_parameters JSON DEFAULT NULL";
    }
    if (!in_array('synced', $existing_columns)) {
        $missing_columns[] = "ADD COLUMN synced TINYINT(1) DEFAULT 0";
    }

    // If any column is missing, modify the table
    if (!empty($missing_columns)) {
        $alter_query = "ALTER TABLE $table_name " . implode(", ", $missing_columns) . ";";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $wpdb->query($alter_query);
    }
}

// Run the migration on plugin activation
register_activation_hook(__FILE__, 'everxp_migrate_api_user_logs');





register_activation_hook(__FILE__, function() {
    ob_start(); // Start output buffering
    everxp_create_custom_tables();
    everxp_add_foreign_keys();
    $unexpected_output = ob_get_clean(); // Get any unexpected output

    if (!empty($unexpected_output)) {
        error_log('Unexpected output during activation: ' . $unexpected_output);
    }
});


// Schedule the cron event
register_activation_hook(__FILE__, 'everxp_schedule_cron_job');
function everxp_schedule_cron_job() {
    if (!wp_next_scheduled('everxp_sync_logs_cron')) {
        wp_schedule_event(time(), 'hourly', 'everxp_sync_logs_cron');
    }
}

// Clear the cron event on plugin deactivation
register_deactivation_hook(__FILE__, 'everxp_clear_cron_job');
function everxp_clear_cron_job() {
    wp_clear_scheduled_hook('everxp_sync_logs_cron');
}


if (!function_exists('everxp_check_domain')) {
    final class EverXP_Domain_Check {
        public static function get_domain() {
            $headers = getallheaders();

            // Fetch the domain from the headers or fallback to HTTP_HOST
            $domain = isset($headers['Origin']) ? $headers['Origin'] : $_SERVER['HTTP_HOST'];

            // If the domain is empty, assign localhost as the default
            if (empty($domain)) {
                $domain = 'localhost';
            }

            // If localhost with port, normalize it to 'localhost'
            if (strpos($domain, 'localhost:') !== false) {
                $domain = 'localhost';
            }

            // Remove http://, https://, and trailing slashes
            $domain = parse_url($domain, PHP_URL_HOST) ?? $domain;
            $domain = rtrim($domain, '/');

            return $domain;
        }
    }

    // Wrapper function to prevent direct manipulation
    function everxp_check_domain() {
        return EverXP_Domain_Check::get_domain();
    }
}