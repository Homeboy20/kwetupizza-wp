<?php
// Location: /wp-content/plugins/kwetu-pizza-plugin/includes/common-functions.php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Removed transient-based conversation context functions (get, set, reset)
// Database-based versions are now in whatsapp-handler.php

// Get the inactivity timeout period in seconds (default: 3 minutes)
function kwetupizza_get_inactivity_timeout() {
    $timeout = get_option('kwetupizza_inactivity_timeout', 3);
    return $timeout * 60; // Convert minutes to seconds
}

// Check for inactivity and reset conversation if idle for the timeout period
function kwetupizza_check_inactivity_and_reset($from) {
    $context = kwetupizza_get_conversation_context($from);
    $timeout = kwetupizza_get_inactivity_timeout();

    if (isset($context['last_activity'])) {
        $time_since_last_activity = time() - $context['last_activity'];

        // Check if timeout period has passed
        if ($time_since_last_activity > $timeout) {
            // Before resetting, save the current context for recovery
            $backup_context = $context;
            update_user_meta(kwetupizza_get_user_id_by_phone($from), 'last_conversation_context', $backup_context);
            
            // Reset the conversation
            kwetupizza_reset_conversation($from);
            
            // Determine if we should send a timeout message based on the conversation state
            if (!isset($context['awaiting']) || $context['awaiting'] != 'greeting') {
                $timeout_minutes = round($timeout / 60);
                kwetupizza_send_whatsapp_message(
                    $from, 
                    "It seems you've been inactive for {$timeout_minutes} minutes. Your session has been reset. Type 'menu' to see available options or 'continue' to resume your previous conversation."
                );
            }
            return true;  // Session has been reset
        }
    }
    return false;  // No reset needed
}

// Get user ID by phone number
function kwetupizza_get_user_id_by_phone($phone) {
    global $wpdb;
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}kwetupizza_users WHERE phone = %s",
        $phone
    ));
    return $user_id;
}

// Recover the last conversation context if user types 'continue'
function kwetupizza_recover_conversation($from) {
    $user_id = kwetupizza_get_user_id_by_phone($from);
    if (!$user_id) return false;
    
    $backup_context = get_user_meta($user_id, 'last_conversation_context', true);
    if (!$backup_context) return false;
    
    // Restore the context
    kwetupizza_set_conversation_context($from, $backup_context);
    
    // Send a message to the user indicating where they left off
    $state = isset($backup_context['awaiting']) ? $backup_context['awaiting'] : 'unknown';
    $cart_items = isset($backup_context['cart']) ? count($backup_context['cart']) : 0;
    
    $message = "Welcome back! We've restored your session. ";
    
    if ($cart_items > 0) {
        $message .= "You have {$cart_items} item(s) in your cart. ";
    }
    
    switch ($state) {
        case 'menu_selection':
            $message .= "You were viewing our menu.";
            kwetupizza_send_full_menu($from);
            break;
        case 'quantity':
            $message .= "You were about to specify a quantity.";
            break;
        case 'add_or_checkout':
            $message .= "You were deciding whether to add more items or checkout.";
            kwetupizza_send_cart_summary($from, $backup_context['cart']);
            kwetupizza_send_whatsapp_message($from, "Type 'add' to add more items, or 'checkout' to proceed with your order.");
            break;
        case 'address_input':
            $message .= "You were about to provide your delivery address.";
            kwetupizza_send_whatsapp_message($from, "Please provide your delivery address.");
            break;
        case 'payment_provider':
            $message .= "You were selecting a payment method.";
            kwetupizza_send_payment_options($from);
            break;
        default:
            $message .= "Type 'menu' to see our options.";
            break;
    }
    
    kwetupizza_send_whatsapp_message($from, $message);
    return true;
}

// Function to send payment options
function kwetupizza_send_payment_options($from) {
    $message = "Please select your payment method:";
    
    $buttons = [
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'mpesa_btn',
                'title' => '1. M-Pesa'
            ]
        ],
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'tigopesa_btn',
                'title' => '2. Tigo Pesa'
            ]
        ],
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'airtelmoney_btn',
                'title' => '3. Airtel Money'
            ]
        ],
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'cash_btn',
                'title' => '4. Cash on Delivery'
            ]
        ]
    ];
    
    kwetupizza_send_whatsapp_message($from, $message, 'interactive', null, $buttons);
}

// Send WhatsApp Message using the WhatsApp API
function kwetupizza_send_whatsapp_message($phone_number, $message, $message_type = 'text', $media_url = null, $buttons = null) {
    // Configuration options
    $access_token = kwetupizza_get_secure_option('kwetupizza_whatsapp_token');
    $business_account_id = kwetupizza_get_secure_option('kwetupizza_whatsapp_business_account_id');
    $phone_id = kwetupizza_get_secure_option('kwetupizza_whatsapp_phone_id');
    $api_version = get_option('kwetupizza_whatsapp_api_version', 'v15.0'); // Default to v15.0 if not set
    $enable_logging = get_option('kwetupizza_enable_logging', false);

    // Use Business Account ID if available, otherwise fall back to Phone ID
    $id_to_use = !empty($business_account_id) ? $business_account_id : $phone_id;
    $url = "https://graph.facebook.com/{$api_version}/{$id_to_use}/messages";

    if ($enable_logging) {
        error_log("WhatsApp API URL: {$url}");
        error_log("Using " . (!empty($business_account_id) ? "Business Account ID" : "Phone ID"));
    }

    // Input validation
    if (empty($phone_number) || empty($message)) {
        if ($enable_logging) {
            error_log('Invalid phone number or message content.');
        }
        return false;
    }

    // Validate phone number format (simple regex for illustration)
    if (!preg_match('/^\d{7,15}$/', $phone_number)) {
        if ($enable_logging) {
            error_log('Invalid phone number format: ' . $phone_number);
        }
        return false;
    }

    // Build the request data based on message type
    $data = [
        'messaging_product' => 'whatsapp',
        'to' => $phone_number,
        'type' => $message_type,
    ];

    if ($message_type === 'text') {
        $data['text'] = ['body' => $message];
    } elseif ($message_type === 'interactive' && $buttons !== null) {
        // Format for interactive message with buttons
        $data['interactive'] = [
            'type' => 'button',
            'body' => [
                'text' => $message
            ],
            'action' => [
                'buttons' => $buttons
            ]
        ];
    } elseif (in_array($message_type, ['image', 'video', 'audio', 'document'])) {
        if (empty($media_url)) {
            if ($enable_logging) {
                error_log('Media URL is required for media messages.');
            }
            return false;
        }
        $data[$message_type] = [
            'link' => $media_url,
            'caption' => $message, // Optional caption
        ];
    } else {
        if ($enable_logging) {
            error_log('Unsupported message type: ' . $message_type);
        }
        return false;
    }

    // Set up the request arguments
    $args = [
        'body'    => json_encode($data),
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ],
        'timeout' => 45, // Increase timeout if necessary
    ];

    // Retry mechanism
    $max_retries = 3;
    $attempt = 0;
    do {
        $response = wp_remote_post($url, $args);
        $attempt++;

        if (is_wp_error($response)) {
            if ($enable_logging) {
                error_log("WhatsApp Message Send Error (Attempt {$attempt}): " . $response->get_error_message());
            }
            // Retry on certain errors
            if ($attempt < $max_retries && in_array($response->get_error_code(), ['http_request_failed', 'http_request_timeout'])) {
                sleep(1); // Wait before retrying
                continue;
            }
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if (isset($result['error'])) {
            if ($enable_logging) {
                error_log("WhatsApp API Error (Attempt {$attempt}): " . print_r($result['error'], true));
            }
            // Handle rate limiting or transient errors
            if (isset($result['error']['code']) && in_array($result['error']['code'], [80007, 131000])) {
                // Rate limit or transient error, wait and retry
                if ($attempt < $max_retries) {
                    sleep(2); // Wait longer before retrying
                    continue;
                }
            }
            return false;
        }

        // Success
        if ($enable_logging) {
            error_log('WhatsApp message sent successfully: ' . $response_body);
        }
        return true;

    } while ($attempt < $max_retries);

    // If we reach here, all attempts failed
    if ($enable_logging) {
        error_log('Failed to send WhatsApp message after ' . $max_retries . ' attempts.');
    }
    return false;
}


