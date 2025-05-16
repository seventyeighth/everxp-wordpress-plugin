<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EverXP_Tracker {
    private static $instance;
    private $wpdb;

    const API_BASE_URL = 'https://api.everxp.com';

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        add_action('wp_enqueue_scripts', [$this, 'enqueue_tracking_script']);

        // ✅ Track WooCommerce Add to Cart
        // add_action('woocommerce_add_to_cart', [$this, 'track_add_to_cart'], 10, 6);

        // ✅ Track WooCommerce Purchase
        add_action('woocommerce_thankyou', ['EverXP_Tracker', 'track_successful_purchase'], 10, 1);

        // ✅ Track Checkout Initiated
        // add_action('woocommerce_before_checkout_form', [$this, 'track_checkout_initiated']);

        // ✅ Track WordPress User Registration
        // add_action('user_register', [$this, 'track_user_registration']);

        // ✅ Track Contact Form 7 Submissions
        // add_action('wpcf7_mail_sent', [$this, 'track_form_submission']);
    }

    /**
     * Singleton Instance
     */
    public static function init() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Enqueue JavaScript tracking script with cache busting
     */
    public function enqueue_tracking_script() {
        wp_enqueue_script(
            'everxp-event-tracking',
            plugins_url('../assets/js/event-tracking.js', __FILE__),
            array(),
            filemtime(plugin_dir_path(__FILE__) . '../assets/js/event-tracking.js'),
            true
        );

        // Get and decrypt the API key from WordPress options
        $encrypted_api_key = get_option('everxp_api_key');
        $decrypted_api_key = EverXP_Encryption_Helper::decrypt($encrypted_api_key);
        $api_url           = self::API_BASE_URL . '/logs/track_event';

        //https://api.everxp.com/logs/track_event
        wp_localize_script('everxp-event-tracking', 'everxpTracker', [
            'ajax_url' => $api_url,
            'auth_token' => $decrypted_api_key,
            'user_identifier' => self::everxp_get_user_identifier(),
            'user_data' => self::get_geo_data_for_user(self::everxp_get_user_identifier())
        ]);
    }

    /**
     * Get EverXP UTM Parameters from Cookie
     */
    private static function get_everxp_utms() {
        if (isset($_COOKIE['everxp_utms'])) {
            return json_decode(stripslashes($_COOKIE['everxp_utms']), true);
        }
        return [];
    }

    /**
     * Generate User Identifier
     */
    private static function everxp_get_user_identifier() {
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            return 'user_' . sanitize_text_field($current_user->user_login);
        }

        $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
        $hashed_ip = hash('sha256', $user_ip);
        return $hashed_ip;
    }

    /**
     * Check if an event has EverXP UTM attribution
     */
    private static function is_everxp_attributed($utm_parameters) {
        if (!is_array($utm_parameters)) {
            return false;
        }

        $required_utms = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term'];

        foreach ($required_utms as $utm) {
            if (!isset($utm_parameters[$utm]) || empty($utm_parameters[$utm])) {
                return false;
            }
        }

        return (isset($utm_parameters['utm_source']) && strtolower($utm_parameters['utm_source']) == 'everxp');
    }


    private function extract_utm_value($utm_parameters, $key) {
        return isset($utm_parameters[$key]) ? preg_replace('/^everxp_/', '', $utm_parameters[$key]) : null;
    }

    /**
     * Get or cache geo info for a user based on IP
     */
    private function get_geo_data_for_user($user_identifier) {
        $transient_key = 'everxp_geo_' . md5($user_identifier);
        $cached = get_transient($transient_key);

        $current_ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!$current_ip) return null;

        $needs_refresh = true;

        if (is_array($cached) && isset($cached['ip'])) {
            // Only refresh if IP changed
            if ($cached['ip'] === $current_ip) {
                $needs_refresh = false;
            }
        }

        if ($needs_refresh) {
            $response = wp_remote_get("https://ipapi.co/{$current_ip}/json/");
            if (is_wp_error($response)) return $cached ?: null;

            $body = wp_remote_retrieve_body($response);
            $geo = json_decode($body, true);

            if (!isset($geo['ip'])) return $cached ?: null;

            $geo['ip'] = $current_ip; // Store current IP with geo
            // Cache with refreshed IP
            set_transient($transient_key, $geo, DAY_IN_SECONDS);
            return $geo;
        }

        return $cached;
    }


    /**
     * Track WooCommerce Add to Cart (Ignore Unknown Product Events)
     */
    // public function track_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    //     global $wpdb;

    //     // Get correct product (variation ID takes priority over product ID)
    //     $product = wc_get_product($variation_id ? $variation_id : $product_id);
    //     if (!$product) {
    //         error_log("EverXP Warning: Skipping Add to Cart event (Unknown Product)");
    //         return;
    //     }

    //     // Retrieve product details
    //     $product_id = $product->get_id();
    //     $product_name = $product->get_name();
    //     $price = $product->get_price();

    //     // Ignore events where product details are unknown
    //     if ($product_id == "unknown" || $product_name == "unknown" || empty($product_id)) {
    //         return;
    //     }

    //     // Get EverXP tracking info
    //     $user_identifier = self::everxp_get_user_identifier();
    //     $utm_parameters = $this->get_everxp_utms();

    //     if (!$this->is_everxp_attributed($utm_parameters)) {
    //         return;
    //     }

    //     // Extract user_id, endpoint_id, and data_id from UTMs
    //     $endpoint_id = $this->extract_utm_value($utm_parameters, 'utm_campaign');
    //     $data_id = $this->extract_utm_value($utm_parameters, 'utm_term');

    //     // Insert tracking data into the database
    //     $wpdb->insert("{$wpdb->prefix}api_user_logs", [
    //         'user_id'         => get_current_user_id() ?: NULL,
    //         'endpoint_id'     => $endpoint_id,
    //         'data_id'         => $data_id,
    //         'event_type'      => 'add_to_cart',
    //         'event_data'      => json_encode([
    //             'product_id'   => $product_id,
    //             'product_name' => $product_name,
    //             'price'        => $price,
    //             'quantity'     => $quantity
    //         ]),
    //         'utm_parameters'  => json_encode($utm_parameters),
    //         'timestamp'       => time(),
    //         'user_identifier' => $user_identifier
    //     ]);

    //     if ($wpdb->last_error) {
    //         error_log("EverXP DB Insert Error (Add to Cart): " . $wpdb->last_error);
    //     } else {
    //         error_log("EverXP Add to Cart Logged Successfully: Product ID: $product_id, Name: $product_name");
    //     }
    // }


    /**
     * Track WooCommerce Checkout Initiated
     */
    // public function track_checkout_initiated() {
    //     global $wpdb;
    //     $utm_parameters = $this->get_everxp_utms();
    //     $user_identifier = self::everxp_get_user_identifier();

    //     if (!$this->is_everxp_attributed($utm_parameters)) {
    //         return;
    //     }

    //     // Extract user_id, endpoint_id, and data_id from UTMs
    //     $endpoint_id = $this->extract_utm_value($utm_parameters, 'utm_campaign');
    //     $data_id = $this->extract_utm_value($utm_parameters, 'utm_term');

    //     $wpdb->insert("{$wpdb->prefix}api_user_logs", [
    //         'user_id'         => get_current_user_id() ?: NULL,
    //         'endpoint_id'     => $endpoint_id,
    //         'data_id'         => $data_id,
    //         'event_type'      => 'checkout_initiated',
    //         'event_data'      => json_encode([]),
    //         'utm_parameters'  => json_encode($utm_parameters),
    //         'timestamp'       => time(),
    //         'user_identifier' => $user_identifier
    //     ]);
    // }

    /**
     * Track WooCommerce Purchase
     */
    public static function track_successful_purchase($order_id) {
        if (!$order_id) return;

        $order = wc_get_order($order_id);
        if (!$order || $order->get_status() === 'failed') return;

        $utm_parameters = self::get_everxp_utms();
        $user_identifier = self::everxp_get_user_identifier();

        if (!self::is_everxp_attributed($utm_parameters)) {
            return;
        }

        if (empty($utm_parameters)) return;

        // Build event payload
        $event_data = [
            'eventType'   => 'purchase',
            'eventData'   => [
                'order_id'       => $order_id,
                'order_key'      => $order->get_order_key(),
                'total_price'    => $order->get_total(),
                'currency'       => $order->get_currency(),
                'utm_parameters' => $utm_parameters,
            ],
            'user_data' => NULL,
            'user_identifier' => $user_identifier,
        ];

        // Send to EverXP API (use wp_remote_post)
        $api_url = self::API_BASE_URL . '/logs/track_event';
        $api_key = EverXP_Encryption_Helper::decrypt(get_option('everxp_api_key'));

        wp_remote_post($api_url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ],
            'body'    => json_encode($event_data),
            'timeout' => 10
        ]);

    }


    /**
     * Track WordPress User Registration
     */
    // public function track_user_registration($user_id) {
    //     global $wpdb;
    //     $utm_parameters = $this->get_everxp_utms();
    //     $user_identifier = self::everxp_get_user_identifier();

    //     if (!$this->is_everxp_attributed($utm_parameters)) {
    //         return;
    //     }

    //     // Extract user_id, endpoint_id, and data_id from UTMs
    //     $endpoint_id = $this->extract_utm_value($utm_parameters, 'utm_campaign');
    //     $data_id = $this->extract_utm_value($utm_parameters, 'utm_term');

    //     $wpdb->insert("{$wpdb->prefix}api_user_logs", [
    //         'user_id'         => get_current_user_id() ?: NULL,
    //         'endpoint_id'     => $endpoint_id,
    //         'data_id'         => $data_id,
    //         'event_type'      => 'user_registration',
    //         'event_data'      => json_encode(['user_id' => $user_id]),
    //         'utm_parameters'  => json_encode($utm_parameters),
    //         'timestamp'       => time(),
    //         'user_identifier' => $user_identifier
    //     ]);
    // }
}

// Initialize the class
EverXP_Tracker::init();
