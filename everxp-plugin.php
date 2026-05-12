<?php
/*
Plugin Name: EverXP
Description: Injects the EverXP embed SDK into the site header.
Version: 4.0.0
Author: EverXP.com
License: GNU General Public License v2 or later
*/

defined('ABSPATH') || exit;

function everxp_api_base_url(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if ($host === 'localhost' || strpos($host, '127.') === 0 || strpos($host, '192.168.') === 0) {
        return 'http://localhost/everxp/everxp-api';
    }
    return 'https://api.everxp.com';
}

// ── Settings page ────────────────────────────────────────────────────────────

add_action('admin_menu', function () {
    add_menu_page(
        'EverXP',
        'EverXP',
        'manage_options',
        'everxp',
        'everxp_settings_page',
        'dashicons-archive',
        2
    );
});

function everxp_settings_page(): void {
    if (!current_user_can('manage_options')) { return; }

    // Save
    if (isset($_POST['everxp_save']) && check_admin_referer('everxp_save_key')) {
        $key = sanitize_text_field(wp_unslash($_POST['everxp_api_key'] ?? ''));
        update_option('everxp_api_key', $key);
        echo '<div class="notice notice-success"><p>API key saved.</p></div>';
    }

    $key = get_option('everxp_api_key', '');
    ?>
    <div class="wrap">
        <h1>EverXP Settings</h1>
        <form method="post">
            <?php wp_nonce_field('everxp_save_key'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="everxp_api_key">API Key</label></th>
                    <td>
                        <input type="text" id="everxp_api_key" name="everxp_api_key"
                               class="regular-text" value="<?php echo esc_attr($key); ?>"
                               placeholder="Base64 API key from your EverXP dashboard">
                        <p class="description">
                            Copy from <a href="https://dashboard.everxp.com/settings" target="_blank">dashboard.everxp.com → Settings</a>.
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save', 'primary', 'everxp_save'); ?>
        </form>
        <?php if ($key): ?>
        <hr>
        <h2>Status</h2>
        <p>✓ SDK will be injected in <code>&lt;head&gt;</code> on every page.</p>
        <p>Script tag preview:</p>
        <pre style="background:#f4f4f4;padding:10px;">&lt;script src="<?php echo esc_url(everxp_api_base_url()); ?>/assets/js/everxp.js" data-key="<?php echo esc_attr($key); ?>" defer&gt;&lt;/script&gt;</pre>
        <?php endif; ?>
    </div>
    <?php
}

// ── Inject SDK into <head> ────────────────────────────────────────────────────

add_action('wp_head', function () {
    $key  = get_option('everxp_api_key', '');
    if (!$key) { return; }
    $base = everxp_api_base_url();
    ?>
<script>
window._everxpKey = <?php echo json_encode($key); ?>;
window._everxpBase = <?php echo json_encode($base); ?>;
(function(d,b){var s=d.createElement('script');s.async=1;s.src=b+'/assets/js/everxp.js';d.head.appendChild(s);})(document,window._everxpBase);
</script>
    <?php
});
