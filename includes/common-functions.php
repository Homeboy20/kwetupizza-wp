<?php
// Location: /wp-content/plugins/kwetu-pizza-plugin/includes/common-functions.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * ====================================
 * Conversation Context & Inactivity
 * ====================================
 */

// Helper function to get conversation context
function kwetupizza_get_conversation_context($from) {
    $context = get_transient("kwetupizza_whatsapp_context_$from");
    return $context ? $context : [];
}

// Update the last activity timestamp
function kwetupizza_set_conversation_context($from, $context) {
    $context['last_activity'] = time();  // Add last activity timestamp
    set_transient("kwetupizza_whatsapp_context_$from", $context, 60 * 60 * 24);  // Store for 24 hours
}

// Reset the conversation context
function kwetupizza_reset_conversation($from) {
    delete_transient("kwetupizza_whatsapp_context_$from");
}

// Check for inactivity and reset conversation if idle for more than 3 minutes
function kwetupizza_check_inactivity_and_reset($from) {
    $context = kwetupizza_get_conversation_context($from);

    if (isset($context['last_activity'])) {
        $time_since_last_activity = time() - $context['last_activity'];

        // 180 seconds = 3 minutes
        if ($time_since_last_activity > 180) {
            kwetupizza_reset_conversation($from);
            kwetupizza_send_greeting_with_menu_and_support($from);
            return true;
        }
    }
    return false;
}

/**
 * ====================================
 * Greeting & Menu Functions
 * ====================================
 */

// Send greeting, menu, and support instructions
function kwetupizza_send_greeting_with_menu_and_support($from) {
    // Fetch the menu message
    $menu_message = kwetupizza_get_menu_message();

    // Compose the greeting message with the menu
    $message  = "Hello! Welcome to KwetuPizza 🍕.\n\n";
    $message .= $menu_message . "\n\n";
    $message .= "Please type the number of the item you'd like to order. If you need assistance, please contact us at +255 696 110 259.";

    // Send the message
    kwetupizza_send_whatsapp_message($from, $message);

    // Set the user's state to 'awaiting_menu_selection'
    $user_context              = kwetupizza_get_conversation_context($from);
    $user_context['awaiting']  = 'menu_selection';
    $user_context['greeted']   = true;
    kwetupizza_set_conversation_context($from, $user_context);
}

// Simple greeting message
function kwetupizza_send_greeting($from) {
    $message = "Hello! Welcome to KwetuPizza 🍕. Type 'menu' to view our options, or 'order' to start your order. If you need to restart, type 'reset'.";
    kwetupizza_send_whatsapp_message($from, $message);

    kwetupizza_set_conversation_context($from, [
        'awaiting' => 'menu_or_order',
        'greeted'  => true
    ]);
}

// Send a default fallback message
function kwetupizza_send_default_message($from) {
    kwetupizza_send_whatsapp_message($from, "Sorry, I didn't understand that. Type 'menu' to see available options.");
}

// Fetch and display the full menu
function kwetupizza_send_full_menu($from) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_products';

    $pizzas   = $wpdb->get_results("SELECT id, product_name, price FROM $table_name WHERE category = 'Pizza'");
    $drinks   = $wpdb->get_results("SELECT id, product_name, price FROM $table_name WHERE category = 'Drinks'");
    $desserts = $wpdb->get_results("SELECT id, product_name, price FROM $table_name WHERE category = 'Dessert'");

    $message = "Here's our menu. Please type the number of the item you'd like to order:\n\n";

    if ($pizzas) {
        $message .= "🍕 Pizzas:\n";
        foreach ($pizzas as $pizza) {
            $message .= "{$pizza->id}. {$pizza->product_name} - " . number_format($pizza->price, 2) . " TZS\n";
        }
    }

    if ($drinks) {
        $message .= "\n🥤 Drinks:\n";
        foreach ($drinks as $drink) {
            $message .= "{$drink->id}. {$drink->product_name} - " . number_format($drink->price, 2) . " TZS\n";
        }
    }

    if ($desserts) {
        $message .= "\n🍰 Desserts:\n";
        foreach ($desserts as $dessert) {
            $message .= "{$dessert->id}. {$dessert->product_name} - " . number_format($dessert->price, 2) . " TZS\n";
        }
    }

    kwetupizza_send_whatsapp_message($from, $message);

    $context             = kwetupizza_get_conversation_context($from);
    $context['awaiting'] = 'menu_selection';
    kwetupizza_set_conversation_context($from, $context);
}

/**
 * ===============================
 * WhatsApp Send & Logging Helpers
 * ===============================
 */