// Send default message
function kwetupizza_send_default_message($from) {
    kwetupizza_send_whatsapp_message($from, "Sorry, I didn't understand that. Type 'menu' to see available options.");
}

// Send greeting message
function kwetupizza_send_greeting($from) {
    $message = "Hello! Welcome to KwetuPizza 🍕. Choose an option below or type 'menu' to see our menu.";
    
    $buttons = [
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'menu_btn',
                'title' => '🍕 See Menu'
            ]
        ],
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'track_btn',
                'title' => '🚚 Track Order'
            ]
        ],
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'help_btn',
                'title' => '❓ Help'
            ]
        ]
    ];
    
    kwetupizza_send_whatsapp_message($from, $message, 'interactive', null, $buttons);
    kwetupizza_set_conversation_context($from, ['awaiting' => 'menu_or_order', 'greeted' => true]);
}

// Send full menu to user with caching for better performance
function kwetupizza_send_full_menu($from) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_products';
    
    // Try to get cached menu first
    $cached_menu = get_transient('kwetupizza_cached_menu');
    
    if (!$cached_menu) {
        // Cache expired or doesn't exist, fetch from database
        $pizzas = $wpdb->get_results("SELECT id, product_name, price FROM $table_name WHERE category = 'Pizza' ORDER BY id ASC");
        $drinks = $wpdb->get_results("SELECT id, product_name, price FROM $table_name WHERE category = 'Drinks' ORDER BY id ASC");
        $desserts = $wpdb->get_results("SELECT id, product_name, price FROM $table_name WHERE category = 'Dessert' ORDER BY id ASC");

        $message = "Here's our menu. Please type the number of the item you'd like to order:\n\n";

        if ($pizzas) {
            $message .= "🍕 *Pizzas:*\n";
            foreach ($pizzas as $pizza) {
                $message .= $pizza->id . ". " . $pizza->product_name . " - " . number_format($pizza->price, 0) . " TZS\n";
            }
        }

        if ($drinks) {
            $message .= "\n🥤 *Drinks:*\n";
            foreach ($drinks as $drink) {
                $message .= $drink->id . ". " . $drink->product_name . " - " . number_format($drink->price, 0) . " TZS\n";
            }
        }

        if ($desserts) {
            $message .= "\n🍰 *Desserts:*\n";
            foreach ($desserts as $dessert) {
                $message .= $dessert->id . ". " . $dessert->product_name . " - " . number_format($dessert->price, 0) . " TZS\n";
            }
        }
        
        // Cache the menu for 1 hour
        set_transient('kwetupizza_cached_menu', $message, HOUR_IN_SECONDS);
        $cached_menu = $message;
    }

    kwetupizza_send_whatsapp_message($from, $cached_menu);
    $context = kwetupizza_get_conversation_context($from);
    $context['awaiting'] = 'menu_selection';
    kwetupizza_set_conversation_context($from, $context);
}

// Log function for debugging
function kwetupizza_log($message) {
    if (WP_DEBUG === true) {
        error_log($message);
    }
}

// Log current context and input for debugging
function kwetupizza_log_context_and_input($from, $input) {
    $log_file = plugin_dir_path(__FILE__) . 'kwetupizza-debug.log';
    $context = kwetupizza_get_conversation_context($from);

    $log_content = "Current Context for user [$from]:\n";
    $log_content .= print_r($context, true);
    $log_content .= "Received Input: $input\n\n";

    file_put_contents($log_file, $log_content, FILE_APPEND);
    error_log($log_content);
}

// Handle user messages and check if user exists
function kwetupizza_handle_whatsapp_message($from, $message) {
    $original_message = $message; // Keep the original message for later use
    $message = strtolower(trim($message));
    $context = kwetupizza_get_conversation_context($from);

    // Check for inactivity and reset conversation if needed
    if (kwetupizza_check_inactivity_and_reset($from)) {
        // The session has been reset, so we can start fresh
        return;
    }

    // Log the current context and incoming message for debugging
    kwetupizza_log_context_and_input($from, $message);

    // Check for 'reset' command
    if ($message === 'reset') {
        kwetupizza_reset_conversation($from);
        kwetupizza_send_whatsapp_message($from, "Your session has been reset. You can start a new order by typing 'menu' or 'order'.");
        return;
    }
    
    // Check for 'continue' command
    if ($message === 'continue') {
        if (kwetupizza_recover_conversation($from)) {
            // Recovery was successful and appropriate messages were sent
            return;
        } else {
            kwetupizza_send_whatsapp_message($from, "Sorry, we couldn't find your previous session. Let's start fresh. Type 'menu' to see our options.");
            return;
        }
    }
    
    // Check for 'track' command
    if (strpos($message, 'track') === 0) {
        $parts = explode(' ', $message);
        if (count($parts) > 1 && is_numeric($parts[1])) {
            kwetupizza_track_order($from, intval($parts[1]));
            return;
        } else {
            kwetupizza_send_whatsapp_message($from, "To track your order, please use the format: track [order_id]. For example: track 123");
            return;
        }
    }
    
    // Handle menu button
    if ($message === 'menu_btn') {
        $message = 'menu';
    }
    
    // Handle track order button
    if ($message === 'track_btn') {
        kwetupizza_send_whatsapp_message($from, "Please enter your order number to track your order. For example: track 123");
        return;
    }
    
    // Handle help button
    if ($message === 'help_btn') {
        kwetupizza_send_help_message($from);
        return;
    }
    
    // Handle premium button or command
    if ($message === 'premium' || $message === 'premium_more_btn') {
        if (function_exists('kwetupizza_handle_premium_options')) {
            kwetupizza_handle_premium_options($from);
            return;
        }
    }

    // If no context exists or user is new, greet them
    if (empty($context) || !isset($context['greeted']) || !$context['greeted']) {
        kwetupizza_send_greeting($from);
        return;
    }

    // Handle different states based on the context
    if (isset($context['awaiting'])) {
        switch ($context['awaiting']) {
            case 'menu_or_order':
                if ($message === 'menu') {
                    kwetupizza_send_full_menu($from);
                } elseif ($message === 'order') {
                    kwetupizza_send_full_menu($from);
                } else {
                    kwetupizza_send_default_message($from);
                }
                break;

            case 'menu_selection':
                if (is_numeric($message)) {
                    $product_id = intval($message);
                    kwetupizza_process_order($from, $product_id);
                } else {
                    kwetupizza_send_whatsapp_message($from, "Please enter a valid product number from the menu, or type 'menu' to see the options again.");
                }
                break;

            case 'quantity':
                if (is_numeric($message) && intval($message) > 0) {
                    $quantity = intval($message);
                    $product_id = $context['selected_product'];
                    kwetupizza_confirm_order_and_request_quantity($from, $product_id, $quantity);
                } else {
                    kwetupizza_send_whatsapp_message($from, "Please enter a valid quantity (a number greater than 0).");
                }
                break;

            case 'special_instructions':
                // Handle special instructions input
                $context['special_instructions'] = $original_message;
                $context['awaiting'] = 'add_or_checkout';
                kwetupizza_set_conversation_context($from, $context);
                
                // Show the current cart with the added item
                kwetupizza_send_cart_summary($from, $context['cart']);
                kwetupizza_send_whatsapp_message($from, "Special instructions saved. Type 'add' to add more items, or 'checkout' to proceed with your order.");
                break;

            case 'add_or_checkout':
                kwetupizza_handle_add_or_checkout($from, $message);
                break;

            case 'address_input':
                // Check if this is a saved address selection
                if (strpos($message, 'address_') === 0) {
                    $address_id = intval(substr($message, 8));
                    if (isset($context['saved_addresses'])) {
                        foreach ($context['saved_addresses'] as $saved_address) {
                            if ($saved_address->id == $address_id) {
                                $original_message = $saved_address->address;
                                break;
                            }
                        }
                    }
                }
                kwetupizza_handle_address_and_ask_payment_provider($from, $original_message);
                break;

            case 'payment_provider':
                kwetupizza_handle_payment_provider_response($from, $message);
                break;

            case 'use_whatsapp_number':
                // Handle button responses
                if ($message === 'yes_btn') {
                    $message = 'yes';
                } elseif ($message === 'no_btn') {
                    $message = 'no';
                }
                kwetupizza_handle_use_whatsapp_number_response($from, $message);
                break;

            case 'payment_phone_input':
                kwetupizza_handle_payment_phone_input($from, $message);
                break;
                
            case 'review':
                // Handle review ratings (coming from buttons or text)
                kwetupizza_handle_review($from, $message);
                break;
                
            case 'review_comment':
                // Handle additional feedback for reviews
                $context = kwetupizza_get_conversation_context($from);
                if (isset($context['review_order_id'])) {
                    kwetupizza_handle_review($from, null, $original_message);
                } else {
                    kwetupizza_send_default_message($from);
                }
                break;

            case 'premium_option':
                // Handle premium option selection
                if (function_exists('kwetupizza_handle_premium_option_selection') && is_numeric($message)) {
                    kwetupizza_handle_premium_option_selection($from, $message);
                } else {
                    kwetupizza_send_whatsapp_message($from, "Please enter a valid option number, or type 'menu' to go back to the menu.");
                }
                break;

            default:
                kwetupizza_send_default_message($from);
                break;
        }
    } else {
        kwetupizza_send_default_message($from);
    }
}

