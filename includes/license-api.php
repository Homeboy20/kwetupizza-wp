<?php
/**
 * License API Handler for KwetuPizza Plugin
 * 
 * Provides API endpoints for handling license verification callbacks and
 * communicating with the remote license server
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * KwetuPizza License API class
 */
class KwetuPizza_License_API {
    /**
     * API namespace for WordPress REST API
     */
    const API_NAMESPACE = 'kwetupizza/v1';
    
    /**
     * Remote API URL
     */
    const REMOTE_API_URL = 'https://api.kwetupizza.com/licenses/v1';
    
    /**
     * Initialize the license API
     */
    public static function init() {
        // Register REST API endpoints
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
        
        // Add AJAX actions
        add_action('wp_ajax_kwetupizza_check_license', array(__CLASS__, 'ajax_check_license'));
    }
    
    /**
     * Register REST API routes
     */
    public static function register_rest_routes() {
        // Register endpoint for license verification callback
        register_rest_route(
            self::API_NAMESPACE,
            '/license/verify',
            array(
                'methods'  => 'POST',
                'callback' => array(__CLASS__, 'handle_verify_callback'),
                'permission_callback' => array(__CLASS__, 'verify_license_request'),
            )
        );
        
        // Register endpoint for license deactivation
        register_rest_route(
            self::API_NAMESPACE,
            '/license/deactivate',
            array(
                'methods'  => 'POST',
                'callback' => array(__CLASS__, 'handle_deactivate_callback'),
                'permission_callback' => array(__CLASS__, 'verify_license_request'),
            )
        );
    }
    
    /**
     * Verify the license request
     * 
     * @param WP_REST_Request $request The request object
     * @return bool|WP_Error
     */
    public static function verify_license_request($request) {
        // Get the signature from the request
        $signature = $request->get_header('X-KwetuPizza-Signature');
        
        if (empty($signature)) {
            return new WP_Error(
                'invalid_signature',
                'Invalid or missing signature',
                array('status' => 403)
            );
        }
        
        // Get request body and compute expected signature
        $body = $request->get_body();
        $license_key = kwetupizza_license_manager()->get_license_key();
        
        // If no license key is set, reject the request
        if (empty($license_key)) {
            return new WP_Error(
                'no_license',
                'No license key is set for this site',
                array('status' => 403)
            );
        }
        
        // Compute expected signature (HMAC with license key as secret)
        $expected_signature = hash_hmac('sha256', $body, $license_key);
        
        // Verify signature using constant time comparison
        if (!hash_equals($expected_signature, $signature)) {
            return new WP_Error(
                'invalid_signature',
                'Invalid signature',
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Handle license verification callback
     * 
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response
     */
    public static function handle_verify_callback($request) {
        $json = $request->get_json_params();
        
        // Check if the request contains valid data
        if (empty($json) || !isset($json['license_key']) || !isset($json['license_status'])) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Invalid request data',
                ),
                400
            );
        }
        
        $license_key = sanitize_text_field($json['license_key']);
        $status = sanitize_text_field($json['license_status']);
        $plan = isset($json['plan']) ? sanitize_text_field($json['plan']) : 'free';
        $expiry = isset($json['expiry']) ? sanitize_text_field($json['expiry']) : '';
        
        // Get the current license key
        $current_license_key = kwetupizza_license_manager()->get_license_key();
        
        // Verify that the received license key matches the stored one
        if ($license_key !== $current_license_key) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'License key mismatch',
                ),
                400
            );
        }
        
        // Update license status and data
        $license_data = array(
            'status' => $status,
            'plan' => $plan,
            'last_check' => current_time('timestamp'),
        );
        
        // Add additional data if provided
        if (!empty($expiry)) {
            $license_data['expiry'] = $expiry;
        }
        
        if (isset($json['customer_name'])) {
            $license_data['customer_name'] = sanitize_text_field($json['customer_name']);
        }
        
        if (isset($json['customer_email'])) {
            $license_data['customer_email'] = sanitize_email($json['customer_email']);
        }
        
        if (isset($json['site_count'])) {
            $license_data['site_count'] = intval($json['site_count']);
        }
        
        if (isset($json['max_sites'])) {
            $license_data['max_sites'] = intval($json['max_sites']);
        }
        
        // Update license data
        update_option('kwetupizza_license_data', $license_data);
        
        // Return success response
        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'License status updated',
                'site_url' => home_url(),
                'site_name' => get_bloginfo('name'),
            ),
            200
        );
    }
    
    /**
     * Handle license deactivation callback
     * 
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response
     */
    public static function handle_deactivate_callback($request) {
        $json = $request->get_json_params();
        
        // Check if the request contains valid data
        if (empty($json) || !isset($json['license_key'])) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Invalid request data',
                ),
                400
            );
        }
        
        $license_key = sanitize_text_field($json['license_key']);
        
        // Get the current license key
        $current_license_key = kwetupizza_license_manager()->get_license_key();
        
        // Verify that the received license key matches the stored one
        if ($license_key !== $current_license_key) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'License key mismatch',
                ),
                400
            );
        }
        
        // Deactivate license
        delete_option('kwetupizza_license_key');
        delete_option('kwetupizza_license_data');
        
        // Return success response
        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'License deactivated',
            ),
            200
        );
    }
    
    /**
     * Send API request to the remote license server
     * 
     * @param string $endpoint API endpoint
     * @param array $data Data to send
     * @return array|WP_Error API response or error
     */
    public static function send_api_request($endpoint, $data = array()) {
        // Add site information to the request
        $data['site_url'] = home_url();
        $data['site_name'] = get_bloginfo('name');
        $data['plugin_version'] = KWETUPIZZA_PLUGIN_VERSION;
        $data['wp_version'] = get_bloginfo('version');
        
        // Prepare the request
        $url = trailingslashit(self::REMOTE_API_URL) . $endpoint;
        
        $response = wp_remote_post($url, array(
            'timeout' => 15,
            'sslverify' => true,
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        
        // Check for successful response (200-299)
        if ($response_code < 200 || $response_code >= 300) {
            $error_message = wp_remote_retrieve_response_message($response);
            
            if (empty($error_message)) {
                $error_message = 'Unknown error occurred.';
            }
            
            return new WP_Error(
                'api_error',
                sprintf('License API Error: %s (%d)', $error_message, $response_code)
            );
        }
        
        // Parse the response body
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'api_error',
                'Invalid JSON response from the license server.'
            );
        }
        
        return $json;
    }
    
    /**
     * Handle AJAX license check
     */
    public static function ajax_check_license() {
        // Check for nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kwetupizza_license_action')) {
            wp_send_json_error(array(
                'message' => 'Security verification failed.',
            ));
        }
        
        // Check license
        $result = kwetupizza_license_manager()->verify_license();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ));
        } else {
            wp_send_json_success(array(
                'message' => 'License verified successfully.',
                'license_data' => kwetupizza_license_manager()->get_license_data(),
            ));
        }
        
        wp_die();
    }
}

// Initialize the license API
KwetuPizza_License_API::init(); 