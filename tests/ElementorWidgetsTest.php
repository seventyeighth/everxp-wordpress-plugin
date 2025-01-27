<?php

use PHPUnit\Framework\TestCase;

class ElementorWidgetsTest extends TestCase {

    protected function setUp(): void {
        require_once dirname(__DIR__) . '/includes/class-everxp-elementor-widget.php';
        require_once dirname(__DIR__) . '/includes/class-everxp-multiple-elementor-widget.php';
        require_once dirname(__DIR__) . '/includes/class-everxp-request.php';
    }

    /**
     * Helper class to test protected render() method
     */
    private function render_widget_output($widget, $settings) {
        // Set the settings for the widget
        $widget->method('get_settings_for_display')->willReturn($settings);

        // Start output buffer
        ob_start();
        $widget->render();
        return ob_get_clean();
    }

    /**
     * Test single Elementor widget rendering with valid attributes
     */
    public function test_single_elementor_widget_valid_attributes() {
        $mock_request = $this->createMock(EverXP_Request::class);
        $mock_request->method('get_random_heading')->willReturn([
            'name' => base64_encode(json_encode('Test Heading'))
        ]);

        $widget = $this->getMockBuilder(EverXP_Elementor_Widget::class)
            ->onlyMethods(['get_settings_for_display'])
            ->getMock();

        $widget->request = $mock_request; // Inject the mock request

        $settings = [
            'folder_id'   => 9,
            'lang'        => 'en',
            'style'       => 1,
            'min_l'       => 0,
            'max_l'       => 500,
            'alignment'   => 'left',
            'color'       => 'black',
            'size'        => '20px',
            'effect'      => 'fade',
            'duration'    => 1000,
        ];

        $output = $this->render_widget_output($widget, $settings);

        $this->assertStringContainsString('Test Heading', $output);
        $this->assertStringContainsString('text-align: left;', $output);
        $this->assertStringContainsString('color: black;', $output);
        $this->assertStringContainsString('font-size: 20px;', $output);
        $this->assertStringContainsString('data-effect="fade"', $output);
        $this->assertStringContainsString('data-duration="1000"', $output);
    }


    /**
     * Test multiple Elementor widget rendering with valid attributes
     */
    public function test_multiple_elementor_widget_valid_attributes() {
        $mock_request = $this->createMock(EverXP_Request::class);
        $mock_request->method('get_multiple_headings')->willReturn([
            ['name' => base64_encode(json_encode('Heading 1'))],
            ['name' => base64_encode(json_encode('Heading 2'))],
        ]);

        $widget = $this->getMockBuilder(EverXP_Multiple_Elementor_Widget::class)
            ->onlyMethods(['get_settings_for_display'])
            ->getMock();

        $widget->request = $mock_request;

        $settings = [
            'folder_id'        => 10,
            'lang'             => 'en',
            'style'            => 1,
            'limit'            => 5,
            'min_l'            => 0,
            'max_l'            => 1500,
            'display'          => 'line',
            'separator'        => ' | ',
            'alignment'        => 'right',
            'font_family'      => 'Arial, sans-serif',
            'font_size'        => '14px',
            'text_color'       => '#FF0000',
            'background_color' => '#FFFFFF',
            'border_color'     => '#CCCCCC',
            'border_radius'    => '10px',
            'padding'          => '15px',
        ];

        $output = $this->render_widget_output($widget, $settings);

        $this->assertStringContainsString('Heading 1', $output);
        $this->assertStringContainsString('Heading 2', $output);
        $this->assertStringContainsString('text-align: right;', $output);
        $this->assertStringContainsString('font-family: Arial, sans-serif;', $output);
        $this->assertStringContainsString('font-size: 14px;', $output);
        $this->assertStringContainsString('color: #FF0000;', $output);
        $this->assertStringContainsString('background-color: #FFFFFF;', $output);
        $this->assertStringContainsString('border: 1px solid #CCCCCC;', $output);
        $this->assertStringContainsString('border-radius: 10px;', $output);
        $this->assertStringContainsString('padding: 15px;', $output);
    }



    /**
     * Test multiple Elementor widget rendering with no Folder ID
     */
    public function test_multiple_elementor_widget_no_folder() {
        $mock_request = $this->createMock(EverXP_Request::class);
        $mock_request->method('get_multiple_headings')->willReturn([
            ['name' => base64_encode(json_encode('Error: Folder ID is required.'))],
        ]);

        $widget = $this->getMockBuilder(EverXP_Multiple_Elementor_Widget::class)
            ->onlyMethods(['get_settings_for_display'])
            ->getMock();

        $widget->request = $mock_request;

        $settings = [
            'padding'          => '15px',
        ];

        $output = $this->render_widget_output($widget, $settings);

        $this->assertStringContainsString('Error: Folder ID is required.', $output);
    }

    /**
     * Test multiple Elementor widget rendering with no Limit
     */
    public function test_multiple_elementor_widget_no_limit() {
        $mock_request = $this->createMock(EverXP_Request::class);
        $mock_request->method('get_multiple_headings')->willReturn([
            ['name' => base64_encode(json_encode('Error: Folder ID is required.'))],
        ]);

        $widget = $this->getMockBuilder(EverXP_Multiple_Elementor_Widget::class)
            ->onlyMethods(['get_settings_for_display'])
            ->getMock();

        $widget->request = $mock_request;

        $settings = [
            'folder_id'          => 10,
        ];

        $output = $this->render_widget_output($widget, $settings);

        $this->assertStringContainsString('Error: Limit results is required.', $output);
    }


}
