<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EverXP_Request {
    private $wpdb;
    private $domain_settings;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        // Load domain settings
        $this->load_domain_settings();
    }

    /**
     * Load domain settings from the WordPress options table
     */
    private function load_domain_settings() {
        $settings = get_option('everxp_domain_settings');
        $this->domain_settings = $settings ? json_decode($settings, true) : [];
    }


    public function get_multiple_headings($params) {
        $user_identifier = self::everxp_get_user_identifier();

        $query = $this->wpdb->prepare(
            "
            SELECT 
                ubh.*, aeh.name 
            FROM 
                {$this->wpdb->prefix}user_bank_headings AS ubh
            INNER JOIN 
                {$this->wpdb->prefix}api_endpoint_headings AS aeh
            ON 
                ubh.heading_id = aeh.ID
            WHERE 
                ubh.folder_id = %d
                AND aeh.language = %s
                AND ubh.style = %d
                AND aeh.length BETWEEN %d AND %d
            LIMIT %d
            ",
            $params['folder_id'],
            $params['lang'],
            $params['style'],
            $params['min_l'],
            $params['max_l'],
            $params['limit']
        );

        $data = $this->wpdb->get_results($query, ARRAY_A);

        if ($data) {
            foreach ($data as &$row) {
                // Extract UTM parameters from the sentence in the database
                $utm_parameters = $this->extract_utms_from_heading(stripslashes(htmlspecialchars_decode(trim(EverXP_Encryption_Helper::decrypt_data($row['name']), '"'))));

                $event_data = [
                    'folder_id' => $row['folder_id'],
                    'heading_id' => $row['heading_id']
                ];

                // Log event with extracted UTM parameters
                $this->log_fetch($row['folder_id'], $row['heading_id'], $user_identifier, 'request', $event_data, $utm_parameters);
            }
        }

        return $data;
    }




    /**
     * Fetch a random heading based on parameters and domain settings
     *
     * @param array $params
     * @return array|null
     */
    public function get_random_heading($params) {
        $user_identifier = self::everxp_get_user_identifier();
        $freshness = isset($this->domain_settings['freshness']) ? $this->domain_settings['freshness'] : 1;

        // Use default style if not provided
        $style = isset($params['style']) && !empty($params['style'])
            ? $params['style']
            : (isset($this->domain_settings['writing_style']) ? $this->domain_settings['writing_style'] : 1); // Default to 1 if not found

        // Handle freshness logic
        $last_data = $this->handle_freshness($freshness, $params['folder_id'], $user_identifier);

        if ($last_data !== null) {
            // Return last fetched data if freshness conditions aren't met
            return $last_data;
        }

        // Fetch new data
        $query = $this->wpdb->prepare(
            "
            SELECT 
                ubh.*, aeh.name
            FROM 
                {$this->wpdb->prefix}user_bank_headings AS ubh
            INNER JOIN 
                {$this->wpdb->prefix}api_endpoint_headings AS aeh
            ON 
                ubh.heading_id = aeh.ID
            WHERE 
                ubh.folder_id = %d
                AND (aeh.language = %s)
                AND ubh.style = %d
                AND aeh.length BETWEEN %d AND %d
                AND (
                    (ubh.start_date IS NULL OR ubh.start_date = '0000-00-00' OR ubh.start_date <= CURDATE())
                    AND (ubh.end_date IS NULL OR ubh.end_date = '0000-00-00' OR ubh.end_date >= CURDATE())
                )
            ORDER BY RAND()
            LIMIT 1
            ",
            $params['folder_id'],
            $params['lang'],
            $style, // Use resolved style
            $params['min_l'],
            $params['max_l']
        );

        $data = $this->wpdb->get_row($query, ARRAY_A);

        if ($data) {
            // Extract UTM parameters from the stored heading
            $utm_parameters = $this->extract_utms_from_heading(stripslashes(htmlspecialchars_decode(trim(EverXP_Encryption_Helper::decrypt_data($data['name']), '"'))));

            // Prepare event data
            $event_data = [
                    'folder_id' => $data['folder_id'],
                    'heading_id' => $data['heading_id']
            ];


            // Log the event with UTM parameters
            $this->log_fetch($data['folder_id'], $data['heading_id'], $user_identifier, 'request', $event_data, $utm_parameters);
        }

        return $data ?: null;
    }




    /**
     * Fetch the last fetched data by data_id.
     *
     * @param int $data_id The ID of the last fetched data.
     * @return array|null The last fetched data or null if not found.
     */
    private function get_last_fetched_data($data_id) {
        $data = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "
                SELECT 
                    ubh.*, aeh.name
                FROM 
                    {$this->wpdb->prefix}user_bank_headings AS ubh
                INNER JOIN 
                    {$this->wpdb->prefix}api_endpoint_headings AS aeh
                ON 
                    ubh.heading_id = aeh.ID
                WHERE 
                    ubh.heading_id = %d
                ",
                $data_id
            ),
            ARRAY_A
        );

        return $data ?: null;
    }



    /**
     * Handle freshness logic based on the domain settings
     *
     * @param string $freshness
     * @param int $folder_id
     * @param string $user_identifier
     */
    private function handle_freshness($freshness, $folder_id, $user_identifier) {

        if ($freshness == 1) {
            // Always fetch new data; no log checks needed
            return null; // Indicates fetch new data
        }

        // Fetch the last log entry
        $last_log = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "
                SELECT timestamp, data_id 
                FROM {$this->wpdb->prefix}api_user_logs 
                WHERE endpoint_id = %d AND user_identifier = %s
                ORDER BY timestamp DESC 
                LIMIT 1
                ",
                $folder_id,
                $user_identifier
            ),
            ARRAY_A
        );

        $current_time = time();

        // If no logs exist, allow fetching new data
        if (!$last_log) {
            return null; // Indicates fetch new data
        }

        $last_timestamp = $last_log['timestamp'];

        // Check freshness condition
        switch ($freshness) {
            case 2: // Daily
                if (($current_time - $last_timestamp) >= DAY_IN_SECONDS) {
                    return null; // Indicates fetch new data
                }
                break;
            case 3: // Weekly
                if (($current_time - $last_timestamp) >= WEEK_IN_SECONDS) {
                    return null; // Indicates fetch new data
                }
                break;
            case 4: // Monthly
                if (($current_time - $last_timestamp) >= MONTH_IN_SECONDS) {
                    return null; // Indicates fetch new data
                }
                break;
        }

        // Freshness not met, fetch last fetched data
        return $this->get_last_fetched_data($last_log['data_id']);
    }


    /**
     * Save a log entry to the logs table
     *
     * @param int $folder_id
     * @param int $data_id
     * @param string $user_identifier
     */
    private function log_fetch($folder_id, $data_id, $user_identifier, $event_type = 'request', $event_data = []) {

        global $wpdb;
        $wpdb->insert(
            "{$wpdb->prefix}api_user_logs",
            [
                'user_id'         => get_current_user_id() ?: NULL,
                'endpoint_id'     => $folder_id,
                'data_id'         => $data_id,
                'timestamp'       => time(),
                'user_identifier' => $user_identifier,
                'event_type'      => sanitize_text_field($event_type),
                'event_data'      => json_encode($event_data),
                'utm_parameters'  => NULL, // Remove UTM tracking for requests
                'referrer_url'    => isset($_SERVER['HTTP_REFERER']) ? esc_url($_SERVER['HTTP_REFERER']) : NULL,
                'synced'          => 0
            ],
            ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d']
        );

        if ($wpdb->last_error) {
            error_log('Log Insertion Error: ' . $wpdb->last_error);
        }
    }




    /**
     * Get the user identifier.
     *
     * @return string A unique user identifier based on the logged-in status or IP address.
     */
    public static function everxp_get_user_identifier() {
        // If user is logged in, use the WordPress username
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            return 'user_' . sanitize_text_field($current_user->user_login);
        }

        // Fallback to IP address
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';

        // Add extra layer of hashing for security
        $hashed_ip = hash('sha256', $user_ip);

        // Check if a cookie exists for a persistent identifier
        $cookie_name = 'everxp_user_identifier';
        if (isset($_COOKIE[$cookie_name])) {
            return sanitize_text_field($_COOKIE[$cookie_name]);
        }

        // Set the hashed IP as a cookie to persist the identifier
        setcookie($cookie_name, $hashed_ip, time() + (365 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

        return $hashed_ip;
    }

    private function extract_utms_from_heading($heading) {
        $utm_params = [];

        // Regular expression to find href attributes in anchor tags
        preg_match_all('/<a\s+[^>]*href=["\']?([^"\'>]+)["\']?/i', $heading, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                // Ignore mailto and javascript links
                if (strpos($url, 'mailto:') === 0 || strpos($url, 'javascript:') === 0) {
                    continue;
                }

                // Parse the URL
                $parsed_url = parse_url($url);
                if (!isset($parsed_url['query'])) continue;

                parse_str($parsed_url['query'], $query_params);

                // Extract only valid UTM parameters
                foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm_key) {
                    if (isset($query_params[$utm_key])) {
                        $utm_params[$utm_key] = sanitize_text_field($query_params[$utm_key]);
                    }
                }
            }
        }

        return $utm_params;
    }


}