// Send help message with information on how to use the bot
function kwetupizza_send_help_message($from) {
    $message = "*KwetuPizza Help Guide* 🍕\n\n"
        . "*Main Commands:*\n"
        . "• *menu* - View our pizza menu\n"
        . "• *track [order#]* - Check your order status\n"
        . "• *reset* - Reset your conversation\n"
        . "• *continue* - Resume a previous session\n\n"
        . "*During Ordering:*\n"
        . "• Enter item number to select from menu\n"
        . "• Enter quantity when asked\n"
        . "• Add special instructions when prompted\n"
        . "• Type 'add' for more items or 'checkout' to complete\n\n"
        . "Need more help? Call us at " . get_option('kwetupizza_customer_support_number', '0712345678');
    
    kwetupizza_send_whatsapp_message($from, $message);
}

// Track order status
function kwetupizza_track_order($from, $order_id) {
    global $wpdb;
    
    // Get the order details
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d", 
        $order_id
    ));
    
    if (!$order) {
        kwetupizza_send_whatsapp_message($from, "Sorry, we couldn't find order #$order_id. Please check the order number and try again.");
        return;
    }
    
    // Verify the order belongs to this customer (by phone number)
    if ($order->customer_phone !== $from && !kwetupizza_phone_matches($order->customer_phone, $from)) {
        kwetupizza_send_whatsapp_message($from, "Sorry, we couldn't verify that order #$order_id belongs to you. Please contact customer support if you need assistance.");
        return;
    }
    
    // Get order items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT oi.quantity, p.product_name 
         FROM {$wpdb->prefix}kwetupizza_order_items oi
         JOIN {$wpdb->prefix}kwetupizza_products p ON oi.product_id = p.id
         WHERE oi.order_id = %d",
        $order_id
    ));
    
    // Format status message
    $status_emoji = [
        'pending' => '⏳',
        'processing' => '🔄',
        'completed' => '✅',
        'delivered' => '🚚',
        'cancelled' => '❌',
        'refunded' => '💰',
        'failed' => '❌'
    ];
    
    $emoji = isset($status_emoji[$order->status]) ? $status_emoji[$order->status] : '📋';
    
    $message = "*Order #$order_id Status: " . ucfirst($order->status) . " $emoji*\n\n";
    $message .= "*Order Date:* " . date('F j, Y, g:i a', strtotime($order->order_date)) . "\n";
    $message .= "*Total:* " . number_format($order->total, 2) . " " . $order->currency . "\n\n";
    
    $message .= "*Items:*\n";
    foreach ($items as $item) {
        $message .= "• " . $item->quantity . "x " . $item->product_name . "\n";
    }
    
    $message .= "\n*Delivery Address:*\n" . $order->delivery_address . "\n\n";
    
    // Add tracking link if available
    $site_url = get_site_url();
    $tracking_url = $site_url . "/wp-json/kwetupizza/v1/order-status/" . $order_id;
    $message .= "For more details, visit: $tracking_url";
    
    // Send the status message
    kwetupizza_send_whatsapp_message($from, $message);
}

// Check if phone numbers match (handles different formats)
function kwetupizza_phone_matches($phone1, $phone2) {
    // Clean both numbers to just digits
    $clean1 = preg_replace('/[^0-9]/', '', $phone1);
    $clean2 = preg_replace('/[^0-9]/', '', $phone2);
    
    // If either is empty, they don't match
    if (empty($clean1) || empty($clean2)) {
        return false;
    }
    
    // Get the last 9 digits of each number for comparison
    $end1 = substr($clean1, -9);
    $end2 = substr($clean2, -9);
    
    return $end1 === $end2;
}

// Process order by adding item to cart
function kwetupizza_process_order($from, $product_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_products';
    $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $product_id));

    if ($product) {
        $message = "You've selected " . $product->product_name . ". Please enter the quantity.";
        kwetupizza_send_whatsapp_message($from, $message);

        $context = kwetupizza_get_conversation_context($from);
        $context['cart'][] = [
            'product_id' => $product_id,
            'product_name' => $product->product_name,
            'price' => $product->price
        ];
        $context['awaiting'] = 'quantity';
        kwetupizza_set_conversation_context($from, $context);
    } else {
        kwetupizza_send_whatsapp_message($from, "Sorry, the selected item is not available.");
    }
}

