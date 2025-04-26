<?php
/*
Plugin Name: KwetuPizza Plugin
Description: A pizza order management plugin with custom database structure, WhatsApp bot integration, and webhook callback URL auto-generation.
Version: 1.5
Author: Homeboy20
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include common functions
require_once plugin_dir_path(__FILE__) . 'includes/common-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/database-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/token-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/license-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/payment-handler.php';

// Include WhatsApp handler
require_once plugin_dir_path(__FILE__) . 'includes/whatsapp-handler.php';

// Include admin pages
require_once plugin_dir_path(__FILE__) . 'admin/dashboard.php';
require_once plugin_dir_path(__FILE__) . 'admin/menu-management.php';
require_once plugin_dir_path(__FILE__) . 'admin/order-management.php';
require_once plugin_dir_path(__FILE__) . 'admin/transaction-management.php';
require_once plugin_dir_path(__FILE__) . 'admin/user-management.php';
require_once plugin_dir_path(__FILE__) . 'admin/user-detail.php';
require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';
require_once plugin_dir_path(__FILE__) . 'admin/whatsapp-inbox.php';
require_once plugin_dir_path(__FILE__) . 'admin/review-management.php';
require_once plugin_dir_path(__FILE__) . 'admin/license-admin.php';
require_once plugin_dir_path(__FILE__) . 'admin/webhook-test-fixed.php';
require_once plugin_dir_path(__FILE__) . 'admin/migration-script.php';

// Define the database version
define('KWETUPIZZA_DB_VERSION', '1.1');

/**
 * Initialize plugin on activation
 */
function kwetupizza_plugin_activation() {
    // Create or update database tables
    kwetupizza_create_tables();
    
    // Add basic plugin information to options
    update_option('kwetupizza_plugin_version', '1.5');
    update_option('kwetupizza_plugin_activated', current_time('mysql'));
    
    // Generate a default webhook security token if not already set
    $token = get_option('kwetupizza_webhook_security_token');
    if (empty($token)) {
        update_option('kwetupizza_webhook_security_token', wp_generate_password(32, true, true));
    }
    
    // Set default settings for new installations
    if (!get_option('kwetupizza_currency')) {
        update_option('kwetupizza_currency', 'TZS');
    }
    
    if (!get_option('kwetupizza_inactivity_timeout')) {
        update_option('kwetupizza_inactivity_timeout', 3); // 3 minutes default
    }
    
    // Create a reference to plugin pages in options for easy lookup
    update_option('kwetupizza_admin_dashboard', admin_url('admin.php?page=kwetupizza-dashboard'));
    update_option('kwetupizza_admin_settings', admin_url('admin.php?page=kwetupizza-settings'));
    update_option('kwetupizza_admin_orders', admin_url('admin.php?page=kwetupizza-orders'));
    update_option('kwetupizza_admin_menu', admin_url('admin.php?page=kwetupizza-menu'));
    update_option('kwetupizza_admin_users', admin_url('admin.php?page=kwetupizza-users'));
}
register_activation_hook(__FILE__, 'kwetupizza_plugin_activation');

/**
 * Create or update the custom database tables upon plugin activation.
 */