// Send WhatsApp message using the WhatsApp API
function kwetupizza_send_whatsapp_message($phone_number, $message, $message_type = 'text', $media_url = null) {
    $access_token  = get_option('kwetupizza_whatsapp_token');
    $phone_id      = get_option('kwetupizza_whatsapp_phone_id');
    $api_version   = get_option('kwetupizza_whatsapp_api_version', 'v15.0');
    $enable_logging= get_option('kwetupizza_enable_logging', true);

    if (!$access_token || !$phone_id) {
        error_log("WhatsApp API credentials are missing.");
        return false;
    }

    // Normalize phone number
    $phone_number = preg_replace('/\D/', '', $phone_number);
    if (strpos($phone_number, '+') !== 0) {
        $phone_number = '+' . $phone_number;
    }

    $url = "https://graph.facebook.com/{$api_version}/{$phone_id}/messages";

    $data = [
        "messaging_product" => "whatsapp",
        "to" => $phone_number,
        "type" => "text",
        "text" => ["body" => $message],
    ];

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
        ],
        'body'    => json_encode($data),
        'timeout' => 45,
    ]);

    if (is_wp_error($response)) {
        error_log("WhatsApp API Request Error: " . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $result        = json_decode($response_body, true);

    if (isset($result['error'])) {
        error_log("WhatsApp API Error Response: " . print_r($result['error'], true));
        return false;
    }

    return true;
}

// Basic logging function
function kwetupizza_log($message) {
    if (WP_DEBUG === true) {
        error_log($message);
    }
}

// Log context & input for debugging
function kwetupizza_log_context_and_input($from, $input) {
    $log_file = plugin_dir_path(__FILE__) . 'kwetupizza-debug.log';
    $context  = kwetupizza_get_conversation_context($from);

    $log_content  = "Current Context for user [$from]:\n";
    $log_content .= print_r($context, true);
    $log_content .= "Received Input: $input\n\n";

    file_put_contents($log_file, $log_content, FILE_APPEND);
    error_log($log_content);
}

/**
 * =====================================
 * Main Conversation Handler
 * =====================================
 */

 function kwetupizza_handle_whatsapp_message($from, $message) {
    $original_message = $message; 
    $message          = strtolower(trim($message));
    $context          = kwetupizza_get_conversation_context($from);

    // Check inactivity
    if (kwetupizza_check_inactivity_and_reset($from)) {
        return; // Conversation was reset, so return
    }

    // Log for debugging
    kwetupizza_log_context_and_input($from, $message);

    // Handle "reset"
    if ($message === 'reset') {
        kwetupizza_reset_conversation($from);
        kwetupizza_send_greeting_with_menu_and_support($from);
        return;
    }

    // Handle "help"
    if ($message === 'help') {
        $help_message  = "To place an order, type the item number from the menu.\n";
        $help_message .= "You can type 'reset' to restart anytime.";
        kwetupizza_send_whatsapp_message($from, $help_message);
        return;
    }

    // Handle 'retry' or 'cancel'
    if ($message === 'retry' || $message === 'cancel') {
        kwetupizza_handle_retry_or_cancel($from, $message);
        return;
    }

    // If user not in DB, gather name/email
    if (!kwetupizza_check_existing_customer($from)) {
        $awaiting = isset($context['awaiting']) ? $context['awaiting'] : '';

        if ($awaiting === 'collect_name') {
            $context['name']     = ucfirst($original_message);
            kwetupizza_send_whatsapp_message($from, "Thanks, {$context['name']}! Now please provide your email address.");
            $context['awaiting'] = 'collect_email';
            kwetupizza_set_conversation_context($from, $context);
            return;
        } elseif ($awaiting === 'collect_email') {
            if (!filter_var($original_message, FILTER_VALIDATE_EMAIL)) {
                kwetupizza_send_whatsapp_message($from, "Please provide a valid email address.");
                return;
            }
            // Save user
            $context['email'] = $original_message;
            kwetupizza_create_user($from, $context);
            kwetupizza_send_whatsapp_message($from, "You're all set, {$context['name']}! Type 'menu' to see options or 'order' to start an order.");
            $context['awaiting'] = 'menu_or_order';
            kwetupizza_set_conversation_context($from, $context);
            return;
        } else {
            kwetupizza_send_whatsapp_message($from, "Hi! We don't have your details on file. What's your name?");
            $context['awaiting'] = 'collect_name';
            kwetupizza_set_conversation_context($from, $context);
            return;
        }
    }

    // If context is empty, greet with menu
    if (empty($context)) {
        kwetupizza_send_greeting_with_menu_and_support($from);
        return;
    }

    // Handle known "friendly" greetings
    if (in_array($message, ['hi', 'hello', 'hey'])) {
        kwetupizza_send_greeting($from);
        $context['awaiting'] = 'menu_or_order';
        $context['greeted']  = true;
        kwetupizza_set_conversation_context($from, $context);
        return;
    }

    // If user typed 'menu' while in certain states
    if ($message === 'menu' && isset($context['awaiting']) && in_array($context['awaiting'], ['menu_selection'])) {
        kwetupizza_send_full_menu($from);
        $context['awaiting'] = 'menu_selection';
        kwetupizza_set_conversation_context($from, $context);
        return;
    }

    // If user typed 'order'
    if ($message === 'order' && isset($context['awaiting']) && in_array($context['awaiting'], ['menu_or_order','menu_selection'])) {
        kwetupizza_send_full_menu($from);
        $context['awaiting'] = 'menu_selection';
        kwetupizza_set_conversation_context($from, $context);
        return;
    }

    // If user typed an item number from the menu
    if (is_numeric($message) && isset($context['awaiting']) && $context['awaiting'] === 'menu_selection') {
        kwetupizza_process_order($from, $message);
        return;
    }

    // If user typed a quantity
    if (isset($context['awaiting']) && $context['awaiting'] === 'quantity') {
        $last_cart = end($context['cart']);
        kwetupizza_confirm_order_and_request_quantity($from, $last_cart['product_id'], $message);
        return;
    }

    // If user typed "add" or "checkout"
    if (isset($context['awaiting']) && $context['awaiting'] === 'add_or_checkout') {
        kwetupizza_handle_add_or_checkout($from, $message);
        return;
    }

    // If user typed an address
    if (isset($context['awaiting']) && $context['awaiting'] === 'address') {
        kwetupizza_handle_address_and_ask_payment_provider($from, $message);
        return;
    }

    // If user typed the payment provider
    if (isset($context['awaiting']) && $context['awaiting'] === 'payment_provider') {
        kwetupizza_handle_payment_provider_response($from, $message);
        return;
    }

    // If user typed "yes/no" for using the same phone number
    if (isset($context['awaiting']) && $context['awaiting'] === 'use_whatsapp_number') {
        kwetupizza_handle_use_whatsapp_number_response($from, $message);
        return;
    }

    // If user typed the phone for payment
    if (isset($context['awaiting']) && $context['awaiting'] === 'payment_phone') {
        kwetupizza_handle_payment_phone_input($from, $message);
        return;
    }

    // If user typed 'retry' or 'cancel' for payment
    if (isset($context['awaiting']) && $context['awaiting'] === 'payment_retry') {
        kwetupizza_handle_retry_or_cancel($from, $message);
        return;
    }

    // ============ SCHEDULED ORDER LOGIC ============
    // If user is providing a time for scheduling
    if (isset($context['awaiting']) && $context['awaiting'] === 'schedule_time') {
        // Make sure we have a product to schedule
        if (!isset($context['scheduled_product'])) {
            kwetupizza_send_whatsapp_message($from, "No product found to schedule. Please type 'menu' to start over.");
            return;
        }

        // We handle scheduled order
        $scheduled_product = $context['scheduled_product'];
        $customer_details  = kwetupizza_get_customer_details($from);
        $customer_name     = $customer_details['name'];
        $customer_phone    = $from;
        // If you want an actual address, you can also store it in context. For now:
        $delivery_address  = "Scheduled Delivery";

        // Attempt to parse user input as a time, and assume "today" or "tomorrow"
        // Here, for simplicity, let's assume "tomorrow" at the time user typed
        $parsed_time  = date('H:i', strtotime($message));
        $tomorrow     = new DateTime('tomorrow', new DateTimeZone('Africa/Nairobi'));
        list($h, $m)  = explode(':', $parsed_time);
        $tomorrow->setTime($h, $m);
        $scheduled_time = $tomorrow->format('Y-m-d H:i:s');

        // Validate the future time
        if (!kwetupizza_validate_scheduled_time($scheduled_time)) {
            kwetupizza_send_whatsapp_message($from, "Invalid time or past time. Please reply with a future time (e.g., '09:00' or '14:30').");
            return;
        }

        // Insert the scheduled order
        $order_id = kwetupizza_save_scheduled_order($scheduled_time, $scheduled_product, $customer_name, $customer_phone, $delivery_address);
        if ($order_id) {
            $friendly_str = $tomorrow->format('D, M j H:i');
            kwetupizza_send_whatsapp_message(
                $from,
                "Your order for *{$scheduled_product['product_name']}* is scheduled for $friendly_str.\nThank you!"
            );
            // Reset the conversation
            kwetupizza_reset_conversation($from);
        } else {
            kwetupizza_send_whatsapp_message($from, "Unable to schedule your order. Please try again or type 'help'.");
        }

        return;
    }

    // If none of the above matched
    kwetupizza_send_default_message($from);
}

/**
 * ===================================
 * Checking/Creating the Customer
 * ===================================
 */
function kwetupizza_check_existing_customer($phone_number) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_users';
    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE phone = %s", $phone_number));
    return $user ? $user->name : false;
}

