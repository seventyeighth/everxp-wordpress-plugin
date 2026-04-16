<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EverXP_Settings {
    public static function init() {
        // Hook to create the admin menu
        add_action('admin_menu', [self::class, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);

	    // NEW: secure endpoints
	    add_action('admin_post_everxp_verify_api_key', [self::class, 'handle_verify_api_key']);
	    add_action('admin_post_everxp_verify_callback', [self::class, 'handle_verify_callback']);

	    // Allow the external host for wp_safe_redirect()
	    add_filter('allowed_redirect_hosts', [self::class, 'allow_external_redirect_hosts']);

    }

	public static function handle_verify_api_key(): void {
	    if (!current_user_can('manage_options')) {
	        wp_die('Insufficient permissions.', 403);
	    }
	    check_admin_referer('everxp_verify_api_key', '_everxp_nonce');

	    $api_key = sanitize_text_field($_POST['everxp_api_key'] ?? '');
	    if ($api_key === '') {
	        wp_safe_redirect(admin_url('admin.php?page=everxp-settings&verification=error&reason=missing_key'));
	        exit;
	    }

	    $domain        = function_exists('everxp_check_domain') ? everxp_check_domain() : (wp_parse_url(home_url(), PHP_URL_HOST) ?: '');
	    //$dashboard_url = 'https://dashboard.everxp.com/login';
	    $dashboard_url = 'http://localhost/everxp/everxp-dashboard/login';

	    // Callback URL carries a nonce "cb" so we can verify even if external doesn't echo token/state
	    $callback_url = add_query_arg(
	        [
	            'action' => 'everxp_verify_callback',
	            'cb'     => wp_create_nonce('everxp_verify_callback'),
	        ],
	        admin_url('admin-post.php')
	    );

	    // Optional "state" (extra binding). If external doesn’t return it, the "cb" nonce still protects the callback.
	    $state = wp_generate_password(20, false, false);
	    set_transient('everxp_state_' . $state, ['user_id' => get_current_user_id(), 'created' => time()], 30 * MINUTE_IN_SECONDS);

	    // Build signed token (unchanged)
	    $secret_key = 'everxp-team-78';
	    $iv_len     = openssl_cipher_iv_length('aes-256-cbc');
	    $iv         = openssl_random_pseudo_bytes($iv_len);
	    $hash       = hash_hmac('sha256', $api_key . $domain, $secret_key);
	    $payload    = wp_json_encode(['api_key' => $api_key, 'domain' => $domain], JSON_UNESCAPED_SLASHES);
	    $cipher     = openssl_encrypt($payload, 'aes-256-cbc', $secret_key, 0, $iv);
	    $token      = base64_encode($iv . $cipher);

	    // Send user to dashboard; if the dashboard ignores token/state, it can still redirect back to $callback_url
	    $url = add_query_arg(
	        [
	            'token'    => rawurlencode($token),
	            'hash'     => $hash,
	            'state'    => rawurlencode($state),
	            'redirect' => rawurlencode($callback_url),
	        ],
	        $dashboard_url
	    );

	    wp_safe_redirect($url);
	    exit;
	}


	/** Allow external EverXP dashboard host(s) for safe redirects. */
	public static function allow_external_redirect_hosts(array $hosts): array {
	    $hosts[] = 'dashboard.everxp.com';
	    $hosts[] = 'localhost'; // uncomment for local dev
	    return array_values(array_unique($hosts));
	}

	/** Secure callback endpoint: validate state + signature; save key; redirect back with notice. */
	public static function handle_verify_callback(): void {
	    // Must be logged-in admin
	    if (!current_user_can('manage_options')) {
	        auth_redirect();
	        exit;
	    }

	    // Two acceptable security gates: callback nonce "cb" OR transient "state"
	    $cb    = isset($_GET['cb']) ? sanitize_text_field(wp_unslash($_GET['cb'])) : '';
	    $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';

	    $cb_ok = $cb && wp_verify_nonce($cb, 'everxp_verify_callback');
	    $st_ok = false;
	    if ($state !== '') {
	        $meta = get_transient('everxp_state_' . $state);
	        delete_transient('everxp_state_' . $state); // one-time use
	        $st_ok = is_array($meta) && !empty($meta['user_id']) && (int)$meta['user_id'] === get_current_user_id();
	    }

	    if (!$cb_ok && !$st_ok) {
	        wp_safe_redirect(admin_url('admin.php?page=everxp-settings&verification=error&reason=bad_state'));
	        exit;
	    }

	    // Preferred: token/hash (new flow)
	    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
	    $hash  = isset($_GET['hash'])  ? sanitize_text_field(wp_unslash($_GET['hash']))  : '';

	    $api_key = '';
	    $domain  = '';

	    if ($token !== '' && $hash !== '') {
	        $secret_key = 'everxp-team-78';
	        $decoded    = base64_decode($token, true);
	        if ($decoded === false) {
	            wp_safe_redirect(admin_url('admin.php?page=everxp-settings&verification=error&reason=bad_token'));
	            exit;
	        }
	        $iv_len = openssl_cipher_iv_length('aes-256-cbc');
	        $iv     = substr($decoded, 0, $iv_len);
	        $cipher = substr($decoded, $iv_len);
	        $json   = openssl_decrypt($cipher, 'aes-256-cbc', $secret_key, 0, $iv);
	        $data   = json_decode((string)$json, true);

	        if (!is_array($data) || empty($data['api_key']) || empty($data['domain'])) {
	            wp_safe_redirect(admin_url('admin.php?page=everxp-settings&verification=error&reason=bad_payload'));
	            exit;
	        }

	        // Verify HMAC
	        $calc = hash_hmac('sha256', $data['api_key'] . $data['domain'], $secret_key);
	        if (!hash_equals($hash, $calc)) {
	            wp_safe_redirect(admin_url('admin.php?page=everxp-settings&verification=error&reason=bad_hash'));
	            exit;
	        }

	        $api_key = (string)$data['api_key'];
	        $domain  = (string)$data['domain'];
	    } else {
	        // Legacy: ?verification=success&api_key=... (from your older flow)
	        $verification = isset($_GET['verification']) ? sanitize_key($_GET['verification']) : '';
	        $maybe_key    = isset($_GET['api_key']) ? sanitize_text_field(wp_unslash($_GET['api_key'])) : '';
	        if ($verification !== 'success' || $maybe_key === '') {
	            wp_safe_redirect(admin_url('admin.php?page=everxp-settings&verification=error&reason=missing_params'));
	            exit;
	        }
	        $api_key = $maybe_key;
	        // Best-effort domain check
	        $domain = function_exists('everxp_check_domain') ? everxp_check_domain() : (wp_parse_url(home_url(), PHP_URL_HOST) ?: '');
	    }

	    // Optional safety: ensure returned domain matches this site
	    $site_domain = function_exists('everxp_check_domain') ? everxp_check_domain() : (wp_parse_url(home_url(), PHP_URL_HOST) ?: '');
	    if ($domain && !hash_equals((string)$site_domain, (string)$domain)) {
	        wp_safe_redirect(admin_url('admin.php?page=everxp-settings&verification=error&reason=domain_mismatch'));
	        exit;
	    }

	    // Save encrypted key
	    $encrypted_api_key = class_exists('EverXP_Encryption_Helper')
	        ? EverXP_Encryption_Helper::encrypt($api_key)
	        : $api_key;

	    update_option('everxp_api_key', $encrypted_api_key);

	    wp_safe_redirect(admin_url('admin.php?page=everxp-settings&verification=success'));
	    exit;
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

	    $banks = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}user_banks WHERE active = 1", ARRAY_A);

	    echo '<h1>EverXP Main Page</h1>';
	    echo '<p>Welcome to the EverXP plugin dashboard.</p>';
	    do_action('everxp_sync_logs_cron');

	    if (!empty($banks)) {
	        echo '<h2>User Banks</h2>';
	        echo '<table class="everxp-banks-table" style="border-collapse: collapse; margin-bottom: 20px;">';
	        echo '<thead><tr><th>Bank ID (folder_id)</th><th>Bank Name</th></tr></thead><tbody>';
	        foreach ($banks as $bank) {
	            echo '<tr><td>' . esc_html($bank['id']) . '</td><td>' . esc_html($bank['name']) . '</td></tr>';
	        }
	        echo '</tbody></table>';
	    } else {
	        echo '<p>No active banks found.</p>';
	    }

	    // Embeds Manager (includes upgraded "Where should it appear?")
	    if (class_exists('EverXP_Embeds')) {
	        EverXP_Embeds::render_admin_block();
	    } else {
	        echo '<div class="error"><p>Embeds module not loaded.</p></div>';
	    }

	    // Quick Links (existing)
	    echo "<h2>Quick Links</h2>";
	    echo '<div class="everxp-quick-links"><ul>';
	    echo '<li><a href="https://dashboard.everxp.com/bank/#!/" target="_blank">Update Banks</a></li>';
	    echo '<li><a href="https://dashboard.everxp.com/bank/#!/" target="_blank">Update Headings</a></li>';
	    echo '<li><a href="https://dashboard.everxp.com/settings" target="_blank">Settings</a></li>';
	    echo '<li><a href="https://dashboard.everxp.com/billing" target="_blank">Upgrade Your Account</a></li>';
	    echo '<li><a href="https://everxp.docs.apiary.io/#" target="_blank">API Documentation</a></li>';
	    echo '</ul><h3>Additional Links</h3><ul>';
	    echo '<li><a href="https://accessily.com" target="_blank">Guest Post Marketplace</a></li>';
	    echo '</ul></div>';

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

	    echo '</div>';
	}





    // Render the settings page content
	public static function render_settings_page() {
	    // Status message from query args (display only; no data processing)
	    $verification = isset($_GET['verification']) ? sanitize_key($_GET['verification']) : '';
	    if ($verification === 'success') {
	        echo '<div class="notice notice-success is-dismissible"><p>API Key verified and saved successfully!</p></div>';
	    } elseif ($verification === 'error') {
	        $reason = isset($_GET['reason']) ? sanitize_key($_GET['reason']) : 'unknown';
	        echo '<div class="notice notice-error is-dismissible"><p>Verification failed: ' . esc_html($reason) . '</p></div>';
	    }

	    // API key status
	    $stored = get_option('everxp_api_key');
	    if ($stored && class_exists('EverXP_Encryption_Helper') && EverXP_Encryption_Helper::decrypt($stored)) {
	        echo '<div class="notice notice-info is-dismissible"><p>API Key is verified and active.</p></div>';
	    }

	    echo '<h1>EverXP Verification</h1>';
		echo '<form method="post" action="' . esc_url( admin_url('admin-post.php?action=everxp_verify_api_key') ) . '" class="everxp-settings-form">';
		    // Nonce with explicit field name
		    wp_nonce_field('everxp_verify_api_key', '_everxp_nonce');
		    echo '<label for="everxp_api_key">API Key:</label>';
		    echo '<input type="text" id="everxp_api_key" name="everxp_api_key" placeholder="Enter your API Key" value="">';
		    echo '<button type="submit" class="button button-primary">Verify API Key</button>';
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
	    </div>
	    <?php
	}

    public static function enqueue_admin_assets(string $hook_suffix) : void {
        // Only our pages
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        $allowed = ['everxp','everxp-settings','everxp-sync','everxp-docs','everxp-shortcode-generator'];
        if (!in_array($page, $allowed, true)) {
            return;
        }

        // Register empty handles we can attach inline assets to
        // Style
        if (!wp_style_is('everxp-admin-style', 'registered')) {
            wp_register_style('everxp-admin-style', false, [], null);
        }
        wp_enqueue_style('everxp-admin-style');

        // Script (defer supported since WP 6.3)
        if (!wp_script_is('everxp-admin-script', 'registered')) {
            wp_register_script(
                'everxp-admin-script',
                false,
                ['jquery'],
                null,
                [ 'in_footer' => true, 'strategy' => 'defer' ] // async/defer ready
            );
        }
        wp_enqueue_script('everxp-admin-script');

        // ================= Inline CSS per page =================
        if ($page === 'everxp') {
            // CSS moved from render_main_page()
            $css = <<<CSS
.everxp-style-options-table, .everxp-banks-table { width: 100%; margin: 20px 0; border-collapse: collapse; }
.everxp-style-options-table th, .everxp-banks-table th,
.everxp-style-options-table td, .everxp-banks-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
.everxp-style-options-table tr:nth-child(even), .everxp-banks-table tr:nth-child(even) { background-color: #f9f9f9; }
pre { background-color: #f4f4f4; padding: 10px; border: 1px solid #ddd; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
.form-table th { width: 260px; }
#everxp-custom-hook-wrap label, #everxp-priority-wrap label { display:inline-block; min-width:90px; }
CSS;
            wp_add_inline_style('everxp-admin-style', $css);
        }

        if ($page === 'everxp-docs') {
            // CSS moved from render_docs_page()
            $css = <<<CSS
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
.everxp-style-options-table tr:nth-child(even) { background-color: #f9f9f9; }
pre { background-color: #f4f4f4; padding: 10px; border: 1px solid #ddd; border-radius: 5px; overflow-x: auto; word-wrap: break-word; }
CSS;
            wp_add_inline_style('everxp-admin-style', $css);
        }

        if ($page === 'everxp-settings') {
            // CSS moved from render_settings_page()
            $css = <<<CSS
.everxp-settings-form { max-width: 600px; background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.everxp-settings-form label { font-size: 14px; font-weight: 600; margin-bottom: 5px; display: block; color: #333; }
.everxp-settings-form input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; margin-bottom: 15px; box-sizing: border-box; }
.everxp-settings-form button { background: #0073aa; color: #fff; border: none; padding: 10px 15px; border-radius: 4px; font-size: 14px; cursor: pointer; }
.everxp-settings-form button:hover { background: #005a87; }
.notice { margin-bottom: 20px; }
CSS;
            wp_add_inline_style('everxp-admin-style', $css);
        }

        if ($page === 'everxp-shortcode-generator') {
            // (Optional) CSS for preview container
            $css = <<<CSS
#shortcode-preview { border: 1px solid #ddd; padding: 15px; background: #f9f9f9; }
CSS;
            wp_add_inline_style('everxp-admin-style', $css);

            // JS moved from render_shortcode_generator_page()
            $js = <<<JS
(function($){
  $('#generate-shortcode').on('click', function(){
    var form = $('#everxp-shortcode-form').serializeArray();
    var shortcode = '';
    var isMultiple = false;

    form.forEach(function(field){
      if (field.name === 'display' || field.name === 'limit' || field.name === 'separator') {
        isMultiple = true;
      }
    });

    shortcode = isMultiple ? '[everxp_shortcode_multiple ' : '[everxp_shortcode ';

    form.forEach(function(field){
      if (field.value) { shortcode += field.name + '="' + field.value.replace(/"/g,'\\"') + '" '; }
    });
    shortcode += ']';

    $('#generated-shortcode').text(shortcode);

    var preview = isMultiple
      ? '<p>Preview of multiple shortcode: <strong>' + shortcode + '</strong></p>'
      : '<p>Preview of single shortcode: <strong>' + shortcode + '</strong></p>';
    $('#shortcode-preview').html(preview);
  });
})(jQuery);
JS;
            wp_add_inline_script('everxp-admin-script', $js, 'after');
        }
    }



}
