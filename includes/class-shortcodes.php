<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EverXP_Shortcodes {

    private $request;

    public function __construct() {
        $this->request = new EverXP_Request();
    }

    public static function init() {
        add_shortcode('everxp_shortcode', [self::class, 'render_shortcode']);
        add_shortcode('everxp_shortcode_multiple', [self::class, 'render_multiple_shortcode']);
    }

    public static function render_shortcode($atts) {

        // Parse attributes
        $atts = shortcode_atts([
            'folder_id' => null,
            'lang'      => 'en',
            'style'     => 1,
            'min_l'     => 0,
            'max_l'     => 9999999
        ], $atts);

        if (empty($atts['folder_id'])) {
            return '<p>Error: folder_id is required.</p>';
        }

        // Require the request class
        require_once plugin_dir_path(__FILE__) . 'class-everxp-request.php';
        $request = new EverXP_Request();

        // Fetch single sentence
        $result = $request->get_random_heading([
            'folder_id' => (int) $atts['folder_id'],
            'lang'      => sanitize_text_field($atts['lang']),
            'style'     => (int) $atts['style'],
            'min_l'     => (int) $atts['min_l'],
            'max_l'     => (int) $atts['max_l']
        ]);

        // Generate a unique cache-buster
        $cache_buster = time() . rand(1000, 9999);

        // If no result is found, return a unique "No Data" message with a cache buster
        if (!$result) {
            return sprintf(
                '<span class="everxp-text-output no-data" data-cache-buster="%s">
                    <p>No matching data found.</p>
                </span>',
                esc_attr($cache_buster)
            );
        }

        return sprintf(
            '<span class="everxp-text-output" data-cache-buster="%s" data-folder-id="%s" data-heading-id="%s">
                %s
            </span>',
            esc_attr($cache_buster),
            esc_attr($result['folder_id']),
            esc_attr($result['heading_id']), // Include heading ID
            stripslashes(htmlspecialchars_decode(trim(EverXP_Encryption_Helper::decrypt_data($result['name']), '"')))
        );

    }

    public static function render_multiple_shortcode($atts) { 

        // Parse attributes
        $atts = shortcode_atts([
            'folder_id' => null,
            'lang'      => 'en',
            'style'     => 1,
            'min_l'     => 0,
            'max_l'     => 99999999,
            'limit'     => 5,
            'separator' => ' | '
        ], $atts);

        if (empty($atts['folder_id'])) {
            return '<p>Error: folder_id is required.</p>';
        }

        // Require the request class
        require_once plugin_dir_path(__FILE__) . 'class-everxp-request.php';
        $request = new EverXP_Request();

        // Fetch multiple sentences
        $results = $request->get_multiple_headings([
            'folder_id' => (int) $atts['folder_id'],
            'lang'      => sanitize_text_field($atts['lang']),
            'style'     => (int) $atts['style'],
            'min_l'     => (int) $atts['min_l'],
            'max_l'     => (int) $atts['max_l'],
            'limit'     => (int) $atts['limit']
        ]);

        // Generate a unique cache-buster
        $cache_buster = time() . rand(1000, 9999);

        // If no result is found, return a unique "No Data" message with a cache buster
        if (empty($results)) {
            return sprintf(
                '<span class="everxp-multi-text-output no-data" data-cache-buster="%s">
                    <p>No matching data found.</p>
                </span>',
                esc_attr($cache_buster)
            );
        }

        $sentences = [];
        $heading_ids = [];
        foreach ($results as $row) {
            $sentences[] = stripslashes(htmlspecialchars_decode(trim(EverXP_Encryption_Helper::decrypt_data($row['name']), '"')));
            $heading_ids[] = esc_attr($row['heading_id']);
        }

        return sprintf(
            '<span class="everxp-multi-text-output" data-cache-buster="%s" data-folder-id="%s" data-heading-id="%s">
                %s
            </span>',
            esc_attr($cache_buster),
            esc_attr($atts['folder_id']),
            implode(',', $heading_ids), // Include all heading IDs
            implode(esc_html($atts['separator']), $sentences)
        );
    }
}

EverXP_Shortcodes::init();