function kwetupizza_create_user($phone_number, $data) {
    global $wpdb;
    $users_table = $wpdb->prefix . 'kwetupizza_users';

    $wpdb->insert($users_table, [
        'name'  => sanitize_text_field($data['name']),
        'email' => sanitize_email($data['email']),
        'phone' => $phone_number,
        'role'  => 'customer',
        'state' => 'active'
    ]);

    kwetupizza_log("User created: " . $phone_number);
}

/**
 * =======================================
 * Process Normal Orders (Inside Hours)
 * =======================================
 */
function kwetupizza_process_order($from, $product_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_products';
    $product    = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $product_id));

    if ($product) {
        $current_time = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
        $start_time   = new DateTime('08:00', new DateTimeZone('Africa/Nairobi'));
        $end_time     = new DateTime('20:30', new DateTimeZone('Africa/Nairobi'));

        // If within normal hours
        if ($current_time >= $start_time && $current_time <= $end_time) {
            $message = "You've selected " . $product->product_name . ". Please enter the quantity.";
            kwetupizza_send_whatsapp_message($from, $message);

            $context            = kwetupizza_get_conversation_context($from);
            $context['cart'][]  = [
                'product_id'   => $product_id,
                'product_name' => $product->product_name,
                'price'        => $product->price
            ];
            $context['awaiting'] = 'quantity';
            kwetupizza_set_conversation_context($from, $context);

        } else {
            // Outside normal hours => schedule for tomorrow
            $message = "Our ordering hours are between 08:00 and 20:30. Would you like to schedule your order for tomorrow? If yes, please reply with your preferred delivery time (e.g., 09:00 or 14:30).";
            kwetupizza_send_whatsapp_message($from, $message);

            $context = kwetupizza_get_conversation_context($from);
            $context['scheduled_product'] = [
                'product_id'   => $product_id,
                'product_name' => $product->product_name,
                'price'        => $product->price
            ];
            $context['awaiting'] = 'schedule_time';
            kwetupizza_set_conversation_context($from, $context);
        }
    } else {
        kwetupizza_send_whatsapp_message($from, "Sorry, the selected item is not available.");
    }
}

/**
 * =======================
 * Scheduled Order Helpers
 * =======================
 */
// Validate the scheduled time is a future datetime
function kwetupizza_validate_scheduled_time($scheduled_time) {
    $now      = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
    $try_time = DateTime::createFromFormat('Y-m-d H:i:s', $scheduled_time, new DateTimeZone('Africa/Nairobi'));

    // Must parse correctly & be future
    if (!$try_time || $try_time <= $now) {
        return false;
    }
    return true;
}

// Actually store the scheduled order in the DB
function kwetupizza_save_scheduled_order($user_input, $scheduled_product, $customer_name, $customer_phone, $delivery_address) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';

    $parsed_time = date('Y-m-d') . ' ' . date('H:i:s', strtotime($user_input));
    if (!$parsed_time) {
        error_log("Invalid scheduled time: " . $user_input);
        return false;
    }

    $order_data = [
        'customer_name'    => sanitize_text_field($customer_name),
        'customer_phone'   => sanitize_text_field($customer_phone),
        'delivery_address' => sanitize_text_field($delivery_address),
        'status'           => 'scheduled',
        'total'            => $scheduled_product['price'] ?? 0,
        'currency'         => 'TZS',
        'scheduled_time'   => $parsed_time,
    ];

    $result = $wpdb->insert($orders_table, $order_data);
    if ($result === false) {
        error_log("Error inserting scheduled order: " . $wpdb->last_error);
        return false;
    }
    return $wpdb->insert_id;
}

/**
 * ====================================
 * Confirm Quantity & Checkout Steps
 * ====================================
 */
function kwetupizza_confirm_order_and_request_quantity($from, $product_id, $quantity) {
    $context = kwetupizza_get_conversation_context($from);

    $cart       = $context['cart'] ?? [];
    $last_index = count($cart) - 1;

    $quantity = (int) $quantity;
    $cart[$last_index]['quantity'] = $quantity;
    $cart[$last_index]['total']    = $cart[$last_index]['price'] * $quantity;

    $context['cart'] = $cart;

    kwetupizza_send_whatsapp_message($from, "Would you like to add more items or proceed to checkout? Type 'add' to add more or 'checkout' to proceed.");
    $context['awaiting'] = 'add_or_checkout';
    kwetupizza_set_conversation_context($from, $context);
}

function kwetupizza_handle_add_or_checkout($from, $response) {
    $context = kwetupizza_get_conversation_context($from);
    $resp    = strtolower(trim($response));

    if ($resp === 'add') {
        // Show menu again for adding more items
        kwetupizza_send_full_menu($from);
        $context['awaiting'] = 'menu_selection';
    } elseif ($resp === 'checkout') {
        // Summarize the cart
        $total           = 0;
        $summary_message = "Here is your order summary:\n";

        // Remove any incomplete items
        $context['cart'] = array_filter($context['cart'], function($item) {
            return isset($item['quantity'], $item['total']);
        });

        foreach ($context['cart'] as $cart_item) {
            $summary_message .= "{$cart_item['quantity']} x {$cart_item['product_name']} - " 
                . number_format($cart_item['total'], 2) . " TZS\n";
            $total += $cart_item['total'];
        }

        $summary_message .= "\nTotal: " . number_format($total, 2) . " TZS\n";

        // Check if this is a scheduled order
        if (!empty($context['scheduled_time'])) {
            $summary_message .= "Scheduled Time: " . $context['scheduled_time'] . "\n";
            $summary_message .= "(Order is scheduled; proceed to confirm final details.)\n\n";
        }

        $summary_message .= "Please provide your delivery address.";

        // Send the summary to user
        kwetupizza_send_whatsapp_message($from, $summary_message);

        // Update the context with the final total, set next step to address
        $context['total']    = $total;
        $context['awaiting'] = 'address';
    } else {
        kwetupizza_send_whatsapp_message($from, "Sorry, I didn't understand that. Type 'add' or 'checkout'.");
        return;
    }

    kwetupizza_set_conversation_context($from, $context);
}