function kwetupizza_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Include the dbDelta function
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Define table names
    $users_table        = $wpdb->prefix . 'kwetupizza_users';
    $products_table     = $wpdb->prefix . 'kwetupizza_products';
    $orders_table       = $wpdb->prefix . 'kwetupizza_orders';
    $order_items_table  = $wpdb->prefix . 'kwetupizza_order_items';
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
    $addresses_table = $wpdb->prefix . 'kwetupizza_addresses';

    // SQL for creating or updating tables
    $sql = "";
    $sql = "";
    // Address Tables
    $sql .= "CREATE TABLE {$addresses_table} (
        id MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id MEDIUMINT(9) UNSIGNED NOT NULL,
        address TEXT NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;\n";
    
    // Users Table
    $sql .= "CREATE TABLE {$users_table} (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        role VARCHAR(20) NOT NULL,
        state VARCHAR(255) DEFAULT 'greeting' NOT NULL,
        UNIQUE KEY phone (phone),
        PRIMARY KEY (id)
    ) $charset_collate;\n";
    
    // Products Table
    $sql .= "CREATE TABLE {$products_table} (
        id MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_name VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        price FLOAT NOT NULL,
        currency VARCHAR(10) NOT NULL,
        category VARCHAR(50) NOT NULL,
        image_url VARCHAR(255) DEFAULT '',
        PRIMARY KEY (id)
    ) $charset_collate;\n";
    
    // Orders Table
    $sql .= "CREATE TABLE {$orders_table} (
        id MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        tx_ref VARCHAR(255) DEFAULT NULL,
        order_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_email VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        delivery_address TEXT NOT NULL,
        delivery_phone VARCHAR(20) NOT NULL,
        status VARCHAR(50) NOT NULL,
        total FLOAT NOT NULL,
        currency VARCHAR(10) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;\n";
    
    // Order Items Table
    $sql .= "CREATE TABLE {$order_items_table} (
        id MEDIUMINT(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id MEDIUMINT(9) UNSIGNED NOT NULL,
        product_id MEDIUMINT(9) UNSIGNED NOT NULL,
        quantity INT NOT NULL,
        price FLOAT NOT NULL,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY product_id (product_id)
    ) $charset_collate;\n";
    
    // Transactions Table
    $sql .= "CREATE TABLE {$transactions_table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id MEDIUMINT(9) UNSIGNED NOT NULL,
        tx_ref VARCHAR(255) NOT NULL,
        transaction_date DATETIME NOT NULL,
        payment_method VARCHAR(100) NOT NULL,
        payment_status VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) NOT NULL,
        payment_provider VARCHAR(50) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY tx_ref (tx_ref),
        KEY order_id (order_id)
    ) $charset_collate;\n";
    
    // Execute the SQL to create or update tables
    dbDelta($sql);
    
    // Execute the SQL to create or update tables
    dbDelta($sql);

    // Update the database version option
    update_option('kwetupizza_db_version', KWETUPIZZA_DB_VERSION);
}

/**
 * Check and update the database version if necessary.
 */
function kwetupizza_update_db_check() {
    if (get_option('kwetupizza_db_version') != KWETUPIZZA_DB_VERSION) {
        kwetupizza_create_tables();
    }
}
add_action('plugins_loaded', 'kwetupizza_update_db_check');

// Generate callback URLs for webhooks
function kwetupizza_get_callback_url($service) {
    return esc_url(home_url('/wp-json/kwetupizza/v1/' . $service . '-webhook'));
}

// Create menu in the WordPress dashboard
function kwetupizza_create_menu() {
    // Add the main menu with Dashboard page
    add_menu_page(
        'KwetuPizza Dashboard',    // Page title
        'KwetuPizza',              // Menu title
        'manage_options',          // Capability
        'kwetupizza-dashboard',    // Menu slug
        'kwetupizza_render_dashboard', // Callback
        'dashicons-store',         // Icon
        5                          // Position (5 puts it near the top, after Posts)
    );
    
    // Add submenu pages - first item is automatically same as parent
    add_submenu_page(
        'kwetupizza-dashboard',    // Parent slug
        'KwetuPizza Dashboard',    // Page title
        'Dashboard',               // Menu title
        'manage_options',          // Capability
        'kwetupizza-dashboard',    // Menu slug (must match parent for first item)
        'kwetupizza_render_dashboard' // Callback
    );
    
    // Other submenu pages
    add_submenu_page('kwetupizza-dashboard', 'Menu Management', 'Menu Management', 'manage_options', 'kwetupizza-menu', 'kwetupizza_render_menu_management');
    add_submenu_page('kwetupizza-dashboard', 'Order Management', 'Order Management', 'manage_options', 'kwetupizza-orders', 'kwetupizza_render_order_management');
    add_submenu_page('kwetupizza-dashboard', 'Transaction Management', 'Transaction Management', 'manage_options', 'kwetupizza-transactions', 'kwetupizza_render_transaction_management');
    add_submenu_page('kwetupizza-dashboard', 'User Management', 'User Management', 'manage_options', 'kwetupizza-users', 'kwetupizza_render_user_management');
    add_submenu_page('kwetupizza-dashboard', 'WhatsApp Inbox', 'WhatsApp Inbox', 'manage_options', 'kwetupizza-whatsapp-inbox', 'kwetupizza_render_whatsapp_inbox');
    add_submenu_page('kwetupizza-dashboard', 'Customer Reviews', 'Customer Reviews', 'manage_options', 'kwetupizza-reviews', 'kwetupizza_render_review_management');
    add_submenu_page('kwetupizza-dashboard', 'Settings', 'Settings', 'manage_options', 'kwetupizza-settings', 'kwetupizza_render_settings_page');
    
    // Move Webhook Tester to the bottom as it's less frequently used
    add_submenu_page('kwetupizza-dashboard', 'Webhook Tester', 'Webhook Tester', 'manage_options', 'kwetupizza-webhook-test', 'kwetupizza_render_webhook_test_page');
}
add_action('admin_menu', 'kwetupizza_create_menu');