// Confirm order and request quantity
function kwetupizza_confirm_order_and_request_quantity($from, $product_id, $quantity) {
    global $wpdb;
    $product_table = $wpdb->prefix . 'kwetupizza_products';
    $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $product_table WHERE id = %d", $product_id));

    if (!$product) {
        kwetupizza_send_whatsapp_message($from, "Sorry, the product you selected is not available. Please choose another item from the menu.");
        kwetupizza_send_full_menu($from);
        return;
    }

    $context = kwetupizza_get_conversation_context($from);
    
    // Initialize the cart if it doesn't exist
    if (!isset($context['cart'])) {
        $context['cart'] = [];
    }
    
    // Calculate total for this item
    $item_total = $product->price * $quantity;
    
    // Add or update item in cart
    $found = false;
    foreach ($context['cart'] as $key => $item) {
        if ($item['product_id'] == $product_id) {
            $context['cart'][$key]['quantity'] += $quantity;
            $context['cart'][$key]['total'] = $product->price * $context['cart'][$key]['quantity'];
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $context['cart'][] = [
            'product_id' => $product_id,
            'name' => $product->product_name,
            'price' => $product->price,
            'quantity' => $quantity,
            'total' => $item_total
        ];
    }
    
    // Update context to ask for special instructions
    $context['awaiting'] = 'special_instructions';
    kwetupizza_set_conversation_context($from, $context);
    
    // Confirm the addition and ask for special instructions
    $message = "Added {$quantity}x {$product->product_name} to your cart.\n\n";
    $message .= "Would you like to add any special instructions for this item? (e.g., 'extra cheese', 'well done', etc.)\n";
    $message .= "Or type 'none' if you don't have any special requests.";
    
    kwetupizza_send_whatsapp_message($from, $message);
}

// Handle add or checkout
function kwetupizza_handle_add_or_checkout($from, $response) {
    $context = kwetupizza_get_conversation_context($from);
    
    if (empty($context['cart'])) {
        kwetupizza_send_whatsapp_message($from, "Your cart is empty. Type 'menu' to see our options.");
        $context['awaiting'] = 'menu_or_order';
        kwetupizza_set_conversation_context($from, $context);
        return;
    }
    
    // Convert "add" response from button to text
    if ($response === 'add_more_btn' || strtolower($response) === 'add') {
        kwetupizza_send_full_menu($from);
        $context['awaiting'] = 'menu_selection';
        kwetupizza_set_conversation_context($from, $context);
        return;
    }
    
    // Convert "checkout" response from button to text
    if ($response === 'checkout_btn' || strtolower($response) === 'checkout') {
        // Display cart summary
        kwetupizza_send_cart_summary($from, $context['cart']);
        
        // Ask for delivery address
        $message = "Please provide your delivery address.";
        
        // Check if user has previous addresses
        $previous_addresses = kwetupizza_get_user_addresses($from);
        
        if (!empty($previous_addresses)) {
            $message .= "\n\nOr select one of your saved addresses:";
            
            $buttons = [];
            $count = 0;
            
            foreach ($previous_addresses as $address) {
                if ($count < 3) { // Max 3 buttons allowed
                    $buttons[] = [
                        'type' => 'reply',
                        'reply' => [
                            'id' => 'address_' . $address->id,
                            'title' => substr($address->address, 0, 20) . (strlen($address->address) > 20 ? '...' : '')
                        ]
                    ];
                    $count++;
                }
            }
            
            if (!empty($buttons)) {
                $context['saved_addresses'] = $previous_addresses;
                kwetupizza_set_conversation_context($from, $context);
                kwetupizza_send_whatsapp_message($from, $message, 'interactive', null, $buttons);
                $context['awaiting'] = 'address_input';
                kwetupizza_set_conversation_context($from, $context);
                return;
            }
        }
        
        kwetupizza_send_whatsapp_message($from, $message);
        $context['awaiting'] = 'address_input';
        kwetupizza_set_conversation_context($from, $context);
        return;
    }
    
    if ($response === 'clear_btn' || strtolower($response) === 'clear') {
        // Clear the cart
        $context['cart'] = [];
        kwetupizza_set_conversation_context($from, $context);
        kwetupizza_send_whatsapp_message($from, "Your cart has been cleared. Type 'menu' to see our options.");
        $context['awaiting'] = 'menu_or_order';
        kwetupizza_set_conversation_context($from, $context);
        return;
    }
    
    // If we get here, the response wasn't recognized
    $message = "Please type 'add' to add more items, 'checkout' to proceed with your order, or 'clear' to empty your cart.";
    
    $buttons = [
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'add_more_btn',
                'title' => '➕ Add More'
            ]
        ],
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'checkout_btn',
                'title' => '✅ Checkout'
            ]
        ],
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'clear_btn',
                'title' => '🗑️ Clear Cart'
            ]
        ]
    ];
    
    kwetupizza_send_whatsapp_message($from, $message, 'interactive', null, $buttons);
}

// Function to get user's saved addresses
function kwetupizza_get_user_addresses($phone) {
    global $wpdb;
    
    // Get user ID
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}kwetupizza_users WHERE phone = %s",
        $phone
    ));
    
    if (!$user_id) {
        return [];
    }
    
    // Get saved addresses
    $addresses = $wpdb->get_results($wpdb->prepare(
        "SELECT id, address, phone_number FROM {$wpdb->prefix}kwetupizza_addresses WHERE user_id = %d ORDER BY id DESC LIMIT 3",
        $user_id
    ));
    
    return $addresses ?: [];
}

// Handle delivery address input and ask for payment provider
function kwetupizza_handle_address_and_ask_payment_provider($from, $address) {
    $context = kwetupizza_get_conversation_context($from);

    if (isset($context['cart'])) {
        // Save the address in the conversation context
        $context['address'] = $address;
        kwetupizza_set_conversation_context($from, $context);

        // Ask which payment network provider the customer will use
        $message = "Which Mobile Money network would you like to use for payment? Reply with one of the following: Vodacom, Tigo, Halopesa, or Airtel";
        kwetupizza_send_whatsapp_message($from, $message);

        // Set the context to expect a network provider response
        $context['awaiting'] = 'payment_provider';
        kwetupizza_set_conversation_context($from, $context);
    } else {
        kwetupizza_send_whatsapp_message($from, "Error processing your order. Please try again.");
    }
}

// Handle payment provider response
function kwetupizza_handle_payment_provider_response($from, $response) {
    $context = kwetupizza_get_conversation_context($from);
    
    // Handle button responses
    if (in_array($response, ['mpesa_btn', 'tigopesa_btn', 'airtelmoney_btn', 'cash_btn'])) {
        $provider_map = [
            'mpesa_btn' => 'mpesa',
            'tigopesa_btn' => 'tigopesa',
            'airtelmoney_btn' => 'airtel',
            'cash_btn' => 'cash'
        ];
        $response = $provider_map[$response];
    }
    
    // Process the response
    $provider = strtolower($response);
    $valid_providers = ['1', '2', '3', '4', 'mpesa', 'tigo', 'tigopesa', 'airtel', 'airtelmoney', 'cash', 'cod'];
    
    if (!in_array($provider, $valid_providers)) {
        $message = "Please select a valid payment method:";
        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'mpesa_btn',
                    'title' => '1. M-Pesa'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'tigopesa_btn',
                    'title' => '2. Tigo Pesa'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'airtelmoney_btn',
                    'title' => '3. Airtel Money'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'cash_btn',
                    'title' => '4. Cash on Delivery'
                ]
            ]
        ];
        kwetupizza_send_whatsapp_message($from, $message, 'interactive', null, $buttons);
        return;
    }
    
    // Map numeric responses to provider names
    if ($provider === '1') $provider = 'mpesa';
    if ($provider === '2') $provider = 'tigopesa';
    if ($provider === '3') $provider = 'airtel';
    if ($provider === '4') $provider = 'cash';
    if ($provider === 'tigo') $provider = 'tigopesa';
    if ($provider === 'airtelmoney') $provider = 'airtel';
    if ($provider === 'cod') $provider = 'cash';
    
    $context['payment_provider'] = $provider;
    kwetupizza_set_conversation_context($from, $context);
    
    if ($provider === 'cash') {
        // No need for mobile money number for cash payments
        $context['payment_phone'] = $from; // Use customer's WhatsApp number for reference
        kwetupizza_set_conversation_context($from, $context);
        
        // Process the order
        kwetupizza_process_order_payment($from, $context);
    } else {
        // Ask if user wants to use the same number for payment
        $message = "Would you like to use your WhatsApp number for $provider payment?";
        $buttons = [
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'yes_btn',
                    'title' => 'Yes'
                ]
            ],
            [
                'type' => 'reply',
                'reply' => [
                    'id' => 'no_btn',
                    'title' => 'No, use different number'
                ]
            ]
        ];
        kwetupizza_send_whatsapp_message($from, $message, 'interactive', null, $buttons);
        $context['awaiting'] = 'use_whatsapp_number';
        kwetupizza_set_conversation_context($from, $context);
    }
}

