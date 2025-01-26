<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EverXP_Settings {
    public static function init() {
        // Hook to create the admin menu
        add_action('admin_menu', [self::class, 'add_admin_menu']);
    }

	public static function add_admin_menu() {
	    // Add the main menu page
	    add_menu_page(
	        'EverXP',                   // Page title
	        'EverXP',                   // Menu title
	        'manage_options',           // Capability
	        'everxp',                   // Menu slug
	        [self::class, 'render_main_page'], // Callback for the main page
	        'dashicons-archive',  // Icon (WordPress Dashicon)
	        2                           // Position
	    );

	    // Add a sub-menu page for Settings
	    add_submenu_page(
	        'everxp',                   // Parent slug
	        'Settings',                 // Page title
	        'Settings',                 // Menu title
	        'manage_options',           // Capability
	        'everxp-settings',          // Menu slug
	        [self::class, 'render_settings_page'] // Callback for the settings page
	    );


	    // Add a sub-menu page for Sync Data
	    add_submenu_page(
	        'everxp',                   // Parent slug
	        'Sync Data',                // Page title
	        'Sync Data',                // Menu title
	        'manage_options',           // Capability
	        'everxp-sync',              // Menu slug
	        ['EverXP_Sync', 'render_sync_page'] // Callback for the Sync page
	    );

	    // Add a sub-menu page for Sync Data
	    add_submenu_page(
			'everxp',                   // Parent slug
			'Documentation',            // Page title
			'Documentation',            // Menu title
			'manage_options',           // Capability
			'everxp-docs',              // Menu slug
			[self::class, 'render_docs_page'], // Callback for the main page
	    );
	}

	public static function render_main_page() {
	    global $wpdb;

	    // Query to fetch banks and IDs from the user_banks table
	    $banks = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}user_banks WHERE active = 1", ARRAY_A);

	    echo '<h1>EverXP Main Page</h1>';
	    echo '<p>Welcome to the EverXP plugin dashboard.</p>';
	    do_action('everxp_sync_logs_cron');

	    // Display banks table if data is available
	    if (!empty($banks)) {
	        echo '<h2>User Banks</h2>';
	        echo '<table class="everxp-banks-table" style="border-collapse: collapse; margin-bottom: 20px;">';
	        echo '<thead>';
	        echo '<tr>';
	        echo '<th>Bank ID (folder_id)</th>';
	        echo '<th>Bank Name</th>';
	        echo '</tr>';
	        echo '</thead>';
	        echo '<tbody>';
	        foreach ($banks as $bank) {
	            echo '<tr>';
	            echo '<td>' . esc_html($bank['id']) . '</td>';
	            echo '<td>' . esc_html($bank['name']) . '</td>';
	            echo '</tr>';
	        }
	        echo '</tbody>';
	        echo '</table>';
	    } else {
	        echo '<p>No active banks found.</p>';
	    }

	    // Quick Links Section
	    echo "<h2>Quick Links</h2>";
	    echo '<div class="everxp-quick-links">';
	    echo '<ul>';
	    echo '<li><a href="https://dashboard.everxp.com/bank/#!/" target="_blank">Update Banks</a></li>';
	    echo '<li><a href="https://dashboard.everxp.com/bank/#!/" target="_blank">Update Headings</a></li>';
	    echo '<li><a href="https://dashboard.everxp.com/settings" target="_blank">Settings</a></li>';
	    echo '<li><a href="https://dashboard.everxp.com/billing" target="_blank">Upgrade Your Account</a></li>';
	    echo '<li><a href="https://everxp.docs.apiary.io/#" target="_blank">API Documentation</a></li>';
	    echo '</ul>';

	    echo '<h3>Additional Links</h3>';
	    echo '<ul>';
	    echo '<li><a href="https://accessily.com" target="_blank">Guest Post Marketplace</a></li>';
	    echo '</ul>';
	    echo '</div>';

	    // Inline CSS for Styling
	    echo '<style>
	        .everxp-style-options-table, .everxp-banks-table {
	            width: 80%;
	            margin: 20px 0;
	            border-collapse: collapse;
	        }
	        .everxp-style-options-table th, .everxp-banks-table th,
	        .everxp-style-options-table td, .everxp-banks-table td {
	            border: 1px solid #ddd;
	            padding: 8px;
	            text-align: left;
	        }
	        .everxp-style-options-table tr:nth-child(even),
	        .everxp-banks-table tr:nth-child(even) {
	            background-color: #f9f9f9;
	        }
	        pre {
	            background-color: #f4f4f4;
	            padding: 10px;
	            border: 1px solid #ddd;
	            border-radius: 5px;
	            overflow-x: auto;
	            white-space: pre-wrap;
	            word-wrap: break-word;
	        }
	    </style>';

	}

    public static function render_docs_page() {
        echo '<div class="wrap">';
        echo '<h1>EverXP Documentation</h1>';
        echo '<p>Here you can find all the information about implementing and customizing EverXP on your WordPress site.</p>';

	    // Implementation Options
	    echo '<h2>Implementation Options</h2>';
	    echo '<p>Below are the options for using EverXP in your WordPress site:</p>';

		// Shortcode Examples
		echo '<h3>1. Shortcode Examples</h3>';
		echo '<p>Copy and paste the following shortcode into your WordPress pages or posts to display dynamic content:</p>';
		echo '<pre>[everxp_shortcode folder_id="6" lang="en" style="1" min_l="50" max_l="200" text_style="bold" alignment="center" text_color="#ff0000" font_size="18px" decoration="underline" effect="fade" duration="2000"]</pre>';
		echo '<p>For displaying multiple rows of content, use the following shortcode examples with various display options:</p>';
		echo '<ul>';
		echo '<li><strong>Line:</strong> Displays content as a one-liner with separators.</li>';
		echo '<pre>[everxp_shortcode_multiple folder_id="6" display="line" separator=" | " limit="5" remove_default_styling="false"]</pre>';
		echo '<li><strong>Slider:</strong> Horizontal sliding content for a carousel effect.</li>';
		echo '<pre>[everxp_shortcode_multiple folder_id="6" display="slider" limit="5" remove_default_styling="false"]</pre>';
		echo '<li><strong>News Ticker (Horizontal):</strong> Scrolling ticker for horizontal news updates.</li>';
		echo '<pre>[everxp_shortcode_multiple folder_id="6" display="news_ticker" rtl="false" scroll_speed="20000" remove_default_styling="false"]</pre>';
		echo '<li><strong>News Ticker (Vertical):</strong> Vertical scrolling ticker for updates.</li>';
		echo '<pre>[everxp_shortcode_multiple folder_id="6" display="news_ticker_vertical" rtl="false" scroll_speed="20000" remove_default_styling="false"]</pre>';
		echo '</ul>';

		// Styling and Animation Options Table
		echo '<h2>Styling and Animation Options</h2>';
		echo '<p>Customize your content using the attributes below for your shortcodes. Note that some options are specific to single or multiple shortcodes:</p>';
		echo '<table class="everxp-style-options-table">';
		echo '<thead><tr><th>Option</th><th>Attribute</th><th>Description</th><th>Example</th></tr></thead>';
		echo '<tbody>';
		echo '<tr><td>Folder ID</td><td>folder_id</td><td><strong>Required:</strong> Specifies the folder from which to fetch data.</td><td>[everxp_shortcode folder_id="6"]</td></tr>';
		echo '<tr><td>Language</td><td>lang</td><td>Defines the language for the content.</td><td>[everxp_shortcode lang="en"]</td></tr>';
		echo '<tr><td>Style</td><td>style</td><td>Applies the content style. Options: 1 (Default), 2, etc.</td><td>[everxp_shortcode style="1"]</td></tr>';
		echo '<tr><td>Min Length</td><td>min_l</td><td>Specifies the minimum length of content in characters.</td><td>[everxp_shortcode min_l="50"]</td></tr>';
		echo '<tr><td>Max Length</td><td>max_l</td><td>Specifies the maximum length of content in characters.</td><td>[everxp_shortcode max_l="500"]</td></tr>';
		echo '<tr><td>Text Style</td><td>text_style</td><td>Applies styles such as default, bold, italic, or highlight.</td><td>[everxp_shortcode text_style="bold"]</td></tr>';
		echo '<tr><td>Alignment</td><td>alignment</td><td>Aligns text to left, center, or right.</td><td>[everxp_shortcode alignment="center"]</td></tr>';
		echo '<tr><td>Text Color</td><td>text_color</td><td>Sets the text color using valid CSS color values.</td><td>[everxp_shortcode text_color="#ff0000"]</td></tr>';
		echo '<tr><td>Font Size</td><td>font_size</td><td>Defines the font size in valid CSS units.</td><td>[everxp_shortcode font_size="18px"]</td></tr>';
		echo '<tr><td>Decoration</td><td>decoration</td><td>Adds text decoration such as underline.</td><td>[everxp_shortcode decoration="underline"]</td></tr>';
		echo '<tr><td>Font Family</td><td>font_family</td><td>Sets the font family for the text.</td><td>[everxp_shortcode font_family="Verdana, sans-serif"]</td></tr>';
		echo '<tr><td>Effect (Single)</td><td>effect</td><td><strong>Single Shortcode Only:</strong> Defines animation effects like fade, slide, or bounce.</td><td>[everxp_shortcode effect="fade"]</td></tr>';
		echo '<tr><td>Duration (Single)</td><td>duration</td><td><strong>Single Shortcode Only:</strong> Sets animation duration in milliseconds.</td><td>[everxp_shortcode duration="2000"]</td></tr>';
		echo '<tr><td>Display (Multiple)</td><td>display</td><td><strong>Multiple Shortcodes Only:</strong> Choose display type: line, slider, news_ticker, or news_ticker_vertical.</td><td>[everxp_shortcode_multiple display="slider"]</td></tr>';
		echo '<tr><td>Separator</td><td>separator</td><td>Defines a separator for line display.</td><td>[everxp_shortcode_multiple separator=" | "]</td></tr>';
		echo '<tr><td>Scroll Speed (Multiple)</td><td>scroll_speed</td><td><strong>Multiple Shortcodes Only:</strong> Specifies scroll speed for tickers in milliseconds.</td><td>[everxp_shortcode_multiple scroll_speed="20000"]</td></tr>';
		echo '<tr><td>RTL (Multiple)</td><td>rtl</td><td><strong>Multiple Shortcodes Only:</strong> Enables Right-to-Left scrolling.</td><td>[everxp_shortcode_multiple rtl="true"]</td></tr>';
		echo '<tr><td>Remove Default Styling</td><td>remove_default_styling</td><td>Disables default styling.</td><td>[everxp_shortcode_multiple remove_default_styling="true"]</td></tr>';
		echo '</tbody>';
		echo '</table>';

		// Inline CSS for Styling
		echo '<style>
		    .everxp-style-options-table {
		        width: 90%;
		        margin: 20px auto;
		        border-collapse: collapse;
		        font-size: 14px;
		    }
		    .everxp-style-options-table th,
		    .everxp-style-options-table td {
		        border: 1px solid #ddd;
		        padding: 8px;
		        text-align: left;
		    }
		    .everxp-style-options-table tr:nth-child(even) {
		        background-color: #f9f9f9;
		    }
		    pre {
		        background-color: #f4f4f4;
		        padding: 10px;
		        border: 1px solid #ddd;
		        border-radius: 5px;
		        overflow-x: auto;
		        word-wrap: break-word;
		    }
		</style>';



	    // Elementor Integration
	    echo '<h3>2. Elementor</h3>';
	    echo '<p>Use the EverXP Widget in Elementor to integrate dynamic content. Navigate to Elementor Editor, search for <strong>EverXP</strong> in the widget panel, and configure the following options:</p>';
	 

        echo '</div>';
    }



    // Render the settings page content
	public static function render_settings_page() {
	    // Check if form is submitted
	    if (isset($_POST['verify_api_key'])) {
	        $api_key = sanitize_text_field($_POST['everxp_api_key']);
	        $domain = everxp_check_domain(); // Automatically fetch the domain

	        // Redirect to external dashboard for verification
			$dashboard_url   = 'https://dashboard.everxp.com/login';
			$redirect_url    = admin_url('admin.php?page=everxp-settings'); 
			$secret_key      = 'everxp-team-78'; 
			$iv_length       = openssl_cipher_iv_length('aes-256-cbc');
			$iv              = openssl_random_pseudo_bytes($iv_length);
			$hash            = hash_hmac('sha256', $api_key . $domain, $secret_key);
			$token_payload   = json_encode(['api_key' => $api_key, 'domain' => $domain]);
			$encrypted_token = openssl_encrypt($token_payload, 'aes-256-cbc', $secret_key, 0, $iv);

			// Combine IV and encrypted token
			$encrypted_data = base64_encode($iv . $encrypted_token);

	        $full_redirect = add_query_arg([
				'token'    => $encrypted_data,
				'hash'     => $hash,
				'redirect' => urlencode($redirect_url),
			], $dashboard_url);


			if (!headers_sent()) {
		        wp_redirect($full_redirect);
		        exit;
		    } else {
			    echo '<script>window.location="' . esc_url_raw($full_redirect) . '";</script>';
			    exit;
		    }
	    }


	    // Save the API key after returning from verification
	    if (isset($_GET['verification']) && $_GET['verification'] === 'success') {
	        $api_key = isset($_GET['api_key']) ? sanitize_text_field($_GET['api_key']) : '';
	        $freshness = isset($_GET['freshness']) ? sanitize_text_field($_GET['freshness']) : '';
	        if (!empty($api_key)) {

        	    $encrypted_api_key = EverXP_Encryption_Helper::encrypt($api_key);
	            update_option('everxp_api_key', $encrypted_api_key);

	            echo '<div class="notice notice-success is-dismissible"><p>API Key verified and saved successfully!</p></div>';
	        }
	    }

	    // Check if API key exists and is decrypted successfully
	    $stored_encrypted_api_key = get_option('everxp_api_key');
	    $api_status_message = '';
	    if ($stored_encrypted_api_key) {
	        $decrypted_api_key = EverXP_Encryption_Helper::decrypt($stored_encrypted_api_key);
	        if ($decrypted_api_key) {
	            $api_status_message = '<div class="notice notice-info"><p>API Key is already verified and active.</p></div>';
	        } else {
	            $api_status_message = '<div class="notice notice-error"><p>Stored API Key is invalid or corrupted.</p></div>';
	        }
	    }

	    // Custom styles for the form
	    echo '<style>
	        .everxp-settings-form {
	            max-width: 600px;
	            background: #fff;
	            padding: 20px;
	            border: 1px solid #ddd;
	            border-radius: 8px;
	            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
	        }
	        .everxp-settings-form label {
	            font-size: 14px;
	            font-weight: 600;
	            margin-bottom: 5px;
	            display: block;
	            color: #333;
	        }
	        .everxp-settings-form input[type="text"] {
	            width: 100%;
	            padding: 8px;
	            border: 1px solid #ccc;
	            border-radius: 4px;
	            font-size: 14px;
	            margin-bottom: 15px;
	            box-sizing: border-box;
	        }
	        .everxp-settings-form button {
	            background: #0073aa;
	            color: #fff;
	            border: none;
	            padding: 10px 15px;
	            border-radius: 4px;
	            font-size: 14px;
	            cursor: pointer;
	        }
	        .everxp-settings-form button:hover {
	            background: #005a87;
	        }
	        .notice {
	            margin-bottom: 20px;
	        }
	    </style>';

	    // Render the settings page
	    echo '<h1>EverXP Verification</h1>';

	    // Display API status message
	    echo $api_status_message;

	    echo '<form method="post" class="everxp-settings-form">';
	    echo '<input type="hidden" name="verify_api_key" value="1">';
	    echo '<label for="everxp_api_key">API Key:</label>';
	    echo '<input type="text" id="everxp_api_key" name="everxp_api_key" placeholder="Enter your API Key" value="">';
	    echo '<button type="submit">Verify API Key</button>';
	    echo '</form>';
	}


}
