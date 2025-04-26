<?php
/**
 * WhatsApp Handler for KwetuPizza Plugin
 * 
 * Improved implementation for reliable WhatsApp Cloud API webhook registration
 * and message processing.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . '/common-functions.php';
require_once plugin_dir_path(dirname(__FILE__)) . '/admin/settings-page.php';

// Get business name function for messages
if (!function_exists('kwetupizza_get_business_name')) {
    function kwetupizza_get_business_name() {
        return get_option('kwetupizza_business_name', 'KwetuPizza');
    }
}

/**
 * Register WhatsApp webhook routes
 * Separated webhook registration from other routes for clarity
 */
if (!function_exists('kwetupizza_register_webhook_routes')) {
    function kwetupizza_register_webhook_routes() {
        // WhatsApp Webhook Routes - GET for verification
        register_rest_route('kwetupizza/v1', '/whatsapp-webhook', array(
            'methods' => 'GET',
            'callback' => 'kwetupizza_handle_whatsapp_verification',
            'permission_callback' => '__return_true',
        ));

        // WhatsApp Webhook Routes - POST for message processing
        register_rest_route('kwetupizza/v1', '/whatsapp-webhook', array(
            'methods' => 'POST',
            'callback' => 'kwetupizza_handle_whatsapp_messages',
            'permission_callback' => '__return_true',
        ));
        
        // Flutterwave Webhook Route
        register_rest_route('kwetupizza/v1', '/flutterwave-webhook', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'log_flutterwave_payment_webhook',
            'permission_callback' => '__return_true',
        ));
        
        // Order tracking route
        register_rest_route('kwetupizza/v1', '/order-status/(?P<order_id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'kwetupizza_get_order_status',
            'permission_callback' => '__return_true',
        ));
        
        // Add Webhook Testing Endpoint
        register_rest_route('kwetupizza/v1', '/test-webhook', array(
            'methods' => WP_REST_Server::ALLMETHODS, // Accept GET, POST, etc.
            'callback' => 'kwetupizza_test_webhook_callback', // Renamed callback function
            'permission_callback' => '__return_true', // Open for testing, consider adding security later
        ));
    }
}
add_action('rest_api_init', 'kwetupizza_register_webhook_routes');

/**
 * Verify the service token for secure API access
 * 
 * @param WP_REST_Request $request The request object
 * @return bool Whether the token is valid
 */
if (!function_exists('kwetupizza_verify_service_token')) {
    function kwetupizza_verify_service_token($request) {
        $token = $request->get_header('X-Webhook-Token');
        $stored_token = get_option('kwetupizza_webhook_security_token');
        
        // If no token is set, deny access
        if (empty($stored_token)) {
            return false;
        }
        
        // Compare the provided token with the stored token
        return $token === $stored_token;
    }
}

/**
 * Service credentials endpoint callback
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response with credentials
 */
if (!function_exists('kwetupizza_service_credentials_endpoint')) {
    function kwetupizza_service_credentials_endpoint($request) {
        $service_name = $request->get_param('service_name');
        $callback_type = $request->get_param('callback_type') ?? 'webhook';
        
        // Get credentials for the requested service
        $credentials = kwetupizza_get_service_credentials($service_name, $callback_type);
        
        if (isset($credentials['error'])) {
            return new WP_REST_Response(['error' => $credentials['error']], 400);
        }
        
        return new WP_REST_Response($credentials, 200);
    }
}

/**
 * WhatsApp webhook verification handler
 * Optimized to strictly follow WhatsApp Cloud API requirements
 * 
 * @param WP_REST_Request $request The request object
 */
