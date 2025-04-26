<?php
/**
 * Database Functions for KwetuPizza Plugin
 * 
 * Contains functions for interacting with the custom database tables
 * created by the plugin.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get a user by phone number
 * 
 * @param string $phone_number The phone number to search for
 * @return object|null User object if found, null otherwise
 */
if (!function_exists('kwetupizza_get_user_by_phone')) {
    function kwetupizza_get_user_by_phone($phone_number) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kwetupizza_users';
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE phone = %s",
            $phone_number
        ));
        
        return $user;
    }
}

/**
 * Create a new user in the KwetuPizza users table
 * 
 * @param array $user_data User data array with name, email, phone, and role
 * @return int|false The user ID on success, false on failure
 */
if (!function_exists('kwetupizza_create_user')) {
    function kwetupizza_create_user($user_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kwetupizza_users';
        
        // Ensure required fields are set
        if (!isset($user_data['name']) || !isset($user_data['phone'])) {
            return false;
        }
        
        // Set defaults for optional fields
        $user_data = wp_parse_args($user_data, array(
            'email' => '',
            'role' => 'customer',
            'state' => 'greeting'
        ));
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => $user_data['name'],
                'email' => $user_data['email'],
                'phone' => $user_data['phone'],
                'role' => $user_data['role'],
                'state' => $user_data['state']
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
}

/**
 * Update a user in the KwetuPizza users table
 * 
 * @param int $user_id The user ID to update
 * @param array $user_data User data array with fields to update
 * @return bool True on success, false on failure
 */
if (!function_exists('kwetupizza_update_user')) {
    function kwetupizza_update_user($user_id, $user_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kwetupizza_users';
        
        $result = $wpdb->update(
            $table_name,
            $user_data,
            array('id' => $user_id)
        );
        
        return $result !== false;
    }
}

/**
 * Update user state for conversation tracking
 * 
 * @param string $phone_number The user's phone number
 * @param string $state The new state
 * @return bool True on success, false on failure
 */
if (!function_exists('kwetupizza_update_user_state')) {
    function kwetupizza_update_user_state($phone_number, $state) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kwetupizza_users';
        
        $result = $wpdb->update(
            $table_name,
            array('state' => $state),
            array('phone' => $phone_number)
        );
        
        return $result !== false;
    }
}

/**
 * Get all products or products in a specific category
 * 
 * @param string $category Optional category to filter by
 * @return array Array of product objects
 */
if (!function_exists('kwetupizza_get_products')) {
    function kwetupizza_get_products($category = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kwetupizza_products';
        
        if (!empty($category)) {
            $products = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE category = %s ORDER BY product_name ASC",
                $category
            ));
        } else {
            $products = $wpdb->get_results("SELECT * FROM $table_name ORDER BY category, product_name ASC");
        }
        
        return $products;
    }
}

/**
 * Get a product by ID
 * 
 * @param int $product_id The product ID
 * @return object|null Product object if found, null otherwise
 */
if (!function_exists('kwetupizza_get_product')) {
    function kwetupizza_get_product($product_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kwetupizza_products';
        
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $product_id
        ));
        
        return $product;
    }
}

/**
 * Get an order by ID
 * 
 * @param int $order_id The order ID
 * @return object|null Order object if found, null otherwise
 */
if (!function_exists('kwetupizza_get_order')) {
    function kwetupizza_get_order($order_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kwetupizza_orders';
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $order_id
        ));
        
        return $order;
    }
}

/**
 * Get an order by transaction reference
 * 
 * @param string $tx_ref The transaction reference
 * @return object|null Order object if found, null otherwise
 */
if (!function_exists('kwetupizza_get_order_by_tx_ref')) {
    function kwetupizza_get_order_by_tx_ref($tx_ref) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kwetupizza_orders';
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE tx_ref = %s",
            $tx_ref
        ));
        
        return $order;
    }
}

/**
 * Get order items for an order
 * 
 * @param int $order_id The order ID
 * @return array Array of order item objects
 */
if (!function_exists('kwetupizza_get_order_items')) {
    function kwetupizza_get_order_items($order_id) {
        global $wpdb;
        
        $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
        $products_table = $wpdb->prefix . 'kwetupizza_products';
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, p.product_name, p.description 
             FROM $order_items_table i
             JOIN $products_table p ON i.product_id = p.id
             WHERE i.order_id = %d",
            $order_id
        ));
        
        return $items;
    }
}

/**
 * Save a transaction to the database
 * 
 * @param array $transaction_data Transaction data array
 * @return int|false The transaction ID on success, false on failure
 */
if (!function_exists('kwetupizza_save_transaction')) {
    function kwetupizza_save_transaction($transaction_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kwetupizza_transactions';
        
        // Ensure required fields are set
        if (!isset($transaction_data['order_id']) || !isset($transaction_data['tx_ref']) || 
            !isset($transaction_data['amount']) || !isset($transaction_data['payment_status'])) {
            return false;
        }
        
        // Set defaults for optional fields
        $transaction_data = wp_parse_args($transaction_data, array(
            'transaction_date' => current_time('mysql'),
            'payment_method' => 'mobile_money',
            'currency' => get_option('kwetupizza_currency', 'TZS'),
            'payment_provider' => 'flutterwave'
        ));
        
        $result = $wpdb->insert($table_name, $transaction_data);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
}

/**
 * Get user orders by phone number
 * 
 * @param string $phone_number The customer's phone number
 * @param int $limit Optional limit on number of orders to return
 * @return array Array of order objects
 */
if (!function_exists('kwetupizza_get_user_orders')) {
    function kwetupizza_get_user_orders($phone_number, $limit = 5) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kwetupizza_orders';
        
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE customer_phone = %s 
             ORDER BY order_date DESC 
             LIMIT %d",
            $phone_number,
            $limit
        ));
        
        return $orders;
    }
}

/**
 * Get product categories
 * 
 * @return array Array of category names
 */
if (!function_exists('kwetupizza_get_product_categories')) {
    function kwetupizza_get_product_categories() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kwetupizza_products';
        
        $categories = $wpdb->get_col("SELECT DISTINCT category FROM $table_name ORDER BY category ASC");
        
        return $categories;
    }
}

/**
 * Delete a user and their associated data
 * 
 * @param int $user_id The user ID to delete
 * @return bool True on success, false on failure
 */
if (!function_exists('kwetupizza_delete_user')) {
    function kwetupizza_delete_user($user_id) {
        global $wpdb;
        
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        $result = $wpdb->delete($users_table, array('id' => $user_id));
        
        return $result !== false;
    }
}

/**
 * Check if a table exists in the database
 * 
 * @param string $table_name The table name to check
 * @return bool True if table exists, false otherwise
 */
if (!function_exists('kwetupizza_table_exists')) {
    function kwetupizza_table_exists($table_name) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->esc_like($table_name)
        ));
        
        return $result === $table_name;
    }
} 