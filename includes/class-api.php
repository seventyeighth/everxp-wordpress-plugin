<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EverXP_API {
    public static function init() {
        add_action('rest_api_init', function () {
            register_rest_route('everxp/v1', '/request', [
                'methods' => 'POST',
                'callback' => [self::class, 'handle_request'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public static function handle_request(WP_REST_Request $request) {
        $params = $request->get_params();
        $api_key = $params['api_key'] ?? '';
        
        // Validate API key
        if (empty($api_key) || !self::validate_key($api_key)) {
            return new WP_Error('unauthorized', 'Invalid API Key', ['status' => 401]);
        }

        // Call the logic adapted from `V2.php` request
        // Example for single request:
        return [
            'status' => 'success',
            'data' => 'Sample Response', // Replace with actual logic
        ];
    }

    private static function validate_key($api_key) {
        // Validate API key logic from V2.php
        return $api_key === 'valid_api_key_example'; // Replace with real logic
    }
}