// Send cart summary with a better format
function kwetupizza_send_cart_summary($from, $cart) {
    if (empty($cart)) {
        kwetupizza_send_whatsapp_message($from, "Your cart is empty. Type 'menu' to see our options.");
        return;
    }
    
    $total = 0;
    $message = "🛒 *Your Cart*\n\n";
    
    foreach ($cart as $item) {
        $item_total = $item['price'] * $item['quantity'];
        $total += $item_total;
        
        $special_instructions = isset($item['special_instructions']) && $item['special_instructions'] !== 'none' 
            ? "\n   _Note: " . $item['special_instructions'] . "_" 
            : "";
            
        $message .= "*{$item['quantity']}x {$item['name']}*\n";
        $message .= "   Price: " . number_format($item['price'], 0) . " × {$item['quantity']} = " . number_format($item_total, 0) . " TZS" . $special_instructions . "\n\n";
    }

    // Calculate and add the service fee if enabled
    $service_fee = 0;
    if (function_exists('kwetupizza_calculate_order_fee')) {
        $service_fee = kwetupizza_calculate_order_fee($total);
        if ($service_fee > 0) {
            $fee_label = get_option('kwetupizza_fee_label', 'Service Fee');
            $message .= "*{$fee_label}: " . number_format($service_fee, 0) . " TZS*\n\n";
            $total += $service_fee;
        }
    }
    
    $message .= "*Total: " . number_format($total, 0) . " TZS*";
    
    // Add premium options if enabled
    if (function_exists('kwetupizza_get_premium_options')) {
        $premium_options = kwetupizza_get_premium_options();
        if (!empty($premium_options)) {
            $message .= "\n\n*Premium Options Available:*\n";
            foreach ($premium_options as $option_key => $option) {
                $message .= "• {$option['label']} (+{$option['fee']} TZS): {$option['description']}\n";
            }
            $message .= "\nType 'premium' to add premium options to your order.";
        }
    }
    
    $buttons = [
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'add_more_btn',
                'title' => '➕ Add More'
            ]
        ],
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'checkout_btn',
                'title' => '✅ Checkout'
            ]
        ],
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'clear_btn',
                'title' => '🗑️ Clear Cart'
            ]
        ]
    ];
    
    // Apply affiliate links to the message content if the feature is enabled
    if (function_exists('kwetupizza_add_affiliate_links')) {
        $message = kwetupizza_add_affiliate_links($message);
    }
    
    kwetupizza_send_whatsapp_message($from, $message, 'interactive', null, $buttons);
    
    // Save the updated total with service fee to the context
    $context = kwetupizza_get_conversation_context($from);
    $context['total'] = $total;
    $context['service_fee'] = $service_fee;
    kwetupizza_set_conversation_context($from, $context);
}

// Handle use WhatsApp number response
function kwetupizza_handle_use_whatsapp_number_response($from, $response) {
    $response = strtolower(trim($response));
    $context = kwetupizza_get_conversation_context($from);

    if (isset($context['awaiting']) && $context['awaiting'] === 'use_whatsapp_number') {
        if ($response === 'yes') {
            kwetupizza_generate_mobile_money_push($from, $context['cart'], $context['address'], $from);
        } elseif ($response === 'no') {
            $message = "Please provide the phone number you'd like to use for mobile money payment.";
            kwetupizza_send_whatsapp_message($from, $message);

            $context['awaiting'] = 'payment_phone';
            kwetupizza_set_conversation_context($from, $context);
        } else {
            kwetupizza_send_whatsapp_message($from, "Please reply with 'yes' or 'no'.");
        }
    } else {
        kwetupizza_send_default_message($from);
    }
}

// Handle the input for the payment phone number
function kwetupizza_handle_payment_phone_input($from, $payment_phone) {
    $context = kwetupizza_get_conversation_context($from);

    // Check if the user is expected to provide a phone number
    if (isset($context['awaiting']) && $context['awaiting'] === 'payment_phone') {
        // Proceed with the provided phone number for payment
        kwetupizza_generate_mobile_money_push($from, $context['cart'], $context['address'], $payment_phone);
    } else {
        kwetupizza_send_whatsapp_message($from, "I'm not expecting a payment phone number at this moment. Please restart your order if you want to make changes.");
    }
}


// Save Order
function kwetupizza_save_order($from, $context) {
    global $wpdb;

    if (empty($context['address']) || empty($context['cart']) || !isset($context['total'])) {
        error_log('kwetupizza_save_order: Missing required data in context.');
        error_log('Context data: ' . print_r($context, true));
        return false;
    }

    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';

    // Retrieve customer details
    $customer_details = kwetupizza_get_customer_details($from);

    // Insert order details
    $order_data = [
        'customer_name'     => $customer_details['name'],
        'customer_email'    => $customer_details['email'],
        'customer_phone'    => $from,
        'delivery_address'  => $context['address'],
        'delivery_phone'    => $from,
        'status'            => 'pending',
        'total'             => $context['total'],
        'currency'          => 'TZS',
    ];

    $inserted = $wpdb->insert($orders_table, $order_data);

    if ($inserted === false) {
        error_log('kwetupizza_save_order: Failed to insert order - ' . $wpdb->last_error);
        error_log('Order data: ' . print_r($order_data, true));
        return false;
    }

    $order_id = $wpdb->insert_id;

    // Insert order items
    foreach ($context['cart'] as $item) {
        if (!isset($item['product_id'], $item['quantity'], $item['price'])) {
            error_log('kwetupizza_save_order: Missing product data for cart item.');
            error_log('Cart item: ' . print_r($item, true));
            continue; // Skip items with missing data
        }

        $item_data = [
            'order_id'   => $order_id,
            'product_id' => $item['product_id'],
            'quantity'   => $item['quantity'],
            'price'      => $item['price']
        ];

        $insert_item = $wpdb->insert($order_items_table, $item_data);

        if ($insert_item === false) {
            error_log('kwetupizza_save_order: Failed to insert order item for product ID ' . $item['product_id'] . ': ' . $wpdb->last_error);
            error_log('Order item data: ' . print_r($item_data, true));
        }
    }

    return $order_id;
}

// Retrieve customer details
function kwetupizza_get_customer_details($from) {
    global $wpdb;
    $users_table = $wpdb->prefix . 'kwetupizza_users';
    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE phone = %s", $from));
    if ($user) {
        return [
            'name'  => $user->name,
            'email' => $user->email,
        ];
    } else {
        return [
            'name'  => 'Customer',
            'email' => kwetupizza_get_customer_email($from),
        ];
    }
}

// Helper function to get customer email (for simplicity, derived from phone number)
function kwetupizza_get_customer_email($from) {
    return "customer+" . $from . "@example.com";
}


// Function to notify admin on order status via WhatsApp and NextSMS
if (!function_exists('kwetupizza_notify_admin')) {
    // Notify admin on order status via WhatsApp
// In common-functions.php

if (!function_exists('kwetupizza_notify_admin')) {
    function kwetupizza_notify_admin($order_id, $success = true, $type = 'payment') {
        global $wpdb;

        // Retrieve order details
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d",
            $order_id
        ));

        if (!$order) {
            error_log("Order not found for notification: Order ID {$order_id}");
            return;
        }

        // Fetch customer details
        $customer_name = $order->customer_name;
        $customer_phone = $order->customer_phone;
        $delivery_address = $order->delivery_address;
        $order_total = $order->total;

        // Fetch transaction details
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kwetupizza_transactions WHERE order_id = %d ORDER BY id DESC LIMIT 1",
            $order_id
        ));
        $transaction_id = $transaction ? $transaction->id : 'N/A';

        // Fetch order items
        $order_items = $wpdb->get_results($wpdb->prepare(
            "SELECT oi.*, p.product_name FROM {$wpdb->prefix}kwetupizza_order_items oi
            LEFT JOIN {$wpdb->prefix}kwetupizza_products p ON oi.product_id = p.id
            WHERE oi.order_id = %d",
            $order_id
        ));

        $items_details = '';
        foreach ($order_items as $item) {
            $items_details .= "{$item->quantity} x {$item->product_name}\n";
        }

        // Build the message
        $message = "🚀 New Order Alert!\n";
        $message .= "Order ID: {$order_id}\n";
        $message .= "Transaction ID: {$transaction_id}\n";
        $message .= "Customer Name: {$customer_name}\n";
        $message .= "Customer Phone: {$customer_phone}\n";
        $message .= "Delivery Address: {$delivery_address}\n";
        $message .= "Order Total: " . number_format($order_total, 2) . " TZS\n";
        $message .= "Order Items:\n{$items_details}";

        // Include payment status if applicable
        if ($type === 'payment') {
            $payment_status = $success ? 'Successful' : 'Failed';
            $message .= "Payment Status: {$payment_status}\n";
        }

        // Send notifications
        $admin_whatsapp = get_option('kwetupizza_admin_whatsapp');
        $admin_sms = get_option('kwetupizza_admin_sms_number');

        // Send WhatsApp message
        if ($admin_whatsapp) {
            $whatsapp_sent = kwetupizza_send_whatsapp_message($admin_whatsapp, $message);
            if (!$whatsapp_sent) {
                error_log('Failed to send WhatsApp message to admin.');
            }
        } else {
            error_log('Admin WhatsApp number not set.');
        }

        // Send SMS message
        if ($admin_sms) {
            $sms_sent = kwetupizza_send_sms($admin_sms, $message);
            if (!$sms_sent) {
                error_log('Failed to send SMS message to admin.');
            }
        } else {
            error_log('Admin SMS number not set.');
        }
    }
}

    
} // Correctly closing the if-statement