/**
 * ====================================
 * Payment Provider & Flow
 * ====================================
 */
function kwetupizza_handle_address_and_ask_payment_provider($from, $address) {
    $context = kwetupizza_get_conversation_context($from);

    if (!empty($context['cart'])) {
        $context['address'] = $address;
        kwetupizza_set_conversation_context($from, $context);

        $msg = "Which Mobile Money network? Reply: Vodacom, Tigo, Halopesa, or Airtel.";
        kwetupizza_send_whatsapp_message($from, $msg);

        $context['awaiting'] = 'payment_provider';
        kwetupizza_set_conversation_context($from, $context);
    } else {
        kwetupizza_send_whatsapp_message($from, "Error processing your order. Try again.");
    }
}

function kwetupizza_handle_payment_provider_response($from, $provider) {
    $provider = strtolower(trim($provider));
    $context  = kwetupizza_get_conversation_context($from);

    if (isset($context['awaiting']) && $context['awaiting'] === 'payment_provider') {
        $valid_providers = ['vodacom','tigo','halopesa','airtel'];
        if (in_array($provider, $valid_providers)) {
            $context['payment_provider'] = ucfirst($provider);
            $context['awaiting']         = 'use_whatsapp_number';
            kwetupizza_set_conversation_context($from, $context);

            $msg = "Use your WhatsApp number ($from) for payment? Reply 'yes' or 'no'.";
            kwetupizza_send_whatsapp_message($from, $msg);
        } else {
            kwetupizza_send_whatsapp_message($from, "Invalid provider. Reply Vodacom, Tigo, Halopesa, or Airtel.");
        }
    } else {
        kwetupizza_send_default_message($from);
    }
}

function kwetupizza_handle_use_whatsapp_number_response($from, $response) {
    $response = strtolower(trim($response));
    $context  = kwetupizza_get_conversation_context($from);

    if (isset($context['awaiting']) && $context['awaiting'] === 'use_whatsapp_number') {
        if ($response === 'yes') {
            kwetupizza_generate_mobile_money_push($from, $context['cart'], $context['address'], $from);
        } elseif ($response === 'no') {
            kwetupizza_send_whatsapp_message($from, "Please provide the phone number you'd like to use for mobile money payment.");
            $context['awaiting'] = 'payment_phone';
            kwetupizza_set_conversation_context($from, $context);
        } else {
            kwetupizza_send_whatsapp_message($from, "Please reply 'yes' or 'no'.");
        }
    } else {
        kwetupizza_send_default_message($from);
    }
}

function kwetupizza_handle_payment_phone_input($from, $payment_phone) {
    $context = kwetupizza_get_conversation_context($from);
    if (isset($context['awaiting']) && $context['awaiting'] === 'payment_phone') {
        kwetupizza_generate_mobile_money_push($from, $context['cart'], $context['address'], $payment_phone);
    } else {
        kwetupizza_send_whatsapp_message($from, "Wasn't expecting a phone number here. Type 'reset' to restart if needed.");
    }
}

/**
 * Save normal order
 */
function kwetupizza_save_order($from, $context) {
    global $wpdb;

    if (empty($context['address']) || empty($context['cart']) || !isset($context['total'])) {
        error_log('kwetupizza_save_order: Missing data in context.');
        error_log('Context: ' . print_r($context, true));
        return false;
    }

    $orders_table      = $wpdb->prefix . 'kwetupizza_orders';
    $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
    $customer_details  = kwetupizza_get_customer_details($from);

    $order_data = [
        'customer_name'    => $customer_details['name'],
        'customer_email'   => $customer_details['email'],
        'customer_phone'   => $from,
        'delivery_address' => $context['address'],
        'delivery_phone'   => $from,
        'status'           => 'pending',
        'total'            => $context['total'],
        'currency'         => 'TZS',
    ];

    $inserted = $wpdb->insert($orders_table, $order_data);
    if ($inserted === false) {
        error_log('kwetupizza_save_order: Insert error ' . $wpdb->last_error);
        error_log('Data: ' . print_r($order_data, true));
        return false;
    }

    $order_id = $wpdb->insert_id;

    // Insert order items
    foreach ($context['cart'] as $item) {
        if (!isset($item['product_id'], $item['quantity'], $item['price'])) {
            error_log('Missing product data for cart item: ' . print_r($item, true));
            continue;
        }
        $wpdb->insert($order_items_table, [
            'order_id'   => $order_id,
            'product_id' => $item['product_id'],
            'quantity'   => $item['quantity'],
            'price'      => $item['price']
        ]);
    }

    return $order_id;
}

/**
 * Get customer details (name,email) from DB or fallback
 */
function kwetupizza_get_customer_details($from) {
    global $wpdb;
    $users_table = $wpdb->prefix . 'kwetupizza_users';
    $user       = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE phone = %s", $from));
    if ($user) {
        return [
            'name'  => $user->name,
            'email' => $user->email,
        ];
    }
    return [
        'name'  => 'Customer',
        'email' => kwetupizza_get_customer_email($from),
    ];
}

/**
 * Generate fallback email if missing
 */
function kwetupizza_get_customer_email($from) {
    return "customer+" . $from . "@example.com";
}

/**
 * Payment Retry/Cancel + Admin Notification code remain below unchanged...
 * (No changes to nextsms or flutterwave sections)
 */

// ...
// For brevity, the rest of your existing Flutterwave/NextSMS logic follows here
// along with the existing order/payment notifications.