// Enqueue admin scripts and styles
function kwetupizza_admin_scripts($hook) {
    // Only enqueue on our plugin's settings page
    if (strpos($hook, 'kwetupizza') !== false) {
        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Enqueue media uploader
        wp_enqueue_media();
    }
}
add_action('admin_enqueue_scripts', 'kwetupizza_admin_scripts');

// Sections callback
function kwetupizza_settings_section_callback() {
    echo '<p>Configure the plugin settings below:</p>';
}

// Settings callbacks for general settings
function kwetupizza_currency_callback() {
    $value = get_option('kwetupizza_currency', 'TZS');
    echo '<input type="text" name="kwetupizza_currency" value="' . esc_attr($value) . '" />';
}

function kwetupizza_location_callback() {
    $value = get_option('kwetupizza_location', '');
    echo '<input type="text" name="kwetupizza_location" value="' . esc_attr($value) . '" />';
}

function kwetupizza_delivery_area_callback() {
    $value = get_option('kwetupizza_delivery_area', '');
    echo '<textarea name="kwetupizza_delivery_area" rows="3" cols="50">' . esc_textarea($value) . '</textarea>';
}

function kwetupizza_customer_support_number_callback() {
    $value = get_option('kwetupizza_customer_support_number', '');
    echo '<input type="text" name="kwetupizza_customer_support_number" value="' . esc_attr($value) . '" />';
    echo '<p class="description">Phone number for customer support, displayed in help messages.</p>';
}

function kwetupizza_inactivity_timeout_callback() {
    $value = get_option('kwetupizza_inactivity_timeout', 3);
    echo '<input type="number" name="kwetupizza_inactivity_timeout" value="' . esc_attr($value) . '" min="1" max="60" /> minutes';
    echo '<p class="description">Time after which an inactive conversation will be reset.</p>';
}

function kwetupizza_enable_auto_reviews_callback() {
    $checked = get_option('kwetupizza_enable_auto_reviews', false) ? 'checked' : '';
    echo '<input type="checkbox" name="kwetupizza_enable_auto_reviews" value="1" ' . $checked . ' />';
    echo '<p class="description">Automatically send review requests after order completion.</p>';
}

function kwetupizza_review_delay_callback() {
    $value = get_option('kwetupizza_review_delay', 1);
    echo '<input type="number" name="kwetupizza_review_delay" value="' . esc_attr($value) . '" min="1" max="72" /> hours';
    echo '<p class="description">Time to wait after order completion before sending review request.</p>';
}

function kwetupizza_enable_logging_callback() {
    $checked = get_option('kwetupizza_enable_logging', false) ? 'checked' : '';
    echo '<input type="checkbox" name="kwetupizza_enable_logging" value="1" ' . $checked . ' />';
    echo '<p class="description">Enable detailed logging for debugging.</p>';
}

// Settings callbacks for WhatsApp API
function kwetupizza_whatsapp_token_callback() {
    $token = get_option('kwetupizza_whatsapp_token', '');
    echo "<input type='text' name='kwetupizza_whatsapp_token' value='" . esc_attr($token) . "' />";
}

function kwetupizza_whatsapp_phone_id_callback() {
    $phone_id = get_option('kwetupizza_whatsapp_phone_id', '');
    echo "<input type='text' name='kwetupizza_whatsapp_phone_id' value='" . esc_attr($phone_id) . "' />";
}

function kwetupizza_whatsapp_verify_token_callback() {
    $verify_token = get_option('kwetupizza_whatsapp_verify_token', '');
    echo '<label for="kwetupizza_whatsapp_verify_token">Enter your WhatsApp Verify Token:</label><br />';
    echo "<input type='text' id='kwetupizza_whatsapp_verify_token' name='kwetupizza_whatsapp_verify_token' value='" . esc_attr($verify_token) . "' />";
    echo '<p class="description">This token must match the one you set in your WhatsApp Business API webhook configuration.</p>';
}