// No extra closing brace here



// Function to send SMS using NextSMS API
function kwetupizza_send_sms($phone_number, $message) {
    $username = kwetupizza_get_secure_option('kwetupizza_nextsms_username');
    $password = kwetupizza_get_secure_option('kwetupizza_nextsms_password');
    $sender_id = kwetupizza_get_secure_option('kwetupizza_nextsms_sender_id', 'KwetuPizza');
    $enable_logging = get_option('kwetupizza_enable_logging', false);

    if (!$username || !$password) {
        if ($enable_logging) {
            error_log('NextSMS username or password not set.');
        }
        return false;
    }

    // Clean up and validate phone number
    $phone_number = preg_replace('/\D/', '', $phone_number);
    if (!preg_match('/^\d{7,15}$/', $phone_number)) {
        if ($enable_logging) {
            error_log('Invalid phone number format: ' . $phone_number);
        }
        return false;
    }

    $url = 'https://messaging-service.co.tz/api/sms/v1/text/single';

    $data = [
        'from' => $sender_id,
        'to' => $phone_number,
        'text' => $message,
    ];

    $auth_string = base64_encode($username . ':' . $password);

    $args = [
        'headers' => [
            'Authorization' => 'Basic ' . $auth_string,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ],
        'body' => json_encode($data),
        'timeout' => 45,
    ];

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        if ($enable_logging) {
            error_log('Failed to send SMS: ' . $response->get_error_message());
        }
        return false;
    } else {
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        $status_group_id = $response_body['messages'][0]['status']['groupId'] ?? null;

        if ($status_group_id == 1 || $status_group_id == 3) {
            // Message is pending or delivered
            if ($enable_logging) {
                error_log('SMS sent successfully to ' . $phone_number . '. Status: ' . $response_body['messages'][0]['status']['name']);
            }
            return true;
        } else {
            // Handle other statuses
            $error_description = $response_body['messages'][0]['status']['description'] ?? 'Unknown error';
            if ($enable_logging) {
                error_log('SMS sending failed: ' . $error_description);
            }
            return false;
        }
    }
}

/**
 * Alias function for kwetupizza_send_sms to maintain compatibility
 * with any references to kwetupizza_send_nextsms in the codebase
 * 
 * @param string $phone_number The recipient's phone number
 * @param string $message The message to be sent
 * @return bool Whether the SMS was sent successfully
 */
function kwetupizza_send_nextsms($phone_number, $message) {
    return kwetupizza_send_sms($phone_number, $message);
}

// Verify payment using Flutterwave API
function kwetupizza_verify_payment($transaction_id) {
    $secret_key = kwetupizza_get_secure_option('kwetupizza_flw_secret_key');
    $url = "https://api.flutterwave.com/v3/transactions/$transaction_id/verify";

    // Avoid logging actual token in logs
    error_log('Verifying payment for transaction ID: ' . $transaction_id);

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
    
    // Log only status without sensitive transaction details
    $result = json_decode($response_body, true);
    if ($result) {
        error_log('Flutterwave Verification Status: ' . ($result['status'] ?? 'Unknown'));
    } else {
        error_log('Invalid response from Flutterwave API');
    }

    if (isset($result['status']) && $result['status'] == 'success' && $result['data']['status'] == 'successful') {
        return $result['data']; // Return payment data on success
    }

    error_log('Flutterwave Verification Failed for transaction ID: ' . $transaction_id);
    return false;
}