function kwetupizza_notify_admin($order_id, $success = true, $type = 'payment') {
    global $wpdb;

    // Retrieve order details
    $order = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d", $order_id)
    );

    if (!$order) {
        error_log("Order not found for notification: Order ID {$order_id}");
        return;
    }

    // Fetch customer and order details
    $customer_name    = $order->customer_name;
    $customer_phone   = $order->customer_phone;
    $delivery_address = $order->delivery_address;
    $order_total      = $order->total;
    $transaction_id   = $order->tx_ref ?: 'N/A';

    // Fetch order items (summary includes item names & quantities)
    $order_items = kwetupizza_get_order_items_summary($order_id);

    // Prepare message for SMS (admin)
    $message  = "New Order Alert!\n";
    $message .= "Order ID: {$order_id}\n";
    $message .= "Transaction ID: {$transaction_id}\n";
    $message .= "Customer: {$customer_name}\n";
    $message .= "Phone: {$customer_phone}\n";
    $message .= "Address: {$delivery_address}\n";
    $message .= "Total: " . number_format($order_total, 2) . " TZS\n";
    // Here we include the item names from $order_items:
    $message .= "Items:\n{$order_items}\n";
    $message .= "Payment Status: " . ($success ? 'Successful' : 'Failed');

    // Prepare template parameters for WhatsApp
    $template_params = [
        $order_id,                    // {{1}} Order ID
        $transaction_id,              // {{2}} Transaction ID
        $customer_name,               // {{3}} Customer Name
        $customer_phone,              // {{4}} Customer Phone
        $delivery_address,            // {{5}} Delivery Address
        number_format($order_total, 2), // {{6}} Order Total
        $order_items,                 // {{7}} Order Items
        $success ? 'Successful' : 'Failed' // {{8}} Payment Status
    ];

    // Get admin contacts
    $admin_whatsapp = get_option('kwetupizza_admin_whatsapp');
    $admin_sms      = get_option('kwetupizza_admin_sms_number');

    // Send WhatsApp notification to admin (if configured)
    if ($admin_whatsapp) {
        error_log("Attempting to send WhatsApp notification to admin: $admin_whatsapp");
        $template_name = 'order_received_admin'; // Replace with your actual template name
        $whatsapp_sent = kwetupizza_send_whatsapp_message(
            $admin_whatsapp,
            '', // No direct text body here because we're using a template
            'text',  // message_type in your code
            null,
            true,    // indicates "use template"
            $template_name,
            $template_params
        );
        if (!$whatsapp_sent) {
            error_log('Failed to send WhatsApp message to admin.');
        } else {
            error_log('WhatsApp message sent to admin successfully.');
        }
    } else {
        error_log('Admin WhatsApp number not set.');
    }

    // Send SMS notification to admin (if configured)
    if ($admin_sms) {
        error_log("Attempting to send SMS notification to admin: $admin_sms");
        $sms_sent = kwetupizza_send_sms($admin_sms, $message);
        if (!$sms_sent) {
            error_log('Failed to send SMS message to admin.');
        } else {
            error_log('SMS message sent to admin successfully.');
        }
    } else {
        error_log('Admin SMS number not set.');
    }
}

function kwetupizza_get_order_items_summary($order_id) {
    global $wpdb;
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT oi.*, p.product_name
         FROM {$wpdb->prefix}kwetupizza_order_items oi
         LEFT JOIN {$wpdb->prefix}kwetupizza_products p
         ON oi.product_id = p.id
         WHERE oi.order_id = %d",
        $order_id
    ));

    $summary = '';
    foreach ($items as $item) {
        $summary .= "{$item->quantity} x {$item->product_name}\n";
    }
    return $summary;
}


