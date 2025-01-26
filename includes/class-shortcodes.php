<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EverXP_Shortcodes {


    private $request; // Define a private property for the request object

    public function __construct() {
        $this->request = new EverXP_Request(); // Initialize with the actual request class
    }
    
    public static function init() {
        add_shortcode('everxp_shortcode', [self::class, 'render_shortcode']);
        add_shortcode('everxp_shortcode_multiple', [self::class, 'everxp_shortcode_multiple_handler']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_scripts']);
    }

    public static function enqueue_scripts() {
        // Enqueue custom CSS and JS
        wp_enqueue_style('everxp-shortcode-style', plugins_url('../assets/css/everxp-style.css', __FILE__));
        wp_enqueue_style('everxp-shortcode-multiple-style', plugins_url('../assets/css/everxp-multiple-rows.css', __FILE__));

        wp_enqueue_script('everxp-shortcode-script', plugins_url('../assets/js/everxp-script.js', __FILE__), ['jquery'], null, true);
        wp_enqueue_script('everxp-shortcode-multiple-script', plugins_url('../assets/js/everxp-multiple-rows.js', __FILE__), ['jquery'], null, true);
    }

    public static function render_shortcode($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts([
            'folder_id'    => null,
            'lang'         => 'en',
            'style'        => 1,
            'min_l'        => 0,
            'max_l'        => 5000,
            'text_style'   => 'default',
            'alignment'    => 'left',
            'color'        => 'black',
            'size'         => '16px',
            'decoration'   => 'none',
            'font_family'  => 'Arial, sans-serif',
            'effect'       => 'fade',
            'duration'     => 1000
        ], $atts);

        if (empty($atts['folder_id'])) {
            return '<p>Error: folder_id is required.</p>';
        }

        // Require the request class
        require_once plugin_dir_path(__FILE__) . 'class-everxp-request.php';
        $request = new EverXP_Request();

        // Fetch data
        $result = $request->get_random_heading([
            'folder_id' => (int) $atts['folder_id'],
            'lang'      => sanitize_text_field($atts['lang']),
            'style'     => (int) $atts['style'],
            'min_l'     => (int) $atts['min_l'],
            'max_l'     => (int) $atts['max_l']
        ]);

        if (!$result) {
            return '<p>No matching data found.</p>';
        }

        // Inline style
        $style = sprintf(
            'text-align: %s; color: %s; font-size: %s; text-decoration: %s; font-family: %s;',
            esc_attr($atts['alignment']),
            esc_attr($atts['color']),
            esc_attr($atts['size']),
            esc_attr($atts['decoration']),
            esc_attr($atts['font_family'])
        );

        // Generate output
        $decrypted_heading = trim(EverXP_Encryption_Helper::decrypt_data($result['name']), '"');
        $text_style = self::apply_text_style($atts['text_style'], esc_html($decrypted_heading));

        return sprintf(
            '<div class="everxp-text-output">
                <p class="everxp-animated" data-effect="%s" data-duration="%d" style="%s">%s</p>
            </div>',
            esc_attr($atts['effect']),
            esc_attr($atts['duration']),
            $style,
            $text_style
        );
    }

    public static function everxp_shortcode_multiple_handler($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts([
            'folder_id'        => null,
            'lang'             => 'en',
            'style'            => 1,
            'min_l'            => 0,
            'max_l'            => 5000,
            'limit'            => 5,
            'display'          => 'box', // Default display type
            'separator'        => ' | ',
            'alignment'        => 'left',
            'font_family'      => 'Arial, sans-serif',
            'font_size'        => '16px',
            'text_color'       => '#000',
            'background_color' => '#f9f9f9',
            'border_color'     => '#ddd',
            'border_radius'    => '5px',
            'padding'          => '15px',
            'scroll_speed'     => 20000, // Ticker speed in milliseconds
            'rtl'              => false,
            'effect'           => 'none',
            'duration'         => 1000, // Animation duration
            'remove_default_styling' => false, // Default is to apply styling
        ], $atts);


        if (empty($atts['folder_id'])) {
            return '<p>Error: folder_id is required.</p>';
        }

        // Fetch data using the request class
        require_once plugin_dir_path(__FILE__) . 'class-everxp-request.php';
        $request = new EverXP_Request();

        $results = $request->get_multiple_headings([
            'folder_id' => (int) $atts['folder_id'],
            'lang'      => sanitize_text_field($atts['lang']),
            'style'     => (int) $atts['style'],
            'min_l'     => (int) $atts['min_l'],
            'max_l'     => (int) $atts['max_l'],
            'limit'     => (int) $atts['limit']
        ]);

        if (empty($results)) {
            return '<p>No matching data found.</p>';
        }

        // Prepare styles
        $custom_styles = sprintf(
            'text-align: %s; font-family: %s; font-size: %s; color: %s; background-color: %s; border: 1px solid %s; border-radius: %s; padding: %s;',
            esc_attr($atts['alignment']),
            esc_attr($atts['font_family']),
            esc_attr($atts['font_size']),
            esc_attr($atts['text_color']),
            esc_attr($atts['background_color']),
            esc_attr($atts['border_color']),
            esc_attr($atts['border_radius']),
            esc_attr($atts['padding'])
        );


        $remove_styling  = filter_var($atts['remove_default_styling'], FILTER_VALIDATE_BOOLEAN);

        // Conditional container class and inline styles
        $container_class = $remove_styling ? '' : 'everxp-multi-rows-container';
        $inline_styles   = $remove_styling ? '' : esc_attr($custom_styles);


        $output = '<div class="'.$container_class .' '. ($atts['rtl'] ? 'rtl' : '') . '" style="' . esc_attr($custom_styles) . '">';

        switch ($atts['display']) {
            case 'line':
                $lines = [];
                foreach ($results as $row) {
                    $lines[] = trim(EverXP_Encryption_Helper::decrypt_data($row['name']), '"');
                }
                $output .= '<p>' . implode(esc_html($atts['separator']), array_map('esc_html', $lines)) . '</p>';
                if (!$remove_styling) {
                    $output .= '<div class="everxp-credit">
                                    Powered by <a href="https://everxp.com" target="_blank">EverXP</a>
                                </div>';
                }
                break;

            case 'slider':
                $output .= '<div class="everxp-multi-slider">';
                foreach ($results as $row) {
                    $text = trim(EverXP_Encryption_Helper::decrypt_data($row['name']), '"');
                    $output .= '<div class="multi-slide">' . esc_html($text) . '</div>';
                }
                $output .= '</div>';
                if (!$remove_styling) {
                    $output .= '<div class="everxp-credit">
                                    Powered by <a href="https://everxp.com" target="_blank">EverXP</a>
                                </div>';
                }
                break;

            case 'news_ticker':
                // Horizontal scrolling news ticker
                $output .= '<div class="everxp-news-ticker">';
                $output .= '<div class="ticker-content ' . esc_attr($atts['alignment']) . '">';
                foreach ($results as $row) {
                    $text = trim(EverXP_Encryption_Helper::decrypt_data($row['name']), '"');
                    $output .= '<span>' . esc_html($text) . '</span> ';
                }
                $output .= '</div></div>';
                if (!$remove_styling) {
                    $output .= '<div class="everxp-credit">
                                    Powered by <a href="https://everxp.com" target="_blank">EverXP</a>
                                </div>';
                }
                break;

            case 'news_ticker_vertical':
                // Vertical scrolling news ticker
                $output .= '<div class="everxp-news-ticker vertical">';
                $output .= '<div class="ticker-content ' . esc_attr($atts['alignment']) . '">';
                foreach ($results as $row) {
                    $text = trim(EverXP_Encryption_Helper::decrypt_data($row['name']), '"');
                    $output .= '<p>' . esc_html($text) . '</p>';
                }
                $output .= '</div></div>';
                if (!$remove_styling) {
                    $output .= '<div class="everxp-credit">
                                    Powered by <a href="https://everxp.com" target="_blank">EverXP</a>
                                </div>';
                }
                break;

            default:
                // Default static box
                $output .= '<div class="everxp-multi-box">';
                foreach ($results as $row) {
                    $text = trim(EverXP_Encryption_Helper::decrypt_data($row['name']), '"');
                    $output .= '<p>' . esc_html($text) . '</p>';
                }
                $output .= '</div>';
                if (!$remove_styling) {
                    $output .= '<div class="everxp-credit">
                                    Powered by <a href="https://everxp.com" target="_blank">EverXP</a>
                                </div>';
                }
                break;
        }

        $output .= '</div>';

        return $output;
    }




    private static function apply_text_style($style, $text) {
        switch ($style) {
            case 'bold':
                return '<strong>' . $text . '</strong>';
            case 'italic':
                return '<em>' . $text . '</em>';
            case 'highlight':
                return '<span style="background-color: yellow;">' . $text . '</span>';
            case 'default':
            default:
                return $text;
        }
    }
}

EverXP_Shortcodes::init();
