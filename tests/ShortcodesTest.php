<?php

use PHPUnit\Framework\TestCase;

class ShortcodesTest extends TestCase {

    protected function setUp(): void {
        require_once dirname(__DIR__) . '/includes/class-shortcodes.php';
        require_once dirname(__DIR__) . '/includes/class-everxp-request.php';
    }

    /**
     * Test render_shortcode with all valid attributes
     */
    public function test_render_shortcode_all_attributes() {
        $mock_request = $this->createMock(EverXP_Request::class);
        $mock_request->method('get_random_heading')->willReturn([
            'name' => base64_encode(json_encode('Test Heading'))
        ]);

        $shortcodes = new EverXP_Shortcodes();
        $shortcodes->set_request($mock_request);

        $output = $shortcodes->render_shortcode([
            'folder_id'   => 9,
            'lang'        => 'en',
            'style'       => 1,
            'min_l'       => 0,
            'max_l'       => 2000,
            'text_style'  => 'bold',
            'alignment'   => 'right',
            'color'       => '#FF5733',
            'size'        => '18px',
            'decoration'  => 'underline',
            'font_family' => 'Verdana, sans-serif',
            'effect'      => 'slide',
            'duration'    => 2000,
        ]);

        $this->assertStringContainsString('Test Heading', $output);
        $this->assertStringContainsString('text-align: right;', $output);
        $this->assertStringContainsString('color: #FF5733;', $output);
        $this->assertStringContainsString('font-size: 18px;', $output);
        $this->assertStringContainsString('text-decoration: underline;', $output);
        $this->assertStringContainsString('font-family: Verdana, sans-serif;', $output);
        $this->assertStringContainsString('data-effect="slide"', $output);
        $this->assertStringContainsString('data-duration="2000"', $output);
    }

    /**
     * Test render_shortcode with missing optional attributes
     */
    public function test_render_shortcode_missing_optional_attributes() {
        $mock_request = $this->createMock(EverXP_Request::class);
        $mock_request->method('get_random_heading')->willReturn([
            'name' => base64_encode(json_encode('Test Heading'))
        ]);

        $shortcodes = new EverXP_Shortcodes();
        $shortcodes->set_request($mock_request);

        $output = $shortcodes->render_shortcode([
            'folder_id' => 9,
        ]);

        $this->assertStringContainsString('Test Heading', $output);
        $this->assertStringContainsString('text-align: left;', $output);
        $this->assertStringContainsString('color: black;', $output);
        $this->assertStringContainsString('font-size: 16px;', $output);
        $this->assertStringContainsString('text-decoration: none;', $output);
        $this->assertStringContainsString('font-family: Arial, sans-serif;', $output);
        $this->assertStringContainsString('data-effect="fade"', $output);
        $this->assertStringContainsString('data-duration="1000"', $output);
    }

    /**
     * Test render_shortcode with missing required attributes
     */
    public function test_render_shortcode_missing_required_attributes() {
        $shortcodes = new EverXP_Shortcodes();

        $output = $shortcodes->render_shortcode([
            'lang' => 'en'
        ]);

        $this->assertStringContainsString('Error: folder_id is required.', $output);
    }

    /**
     * Test everxp_shortcode_multiple_handler with all attributes
     */
    public function test_everxp_shortcode_multiple_handler_all_attributes() {
        $mock_request = $this->createMock(EverXP_Request::class);
        $mock_request->method('get_multiple_headings')->willReturn([
            ['name' => base64_encode(json_encode('Heading 1'))],
            ['name' => base64_encode(json_encode('Heading 2'))],
        ]);

        $shortcodes = new EverXP_Shortcodes();
        $shortcodes->set_request($mock_request);

        $output = $shortcodes->everxp_shortcode_multiple_handler([
            'folder_id'        => 10,
            'lang'             => 'en',
            'style'            => 1,
            'min_l'            => 0,
            'max_l'            => 1500,
            'limit'            => 3,
            'display'          => 'line',
            'separator'        => ' | ',
            'alignment'        => 'center',
            'font_family'      => 'Courier New, monospace',
            'font_size'        => '14px',
            'text_color'       => '#007BFF',
            'background_color' => '#f0f0f0',
            'border_color'     => '#ccc',
            'border_radius'    => '10px',
            'padding'          => '20px',
            'scroll_speed'     => 15000,
            'rtl'              => true,
            'duration'         => 3000,
        ]);

        $this->assertStringContainsString('Heading 1', $output);
        $this->assertStringContainsString('Heading 2', $output);
        $this->assertStringContainsString('text-align: center;', $output);
        $this->assertStringContainsString('font-family: Courier New, monospace;', $output);
        $this->assertStringContainsString('font-size: 14px;', $output);
        $this->assertStringContainsString('color: #007BFF;', $output);
        $this->assertStringContainsString('background-color: #f0f0f0;', $output);
        $this->assertStringContainsString('border: 1px solid #ccc;', $output);
        $this->assertStringContainsString('border-radius: 10px;', $output);
        $this->assertStringContainsString('padding: 20px;', $output);
        $this->assertStringContainsString('rtl', $output);
    }

    /**
     * Test everxp_shortcode_multiple_handler with missing required attributes
     */
    public function test_everxp_shortcode_multiple_handler_missing_required() {
        $shortcodes = new EverXP_Shortcodes();

        $output = $shortcodes->everxp_shortcode_multiple_handler([]);

        $this->assertStringContainsString('Error: folder_id is required.', $output);
    }

    /**
     * Test everxp_shortcode_multiple_handler with invalid display type
     */
    public function test_everxp_shortcode_multiple_handler_invalid_display() {
        $mock_request = $this->createMock(EverXP_Request::class);
        $mock_request->method('get_multiple_headings')->willReturn([
            ['name' => base64_encode(json_encode('Heading 1'))]
        ]);

        $shortcodes = new EverXP_Shortcodes();
        $shortcodes->set_request($mock_request);

        $output = $shortcodes->everxp_shortcode_multiple_handler([
            'folder_id' => 10,
            'display'   => 'invalid_display',
        ]);

        $this->assertStringContainsString('Heading 1', $output);
    }
}