if (!function_exists('kwetupizza_handle_whatsapp_verification')) {
    function kwetupizza_handle_whatsapp_verification($request) {
        // Log all request details for debugging
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('WhatsApp webhook verification - Full request details:');
            error_log('Method: ' . $_SERVER['REQUEST_METHOD']);
            error_log('Query String: ' . $_SERVER['QUERY_STRING']);
            error_log('REQUEST parameters: ' . print_r($_REQUEST, true));
            error_log('GET parameters: ' . print_r($_GET, true));
        }
        
        // Get verification parameters from the request
        // Try multiple methods to get the parameters
        $mode = $request->get_param('hub.mode');
        if (empty($mode)) {
            $mode = isset($_GET['hub_mode']) ? $_GET['hub_mode'] : (isset($_GET['hub.mode']) ? $_GET['hub.mode'] : '');
        }
        
        $token = $request->get_param('hub.verify_token');
        if (empty($token)) {
            $token = isset($_GET['hub_verify_token']) ? $_GET['hub_verify_token'] : (isset($_GET['hub.verify_token']) ? $_GET['hub.verify_token'] : '');
        }
        
        $challenge = $request->get_param('hub.challenge');
        if (empty($challenge)) {
            $challenge = isset($_GET['hub_challenge']) ? $_GET['hub_challenge'] : (isset($_GET['hub.challenge']) ? $_GET['hub.challenge'] : '');
        }
        
        // Retrieve stored token
        $verify_token = get_option('kwetupizza_whatsapp_verify_token');
        
        // Log verification attempt details
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('WhatsApp webhook verification attempt:');
            error_log('hub.mode: ' . sanitize_text_field($mode ?: ''));
            error_log('hub.challenge: ' . sanitize_text_field($challenge ?: ''));
            error_log('Token provided: ' . (empty($token) ? 'EMPTY' : 'PROVIDED'));
            error_log('Token stored: ' . (empty($verify_token) ? 'EMPTY' : 'EXISTS'));
        }
        
        // Step 1: Check if all required parameters exist
        if (empty($mode) || empty($token) || empty($challenge)) {
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('Verification failed: Missing required parameters');
            }
            return new WP_REST_Response('Verification failed: Missing parameters', 400);
        }
        
        // Step 2: Verify the mode is 'subscribe'
        if ($mode !== 'subscribe') {
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('Verification failed: Invalid mode "' . sanitize_text_field($mode) . '"');
            }
            return new WP_REST_Response('Verification failed: Invalid mode', 400);
        }
        
        // Step 3: Verify the token matches
        if ($verify_token !== $token) {
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('Verification failed: Token mismatch');
            }
            return new WP_REST_Response('Verification failed: Token mismatch', 403);
        }
        
        // Log success
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('Webhook verification successful! Returning challenge.');
        }
        
        // CRITICAL: Return a plain text response with the challenge - NOT WordPress JSON
        // This must be a direct output without modification by WordPress
        status_header(200);
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }
}

/**
 * WhatsApp message handler
 * Processes incoming messages from WhatsApp
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response The response
 */
if (!function_exists('kwetupizza_handle_whatsapp_messages')) {
    function kwetupizza_handle_whatsapp_messages($request) {
        $body = $request->get_body();
        
        // Verify the signature if app secret is configured
        $signature = $request->get_header('x-hub-signature-256');
        if (!empty($signature) && !kwetupizza_verify_whatsapp_signature($signature, $body)) {
            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('WhatsApp webhook: Signature verification failed');
            }
            return new WP_REST_Response('Invalid signature', 403);
        }
        
        // Parse request data
        $webhook_data = json_decode($body, true);
        
        // Log the request data for debugging
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('WhatsApp webhook payload received: ' . print_r($webhook_data, true));
        }
        
        // Always acknowledge receipt to prevent retries
        if (empty($webhook_data) || !is_array($webhook_data)) {
            return new WP_REST_Response('Received', 200);
        }
        
        // Process immediately for better response time
        if (isset($webhook_data['entry'][0]['changes'][0]['value']['messages'])) {
            // Process synchronously for actual messages
            kwetupizza_process_whatsapp_webhook($webhook_data);
        } else {
            // Use WordPress scheduling for status updates or other non-message events
            wp_schedule_single_event(time(), 'kwetupizza_process_whatsapp_webhook', array($webhook_data));
        }
        
        // Return success response immediately
        return new WP_REST_Response('Received', 200);
    }
}

