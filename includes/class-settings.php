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

	    // Add a sub-menu page for Shortcode generator
	    // add_submenu_page(
		// 	'everxp',                      // Parent slug
		// 	'Shortcode Generator',         // Page title
		// 	'Shortcode Generator',         // Menu title
		// 	'manage_options',              // Capability
		// 	'everxp-shortcode-generator',  // Menu slug
	    //     [self::class, 'render_shortcode_generator_page'] // Callback for the settings page
	    // );

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
    echo '<p>Welcome to the EverXP Documentation! Below, you will find instructions on how to implement EverXP shortcodes on your WordPress site.</p>';

    // Shortcode Examples
    echo '<h2>1. Single Text Shortcode</h2>';
    echo '<p>To display a single dynamic text, use:</p>';
    echo '<pre>[everxp_shortcode folder_id="6" lang="en" style="1" min_l="50" max_l="200"]</pre>';

    // Multiple Content Display Options
    echo '<h2>2. Multiple Text Shortcode</h2>';
    echo '<p>To display multiple dynamic texts, use:</p>';
    echo '<pre>[everxp_shortcode_multiple folder_id="6" limit="5" separator=" | "]</pre>';

    // Shortcode Options Table
    echo '<h2>Shortcode Options</h2>';
    echo '<p>Customize your content with the following attributes:</p>';

    echo '<table class="everxp-style-options-table">';
    echo '<thead><tr><th>Option</th><th>Attribute</th><th>Description</th><th>Example</th></tr></thead>';
    echo '<tbody>';

    // Basic Options
    echo '<tr><td>Folder ID</td><td>folder_id</td><td><strong>Required:</strong> Defines the content folder.</td><td>[everxp_shortcode folder_id="6"]</td></tr>';
    echo '<tr><td>Language</td><td>lang</td><td>Sets the content language.</td><td>[everxp_shortcode lang="en"]</td></tr>';
    echo '<tr><td>Style</td><td>style</td><td>Defines the content style.</td><td>[everxp_shortcode style="1"]</td></tr>';
    echo '<tr><td>Min Length</td><td>min_l</td><td>Sets the minimum text length.</td><td>[everxp_shortcode min_l="50"]</td></tr>';
    echo '<tr><td>Max Length</td><td>max_l</td><td>Sets the maximum text length.</td><td>[everxp_shortcode max_l="500"]</td></tr>';
    
    // Multiple Shortcode-Specific Options
    echo '<tr><td>Limit</td><td>limit</td><td>Defines the number of texts to display.</td><td>[everxp_shortcode_multiple limit="5"]</td></tr>';
    echo '<tr><td>Separator</td><td>separator</td><td>Sets a separator for multiple texts.</td><td>[everxp_shortcode_multiple separator=" | "]</td></tr>';

    echo '</tbody></table>';

    // Inline CSS for Styling (Minimal)
    echo '<style>
        .everxp-style-options-table {
            width: 90%;
            margin: 20px auto;
            border-collapse: collapse;
            font-size: 14px;
        }
        .everxp-style-options-table th, .everxp-style-options-table td {
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

    echo '</div>';
}





    // Render the settings page content
	public static function render_settings_page() {
	    // Check if form is submitted
	    if (isset($_POST['verify_api_key'])) {
	        $api_key = sanitize_text_field($_POST['everxp_api_key']);
	        $domain = everxp_check_domain(); // Automatically fetch the domain

	        // Redirect to external dashboard for verification
			$dashboard_url   = 'http://localhost/everxp/everxp-dashboard/login';
			//$dashboard_url = 'https://dashboard.everxp.com/login';
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


	public static function render_shortcode_generator_page() {
	    ?>
	    <div class="wrap">
	        <h1>Shortcode Generator</h1>
	        <div style="display: flex; gap: 20px;">
	            <!-- Form Section -->
	            <div style="flex: 1;">
	                <form id="everxp-shortcode-form">
	                    <h2>Single Shortcode</h2>
	                    <table class="form-table">
	                        <tr>
	                            <th><label for="folder_id">Folder ID</label></th>
	                            <td><input type="number" id="folder_id" name="folder_id" placeholder="6" required></td>
	                        </tr>
	                        <tr>
	                            <th><label for="lang">Language</label></th>
	                            <td><input type="text" id="lang" name="lang" placeholder="en" value="en"></td>
	                        </tr>
	                        <tr>
	                            <th><label for="style">Style</label></th>
	                            <td>
	                                <select id="style" name="style">
	                                    <option value="1">Pure (Default)</option>
	                                    <option value="2">Playful</option>
	                                    <option value="3">Guiding</option>
	                                </select>
	                            </td>
	                        </tr>
	                        <tr>
	                            <th><label for="alignment">Alignment</label></th>
	                            <td>
	                                <select id="alignment" name="alignment">
	                                    <option value="left">Left</option>
	                                    <option value="center">Center</option>
	                                    <option value="right">Right</option>
	                                </select>
	                            </td>
	                        </tr>
	                        <tr>
	                            <th><label for="color">Text Color</label></th>
	                            <td><input type="color" id="color" name="color" value="#000000"></td>
	                        </tr>
	                        <tr>
	                            <th><label for="size">Font Size</label></th>
	                            <td><input type="text" id="size" name="size" placeholder="16px" value="16px"></td>
	                        </tr>
	                        <tr>
	                            <th><label for="effect">Effect</label></th>
	                            <td>
	                                <select id="effect" name="effect">
	                                    <option value="none">None</option>
	                                    <option value="fade">Fade</option>
	                                    <option value="slide">Slide</option>
	                                    <option value="bounce">Bounce</option>
	                                </select>
	                            </td>
	                        </tr>
	                        <tr>
	                            <th><label for="duration">Duration</label></th>
	                            <td><input type="number" id="duration" name="duration" placeholder="1000ms" value="1000"></td>
	                        </tr>
	                    </table>

	                    <h2>Multiple Shortcode</h2>
	                    <table class="form-table">
	                        <tr>
	                            <th><label for="display">Display</label></th>
	                            <td>
	                                <select id="display" name="display">
	                                    <option value="line">Line</option>
	                                    <option value="slider">Slider</option>
	                                    <option value="news_ticker">News Ticker (Horizontal)</option>
	                                    <option value="news_ticker_vertical">News Ticker (Vertical)</option>
	                                </select>
	                            </td>
	                        </tr>
	                        <tr>
	                            <th><label for="limit">Limit</label></th>
	                            <td><input type="number" id="limit" name="limit" placeholder="5" value="5"></td>
	                        </tr>
	                        <tr>
	                            <th><label for="separator">Separator</label></th>
	                            <td><input type="text" id="separator" name="separator" placeholder=" | " value=" | "></td>
	                        </tr>
	                        <tr>
	                            <th><label for="scroll_speed">Scroll Speed</label></th>
	                            <td><input type="number" id="scroll_speed" name="scroll_speed" placeholder="20000ms" value="20000"></td>
	                        </tr>
	                        <tr>
	                            <th><label for="rtl">Right-to-Left</label></th>
	                            <td>
	                                <select id="rtl" name="rtl">
	                                    <option value="false">False</option>
	                                    <option value="true">True</option>
	                                </select>
	                            </td>
	                        </tr>
	                    </table>

	                    <button type="button" id="generate-shortcode" class="button button-primary">Generate Shortcode</button>
	                </form>
	            </div>

	            <!-- Preview Section -->
	            <div style="flex: 1;">
	                <h2>Generated Shortcode</h2>
	                <pre id="generated-shortcode"></pre>

	                <h2>Preview</h2>
	                <div id="shortcode-preview" style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">
	                    <p>Your preview will appear here.</p>
	                </div>
	            </div>
	        </div>

	        <!-- Inline JavaScript -->
	        <script>
	            (function($) {
	                $('#generate-shortcode').on('click', function() {
	                    const form = $('#everxp-shortcode-form').serializeArray();
	                    let shortcode = '';
	                    let isMultiple = false;

	                    // Determine shortcode type
	                    form.forEach(field => {
	                        if (field.name === 'display' || field.name === 'limit' || field.name === 'separator') {
	                            isMultiple = true;
	                        }
	                    });

	                    if (isMultiple) {
	                        shortcode = '[everxp_shortcode_multiple ';
	                    } else {
	                        shortcode = '[everxp_shortcode ';
	                    }

	                    // Build the shortcode
	                    form.forEach(field => {
	                        if (field.value) {
	                            shortcode += `${field.name}="${field.value}" `;
	                        }
	                    });
	                    shortcode += ']';

	                    // Update the UI
	                    $('#generated-shortcode').text(shortcode);

	                    // Render a basic preview (mock for demonstration)
	                    let previewContent = isMultiple
	                        ? `<p>Preview of multiple shortcode (e.g., headlines or text): <strong>${shortcode}</strong></p>`
	                        : `<p>Preview of single shortcode: <strong>${shortcode}</strong></p>`;

	                    $('#shortcode-preview').html(previewContent);
	                });
	            })(jQuery);
	        </script>
	    </div>
	    <?php
	}



}
