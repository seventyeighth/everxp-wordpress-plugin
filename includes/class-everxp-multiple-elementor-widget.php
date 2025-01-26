<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class EverXP_Multiple_Elementor_Widget extends Widget_Base {

    private $request;

    public function __construct() {
        parent::__construct();
        $this->request = new EverXP_Request();
    }

    // Add a setter for testing purposes
    public function set_request($request) {
        $this->request = $request;
    }

    public function get_name() {
        return 'everxp_multiple_elementor_widget';
    }

    public function get_title() {
        return __('EverXP Multiple Rows', 'everxp');
    }

    public function get_icon() {
        return 'eicon-post-list';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function _register_controls() {
        global $wpdb;

        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'everxp'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        // Folder Selection Control
        $folders = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}user_banks WHERE active = 1", ARRAY_A);
        $folder_options = ['' => __('Select a Folder', 'everxp')];
        foreach ($folders as $folder) {
            $folder_options[$folder['id']] = $folder['name'];
        }

        $this->add_control(
            'folder_id',
            [
                'label' => __('Folder', 'everxp'),
                'type' => Controls_Manager::SELECT,
                'options' => $folder_options,
                'default' => '',
                'description' => __('Select a folder to filter headings.', 'everxp'),
            ]
        );

        // Language Control
        $this->add_control(
            'lang',
            [
                'label' => __('Language', 'everxp'),
                'type' => Controls_Manager::TEXT,
                'default' => 'en',
            ]
        );

        // Style Control
        $this->add_control(
            'style',
            [
                'label' => __('Style', 'everxp'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '1' => __('Pure (Default)', 'everxp'),
                    '2' => __('Playful', 'everxp'),
                    '3' => __('Guiding', 'everxp'),
                ],
                'default' => '1',
            ]
        );

        // Display Control
        $this->add_control(
            'display',
            [
                'label' => __('Display Mode', 'everxp'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'line' => __('Line', 'everxp'),
                    'slider' => __('Slider', 'everxp'),
                    'news_ticker' => __('News Ticker (Horizontal)', 'everxp'),
                    'news_ticker_vertical' => __('News Ticker (Vertical)', 'everxp'),
                ],
                'default' => 'line',
                'description' => __('Choose how to display the content.', 'everxp'),
            ]
        );

        // Limit Control
        $this->add_control(
            'limit',
            [
                'label' => __('Limit', 'everxp'),
                'type' => Controls_Manager::NUMBER,
                'default' => 5,
                'description' => __('Set the maximum number of rows to display.', 'everxp'),
            ]
        );

        // Min Length Control
        $this->add_control(
            'min_l',
            [
                'label' => __('Minimum Length', 'everxp'),
                'type' => Controls_Manager::NUMBER,
                'default' => 0,
            ]
        );

        // Max Length Control
        $this->add_control(
            'max_l',
            [
                'label' => __('Maximum Length', 'everxp'),
                'type' => Controls_Manager::NUMBER,
                'default' => 5000,
            ]
        );

        // Alignment Control
        $this->add_control(
            'alignment',
            [
                'label' => __('Alignment', 'everxp'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'left' => __('Left', 'everxp'),
                    'center' => __('Center', 'everxp'),
                    'right' => __('Right', 'everxp'),
                ],
                'default' => 'left',
            ]
        );


        // RTL Control
        $this->add_control(
            'rtl',
            [
                'label' => __('RTL', 'everxp'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'false' => __('false', 'everxp'),
                    'true' => __('true', 'everxp'),
                ],
                'default' => 'left',
            ]
        );


        // Font Family Control
        $this->add_control(
            'font_family',
            [
                'label' => __('Font Family', 'everxp'),
                'type' => Controls_Manager::TEXT,
                'default' => 'Arial, sans-serif',
            ]
        );

        // Font Size Control
        $this->add_control(
            'size',
            [
                'label' => __('Font Size', 'everxp'),
                'type' => Controls_Manager::TEXT,
                'default' => '16px',
            ]
        );

        // Text Color Control
        $this->add_control(
            'color',
            [
                'label' => __('Text Color', 'everxp'),
                'type' => Controls_Manager::COLOR,
                'default' => '#000000',
            ]
        );

        // Background Color Control
        $this->add_control(
            'background_color',
            [
                'label' => __('Background Color', 'everxp'),
                'type' => Controls_Manager::COLOR,
                'default' => '#f9f9f9',
            ]
        );

        // Border Color Control
        $this->add_control(
            'border_color',
            [
                'label' => __('Border Color', 'everxp'),
                'type' => Controls_Manager::COLOR,
                'default' => '#ddd',
            ]
        );

        // Border Radius Control
        $this->add_control(
            'border_radius',
            [
                'label' => __('Border Radius', 'everxp'),
                'type' => Controls_Manager::TEXT,
                'default' => '5px',
            ]
        );

        // Padding Control
        $this->add_control(
            'padding',
            [
                'label' => __('Padding', 'everxp'),
                'type' => Controls_Manager::TEXT,
                'default' => '15px',
            ]
        );


        // Animation Duration Control
        $this->add_control(
            'duration',
            [
                'label' => __('Animation Duration', 'everxp'),
                'type' => Controls_Manager::NUMBER,
                'default' => 10000,
                'description' => __('Duration in milliseconds.', 'everxp'),
            ]
        );


        // Remove Default Styling Control
        $this->add_control(
            'remove_default_styling',
            [
                'label' => __('Remove Default Styling', 'everxp'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'everxp'),
                'label_off' => __('No', 'everxp'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Check to remove the default container styling and EverXP credit.', 'everxp'),
            ]
        );

        $this->end_controls_section();
    }

  

    public function render() {
        $settings = $this->get_settings_for_display() ;

        if (empty($settings) || !is_array($settings)) {
            echo '<p>Error: Missing or invalid widget settings.</p>';
            return;
        }

        // Access settings safely
        $folder_id = isset($settings['folder_id']) ? $settings['folder_id'] : null;
        if (empty($folder_id)) {
            echo '<p>Error: folder_id is required.</p>';
            return;
        }

        require_once plugin_dir_path(__FILE__) . 'class-everxp-request.php';
        $this->request = new EverXP_Request(); 

        // Apply defaults to other settings
        $folder_id        = $settings['folder_id'] ?? 0;
        $lang             = $settings['lang'] ?? 'en';
        $style            = $settings['style'] ?? 1;
        $min_l            = $settings['min_l'] ?? 0;
        $max_l            = $settings['max_l'] ?? 99999;
        $limit            = $settings['limit'] ?? '5';
        $alignment        = $settings['alignment'] ?? 'left';
        $font_family      = $settings['font_family'] ?? 'Arial, sans-serif';
        $font_size        = $settings['size'] ?? '16px';
        $color            = $settings['color'] ?? '#000000';
        $background_color = $settings['background_color'] ?? '#ffffff';
        $border_color     = $settings['border_color'] ?? 'transparent';
        $border_radius    = $settings['border_radius'] ?? '3px';
        $padding          = $settings['padding'] ?? '15px';
        $rtl              = $settings['rtl'] ?? false;

        // Fetch data
        $results = $request->get_multiple_headings([
            'folder_id' => (int) $folder_id,
            'lang'      => sanitize_text_field($lang),
            'style'     => (int) $style,
            'min_l'     => (int) $min_l,
            'max_l'     => (int) $max_l,
            'limit'     => (int) $limit,
        ]);

        if (empty($results)) {
            echo '<p>No matching data found.</p>';
            return;
        }

        // Prepare styles
        $custom_styles = sprintf(
            'text-align: %s; font-family: %s; font-size: %s; color: %s; background-color: %s; border: 1px solid %s; border-radius: %s; padding: %s; rtl: %s;',
            esc_attr($alignment),
            esc_attr($font_family),
            esc_attr($font_size),
            esc_attr($color),
            esc_attr($background_color),
            esc_attr($border_color),
            esc_attr($border_radius),
            esc_attr($padding),
            esc_attr($rtl),
        );


        // Check if default styling should be removed
        $remove_styling  = $settings['remove_default_styling'] === 'yes';

        // Conditional container class and inline styles
        $container_class = $remove_styling ? '' : 'everxp-multi-rows-container';
        $inline_styles   = $remove_styling ? '' : esc_attr($custom_styles);

        // Output container with appropriate display type
        $output = '<div class="'.$container_class.'" style="' . esc_attr($custom_styles) . '">';

        switch ($settings['display']) {
            case 'line':
                // One-liner with separator
                $lines = [];
                foreach ($results as $row) {
                    $lines[] = trim(EverXP_Encryption_Helper::decrypt_data($row['name']), '"');
                }
                $output .= sprintf(
                    '<p data-duration="%d" style="%s">%s</p>',
                    esc_attr($settings['duration']),
                    esc_attr($custom_styles),
                    esc_html(implode($settings['separator'], $lines))
                );
                break;

            case 'slider':
                // Sliding row text
                $output .= '<div class="everxp-multi-slider" data-animation="' . esc_attr($settings['display']) . '">';
                foreach ($results as $row) {
                    $text = trim(EverXP_Encryption_Helper::decrypt_data($row['name']), '"');
                    $output .= '<div class="multi-slide">' . esc_html($text) . '</div>';
                }
                $output .= '</div>';
                break;

            case 'news_ticker':
                // Horizontal scrolling news ticker
                $output .= '<div class="everxp-news-ticker ' . ($settings['rtl'] ? 'rtl' : '') . '" data-duration="'.$settings['duration'].'">';
                $output .= '<div class="ticker-content ' . esc_attr($settings['alignment']) . '">';
                foreach ($results as $row) {
                    $text = trim(EverXP_Encryption_Helper::decrypt_data($row['name']), '"');
                    $output .= '<span>' . esc_html($text) . '</span> ';
                }
                $output .= '</div></div>';
                break;

            case 'news_ticker_vertical':
                // Vertical scrolling news ticker
                $output .= '<div class="everxp-news-ticker vertical ' . ($settings['rtl'] ? 'rtl' : '') . '" data-duration="'.$settings['duration'].'">';
                $output .= '<div class="ticker-content ' . esc_attr($settings['alignment']) . '">';
                foreach ($results as $row) {
                    $text = trim(EverXP_Encryption_Helper::decrypt_data($row['name']), '"');
                    $output .= '<p>' . esc_html($text) . '</p>';
                }
                $output .= '</div></div>';
                break;

            default:
                // Default static box
                foreach ($results as $row) {
                    $text = trim(EverXP_Encryption_Helper::decrypt_data($row['name']), '"');
                    $output .= sprintf(
                        '<p data-duration="%d">%s</p>',
                        esc_attr($settings['duration']),
                        esc_html($text)
                    );
                }
                break;
        }

        // Add EverXP credit if styling is not removed
        if (!$remove_styling) {
            $output .= '<div class="everxp-credit">
                            Powered by <a href="https://everxp.com" target="_blank">EverXP</a>
                        </div>';
        }

        $output .= '</div>';

        echo $output;
    }

}