/**
 * Async webhook processor
 * Handles the webhook data processing in the background
 */
if (!function_exists('kwetupizza_process_whatsapp_webhook')) {
    function kwetupizza_process_whatsapp_webhook($webhook_data) {
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('Processing webhook data asynchronously: ' . print_r($webhook_data, true));
        }
        
        // Extract data from the webhook payload
        if (!isset($webhook_data['entry'][0]['changes'][0]['value'])) {
            error_log('Invalid webhook structure: Missing value data');
            return;
        }
        
        $value = $webhook_data['entry'][0]['changes'][0]['value'];
        
        // Message processing
        if (isset($value['messages']) && !empty($value['messages'][0])) {
            $message = $value['messages'][0];
            $from = $message['from'];
            $message_type = $message['type'];
            $message_content = '';

            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('Extracted message from: ' . $from . ', type: ' . $message_type);
            }

            // Extract message content based on type
            switch ($message_type) {
                case 'text':
                    $message_content = isset($message['text']['body']) ? $message['text']['body'] : '';
                    break;
                    
                case 'interactive':
                    if (isset($message['interactive']['button_reply']['id'])) {
                        $message_content = $message['interactive']['button_reply']['id'];
                    } elseif (isset($message['interactive']['list_reply']['id'])) {
                        $message_content = $message['interactive']['list_reply']['id'];
                    }
                    break;
                    
                case 'location':
                    if (isset($message['location'])) {
                        $latitude = $message['location']['latitude'];
                        $longitude = $message['location']['longitude'];
                        $message_content = "location:$latitude,$longitude";
                    }
                    break;
                    
                default:
                    $message_content = "unsupported:$message_type";
            }

            if (get_option('kwetupizza_enable_logging', false)) {
                error_log('Extracted message content: ' . $message_content);
            }

            // Process the message if we have valid data
            if (!empty($from) && !empty($message_content)) {
                // Direct call instead of hook for more immediate response
                kwetupizza_process_message($from, $message_content, $message_type);
                
                if (get_option('kwetupizza_enable_logging', false)) {
                    error_log('Message passed to process_message function');
                }
            } else {
                error_log('Error: Empty from or message_content');
            }
        }
        // Status update processing
        elseif (isset($value['statuses']) && !empty($value['statuses'][0])) {
            $status = $value['statuses'][0];
            do_action('kwetupizza_whatsapp_status_update', $status);
        } else {
            error_log('No messages or statuses found in webhook data');
        }
    }
}
add_action('kwetupizza_process_whatsapp_webhook', 'kwetupizza_process_whatsapp_webhook');

/**
 * Verify WhatsApp webhook signature
 * 
 * @param string $signature The signature from the request header
 * @param string $payload The raw request body
 * @return bool Whether the signature is valid
 */
if (!function_exists('kwetupizza_verify_whatsapp_signature')) {
    function kwetupizza_verify_whatsapp_signature($signature, $payload) {
        $app_secret = get_option('kwetupizza_whatsapp_app_secret');
        
        // If no app secret configured, skip verification
        if (empty($app_secret) || empty($signature) || empty($payload)) {
            return true;
        }
        
        // Ensure we have strings
        $signature = (string)$signature;
        $payload = (string)$payload;
        
        // Check signature format
        if (strpos($signature, 'sha256=') !== 0) {
            return false;
        }

        // Generate expected signature
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $app_secret);
        
        // Compare using timing-safe comparison
        return hash_equals($expected, $signature);
    }
}

/**
 * Get WhatsApp webhook URL for configuration
 * 
 * @return string The webhook URL
 */
if (!function_exists('kwetupizza_get_whatsapp_webhook_url')) {
    function kwetupizza_get_whatsapp_webhook_url() {
        return esc_url(home_url('/wp-json/kwetupizza/v1/whatsapp-webhook'));
    }
}

/**
 * Generate a random verify token for WhatsApp
 * 
 * @return string The generated token
 */
if (!function_exists('kwetupizza_generate_whatsapp_verify_token')) {
    function kwetupizza_generate_whatsapp_verify_token() {
        $token = wp_generate_password(32, false, false);
        update_option('kwetupizza_whatsapp_verify_token', $token);
        return $token;
    }
}

