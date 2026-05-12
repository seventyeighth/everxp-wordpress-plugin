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
        $encrypted = openssl_encrypt(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'aes-256-cbc', self::$key, 0, $iv);
        return base64_encode($iv . $encrypted); // Combine IV and encrypted data
    }

    // Decrypt data
    public static function decrypt_data($encrypted_data) {
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, self::$iv_length); // Extract IV
        $encrypted = substr($data, self::$iv_length);
        return openssl_decrypt($encrypted, 'aes-256-cbc', self::$key, 0, $iv);
    }


    public static function encrypt($data) {
        $iv = openssl_random_pseudo_bytes(self::$iv_length);
        $encrypted = openssl_encrypt((string)$data, 'aes-256-cbc', self::$key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt($data) {
        $raw = base64_decode((string)$data, true);
        if ($raw === false || strlen($raw) <= self::$iv_length) { return false; }
        $iv        = substr($raw, 0, self::$iv_length);
        $encrypted = substr($raw, self::$iv_length);
        $result    = openssl_decrypt($encrypted, 'aes-256-cbc', self::$key, 0, $iv);
        return ($result !== false && $result !== '') ? $result : false;
    }


}

// Initialize the helper
EverXP_Encryption_Helper::init();