function kwetupizza_send_sms($phone_number, $message) {
    $username = get_option('kwetupizza_nextsms_username');
    $password = get_option('kwetupizza_nextsms_password');
    $sender_id = get_option('kwetupizza_nextsms_sender_id', 'KwetuPizza');

    if (!$username || !$password) {
        error_log("NextSMS API credentials are missing.");
        return false;
    }

    $phone_number = preg_replace('/\D/', '', $phone_number);
    if (substr($phone_number, 0, 3) !== '255') {
        $phone_number = '255' . ltrim($phone_number, '0');
    }

    $data = [
        'from' => $sender_id,
        'to'   => $phone_number,
        'text' => $message,
    ];

    $auth_string = base64_encode($username . ':' . $password);
    $response = wp_remote_post('https://messaging-service.co.tz/api/sms/v1/text/single', [
        'headers' => [
            'Authorization' => 'Basic ' . $auth_string,
            'Content-Type'  => 'application/json',
        ],
        'body'    => json_encode($data),
        'timeout' => 45,
    ]);

    if (is_wp_error($response)) {
        error_log("NextSMS API Request Error: " . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $result = json_decode($response_body, true);

    if (isset($result['code']) && $result['code'] == '100') {
        return true;
    } else {
        error_log("NextSMS API Error Response: " . print_r($result, true));
        return false;
    }
}


function kwetupizza_verify_payment($transaction_id) {
    $secret_key = get_option('kwetupizza_flw_secret_key');
    $url = "https://api.flutterwave.com/v3/transactions/$transaction_id/verify";

    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $secret_key,
            'Content-Type'  => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        error_log('Flutterwave Verification Error: ' . $response->get_error_message());
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    error_log('Flutterwave Verification Response: ' . $response_body);

    $result = json_decode($response_body, true);

    if (isset($result['status']) && $result['status'] == 'success' && $result['data']['status'] == 'successful') {
        return $result['data']; // Return payment data on success
    }

    error_log('Flutterwave Verification Failed: ' . print_r($result, true));
    return false;
}


// Confirm payment and notify
function kwetupizza_confirm_payment_and_notify($transaction_id) {
    $transaction_data = kwetupizza_verify_payment($transaction_id);

    if ($transaction_data && $transaction_data['status'] === 'successful') {
        global $wpdb;
        $tx_ref = $transaction_data['tx_ref'];
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE tx_ref = %s", $tx_ref));

        if ($order) {
            // Update the order status to completed
            $wpdb->update("{$wpdb->prefix}kwetupizza_orders", ['status' => 'completed'], ['id' => $order->id]);

            // Save the transaction details
            kwetupizza_save_transaction($order->id, $transaction_data);

            // Customer notification for successful payment
            $customer_message = "✅ Payment Confirmed! Your payment for Order #{$order->id} has been received. Your total is " . number_format($order->total, 2) . " TZS. Thank you for choosing KwetuPizza!";
            kwetupizza_send_whatsapp_message($order->customer_phone, $customer_message);
            kwetupizza_send_sms($order->customer_phone, $customer_message);

            // Admin notification for successful payment
            kwetupizza_notify_admin($order->id, true, 'payment');

            // Reset the conversation context after successful payment
            kwetupizza_reset_conversation($order->customer_phone);

            return true;
        } else {
            error_log("Order not found for tx_ref: $tx_ref during payment confirmation.");
        }
    } else {
        error_log("Payment verification failed for transaction ID: $transaction_id");

        // Handle payment failure
        if (isset($transaction_data['tx_ref'])) {
            global $wpdb;
            $tx_ref = $transaction_data['tx_ref'];
            $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE tx_ref = %s", $tx_ref));

            if ($order) {
                $customer_phone = $order->customer_phone;

                // Set context to expect 'retry' or 'cancel'
                $context = kwetupizza_get_conversation_context($customer_phone);
                $context['awaiting'] = 'payment_retry';
                kwetupizza_set_conversation_context($customer_phone, $context);

                // Send failed payment notification with support number
                kwetupizza_send_failed_payment_notification($customer_phone, $tx_ref);
            } else {
                error_log("Order not found for tx_ref: $tx_ref during payment failure handling.");
            }
        }
    }
    return false;
}


// Save Transaction after successful payment
function kwetupizza_save_transaction($order_id, $transaction_data) {
    global $wpdb;
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';

    // Prepare the data for insertion
    $data = [
        'order_id'         => $order_id,
        'tx_ref'           => $transaction_data['tx_ref'], // Added tx_ref
        'transaction_date' => current_time('mysql'),
        'payment_method'   => sanitize_text_field($transaction_data['payment_type']),
        'payment_status'   => sanitize_text_field($transaction_data['status']),
        'amount'           => floatval($transaction_data['amount']),
        'currency'         => sanitize_text_field($transaction_data['currency']),
        'payment_provider' => isset($transaction_data['meta']['MOMO_NETWORK']) ? sanitize_text_field($transaction_data['meta']['MOMO_NETWORK']) : '',
    ];
    

    // Insert the transaction data into the database
    $inserted = $wpdb->insert($transactions_table, $data);

    // Log success or error
    if ($inserted === false) {
        error_log('Failed to save transaction: ' . $wpdb->last_error);
    } else {
        kwetupizza_log("Transaction saved for order ID: " . $order_id);
    }
}


// Function to send successful payment notification
function kwetupizza_send_success_notification($tx_ref, $phone_number) {
    global $wpdb;

    // Retrieve order by transaction reference
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE tx_ref = %s", $tx_ref));

    if ($order) {
        // Message for customer
        $message = "Your payment for Order #{$order->id} has been successfully processed. Thank you for choosing KwetuPizza!";
        kwetupizza_send_whatsapp_message($phone_number, $message);
    }
}

// Function to send failed payment notification
function kwetupizza_send_failed_payment_notification($phone_number, $tx_ref) {
    global $wpdb;

    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE tx_ref = %s", $tx_ref));

    if ($order) {
        $customer_message = "❌ Unfortunately, your payment for Order #{$order->id} has failed. Please reply 'retry' to try again or contact us at +255 696 110 259 for assistance.";
        kwetupizza_send_whatsapp_message($phone_number, $customer_message);
        kwetupizza_send_sms($phone_number, $customer_message);

        // Notify admin
        kwetupizza_notify_admin($order->id, false, 'payment');

        // Update context to expect 'retry' or 'cancel'
        $context = kwetupizza_get_conversation_context($phone_number);
        $context['awaiting'] = 'payment_retry';
        kwetupizza_set_conversation_context($phone_number, $context);
    }
}

// Send payment success notification to customer
function kwetupizza_send_payment_success_notification($phone_number, $tx_ref) {
    global $wpdb;

    // Retrieve the order using the transaction reference
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE tx_ref = %s",
        $tx_ref
    ));

    if ($order) {
        // Fetch order items
        $order_items = $wpdb->get_results($wpdb->prepare(
            "SELECT oi.*, p.product_name FROM {$wpdb->prefix}kwetupizza_order_items oi
            LEFT JOIN {$wpdb->prefix}kwetupizza_products p ON oi.product_id = p.id
            WHERE oi.order_id = %d",
            $order->id
        ));

        // Build the items list
        $items_details = '';
        foreach ($order_items as $item) {
            $items_details .= "{$item->quantity} x {$item->product_name}\n";
        }

        // Build the message
        $message = "🎉 Thank you for your order!\n";
        $message .= "Order ID: {$order->id}\n";
        $message .= "Order Total: " . number_format($order->total, 2) . " TZS\n";
        $message .= "Order Items:\n{$items_details}";
        $message .= "Delivery Address: {$order->delivery_address}\n";
        $message .= "Your payment has been successfully received. We are preparing your order and will deliver it soon.";

        // Send the message via WhatsApp
        kwetupizza_send_whatsapp_message($phone_number, $message);
    } else {
        // Order not found; you might want to log this
        error_log("Order not found for tx_ref: $tx_ref");
    }
}


/**
 * Payment Failure, Retry, Cancel logic...
 */
// Handle payment failure and notify the customer with a retry option
function kwetupizza_handle_payment_failure($from) {
    $message = "❌ Your payment attempt has failed. To try again, reply with 'retry'. If you need help, contact us at +255 696 110 259.";
    kwetupizza_send_whatsapp_message($from, $message);

    // Update the context to expect a 'retry' or 'cancel' response
    $context = kwetupizza_get_conversation_context($from);
    $context['awaiting'] = 'payment_retry';
    kwetupizza_set_conversation_context($from, $context);
}
// Handle 'retry' or 'cancel' response after a payment failure
function kwetupizza_handle_retry_or_cancel($from, $response) {
    $response = strtolower(trim($response));
    $context  = kwetupizza_get_conversation_context($from);

    if (isset($context['awaiting']) && $context['awaiting'] === 'payment_retry') {
        if ($response === 'retry') {
            // If payment_phone isn't stored, fallback to $from
            $payment_phone = !empty($context['payment_phone']) ? $context['payment_phone'] : $from;

            // Re-trigger the payment push
            kwetupizza_generate_mobile_money_push(
                $from,
                $context['cart'],
                $context['address'],
                $payment_phone,
                true  // indicates retry
            );

        } elseif ($response === 'cancel') {
            kwetupizza_send_whatsapp_message(
                $from,
                "Your order has been cancelled. If you need assistance, please contact us at +255 696 110 259."
            );
            kwetupizza_reset_conversation($from);
        } else {
            kwetupizza_send_whatsapp_message(
                $from,
                "Please reply with 'retry' to try again or 'cancel' to cancel the order."
            );
        }
    } else {
        // If the awaiting context is not 'payment_retry', inform the user
        kwetupizza_send_whatsapp_message(
            $from,
            "I'm sorry, I didn't understand that. Please type 'menu' to start a new order or 'help' for assistance."
        );
    }
}


//function to notify admin
function kwetupizza_notify_admin_by_order_tx_ref($tx_ref, $success = true) {
    global $wpdb;

    // Retrieve order by transaction reference
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE tx_ref = %s", $tx_ref));

    if ($order) {
        // Use the existing notification function
        kwetupizza_notify_admin($order->id, $success, 'payment');
    } else {
        error_log("Order not found for tx_ref: $tx_ref");
    }
}