// Confirm payment and notify
function kwetupizza_confirm_payment_and_notify($transaction_id) {
    $transaction_data = kwetupizza_verify_payment($transaction_id);

    if ($transaction_data && $transaction_data['status'] === 'successful') {
        global $wpdb;
        $tx_ref = $transaction_data['tx_ref'];

        // Retrieve the order using tx_ref
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE tx_ref = %s", $tx_ref));

        if (!$order) {
            error_log("Order not found with tx_ref: $tx_ref");
            return false;
        }

        // Update order status to 'completed'
        $updated = $wpdb->update(
            $wpdb->prefix . 'kwetupizza_orders',
            array('status' => 'completed'),
            array('id' => $order->id)
        );

        if ($updated === false) {
            error_log("Failed to update order status for Order ID: {$order->id}");
            return false;
        }

        // Save transaction details
        kwetupizza_save_transaction($order->id, $transaction_data);

        // Send payment confirmation to customer via WhatsApp
        $message = "✅ Payment Confirmed! Your payment for Order #{$order->id} has been received.";
        kwetupizza_send_whatsapp_message($order->customer_phone, $message);

        // Send payment confirmation to customer via SMS
        $sms_message = "Your payment for Order #{$order->id} has been received. Total: " . number_format($order->total, 2) . " TZS. Thank you for choosing KwetuPizza!";
        kwetupizza_send_sms($order->customer_phone, $sms_message);

        // Notify admin
        kwetupizza_notify_admin($order->id, true);

        return true;
    }

    error_log("Payment verification failed for transaction ID: $transaction_id");
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
// Function to send failed payment notification
function kwetupizza_send_failed_payment_notification($phone_number, $tx_ref) {
    global $wpdb;

    // Retrieve order by transaction reference
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE tx_ref = %s", $tx_ref));

    if ($order) {
        // Message for customer
        $message = "❌ Unfortunately, your payment for Order #{$order->id} has failed. Please try again or contact us for assistance.";

        // Send WhatsApp message
        kwetupizza_send_whatsapp_message($phone_number, $message);

        // Send SMS message
        kwetupizza_send_sms($phone_number, $message);
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

// Function to notify admin on order status via WhatsApp and SMS
if (!function_exists('kwetupizza_notify_admin')) {
    function kwetupizza_notify_admin($order_id, $payment_status) {
        global $wpdb;
        $enable_logging = get_option('kwetupizza_enable_logging', false);

        // Retrieve order details
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d", $order_id));

        if (!$order) {
            if ($enable_logging) {
                error_log("Order not found for notification: Order ID {$order_id}");
            }
            return;
        }

        $total_amount = $order->total;
        $delivery_address = $order->delivery_address;
        $user_phone = $order->customer_phone;

        $admin_phone_number = get_option('kwetupizza_admin_whatsapp');

        // Prepare template parameters for WhatsApp message
        $template_parameters = [
            [
                "type" => "text",
                "text" => $order_id
            ],
            [
                "type" => "text",
                "text" => $total_amount
            ],
            [
                "type" => "text",
                "text" => ucfirst($payment_status)
            ],
            [
                "type" => "text",
                "text" => $delivery_address
            ],
            [
                "type" => "text",
                "text" => $user_phone
            ]
        ];

        // Send WhatsApp message using the template
        $message_sent = kwetupizza_send_whatsapp_message($admin_phone_number, 'template', 'admin_notification', $template_parameters);

        if (!$message_sent && $enable_logging) {
            error_log('Failed to send WhatsApp message to admin.');
        }

    // Send SMS notification to admin
    $admin_sms_number = get_option('kwetupizza_admin_sms_number'); // Ensure this option is set

    if ($admin_sms_number) {
        $sms_message = "🚀 New Order Alert!\nOrder ID: {$order_id}\nTotal: {$total_amount} TZS\nPayment Status: " . ucfirst($payment_status) . "\nDelivery: {$delivery_address}\nCustomer Phone: {$user_phone}\nPlease process the order promptly.";

        $sms_sent = kwetupizza_send_sms($admin_sms_number, $sms_message);

        if (!$sms_sent && $enable_logging) {
            error_log('Failed to send SMS notification to admin.');
        }
    } else {
        if ($enable_logging) {
            error_log('Admin SMS number not set.');
        }
    }
}
}
//save menu item
function kwetupizza_save_menu_item() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_products';

    // Check if data is set
    if (isset($_POST['data'])) {
        parse_str($_POST['data'], $data);

        // Sanitize and validate input
        $product_name = sanitize_text_field($data['product_name']);
        $description = sanitize_textarea_field($data['description']);
        $price = floatval($data['price']);
        $currency = sanitize_text_field($data['currency']);
        $category = sanitize_text_field($data['category']);
        $image_url = esc_url_raw($data['image_url']);

        // Check for required fields
        if (empty($product_name) || empty($price) || empty($currency) || empty($category)) {
            wp_send_json_error(['message' => 'All fields are required.']);
            wp_die();
        }

        $menu_item_id = isset($data['menu_item_id']) ? intval($data['menu_item_id']) : 0;

        // Insert or update logic
        if ($menu_item_id == 0) {
            $result = $wpdb->insert($table_name, [
                'product_name' => $product_name,
                'description' => $description,
                'price' => $price,
                'currency' => $currency,
                'category' => $category,
                'image_url' => $image_url
            ]);

            if ($result === false) {
                wp_send_json_error(['message' => 'Failed to insert the new menu item.']);
            } else {
                wp_send_json_success(['message' => 'Menu item added successfully.']);
            }
        } else {
            $result = $wpdb->update($table_name, [
                'product_name' => $product_name,
                'description' => $description,
                'price' => $price,
                'currency' => $currency,
                'category' => $category,
                'image_url' => $image_url
            ], ['id' => $menu_item_id]);

            if ($result === false) {
                wp_send_json_error(['message' => 'Failed to update the menu item.']);
            } else {
                wp_send_json_success(['message' => 'Menu item updated successfully.']);
            }
        }
    } else {
        wp_send_json_error(['message' => 'Invalid request.']);
    }

    wp_die();
}
add_action('wp_ajax_kwetupizza_save_menu_item', 'kwetupizza_save_menu_item');

// Send review request after order completion
function kwetupizza_send_review_request($phone_number, $order_id) {
    global $wpdb;
    
    // Get order details
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d",
        $order_id
    ));
    
    if (!$order) {
        error_log("Failed to send review request: Order #$order_id not found");
        return false;
    }
    
    // Check if a review has already been sent for this order
    $review_sent = get_post_meta($order_id, 'review_request_sent', true);
    if ($review_sent) {
        return false; // Avoid sending duplicate review requests
    }
    
    // Create message with rating buttons
    $message = "Thank you for your order from KwetuPizza! We hope you enjoyed your meal.\n\n"
        . "How would you rate your experience? (1-5 stars)";
    
    $buttons = [
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'rating_1',
                'title' => '⭐ (1)'
            ]
        ],
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'rating_2',
                'title' => '⭐⭐ (2)'
            ]
        ],
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'rating_3', 
                'title' => '⭐⭐⭐ (3)'
            ]
        ]
    ];
    
    // Send the request
    $sent = kwetupizza_send_whatsapp_message($phone_number, $message, 'interactive', null, $buttons);
    
    if ($sent) {
        // Mark this order as having received a review request
        update_post_meta($order_id, 'review_request_sent', true);
        
        // Update user context to expect a review response
        $context = kwetupizza_get_conversation_context($phone_number);
        $context['awaiting'] = 'review';
        $context['review_order_id'] = $order_id;
        kwetupizza_set_conversation_context($phone_number, $context);
        
        return true;
    }
    
    return false;
}

// Handle review responses
function kwetupizza_handle_review($from, $rating, $comment = '') {
    global $wpdb;
    
    $context = kwetupizza_get_conversation_context($from);
    
    // Check if we're expecting a review
    if (!isset($context['awaiting']) || $context['awaiting'] !== 'review' || !isset($context['review_order_id'])) {
        return false;
    }
    
    $order_id = $context['review_order_id'];
    
    // Parse rating from button ID or manually entered value
    if (strpos($rating, 'rating_') === 0) {
        $rating = intval(substr($rating, 7));
    } else {
        $rating = intval($rating);
    }
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        kwetupizza_send_whatsapp_message($from, "Please provide a rating between 1 and 5 stars.");
        return false;
    }
    
    // Create reviews table if it doesn't exist
    kwetupizza_maybe_create_reviews_table();
    
    // Save the review
    $wpdb->insert(
        $wpdb->prefix . 'kwetupizza_reviews',
        [
            'order_id' => $order_id,
            'phone_number' => $from,
            'rating' => $rating,
            'comment' => $comment,
            'created_at' => current_time('mysql')
        ]
    );
    
    // If first response was just the rating, ask for additional feedback for ratings <= 3
    if (empty($comment) && $rating <= 3) {
        kwetupizza_send_whatsapp_message($from, "Thank you for your rating. We'd appreciate any additional feedback on how we can improve your experience next time.");
        $context['awaiting'] = 'review_comment';
        kwetupizza_set_conversation_context($from, $context);
        return true;
    }
    
    // For good ratings or if comment already provided
    $messages = [
        1 => "We're sorry to hear about your experience. Your feedback is valuable and will help us improve.",
        2 => "Thanks for your feedback. We'll work on improving our service based on your rating.",
        3 => "Thank you for your feedback. We appreciate your honesty and will strive to do better.",
        4 => "Thank you for your positive feedback! We're glad you enjoyed your experience.",
        5 => "Thank you for your excellent rating! We're thrilled you enjoyed your order and look forward to serving you again soon!"
    ];
    
    $response = isset($messages[$rating]) ? $messages[$rating] : "Thank you for your feedback!";
    kwetupizza_send_whatsapp_message($from, $response);
    
    // Reset the context since the review is complete
    $context['awaiting'] = 'menu_or_order';
    unset($context['review_order_id']);
    kwetupizza_set_conversation_context($from, $context);
    
    // Notify admin about low ratings
    if ($rating <= 2) {
        $admin_whatsapp = get_option('kwetupizza_admin_whatsapp');
        $admin_message = "⚠️ Low Rating Alert: Order #$order_id received a $rating-star rating.";
        if (!empty($comment)) {
            $admin_message .= "\nComment: $comment";
        }
        kwetupizza_send_whatsapp_message($admin_whatsapp, $admin_message);
    }
    
    return true;
}

// Create reviews table if it doesn't exist
function kwetupizza_maybe_create_reviews_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_reviews';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id mediumint(9) NOT NULL,
            phone_number varchar(20) NOT NULL,
            rating tinyint(1) NOT NULL,
            comment text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
}

// Function to clear menu cache when products are updated
function kwetupizza_clear_menu_cache() {
    delete_transient('kwetupizza_cached_menu');
}
add_action('kwetupizza_product_updated', 'kwetupizza_clear_menu_cache');
add_action('kwetupizza_product_added', 'kwetupizza_clear_menu_cache');
add_action('kwetupizza_product_deleted', 'kwetupizza_clear_menu_cache');

/**
 * Handle premium options command
 * 
 * @param string $from The user's phone number
 * @return void
 */
