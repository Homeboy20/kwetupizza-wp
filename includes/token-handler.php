<?php
/**
 * Token Handler for KwetuPizza Plugin
 * 
 * Handles token generation, verification, and management for various
 * API integrations and security features.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include common functions
require_once plugin_dir_path(__FILE__) . 'common-functions.php';

/**
 * Generate a secure webhook token
 * 
 * @param int $length The length of the token
 * @return string The generated token
 */
if (!function_exists('kwetupizza_generate_webhook_token')) {
    function kwetupizza_generate_webhook_token($length = 32) {
        return wp_generate_password($length, true, true);
    }
}

/**
 * Verify WhatsApp webhook signature
 * 
 * @param string $signature The signature to verify
 * @param string $data The data to verify against
 * @return bool True if signature is valid, false otherwise
 */
// Removed incorrect WhatsApp signature verification function

/**
 * Set a secure option in the database
 * 
 * @param string $option_name The option name
 * @param mixed $value The option value
 * @return bool True on success, false on failure
 */
if (!function_exists('kwetupizza_set_secure_option')) {
    function kwetupizza_set_secure_option($option_name, $value) {
        return update_option($option_name, $value);
    }
}

/**
 * Generate a secure API token
 * 
 * @return string The generated API token
 */
if (!function_exists('kwetupizza_generate_api_token')) {
    function kwetupizza_generate_api_token() {
        $token = wp_generate_password(64, true, true);
        
        // Save the token in the database
        kwetupizza_set_secure_option('kwetupizza_api_token', $token);
        
        return $token;
    }
}

/**
 * Verify API token
 * 
 * @param string $token The token to verify
 * @return bool True if token is valid, false otherwise
 */
if (!function_exists('kwetupizza_verify_api_token')) {
    function kwetupizza_verify_api_token($token) {
        $stored_token = kwetupizza_get_secure_option('kwetupizza_api_token');
        
        if (empty($stored_token)) {
            return false;
        }
        
        return hash_equals($stored_token, $token);
    }
}

/**
 * Generate a conversation token for tracking user sessions
 * 
 * @param string $phone_number The user's phone number
 * @return string The generated conversation token
 */
if (!function_exists('kwetupizza_generate_conversation_token')) {
    function kwetupizza_generate_conversation_token($phone_number) {
        $token = md5($phone_number . time() . wp_generate_password(12, true, true));
        
        // Save the token in the database
        $conversation_tokens = get_option('kwetupizza_conversation_tokens', array());
        $conversation_tokens[$phone_number] = array(
            'token' => $token,
            'created' => time()
        );
        
        update_option('kwetupizza_conversation_tokens', $conversation_tokens);
        
        return $token;
    }
}

/**
 * Get conversation token for a user
 * 
 * @param string $phone_number The user's phone number
 * @return string|false The conversation token or false if not found
 */
if (!function_exists('kwetupizza_get_conversation_token')) {
    function kwetupizza_get_conversation_token($phone_number) {
        $conversation_tokens = get_option('kwetupizza_conversation_tokens', array());
        
        if (isset($conversation_tokens[$phone_number])) {
            return $conversation_tokens[$phone_number]['token'];
        }
        
        return false;
    }
}

/**
 * Get user conversation context
 * 
 * @param string $phone_number The user's phone number
 * @return array The conversation context
 */
if (!function_exists('kwetupizza_get_conversation_context')) {
    // Skip defining this if it already exists in common-functions.php
}

/**
 * Set user conversation context
 * 
 * @param string $phone_number The user's phone number
 * @param array $context The conversation context
 * @return bool True on success, false on failure
 */
if (!function_exists('kwetupizza_set_conversation_context')) {
    // Skip defining this if it already exists in common-functions.php
}

/**
 * Check if conversation is expired
 * 
 * @param string $phone_number The user's phone number
 * @return bool True if conversation is expired, false otherwise
 */
if (!function_exists('kwetupizza_is_conversation_expired')) {
    function kwetupizza_is_conversation_expired($phone_number) {
        $context = kwetupizza_get_conversation_context($phone_number);
        
        if (!isset($context['last_activity'])) {
            return true;
        }
        
        $timeout = get_option('kwetupizza_inactivity_timeout', 3) * 60; // Convert minutes to seconds
        $current_time = time();
        
        return ($current_time - $context['last_activity']) > $timeout;
    }
}

/**
 * Reset conversation context
 * 
 * @param string $phone_number The user's phone number
 * @return bool True on success, false on failure
 */
if (!function_exists('kwetupizza_reset_conversation')) {
    // Skip defining this if it already exists in common-functions.php
}

/**
 * Generate a payment verification token
 * 
 * @param string $tx_ref The transaction reference
 * @return string The generated token
 */
if (!function_exists('kwetupizza_generate_payment_token')) {
    function kwetupizza_generate_payment_token($tx_ref) {
        $token = wp_generate_password(16, false);
        
        // Save the token in the database
        $payment_tokens = get_option('kwetupizza_payment_tokens', array());
        $payment_tokens[$tx_ref] = array(
            'token' => $token,
            'created' => time(),
            'expires' => time() + (30 * 60) // Token expires in 30 minutes
        );
        
        update_option('kwetupizza_payment_tokens', $payment_tokens);
        
        return $token;
    }
}

/**
 * Verify payment token
 * 
 * @param string $tx_ref The transaction reference
 * @param string $token The token to verify
 * @return bool True if token is valid, false otherwise
 */
if (!function_exists('kwetupizza_verify_payment_token')) {
    function kwetupizza_verify_payment_token($tx_ref, $token) {
        $payment_tokens = get_option('kwetupizza_payment_tokens', array());
        
        if (!isset($payment_tokens[$tx_ref])) {
            return false;
        }
        
        // Check if token is expired
        if ($payment_tokens[$tx_ref]['expires'] < time()) {
            // Clean up expired token
            unset($payment_tokens[$tx_ref]);
            update_option('kwetupizza_payment_tokens', $payment_tokens);
            
            return false;
        }
        
        return hash_equals($payment_tokens[$tx_ref]['token'], $token);
    }
}

/**
 * Clean up expired payment tokens
 */
if (!function_exists('kwetupizza_cleanup_payment_tokens')) {
    function kwetupizza_cleanup_payment_tokens() {
        $payment_tokens = get_option('kwetupizza_payment_tokens', array());
        $current_time = time();
        $cleaned = false;
        
        foreach ($payment_tokens as $tx_ref => $data) {
            if ($data['expires'] < $current_time) {
                unset($payment_tokens[$tx_ref]);
                $cleaned = true;
            }
        }
        
        if ($cleaned) {
            update_option('kwetupizza_payment_tokens', $payment_tokens);
        }
    }
}

// Schedule daily cleanup of expired tokens
if (!wp_next_scheduled('kwetupizza_daily_token_cleanup')) {
    wp_schedule_event(time(), 'daily', 'kwetupizza_daily_token_cleanup');
}

add_action('kwetupizza_daily_token_cleanup', 'kwetupizza_cleanup_payment_tokens'); 