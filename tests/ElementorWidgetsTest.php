<?php

use PHPUnit\Framework\TestCase;

class ElementorWidgetsTest extends TestCase {

    protected function setUp(): void {
        require_once dirname(__DIR__) . '/includes/class-everxp-elementor-widget.php';
        require_once dirname(__DIR__) . '/includes/class-everxp-multiple-elementor-widget.php';
        require_once dirname(__DIR__) . '/includes/class-everxp-request.php';
    }

    /**
     * Test single elementor widget rendering with valid attributes.
     */
    public function test_single_elementor_widget_valid_attributes() {
        // Mock EverXP_Request
        $mock_request = $this->createMock(EverXP_Request::class);
        $mock_request->method('get_random_heading')->willReturn([
            'name' => base64_encode(json_encode('Test Heading'))
        ]);

        // Create an instance of the Elementor widget and inject the mock
        $widget = new EverXP_Elementor_Widget();
        $widget->set_request($mock_request);

        // Simulate attributes
        $attributes = [
            'folder_id'   => 9,
            'lang'        => 'en',
            'style'       => 1,
            'min_l'       => 0,
            'max_l'       => 500,
            'alignment'   => 'center',
            'text_color'  => '#0000FF',
            'font_size'   => '20px',
            'effect'      => 'fade',
            'duration'    => 1500
        ];

        $output = $widget->render($attributes);

        // Assertions
        $this->assertStringContainsString('Test Heading', $output);
        $this->assertStringContainsString('text-align: center;', $output);
        $this->assertStringContainsString('color: #0000FF;', $output);
        $this->assertStringContainsString('font-size: 20px;', $output);
        $this->assertStringContainsString('data-effect="fade"', $output);
        $this->assertStringContainsString('data-duration="1500"', $output);
    }

    /**
     * Test single elementor widget rendering with missing attributes.
     */
    public function test_single_elementor_widget_missing_attributes() {
        $mock_request = $this->createMock(EverXP_Request::class);
        $mock_request->method('get_random_heading')->willReturn([
            'name' => base64_encode(json_encode('Default Heading'))
        ]);

        $widget = new EverXP_Elementor_Widget();
        $widget->set_request($mock_request);

        // Call with minimal attributes
        $attributes = [
            'folder_id' => 9,
        ];

        $output = $widget->render($attributes);

        // Assertions
        $this->assertStringContainsString('Default Heading', $output);
        $this->assertStringContainsString('text-align: left;', $output);
        $this->assertStringContainsString('color: black;', $output);
        $this->assertStringContainsString('font-size: 16px;', $output);
        $this->assertStringContainsString('data-effect="fade"', $output);
        $this->assertStringContainsString('data-duration="1000"', $output);
    }

    /**
     * Test multiple elementor widget rendering with valid attributes.
     */
    public function test_multiple_elementor_widget_valid_attributes() {
        $mock_request = $this->createMock(EverXP_Request::class);
        $mock_request->method('get_multiple_headings')->willReturn([
            ['name' => base64_encode(json_encode('Heading 1'))],
            ['name' => base64_encode(json_encode('Heading 2'))],
        ]);

        $widget = new EverXP_Multiple_Elementor_Widget();
        $widget->set_request($mock_request);

        $attributes = [
            'folder_id'        => 10,
            'lang'             => 'en',
            'style'            => 1,
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

        $output = $widget->render($attributes);

        // Assertions
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
     * Test multiple elementor widget rendering with missing required attributes.
     */
    public function test_multiple_elementor_widget_missing_required_attributes() {
        $widget = new EverXP_Multiple_Elementor_Widget();

        $output = $widget->render([
            'lang' => 'en'
        ]);

        $this->assertStringContainsString('Error: folder_id is required.', $output);
    }

    /**
     * Test invalid display type for multiple elementor widget.
     */
    public function test_multiple_elementor_widget_invalid_display_type() {
        $mock_request = $this->createMock(EverXP_Request::class);
        $mock_request->method('get_multiple_headings')->willReturn([
            ['name' => base64_encode(json_encode('Heading 1'))]
        ]);

        $widget = new EverXP_Multiple_Elementor_Widget();
        $widget->set_request($mock_request);

        $output = $widget->render([
            'folder_id' => 10,
            'display'   => 'invalid_type',
        ]);

        $this->assertStringContainsString('Heading 1', $output);
    }
}