function kwetupizza_whatsapp_api_version_callback() {
    $api_version = get_option('kwetupizza_whatsapp_api_version', 'v15.0');
    echo "<input type='text' name='kwetupizza_whatsapp_api_version' value='" . esc_attr($api_version) . "' />";
}

// Settings callbacks for Flutterwave
function kwetupizza_flw_public_key_callback() {
    $public_key = get_option('kwetupizza_flw_public_key', '');
    echo "<input type='text' name='kwetupizza_flw_public_key' value='" . esc_attr($public_key) . "' />";
}

function kwetupizza_flw_secret_key_callback() {
    $secret_key = get_option('kwetupizza_flw_secret_key', '');
    echo "<input type='text' name='kwetupizza_flw_secret_key' value='" . esc_attr($secret_key) . "' />";
}

function kwetupizza_flw_encryption_key_callback() {
    $encryption_key = get_option('kwetupizza_flw_encryption_key', '');
    echo "<input type='text' name='kwetupizza_flw_encryption_key' value='" . esc_attr($encryption_key) . "' />";
}

function kwetupizza_flw_webhook_secret_callback() {
    $secret_hash = get_option('kwetupizza_flw_webhook_secret', '');
    echo "<input type='text' name='kwetupizza_flw_webhook_secret' value='" . esc_attr($secret_hash) . "' />";
    echo "<p>Please ensure this matches the 'Secret Hash' set in your Flutterwave Dashboard under Webhook settings.</p>";
}

// Settings callbacks for NextSMS
function kwetupizza_nextsms_username_callback() {
    $username = get_option('kwetupizza_nextsms_username', '');
    echo "<input type='text' name='kwetupizza_nextsms_username' value='" . esc_attr($username) . "' />";
}

function kwetupizza_nextsms_password_callback() {
    $password = get_option('kwetupizza_nextsms_password', '');
    echo "<input type='password' name='kwetupizza_nextsms_password' value='" . esc_attr($password) . "' />";
}

function kwetupizza_nextsms_sender_id_callback() {
    $sender_id = get_option('kwetupizza_nextsms_sender_id', 'KwetuPizza');
    echo "<input type='text' name='kwetupizza_nextsms_sender_id' value='" . esc_attr($sender_id) . "' />";
}

// Settings callbacks for admin notifications
function kwetupizza_admin_whatsapp_callback() {
    $admin_whatsapp = get_option('kwetupizza_admin_whatsapp', '');
    echo "<input type='text' name='kwetupizza_admin_whatsapp' value='" . esc_attr($admin_whatsapp) . "' />";
}

function kwetupizza_admin_sms_callback() {
    $admin_sms = get_option('kwetupizza_admin_sms_number', '');
    echo "<input type='text' name='kwetupizza_admin_sms_number' value='" . esc_attr($admin_sms) . "' />";
}

// Function to process successful payments
function kwetupizza_process_successful_payment($data) {
    global $wpdb;

    // Extract transaction details
    $tx_ref = $data['data']['tx_ref'];
    $amount = $data['data']['amount'];
    $currency = $data['data']['currency'];

    // Log transaction details
    $log_file = plugin_dir_path(__FILE__) . 'flutterwave-webhook.log';
    file_put_contents($log_file, "Processing successful payment for tx_ref: $tx_ref, Amount: $amount $currency" . PHP_EOL, FILE_APPEND);

    // Update the order in the database to "completed"
    $wpdb->update(
        $wpdb->prefix . 'kwetupizza_orders',
        ['status' => 'completed'],
        ['tx_ref' => $tx_ref]
    );

    // Save the transaction details
    $transaction_data = [
        'tx_ref'    => $tx_ref,
        'amount'    => $amount,
        'currency'  => $currency,
        'status'    => $data['data']['status'],
        'payment_type' => $data['data']['payment_type'],
        'network'   => $data['data']['network'],
    ];
    kwetupizza_save_transaction($tx_ref, $transaction_data);

    // Notify the customer and admin
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE tx_ref = %s", $tx_ref));
    if ($order) {
        $business_name = get_option('kwetupizza_business_name', 'KwetuPizza');
        $message = "Your payment of {$currency} {$amount} for order ID {$order->id} has been successfully completed. Thank you for choosing {$business_name}!";
        kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        kwetupizza_notify_admin($order->id, 'successful');
    }
}