function kwetupizza_handle_premium_options($from) {
    // Get the available premium options
    if (!function_exists('kwetupizza_get_premium_options')) {
        kwetupizza_send_whatsapp_message($from, "Premium options are not available at this time.");
        return;
    }
    
    $premium_options = kwetupizza_get_premium_options();
    if (empty($premium_options)) {
        kwetupizza_send_whatsapp_message($from, "Premium options are not available at this time.");
        return;
    }
    
    // Get the user's conversation context
    $context = kwetupizza_get_conversation_context($from);
    
    // Check if the user has an active order
    if (empty($context['cart'])) {
        kwetupizza_send_whatsapp_message($from, "You need to add items to your cart before selecting premium options. Type 'menu' to see our options.");
        return;
    }
    
    // Build the message with premium options
    $message = "🌟 *Premium Options*\n\n";
    $message .= "Enhance your order with these premium options:\n\n";
    
    $option_index = 1;
    foreach ($premium_options as $option_key => $option) {
        $message .= "{$option_index}. *{$option['label']}* - {$option['fee']} TZS\n";
        $message .= "   {$option['description']}\n\n";
        $option_index++;
    }
    
    $message .= "To add a premium option, reply with the number of the option you want to add.";
    
    // Send the message and update the context
    kwetupizza_send_whatsapp_message($from, $message);
    
    // Update the context to wait for premium option selection
    $context['awaiting'] = 'premium_option';
    $context['premium_options'] = $premium_options;
    kwetupizza_set_conversation_context($from, $context);
}

/**
 * Handle premium option selection
 * 
 * @param string $from The user's phone number
 * @param string $option_index The selected option index
 * @return void
 */
function kwetupizza_handle_premium_option_selection($from, $option_index) {
    // Get the user's conversation context
    $context = kwetupizza_get_conversation_context($from);
    
    // Check if the user is expected to select a premium option
    if (!isset($context['awaiting']) || $context['awaiting'] !== 'premium_option' || empty($context['premium_options'])) {
        kwetupizza_send_whatsapp_message($from, "I'm not expecting a premium option selection at this moment. Type 'premium' to see available options.");
        return;
    }
    
    // Convert option index to integer
    $option_index = intval($option_index);
    
    // Get the premium options from the context
    $premium_options = $context['premium_options'];
    $option_keys = array_keys($premium_options);
    
    // Check if the option index is valid
    if ($option_index < 1 || $option_index > count($premium_options)) {
        kwetupizza_send_whatsapp_message($from, "Invalid option. Please select a number between 1 and " . count($premium_options) . ".");
        return;
    }
    
    // Get the selected option
    $selected_option_key = $option_keys[$option_index - 1];
    $selected_option = $premium_options[$selected_option_key];
    
    // Add the premium option to the context
    if (!isset($context['premium_selections'])) {
        $context['premium_selections'] = [];
    }
    $context['premium_selections'][$selected_option_key] = $selected_option;
    
    // Update the total if it exists
    if (isset($context['total'])) {
        $context['total'] += $selected_option['fee'];
    }
    
    // Update the context
    $context['awaiting'] = null; // Reset awaiting state
    kwetupizza_set_conversation_context($from, $context);
    
    // Send confirmation message
    $message = "✅ Added *{$selected_option['label']}* to your order for an additional {$selected_option['fee']} TZS.\n\n";
    $message .= "Your updated total is " . number_format($context['total'], 0) . " TZS.";
    
    $buttons = [
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'premium_more_btn',
                'title' => '➕ More Premium Options'
            ]
        ],
        [
            'type' => 'reply',
            'reply' => [
                'id' => 'checkout_btn',
                'title' => '✅ Checkout'
            ]
        ]
    ];
    
    kwetupizza_send_whatsapp_message($from, $message, 'interactive', null, $buttons);
}

/**
 * Encrypt sensitive data using OpenSSL
 * 
 * @param string $data The data to encrypt
 * @return string The encrypted data
 */
function kwetupizza_encrypt_data($data) {
    if (empty($data)) {
        return '';
    }
    
    // Generate a random encryption key if not already set
    $encryption_key = get_option('kwetupizza_encryption_key');
    if (empty($encryption_key)) {
        $encryption_key = wp_generate_password(32, true, true);
        update_option('kwetupizza_encryption_key', $encryption_key);
    }
    
    // Generate an initialization vector
    $iv_size = openssl_cipher_iv_length('AES-256-CBC');
    $iv = openssl_random_pseudo_bytes($iv_size);
    
    // Encrypt the data
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $encryption_key, 0, $iv);
    
    // Combine the IV and encrypted data
    $encrypted_data = base64_encode($iv . $encrypted);
    
    return $encrypted_data;
}

/**
 * Decrypt sensitive data using OpenSSL
 * 
 * @param string $encrypted_data The encrypted data
 * @return string The decrypted data
 */
function kwetupizza_decrypt_data($encrypted_data) {
    // Bail if no data
    if (empty($encrypted_data)) {
        return false;
    }
    
    // Get the encryption key
    $encryption_key = get_option('kwetupizza_encryption_key');
    
    // If no encryption key, return the data as is (for backward compatibility)
    if (empty($encryption_key)) {
        return $encrypted_data;
    }
    
    // Decode the data
    $encrypted_data = base64_decode($encrypted_data);
    if (false === $encrypted_data) {
        return false;
    }
    
    // Extract the IV from the encrypted data
    $iv_size = openssl_cipher_iv_length('AES-256-CBC');
    $iv = substr($encrypted_data, 0, $iv_size);
    
    // Ensure IV is exactly the right length (16 bytes for AES-256-CBC)
    if (strlen($iv) < $iv_size) {
        $iv = str_pad($iv, $iv_size, "\0"); // Pad with null bytes
    }
    
    // Get the encrypted data without the IV
    $encrypted_data_without_iv = substr($encrypted_data, $iv_size);
    
    // Decrypt the data
    $decrypted_data = openssl_decrypt(
        $encrypted_data_without_iv,
        'AES-256-CBC',
        $encryption_key,
        0,
        $iv
    );
    
    // Return the decrypted data
    return $decrypted_data;
}

/**
 * Securely update sensitive options by encrypting them before storing
 * 
 * @param string $option_name The option name
 * @param string $value The option value to encrypt and store
 * @return bool Whether the option was updated
 */
function kwetupizza_update_secure_option($option_name, $value) {
    $sensitive_options = [
        'kwetupizza_whatsapp_token',
        'kwetupizza_whatsapp_verify_token',
        'kwetupizza_whatsapp_phone_id',
        'kwetupizza_flw_public_key',
        'kwetupizza_flw_secret_key',
        'kwetupizza_flw_encryption_key',
        'kwetupizza_flw_webhook_secret',
        'kwetupizza_nextsms_password',
        'kwetupizza_nextsms_username',
        'kwetupizza_nextsms_sender_id'
    ];
    
    if (in_array($option_name, $sensitive_options)) {
        $encrypted_value = kwetupizza_encrypt_data($value);
        return update_option($option_name, $encrypted_value);
    } else {
        return update_option($option_name, $value);
    }
}

/**
 * Get and decrypt sensitive option values
 * 
 * @param string $option_name The option name
 * @param mixed $default The default value if option not found
 * @return string The decrypted option value or default
 */
function kwetupizza_get_secure_option($option_name, $default = '') {
    $sensitive_options = [
        'kwetupizza_whatsapp_token',
        'kwetupizza_whatsapp_verify_token',
        'kwetupizza_whatsapp_phone_id',
        'kwetupizza_flw_public_key',
        'kwetupizza_flw_secret_key',
        'kwetupizza_flw_encryption_key',
        'kwetupizza_flw_webhook_secret',
        'kwetupizza_nextsms_password',
        'kwetupizza_nextsms_username',
        'kwetupizza_nextsms_sender_id'
    ];
    
    $value = get_option($option_name, $default);
    
    if (in_array($option_name, $sensitive_options) && !empty($value)) {
        return kwetupizza_decrypt_data($value);
    }
    
    return $value;
}

?>