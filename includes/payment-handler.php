<?php
/**
 * Payment Handler for KwetuPizza Plugin
 * 
 * Handles payment processing, verification, and notifications
 * for different payment methods.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include common functions for secure option handling
require_once plugin_dir_path(__FILE__) . 'common-functions.php';

/**
 * Initialize Flutterwave payment
 * 
 * @param int $order_id The order ID
 * @param array $customer_data Customer data array
 * @return array|WP_Error Payment initialization data or error
 */
if (!function_exists('kwetupizza_initialize_flutterwave_payment')) {
    function kwetupizza_initialize_flutterwave_payment($order_id, $customer_data) {
        // Get order details
        $order = kwetupizza_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', 'Invalid order ID');
        }
        
        // Generate transaction reference
        $tx_ref = 'kwetu_' . $order_id . '_' . time();
        
        // Update order with transaction reference
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'kwetupizza_orders',
            array('tx_ref' => $tx_ref),
            array('id' => $order_id)
        );
        
        // Get Flutterwave API keys
        $public_key = kwetupizza_get_secure_option('kwetupizza_flw_public_key');
        $secret_key = kwetupizza_get_secure_option('kwetupizza_flw_secret_key');
        
        if (empty($public_key) || empty($secret_key)) {
            return new WP_Error('missing_api_keys', 'Flutterwave API keys are not configured');
        }
        
        $currency = get_option('kwetupizza_currency', 'TZS');
        
        // Create payment data
        $payment_data = array(
            'tx_ref' => $tx_ref,
            'amount' => $order->total,
            'currency' => $currency,
            'redirect_url' => home_url('/kwetupizza-payment-callback?order_id=' . $order_id),
            'customer' => array(
                'email' => $customer_data['email'],
                'name' => $customer_data['name'],
                'phone_number' => $customer_data['phone']
            ),
            'meta' => array(
                'order_id' => $order_id
            ),
            'customizations' => array(
                'title' => get_bloginfo('name') . ' - Order #' . $order_id,
                'description' => 'Payment for your order',
                'logo' => get_site_icon_url()
            )
        );
        
        return array(
            'public_key' => $public_key,
            'tx_ref' => $tx_ref,
            'payment_data' => $payment_data
        );
    }
}

/**
 * Process Flutterwave mobile money payment
 * 
 * @param int $order_id The order ID
 * @param string $phone_number Customer phone number
 * @param string $network Mobile money network (vodacom, tigo, etc.)
 * @return array|WP_Error Payment response or error
 */
if (!function_exists('kwetupizza_process_mobile_money_payment')) {
    function kwetupizza_process_mobile_money_payment($order_id, $phone_number, $network) {
        // Get order details
        $order = kwetupizza_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', 'Invalid order ID');
        }
        
        // Generate transaction reference
        $tx_ref = 'kwetu_' . $order_id . '_' . time();
        
        // Update order with transaction reference
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'kwetupizza_orders',
            array('tx_ref' => $tx_ref),
            array('id' => $order_id)
        );
        
        // Get Flutterwave API keys
        $secret_key = kwetupizza_get_secure_option('kwetupizza_flw_secret_key');
        
        if (empty($secret_key)) {
            return new WP_Error('missing_api_keys', 'Flutterwave API keys are not configured');
        }
        
        $currency = get_option('kwetupizza_currency', 'TZS');
        
        // Create payment request data
        $request_data = array(
            'tx_ref' => $tx_ref,
            'amount' => $order->total,
            'currency' => $currency,
            'network' => $network,
            'email' => $order->customer_email,
            'phone_number' => $phone_number,
            'fullname' => $order->customer_name
        );
        
        // Send request to Flutterwave API
        $response = wp_remote_post('https://api.flutterwave.com/v3/charges?type=mobile_money_tanzania', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (!isset($result['status']) || $result['status'] !== 'success') {
            $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
            return new WP_Error('payment_failed', $error_message);
        }
        
        return array(
            'status' => 'success',
            'message' => $result['message'],
            'tx_ref' => $tx_ref,
            'flw_ref' => isset($result['data']['flw_ref']) ? $result['data']['flw_ref'] : ''
        );
    }
}