// Function to process failed payment
function kwetupizza_process_failed_payment($data) {
    global $wpdb;

    // Extract transaction reference
    $tx_ref = $data['data']['tx_ref'];

    // Log failure details
    $log_file = plugin_dir_path(__FILE__) . 'flutterwave-webhook.log';
    file_put_contents($log_file, "Processing failed payment for tx_ref: $tx_ref" . PHP_EOL, FILE_APPEND);

    // Update order status to "failed"
    $wpdb->update(
        $wpdb->prefix . 'kwetupizza_orders',
        ['status' => 'failed'],
        ['tx_ref' => $tx_ref]
    );

    // Handle payment failure (e.g., notify customer)
    kwetupizza_handle_failed_payment($tx_ref);
}

// Function to handle failed payment and notify customer/admin
function kwetupizza_handle_failed_payment($tx_ref) {
    global $wpdb;

    // Fetch the order using tx_ref
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE tx_ref = %s", $tx_ref));

    if ($order) {
        // Notify the customer
        kwetupizza_send_payment_failed_notification($order->id);

        // Notify the admin
        kwetupizza_notify_admin($order->id, 'failed');
    } else {
        error_log("Order not found for transaction reference: $tx_ref");
    }
}

// Function to send payment failed notification via WhatsApp and SMS
function kwetupizza_send_payment_failed_notification($order_id) {
    global $wpdb;
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d", $order_id));

    if ($order) {
        $message = "Your payment for order ID {$order->id} could not be completed. Please try again by following this link: [Payment Link].";

        // Send WhatsApp notification
        kwetupizza_send_whatsapp_message($order->customer_phone, $message);

        // Optionally, send SMS notification using NextSMS
         kwetupizza_send_sms($order->customer_phone, $message);
    }
}

/**
 * Schedule a review request after order is marked as delivered
 */
function kwetupizza_schedule_review_request($order_id) {
    // Only schedule if auto-reviews are enabled
    if (!get_option('kwetupizza_enable_auto_reviews', false)) {
        return;
    }
    
    global $wpdb;
    
    // Get the order details
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT customer_phone FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d",
        $order_id
    ));
    
    if (!$order || empty($order->customer_phone)) {
        error_log("Cannot schedule review request: Missing order data for order #$order_id");
        return;
    }
    
    // Get the delay setting (in hours)
    $delay_hours = intval(get_option('kwetupizza_review_delay', 1));
    
    // Schedule the review request
    wp_schedule_single_event(
        time() + ($delay_hours * HOUR_IN_SECONDS),
        'kwetupizza_send_scheduled_review',
        [$order_id, $order->customer_phone]
    );
    
    // Log that we've scheduled the review
    update_post_meta($order_id, 'review_request_scheduled', current_time('mysql'));
}
add_action('kwetupizza_order_delivered', 'kwetupizza_schedule_review_request');

/**
 * Callback for scheduled review sending
 */
function kwetupizza_send_scheduled_review($order_id, $phone_number) {
    // Send the review request
    $sent = kwetupizza_send_review_request($phone_number, $order_id);
    
    if ($sent) {
        update_post_meta($order_id, 'review_request_sent', current_time('mysql'));
    } else {
        error_log("Failed to send scheduled review request for order #$order_id to $phone_number");
    }
}
add_action('kwetupizza_send_scheduled_review', 'kwetupizza_send_scheduled_review', 10, 2);

/**
 * Update order status and trigger appropriate hooks
 */
function kwetupizza_update_order_status($order_id, $new_status) {
    global $wpdb;
    
    // Validate order ID
    if (!$order_id || !is_numeric($order_id)) {
        return false;
    }
    
    // Validate status
    $valid_statuses = ['pending', 'processing', 'completed', 'delivered', 'cancelled', 'refunded', 'failed'];
    if (!in_array($new_status, $valid_statuses)) {
        return false;
    }
    
    // Update the order status
    $updated = $wpdb->update(
        $wpdb->prefix . 'kwetupizza_orders',
        ['status' => $new_status],
        ['id' => $order_id],
        ['%s'],
        ['%d']
    );
    
    if ($updated) {
        // Trigger appropriate action based on new status
        do_action('kwetupizza_order_status_' . $new_status, $order_id);
        
        // Special handling for 'delivered' status
        if ($new_status === 'delivered') {
            do_action('kwetupizza_order_delivered', $order_id);
        }
        
        return true;
    }
    
    return false;
}

// Add AJAX handler for WhatsApp webhook testing
add_action('wp_ajax_test_whatsapp_webhook', 'kwetupizza_test_whatsapp_webhook_ajax');


