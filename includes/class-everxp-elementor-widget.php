<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class EverXP_Elementor_Widget extends Widget_Base {

    private $request;

    public function set_request($request) {
        $this->request = $request;
    }

    public function get_name() {
        return 'everxp_elementor_widget';
    }

    public function get_title() {
        return __('EverXP Widget', 'everxp');
    }

    public function get_icon() {
        return 'eicon-text';
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
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    '1' => __('Pure (Default)', 'everxp'),
                    '2' => __('Playful', 'everxp'),
                    '3' => __('Guiding', 'everxp'),
                ],
                'default' => '1', // Default value
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

        // Color Control
        $this->add_control(
            'color',
            [
                'label' => __('Text Color', 'everxp'),
                'type' => Controls_Manager::COLOR,
                'default' => '#000000',
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

        // Text Decoration Control
        $this->add_control(
            'decoration',
            [
                'label' => __('Text Decoration', 'everxp'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'none' => __('None', 'everxp'),
                    'underline' => __('Underline', 'everxp'),
                    'line-through' => __('Line Through', 'everxp'),
                ],
                'default' => 'none',
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

        // Animation Effect Control
        $this->add_control(
            'effect',
            [
                'label' => __('Animation Effect', 'everxp'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'none' => __('None', 'everxp'),
                    'fade' => __('Fade', 'everxp'),
                    'slide' => __('Slide', 'everxp'),
                    'bounce' => __('Bounce', 'everxp'),
                ],
                'default' => 'none',
            ]
        );

        // Animation Duration Control
        $this->add_control(
            'duration',
            [
                'label' => __('Animation Duration', 'everxp'),
                'type' => Controls_Manager::NUMBER,
                'default' => 1000,
                'description' => __('Duration in milliseconds.', 'everxp'),
            ]
        );

        $this->end_controls_section();
    }


    public function render() {
        $settings = $this->get_settings_for_display();

        // Check if settings are valid
        if (empty($settings) || !is_array($settings)) {
            echo '<p>Error: Missing or invalid widget settings.</p>';
            return;
        }

        if (empty($settings['folder_id'])) {
            echo '<p>Error: Folder ID is required.</p>';
            return;
        }

        // Include the request class
        require_once plugin_dir_path(__FILE__) . 'class-everxp-request.php';
        $request = $this->request ?? new EverXP_Request();

        // Fetch data
        $result = $request->get_random_heading([
            'folder_id' => (int) $settings['folder_id'],
            'lang'      => sanitize_text_field($settings['lang']),
            'style'     => (int) $settings['style'],
            'min_l'     => (int) $settings['min_l'],
            'max_l'     => (int) $settings['max_l'],
        ]);

        if (!$result) {
            var_dump($result);
            echo '<p>No matching data found.</p>';
            return;
        }

        $decrypted_heading = trim(EverXP_Encryption_Helper::decrypt_data($result['name']), '"');

        // Inline styles
        $style = sprintf(
            'text-align: %s; color: %s; font-size: %s; text-decoration: %s; font-family: %s;',
            esc_attr($settings['alignment']),
            esc_attr($settings['color']),
            esc_attr($settings['size']),
            esc_attr($settings['decoration']),
            esc_attr($settings['font_family'])
        );

        // Handle effect: If 'none', output the text without animation classes
        if ($settings['effect'] === 'none') {
            echo sprintf(
                '<div class="everxp-text-output">
                    <p style="%s">%s</p>
                </div>',
                $style,
                esc_html($decrypted_heading)
            );
        } else {
            echo sprintf(
                '<div class="everxp-text-output">
                    <p class="everxp-animated" data-effect="%s" data-duration="%d" style="%s">%s</p>
                </div>',
                esc_attr($settings['effect']),
                esc_attr($settings['duration']),
                $style,
                esc_html($decrypted_heading)
            );
        }
    }

}