/**
 * Render helper text for WhatsApp webhook configuration
 */
if (!function_exists('kwetupizza_render_whatsapp_webhook_helper')) {
    function kwetupizza_render_whatsapp_webhook_helper() {
        $webhook_url = kwetupizza_get_whatsapp_webhook_url();
        $verify_token = kwetupizza_get_secure_option('kwetupizza_whatsapp_verify_token');
        
        if (empty($verify_token)) {
            $verify_token = kwetupizza_generate_whatsapp_verify_token();
        }
        
        echo '<div class="notice notice-info">';
        echo '<p><strong>WhatsApp Webhook Configuration Instructions:</strong></p>';
        echo '<ol>';
        echo '<li>Go to <a href="https://developers.facebook.com/apps" target="_blank">Meta Developers</a> and select your app</li>';
        echo '<li>Navigate to WhatsApp > Configuration</li>';
        echo '<li>Under Webhook, click "Configure"</li>';
        echo '<li>Enter this Callback URL: <code>' . esc_html($webhook_url) . '</code></li>';
        echo '<li>Enter this Verify Token: <code>' . esc_html($verify_token) . '</code></li>';
        echo '<li>Subscribe to these fields: <code>messages</code></li>';
        echo '<li>Click "Verify and Save"</li>';
        echo '</ol>';
        
        // Add test button
        echo '<p><button type="button" id="test-whatsapp-webhook" class="button">Test Webhook Configuration</button>';
        echo '<span id="webhook-test-result" style="margin-left: 10px;"></span></p>';
        echo '</div>';
        
        // Add JavaScript to test the webhook
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#test-whatsapp-webhook').on('click', function() {
                var $button = $(this);
                var $result = $('#webhook-test-result');
                
                $button.prop('disabled', true);
                $result.html('<em>Testing webhook...</em>');
                
                $.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'test_whatsapp_webhook',
                        nonce: '<?php echo wp_create_nonce('test_whatsapp_webhook'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color:green;">✓ Success! Webhook is properly configured.</span>');
                        } else {
                            $result.html('<span style="color:red;">✗ Error: ' + response.data + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color:red;">✗ Test failed. Check server logs.</span>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
}

/**
 * AJAX handler for testing the WhatsApp webhook
 */
if (!function_exists('kwetupizza_test_whatsapp_webhook_ajax')) {
    function kwetupizza_test_whatsapp_webhook_ajax() {
        check_ajax_referer('test_whatsapp_webhook', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $webhook_url = kwetupizza_get_whatsapp_webhook_url();
        $verify_token = get_option('kwetupizza_whatsapp_verify_token');
        
        if (empty($verify_token)) {
            wp_send_json_error('Verify token is not configured');
        }
        
        // Build test URL with query parameters
        $test_url = add_query_arg(array(
            'hub.mode' => 'subscribe',
            'hub.verify_token' => $verify_token,
            'hub.challenge' => 'webhook_challenge_test'
        ), $webhook_url);
        
        // Send a test request
        $response = wp_remote_get($test_url, array(
            'timeout' => 15,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            wp_send_json_error("HTTP error $code: $body");
        }
        
        if ($body !== 'webhook_challenge_test') {
            wp_send_json_error("Challenge response mismatch. Got: $body");
        }
        
        wp_send_json_success();
    }
}
add_action('wp_ajax_test_whatsapp_webhook', 'kwetupizza_test_whatsapp_webhook_ajax');

// Process WhatsApp messages (called via wp-cron)
add_action('kwetupizza_process_whatsapp_message', 'kwetupizza_process_message', 10, 3);

/**
 * Process incoming WhatsApp messages
 * 
 * @param string $from The sender's phone number
 * @param string $message The message content
 * @param string $message_type The message type (text, image, etc.)
 * @return bool Whether the message was processed successfully
 */
function kwetupizza_process_message($from, $message, $message_type = 'text') {
    // Add detailed logging
    if (get_option('kwetupizza_enable_logging', false)) {
        error_log('Processing WhatsApp message: ' . $message . ' from: ' . $from . ' type: ' . $message_type);
    }
    
    try {
        // Send a basic response
        $success = kwetupizza_send_whatsapp_message(
            $from, 
            "Hello! Thanks for your message: \"$message\". Our system is now processing your request.", 
            'text'
        );
        
        if ($success) {
            error_log("Successfully sent message to $from");
        } else {
            error_log("Failed to send message to $from");
        }
        
        return $success;
    } catch (Exception $e) {
        error_log("Exception when sending message: " . $e->getMessage());
        return false;
    }
}

function kwetupizza_get_order_status($request) {
    // Implement your order status logic here
    // This function should be kept from the original code
}

function log_flutterwave_payment_webhook($request) {
    // Implement your payment webhook logic here
    // This function should be kept from the original code
}

function kwetupizza_verify_flutterwave_signature($request) {
    // Implement your signature verification logic here
    // This function should be kept from the original code
}
if (!function_exists('kwetupizza_notify_admin')) {
    function kwetupizza_notify_admin($order_id, $success = true, $type = 'payment') {
    // Implement your admin notification logic here
    // This function should be kept from the original code
}
}
function kwetupizza_generate_mobile_money_push($from, $cart, $address, $payment_phone) {
    // Implement your mobile money logic here
    // This function should be kept from the original code
}

function kwetupizza_notify_admin_by_order_tx_ref($tx_ref, $success = true) {
    // Implement your admin notification logic here
    // This function should be kept from the original code
}

function kwetupizza_add_affiliate_links($message) {
    // Implement your affiliate links logic here
    // This function should be kept from the original code
}

function kwetupizza_test_webhook_callback($request) {
    // Implement your webhook testing logic here
    // This function should be kept from the original code
}

/**
 * Send a WhatsApp message
 * 
 * @param string $to Recipient phone number
 * @param string $message Message content
 * @param string $message_type Message type (text, image, interactive)
 * @param string $media_url Media URL for media messages
 * @param array $buttons Button data for interactive messages
 * @return bool Whether the message was sent successfully
 */
if (!function_exists('kwetupizza_send_whatsapp_message')) {
    function kwetupizza_send_whatsapp_message($to, $message, $message_type = 'text', $media_url = null, $buttons = null) {
        $token = get_option('kwetupizza_whatsapp_token');
        $phone_id = get_option('kwetupizza_whatsapp_phone_id');
        $api_version = get_option('kwetupizza_whatsapp_api_version', 'v15.0');
        
        if (empty($token) || empty($phone_id)) {
            error_log('WhatsApp API credentials missing');
            return false;
        }
        
        // Ensure version has 'v' prefix
        if (strpos($api_version, 'v') !== 0) {
            $api_version = 'v' . $api_version;
        }
        
        // Log API information for debugging
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('WhatsApp API URL: https://graph.facebook.com/' . $api_version . '/' . $phone_id . '/messages');
            error_log('Using Phone ID: ' . $phone_id);
        }
        
        // Prepare request data
        $request_data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
        ];
        
        // Set message type and content
        if ($message_type === 'text') {
            $request_data['type'] = 'text';
            $request_data['text'] = [
                'preview_url' => false,
                'body' => $message
            ];
        } else if ($message_type === 'image' && !empty($media_url)) {
            $request_data['type'] = 'image';
            $request_data['image'] = [
                'link' => $media_url
            ];
        } else if ($message_type === 'interactive' && !empty($buttons)) {
            $request_data['type'] = 'interactive';
            $request_data['interactive'] = $buttons;
        }
        
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('WhatsApp API Request: ' . json_encode($request_data));
        }
        
        // Send API request
        $response = wp_remote_post("https://graph.facebook.com/{$api_version}/{$phone_id}/messages", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($request_data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log('WhatsApp API Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            error_log('WhatsApp API Error: ' . print_r($response_body, true));
            return false;
        }
        
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('WhatsApp message sent successfully: ' . print_r($response_body, true));
        }
        
        return true;
    }
}

/**
 * Get conversation context for a given phone number
 * 
 * @param string $phone The phone number to get context for
 * @return array The conversation context
 */
if (!function_exists('kwetupizza_get_conversation_context')) {
    function kwetupizza_get_conversation_context($phone) {
        global $wpdb;
        
        // Try to get context from user record
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kwetupizza_users WHERE phone = %s",
            $phone
        ));
        
        if (!$user) {
            // Create a new user if they don't exist
            $wpdb->insert(
                $wpdb->prefix . 'kwetupizza_users',
                array(
                    'name' => 'WhatsApp User',
                    'email' => '',
                    'phone' => $phone,
                    'role' => 'customer',
                    'state' => 'greeting'
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
            
            // Return default context for new users
            return array(
                'state' => 'greeting',
                'cart' => array(),
                'last_activity' => time(),
                'awaiting' => '',
                'order_id' => 0
            );
        }
        
        // State is stored directly in the user record
        $state = $user->state ?: 'greeting';
        
        // Try to get additional context from user meta
        $context_json = get_user_meta($user->id, 'whatsapp_context', true);
        if (!empty($context_json)) {
            $context = json_decode($context_json, true);
            if (is_array($context)) {
                $context['state'] = $state; // Make sure state is current
                return $context;
            }
        }
        
        // Return default context if none found
        return array(
            'state' => $state,
            'cart' => array(),
            'last_activity' => time(),
            'awaiting' => '',
            'order_id' => 0
        );
    }
}

/**
 * Set conversation context for a given phone number
 * 
 * @param string $phone The phone number to set context for
 * @param array $context The conversation context to set
 * @return bool Whether the operation was successful
 */
if (!function_exists('kwetupizza_set_conversation_context')) {
    function kwetupizza_set_conversation_context($phone, $context) {
        global $wpdb;

        if (empty($phone) || !is_array($context)) {
            return false;
        }
        
        // Get user ID
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}kwetupizza_users WHERE phone = %s",
            $phone
        ));
        
        if (!$user) {
            // Create user if they don't exist
            $result = $wpdb->insert(
                $wpdb->prefix . 'kwetupizza_users',
                array(
                    'name' => 'WhatsApp User',
                    'email' => '',
                    'phone' => $phone,
                    'role' => 'customer',
                    'state' => isset($context['state']) ? $context['state'] : 'greeting'
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
            
            if (!$result) {
                return false;
            }
            
            $user_id = $wpdb->insert_id;
        } else {
            $user_id = $user->id;
            
            // Update state in user record
            if (isset($context['state'])) {
                $wpdb->update(
                    $wpdb->prefix . 'kwetupizza_users',
                    array('state' => $context['state']),
                    array('id' => $user_id),
                    array('%s'),
                    array('%d')
                );
            }
        }
        
        // Set last activity time
        $context['last_activity'] = time();
        
        // Save context to user meta
        $context_json = json_encode($context);
        return update_user_meta($user_id, 'whatsapp_context', $context_json);
    }
}

/**
 * Reset conversation context for a given phone number
 * 
 * @param string $phone The phone number to reset context for
 * @return bool Whether the operation was successful
 */
if (!function_exists('kwetupizza_reset_conversation_context')) {
    function kwetupizza_reset_conversation_context($phone) {
        global $wpdb;
        
        // Update state to greeting
        $result = $wpdb->update(
            $wpdb->prefix . 'kwetupizza_users',
            array('state' => 'greeting'),
            array('phone' => $phone),
            array('%s'),
            array('%s')
        );
        
        // Get user ID
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}kwetupizza_users WHERE phone = %s",
            $phone
        ));
        
        if ($user) {
            // Reset context to default
            $default_context = array(
                'state' => 'greeting',
                'cart' => array(),
                'last_activity' => time(),
                'awaiting' => '',
                'order_id' => 0
            );
            
            update_user_meta($user->id, 'whatsapp_context', json_encode($default_context));
        }
        
        return true;
    }
}