/**
 * Menu message + Category emoji
 */
function kwetupizza_get_menu_message() {
    global $wpdb;
    $products_table = $wpdb->prefix . 'kwetupizza_products';

    // Desired order of categories
    $desired_order = ['Pizza', 'Drinks', 'Desserts'];

    // Fetch all products from the database
    $products = $wpdb->get_results("SELECT * FROM $products_table ORDER BY id ASC");

    // Initialize menu message
    $menu_message = "Here's our menu:\n";

    // Loop through desired categories and append products in order
    foreach ($desired_order as $category) {
        $category_products = array_filter($products, function ($product) use ($category) {
            return strtolower($product->category) === strtolower($category);
        });

        if (!empty($category_products)) {
            // Add category header
            $menu_message .= "\n" . kwetupizza_get_category_emoji($category) . " *" . strtoupper($category) . "*:\n";

            // Add products under this category
            foreach ($category_products as $product) {
                $menu_message .= "{$product->id}. {$product->product_name} - " . number_format($product->price, 2) . " TZS\n";
            }
        }
    }

    return $menu_message;
}

function kwetupizza_get_category_emoji($category) {
    switch (strtolower($category)) {
        case 'pizza':
            return '🍕';
        case 'drinks':
            return '🥤';
        case 'desserts':
            return '🍰';
        default:
            return '';
    }
}

/**
 * 2. The function to handle scheduled-time input.
 */
/**
 * Handle user-provided scheduling time (e.g., "09:00" for tomorrow).
 * Instead of resetting, we guide them to proceed with add-or-checkout flow.
 */
function kwetupizza_handle_scheduled_time_input($from, $time_input) {
    $context = kwetupizza_get_conversation_context($from);

    // If we're indeed awaiting schedule_time...
    if (isset($context['awaiting']) && $context['awaiting'] === 'schedule_time') {
        // Convert "09:00" to tomorrow's datetime for Africa/Nairobi
        $tomorrow     = new DateTime('tomorrow', new DateTimeZone('Africa/Nairobi'));
        $parsed       = DateTime::createFromFormat('H:i', $time_input, new DateTimeZone('Africa/Nairobi'));

        if (!$parsed) {
            kwetupizza_send_whatsapp_message($from, "Invalid time format. Please use HH:MM, e.g., 09:30.");
            return;
        }

        // Construct the final datetime
        $tomorrow->setTime((int)$parsed->format('H'), (int)$parsed->format('i'));
        if ($tomorrow <= new DateTime('now', new DateTimeZone('Africa/Nairobi'))) {
            kwetupizza_send_whatsapp_message($from, "That time is not in the future. Please try a valid future time.");
            return;
        }

        // Store in context
        $context['scheduled_time'] = $tomorrow->format('Y-m-d H:i:s');

        // Respond to user
        kwetupizza_send_whatsapp_message($from, 
            "Your order is scheduled for {$time_input} tomorrow.\n" .
            "Would you like to *add more items* or *checkout* now?"
        );

        // Move them to "add_or_checkout" step
        $context['awaiting'] = 'add_or_checkout';
        kwetupizza_set_conversation_context($from, $context);

    } else {
        // Not in correct state, send default message
        kwetupizza_send_default_message($from);
    }
}


/**
 * Save the final scheduled order in the DB.
 * We use the 'cart' and 'scheduled_time' from context,
 * plus the user's final address to create the order.
 */
function kwetupizza_save_scheduled_order_in_checkout($from, $context) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';

    $customer_details = kwetupizza_get_customer_details($from);

    // Calculate total if not already done
    $cart_total = 0;
    foreach ($context['cart'] as $item) {
        if (isset($item['total'])) {
            $cart_total += $item['total'];
        }
    }

    $order_data = [
        'customer_name'    => $customer_details['name'],
        'customer_email'   => $customer_details['email'],
        'customer_phone'   => $from,
        'delivery_address' => $context['address'] ?? 'No address',
        'delivery_phone'   => $from,
        'status'           => 'scheduled',
        'total'            => $cart_total,
        'currency'         => 'TZS',
        'scheduled_time'   => $context['scheduled_time'],
    ];

    $inserted = $wpdb->insert($orders_table, $order_data);

    if ($inserted === false) {
        error_log("Failed to insert scheduled order: " . $wpdb->last_error);
        return false;
    }

    $order_id = $wpdb->insert_id;

    // Insert cart items
    foreach ($context['cart'] as $item) {
        if (!isset($item['product_id'], $item['quantity'], $item['price'])) {
            continue; // skip incomplete
        }
        $item_data = [
            'order_id'   => $order_id,
            'product_id' => $item['product_id'],
            'quantity'   => $item['quantity'],
            'price'      => $item['price'],
        ];
        $wpdb->insert($order_items_table, $item_data);
    }

    return $order_id;
}

/**
 * ====================================
 * Interactive Response Processing
 * ====================================
 */

function kwetupizza_process_menu_selection($from, $product_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_products';
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $product_id
    ));

    if ($product) {
        $context = kwetupizza_get_conversation_context($from);
        $context['cart'][] = [
            'product_id' => $product_id,
            'product_name' => $product->product_name,
            'price' => $product->price
        ];
        kwetupizza_set_conversation_context($from, $context);
        
        // Show quantity selection buttons
        kwetupizza_show_quantity_buttons($from, $product_id);
    } else {
        kwetupizza_send_whatsapp_message($from, "Sorry, that item is not available. Please try another.");
        kwetupizza_show_interactive_menu($from);
    }
}

function kwetupizza_process_quantity_selection($from, $data) {
    list($product_id, $quantity) = explode('_', $data);
    $context = kwetupizza_get_conversation_context($from);
    
    if (!empty($context['cart'])) {
        $last_index = count($context['cart']) - 1;
        $context['cart'][$last_index]['quantity'] = (int)$quantity;
        $context['cart'][$last_index]['total'] = 
            $context['cart'][$last_index]['price'] * (int)$quantity;
        
        kwetupizza_set_conversation_context($from, $context);
        kwetupizza_show_cart_summary($from);
    } else {
        kwetupizza_send_whatsapp_message($from, "Sorry, there was an error with your order. Please start again.");
        kwetupizza_show_interactive_menu($from);
    }
}