/**
 * Verify Flutterwave payment
 * 
 * @param string $tx_ref Transaction reference
 * @return array|WP_Error Verification result or error
 */
if (!function_exists('kwetupizza_verify_flutterwave_payment')) {
    function kwetupizza_verify_flutterwave_payment($tx_ref) {
        // Get Flutterwave API key
        $secret_key = kwetupizza_get_secure_option('kwetupizza_flw_secret_key');
        
        if (empty($secret_key)) {
            return new WP_Error('missing_api_key', 'Flutterwave API key is not configured');
        }
        
        // Send verification request to Flutterwave API
        $response = wp_remote_get('https://api.flutterwave.com/v3/transactions/verify?tx_ref=' . urlencode($tx_ref), array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (!isset($result['status']) || $result['status'] !== 'success') {
            $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
            return new WP_Error('verification_failed', $error_message);
        }
        
        $status = isset($result['data']['status']) ? $result['data']['status'] : '';
        
        return array(
            'status' => $status,
            'amount' => isset($result['data']['amount']) ? $result['data']['amount'] : 0,
            'currency' => isset($result['data']['currency']) ? $result['data']['currency'] : '',
            'transaction_id' => isset($result['data']['id']) ? $result['data']['id'] : '',
            'payment_method' => isset($result['data']['payment_type']) ? $result['data']['payment_type'] : '',
            'flw_ref' => isset($result['data']['flw_ref']) ? $result['data']['flw_ref'] : ''
        );
    }
}

/**
 * Record payment transaction in the database
 * 
 * @param int $order_id The order ID
 * @param array $payment_data Payment data
 * @return int|false Transaction ID on success, false on failure
 */
if (!function_exists('kwetupizza_record_payment_transaction')) {
    function kwetupizza_record_payment_transaction($order_id, $payment_data) {
        global $wpdb;
        
        $transaction_data = array(
            'order_id' => $order_id,
            'tx_ref' => $payment_data['tx_ref'],
            'transaction_date' => current_time('mysql'),
            'payment_method' => $payment_data['payment_method'],
            'payment_status' => $payment_data['status'],
            'amount' => $payment_data['amount'],
            'currency' => $payment_data['currency'],
            'payment_provider' => 'flutterwave'
        );
        
        // Save transaction
        return kwetupizza_save_transaction($transaction_data);
    }
}

/**
 * Update order status and notify customer after successful payment
 * 
 * @param int $order_id The order ID
 * @return bool True on success, false on failure
 */
if (!function_exists('kwetupizza_confirm_payment_and_notify')) {
    function kwetupizza_confirm_payment_and_notify($order_id) {
        global $wpdb;
        
        // Get order details
        $order = kwetupizza_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        // Update order status
        $updated = $wpdb->update(
            $wpdb->prefix . 'kwetupizza_orders',
            array('status' => 'processing'),
            array('id' => $order_id)
        );
        
        if ($updated === false) {
            return false;
        }
        
        // Send notification to customer
        kwetupizza_send_payment_success_notification($order->customer_phone, $order->tx_ref);
        
        // Notify admin
        kwetupizza_notify_admin($order_id, true, 'payment');
        
        // Schedule review request if enabled
        if (get_option('kwetupizza_enable_auto_reviews', false)) {
            kwetupizza_schedule_review_request($order_id);
        }
        
        return true;
    }
}

/**
 * Send payment success notification to customer
 * 
 * @param string $phone_number Customer phone number
 * @param string $tx_ref Transaction reference
 * @return bool True on success, false on failure
 */
if (!function_exists('kwetupizza_send_payment_success_notification')) {
    function kwetupizza_send_payment_success_notification($phone_number, $tx_ref) {
        // Get order details
        $order = kwetupizza_get_order_by_tx_ref($tx_ref);
        
        if (!$order) {
            return false;
        }
        
        // Get business name
        $business_name = get_option('kwetupizza_business_name', get_bloginfo('name'));
        
        // Prepare message
        $message = "Thank you for your payment to $business_name. ";
        $message .= "Your order #$order->id is now being processed. ";
        $message .= "We will notify you when it's ready for delivery.";
        
        // Send WhatsApp message
        $whatsapp_sent = kwetupizza_send_whatsapp_message($phone_number, $message);
        
        // Send SMS if WhatsApp failed
        if (!$whatsapp_sent) {
            kwetupizza_send_nextsms($phone_number, $message);
        }
        
        return true;
    }
}

/**
 * Send payment failed notification to customer
 * 
 * @param string $phone_number Customer phone number
 * @param string $tx_ref Transaction reference
 * @return bool True on success, false on failure
 */
if (!function_exists('kwetupizza_send_failed_payment_notification')) {
    function kwetupizza_send_failed_payment_notification($phone_number, $tx_ref) {
        // Get order details
        $order = kwetupizza_get_order_by_tx_ref($tx_ref);
        
        if (!$order) {
            return false;
        }
        
        // Get business name and support number
        $business_name = get_option('kwetupizza_business_name', get_bloginfo('name'));
        $support_number = get_option('kwetupizza_customer_support_number', '');
        
        // Prepare message
        $message = "Your payment to $business_name for order #$order->id was not successful. ";
        $message .= "Please try again or use a different payment method.";
        
        if (!empty($support_number)) {
            $message .= " For assistance, please contact us at $support_number.";
        }
        
        // Send WhatsApp message
        $whatsapp_sent = kwetupizza_send_whatsapp_message($phone_number, $message);
        
        // Send SMS if WhatsApp failed
        if (!$whatsapp_sent) {
            kwetupizza_send_nextsms($phone_number, $message);
        }
        
        return true;
    }
}

/**
 * Send SMS using NextSMS API
 * 
 * @param string $phone_number Recipient phone number
 * @param string $message Message to send
 * @return bool True on success, false on failure
 */
if (!function_exists('kwetupizza_send_nextsms')) {
    function kwetupizza_send_nextsms($phone_number, $message) {
        // Get NextSMS credentials
        $username = kwetupizza_get_secure_option('kwetupizza_nextsms_username');
        $password = kwetupizza_get_secure_option('kwetupizza_nextsms_password');
        $sender_id = get_option('kwetupizza_nextsms_sender_id', 'KwetuPizza');
        
        if (empty($username) || empty($password)) {
            return false;
        }
        
        // Format phone number
        $phone_number = preg_replace('/[^0-9]/', '', $phone_number);
        
        // Ensure phone number starts with country code
        if (substr($phone_number, 0, 1) === '0') {
            $phone_number = '255' . substr($phone_number, 1);
        } elseif (substr($phone_number, 0, 3) !== '255') {
            $phone_number = '255' . $phone_number;
        }
        
        // Prepare API request
        $api_url = 'https://messaging-service.co.tz/api/sms/v1/text/single';
        
        $request_data = array(
            'source_addr' => $sender_id,
            'encoding' => 0,
            'schedule_time' => '',
            'message' => $message,
            'recipients' => array(
                array('recipient_id' => '1', 'dest_addr' => $phone_number)
            )
        );
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            ),
            'body' => json_encode($request_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        // Log SMS sending result if logging is enabled
        if (get_option('kwetupizza_enable_logging', false)) {
            error_log('NextSMS API Response: ' . print_r($result, true));
        }
        
        return isset($result['successful']) && $result['successful'];
    }
}

/**
 * Generate payment receipt
 * 
 * @param int $order_id The order ID
 * @return string HTML receipt content
 */
if (!function_exists('kwetupizza_generate_payment_receipt')) {
    function kwetupizza_generate_payment_receipt($order_id) {
        // Get order details
        $order = kwetupizza_get_order($order_id);
        
        if (!$order) {
            return '';
        }
        
        // Get order items
        $items = kwetupizza_get_order_items($order_id);
        
        // Get transaction details
        global $wpdb;
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kwetupizza_transactions WHERE order_id = %d ORDER BY transaction_date DESC LIMIT 1",
            $order_id
        ));
        
        // Get business details
        $business_name = get_option('kwetupizza_business_name', get_bloginfo('name'));
        $business_address = get_option('kwetupizza_location', '');
        
        // Build receipt HTML
        $receipt = '<div class="kwetupizza-receipt">';
        $receipt .= '<h2>' . esc_html($business_name) . ' - Receipt</h2>';
        
        if (!empty($business_address)) {
            $receipt .= '<p>' . esc_html($business_address) . '</p>';
        }
        
        $receipt .= '<hr>';
        $receipt .= '<p><strong>Order #:</strong> ' . esc_html($order->id) . '</p>';
        $receipt .= '<p><strong>Date:</strong> ' . esc_html($order->order_date) . '</p>';
        $receipt .= '<p><strong>Customer:</strong> ' . esc_html($order->customer_name) . '</p>';
        $receipt .= '<p><strong>Status:</strong> ' . esc_html(ucfirst($order->status)) . '</p>';
        
        if ($transaction) {
            $receipt .= '<p><strong>Transaction ID:</strong> ' . esc_html($transaction->tx_ref) . '</p>';
            $receipt .= '<p><strong>Payment Method:</strong> ' . esc_html(ucfirst($transaction->payment_method)) . '</p>';
        }
        
        $receipt .= '<hr>';
        $receipt .= '<table width="100%" border="0" cellspacing="0" cellpadding="5">';
        $receipt .= '<thead><tr><th align="left">Item</th><th align="center">Qty</th><th align="right">Price</th><th align="right">Total</th></tr></thead>';
        $receipt .= '<tbody>';
        
        foreach ($items as $item) {
            $item_total = $item->quantity * $item->price;
            $receipt .= '<tr>';
            $receipt .= '<td>' . esc_html($item->product_name) . '</td>';
            $receipt .= '<td align="center">' . esc_html($item->quantity) . '</td>';
            $receipt .= '<td align="right">' . esc_html(number_format($item->price, 2)) . ' ' . esc_html($order->currency) . '</td>';
            $receipt .= '<td align="right">' . esc_html(number_format($item_total, 2)) . ' ' . esc_html($order->currency) . '</td>';
            $receipt .= '</tr>';
        }
        
        $receipt .= '</tbody>';
        $receipt .= '<tfoot>';
        $receipt .= '<tr><td colspan="3" align="right"><strong>Total</strong></td><td align="right"><strong>' . esc_html(number_format($order->total, 2)) . ' ' . esc_html($order->currency) . '</strong></td></tr>';
        $receipt .= '</tfoot>';
        $receipt .= '</table>';
        
        $receipt .= '<hr>';
        $receipt .= '<p><em>Thank you for your order!</em></p>';
        $receipt .= '</div>';
        
        return $receipt;
    }
}

/**
 * Get customer details for payment by phone number
 * 
 * @param string $phone_number Customer phone number
 * @return array Customer details array
 */
if (!function_exists('kwetupizza_get_customer_details') && !function_exists('kwetupizza_get_customer_details_for_payment')) {
    function kwetupizza_get_customer_details_for_payment($phone_number) {
        // Get user from database
        $user = kwetupizza_get_user_by_phone($phone_number);
        
        if ($user) {
            return array(
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone
            );
        }
        
        // Default values if user not found
        return array(
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => $phone_number
        );
    }
} 