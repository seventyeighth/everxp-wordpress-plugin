<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EverXP_Encryption_Helper {
    private static $key = 'everxp-team-78';
    private static $iv_length;

    // Initialize IV length
    public static function init() {
        self::$iv_length = openssl_cipher_iv_length('aes-256-cbc');
    }

    // Encrypt data
    public static function encrypt_data($data) {
        $iv = openssl_random_pseudo_bytes(self::$iv_length);
        $encrypted = openssl_encrypt(json_encode($data, JSON_UNESCAPED_UNICODE), 'aes-256-cbc', self::$key, 0, $iv);
        return base64_encode($iv . $encrypted); // Combine IV and encrypted data
    }

    // Decrypt data
    public static function decrypt_data($encrypted_data) {
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, self::$iv_length); // Extract IV
        $encrypted = substr($data, self::$iv_length);
        return openssl_decrypt($encrypted, 'aes-256-cbc', self::$key, 0, $iv);
    }


    private static function generate_key() {
        // Use stable factors: ABSPATH and WordPress keys
        $stable_factor = hash('sha256', ABSPATH . AUTH_KEY);
        return hash('sha256', $stable_factor);
    }

    /**
     * Encrypt data using a stable server-specific key
     *
     * @param string $data The data to encrypt
     * @param string $domain The domain name
     * @return string The encrypted string
     */
    public static function encrypt($data) {
        $key = self::generate_key();
        return openssl_encrypt($data, 'aes-256-cbc', $key, 0, substr($key, 0, 16));
    }

    /**
     * Decrypt data using a stable server-specific key
     *
     * @param string $data The encrypted data
     * @param string $domain The domain name
     * @return string|null The decrypted string or null if decryption fails
     */
    public static function decrypt($data) {
        $key = self::generate_key();
        return openssl_decrypt($data, 'aes-256-cbc', $key, 0, substr($key, 0, 16));
    }


}

// Initialize the helper
EverXP_Encryption_Helper::init();