function kwetupizza_process_payment_selection($from, $provider) {
    $context = kwetupizza_get_conversation_context($from);
    
    if (empty($context['cart']) || !isset($context['address'])) {
        kwetupizza_send_whatsapp_message($from, "Sorry, there was an error with your order. Please start again.");
        kwetupizza_reset_conversation($from);
        return;
    }
    
    $context['payment_provider'] = ucfirst(str_replace('payment_', '', $provider));
    kwetupizza_set_conversation_context($from, $context);
    
    // Ask if they want to use their WhatsApp number for payment
    $message_data = [
        "type" => "button",
        "header" => [
            "type" => "text",
            "text" => "Payment Phone Number"
        ],
        "body" => [
            "text" => "Would you like to use your WhatsApp number ($from) for payment?"
        ],
        "action" => [
            "buttons" => [
                [
                    "type" => "reply",
                    "reply" => [
                        "id" => "phone_yes",
                        "title" => "Yes"
                    ]
                ],
                [
                    "type" => "reply",
                    "reply" => [
                        "id" => "phone_no",
                        "title" => "No"
                    ]
                ]
            ]
        ]
    ];
    
    kwetupizza_send_interactive_message($from, $message_data);
}

function kwetupizza_process_confirmation($from, $action) {
    $context = kwetupizza_get_conversation_context($from);
    
    switch($action) {
        case 'phone_yes':
            kwetupizza_generate_mobile_money_push($from, $context['cart'], $context['address'], $from);
            break;
            
        case 'phone_no':
            kwetupizza_send_whatsapp_message($from, "Please provide the phone number you'd like to use for mobile money payment.");
            $context['awaiting'] = 'payment_phone';
            kwetupizza_set_conversation_context($from, $context);
            break;
            
        case 'cart_add':
            kwetupizza_show_interactive_menu($from);
            break;
            
        case 'cart_checkout':
            kwetupizza_send_whatsapp_message($from, "Please provide your delivery address.");
            $context['awaiting'] = 'address';
            kwetupizza_set_conversation_context($from, $context);
            break;
            
        default:
            kwetupizza_send_default_message($from);
    }
}

/**
 * ====================================
 * Order Tracking Functions
 * ====================================
 */

function kwetupizza_update_order_status($order_id, $status, $description = '', $location = null) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $tracking_table = $wpdb->prefix . 'kwetupizza_order_tracking';
    
    // Update order status
    $wpdb->update(
        $orders_table,
        ['status' => $status],
        ['id' => $order_id]
    );
    
    // Add tracking entry
    $tracking_data = [
        'order_id' => $order_id,
        'status' => $status,
        'description' => $description,
        'location_update' => $location ? json_encode($location) : null,
        'created_at' => current_time('mysql')
    ];
    
    $wpdb->insert($tracking_table, $tracking_data);
    
    // Get order details for notification
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $orders_table WHERE id = %d",
        $order_id
    ));
    
    if ($order) {
        // Send status update to customer
        $message = kwetupizza_get_order_status_message($status, $order_id, $description, $location);
        kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        
        // Send status update to admin
        kwetupizza_notify_admin_status_update($order_id, $status, $description, $location);
    }
    
    return true;
}

function kwetupizza_get_order_tracking($order_id) {
    global $wpdb;
    $tracking_table = $wpdb->prefix . 'kwetupizza_order_tracking';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $tracking_table WHERE order_id = %d ORDER BY created_at DESC",
        $order_id
    ));
}

function kwetupizza_notify_admin_status_update($order_id, $status, $description = '', $location = null) {
    $admin_whatsapp = get_option('kwetupizza_admin_whatsapp');
    if (!$admin_whatsapp) return;
    
    $message = "🔄 Order #$order_id Status Update\n";
    $message .= "Status: " . ucfirst($status) . "\n";
    if ($description) {
        $message .= "Details: $description\n";
    }
    if ($location) {
        $message .= "Location: " . $location['address'] . "\n";
    }
    
    kwetupizza_send_whatsapp_message($admin_whatsapp, $message);
}

function kwetupizza_estimate_delivery_time($order_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $items_table = $wpdb->prefix . 'kwetupizza_order_items';
    $products_table = $wpdb->prefix . 'kwetupizza_products';
    
    // Get order items and their preparation times
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT oi.quantity, p.preparation_time 
         FROM $items_table oi 
         JOIN $products_table p ON oi.product_id = p.id 
         WHERE oi.order_id = %d",
        $order_id
    ));
    
    // Calculate total preparation time (consider parallel preparation)
    $max_prep_time = 0;
    foreach ($items as $item) {
        $prep_time = $item->preparation_time * ceil($item->quantity / 2); // Assume can prepare 2 at once
        $max_prep_time = max($max_prep_time, $prep_time);
    }
    
    // Add delivery time estimate (30 minutes)
    $total_time = $max_prep_time + 30;
    
    // Calculate estimated delivery time
    $estimated_time = date('Y-m-d H:i:s', strtotime("+$total_time minutes"));
    
    // Update order with estimated delivery time
    $wpdb->update(
        $orders_table,
        ['estimated_delivery_time' => $estimated_time],
        ['id' => $order_id]
    );
    
    return $estimated_time;
}

function kwetupizza_get_order_status_message($status, $order_id, $description = '', $location = null) {
    $base_message = "Order #$order_id Update:\n";
    
    $status_messages = [
        'pending' => '🕒 Your order has been received and is pending confirmation.',
        'confirmed' => '✅ Your order has been confirmed and will be prepared soon.',
        'preparing' => '👨‍🍳 Your order is being prepared with care.',
        'ready' => '✨ Your order is ready for delivery.',
        'delivering' => '🚚 Your order is on the way to you.',
        'delivered' => '🎉 Your order has been delivered. Enjoy!',
        'completed' => '✅ Order completed. Thank you for choosing KwetuPizza!',
        'cancelled' => '❌ Order has been cancelled.'
    ];
    
    $message = $base_message . ($status_messages[$status] ?? ucfirst($status));
    
    if ($description) {
        $message .= "\nDetails: $description";
    }
    
    if ($location) {
        $message .= "\n📍 Current Location: " . $location['address'];
        if (isset($location['eta'])) {
            $message .= "\n⏱ Estimated arrival: " . $location['eta'];
        }
    }
    
    return $message;
}

function kwetupizza_send_delivery_update($order_id, $status, $location = null) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $orders_table WHERE id = %d",
        $order_id
    ));
    
    if (!$order) return false;
    
    $status_message = kwetupizza_get_order_status_message($status, $order_id, '', $location);
    
    // Send to customer
    kwetupizza_send_whatsapp_message($order->customer_phone, $status_message);
    
    // Update tracking
    kwetupizza_update_order_status($order_id, $status, '', $location);
    
    return true;
}

?>