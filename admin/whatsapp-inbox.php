<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Register scripts and styles for the inbox
function kwetupizza_inbox_enqueue_scripts($hook) {
    if ($hook != 'kwetupizza_page_kwetupizza-whatsapp-inbox') {
        return;
    }
    
    // Register and enqueue styles
    wp_enqueue_style('kwetupizza-inbox-style', plugin_dir_url(dirname(__FILE__)) . 'assets/css/whatsapp-inbox.css', array(), '1.0.0');
    
    // Register and enqueue scripts
    wp_enqueue_script('kwetupizza-inbox-script', plugin_dir_url(dirname(__FILE__)) . 'assets/js/whatsapp-inbox.js', array('jquery'), '1.0.0', true);
    
    // Add the messages data as a JavaScript object
    wp_localize_script('kwetupizza-inbox-script', 'kwetupizzaInbox', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('kwetupizza-inbox-nonce'),
    ));
}
add_action('admin_enqueue_scripts', 'kwetupizza_inbox_enqueue_scripts');

// Ajax function to send message from admin
function kwetupizza_send_admin_message() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kwetupizza-inbox-nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Get and sanitize data
    $phone_number = sanitize_text_field($_POST['phone_number']);
    $message = sanitize_textarea_field($_POST['message']);
    
    if (empty($phone_number) || empty($message)) {
        wp_send_json_error('Phone number and message are required');
    }
    
    // Send the message using WhatsApp API
    $result = kwetupizza_send_whatsapp_message($phone_number, $message);
    
    if ($result) {
        // Save message to conversation history
        kwetupizza_save_message_to_history($phone_number, $message, 'admin');
        wp_send_json_success('Message sent successfully');
    } else {
        wp_send_json_error('Failed to send message');
    }
}
add_action('wp_ajax_kwetupizza_send_admin_message', 'kwetupizza_send_admin_message');

// Ajax function to get recent messages for a specific user
function kwetupizza_get_user_messages() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kwetupizza-inbox-nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    $phone_number = sanitize_text_field($_POST['phone_number']);
    
    if (empty($phone_number)) {
        wp_send_json_error('Phone number is required');
    }
    
    // Get messages from the database
    $messages = kwetupizza_get_conversation_history($phone_number);
    
    wp_send_json_success($messages);
}
add_action('wp_ajax_kwetupizza_get_user_messages', 'kwetupizza_get_user_messages');

// Function to get conversation history
function kwetupizza_get_conversation_history($phone_number) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_messages';
    
    // Check if table exists, create if it doesn't
    kwetupizza_maybe_create_messages_table();
    
    $messages = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE phone_number = %s ORDER BY timestamp ASC LIMIT 100",
            $phone_number
        )
    );
    
    return $messages ?: array();
}

// Function to save a message to the history
function kwetupizza_save_message_to_history($phone_number, $message, $direction) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_messages';
    
    // Check if table exists, create if it doesn't
    kwetupizza_maybe_create_messages_table();
    
    // Insert the message
    $wpdb->insert(
        $table_name,
        array(
            'phone_number' => $phone_number,
            'message' => $message,
            'direction' => $direction,
            'timestamp' => current_time('mysql')
        )
    );
    
    return $wpdb->insert_id;
}

// Create messages table if it doesn't exist
function kwetupizza_maybe_create_messages_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_messages';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            phone_number varchar(20) NOT NULL,
            message text NOT NULL,
            direction varchar(10) NOT NULL,
            timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
}

// Get recent customers with WhatsApp conversations
function kwetupizza_get_recent_whatsapp_customers() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_messages';
    
    // Create table if it doesn't exist
    kwetupizza_maybe_create_messages_table();
    
    // Get unique phone numbers with their latest message timestamp
    $customers = $wpdb->get_results(
        "SELECT DISTINCT phone_number, 
         (SELECT MAX(timestamp) FROM $table_name m2 WHERE m2.phone_number = m1.phone_number) as last_message,
         (SELECT message FROM $table_name m3 WHERE m3.phone_number = m1.phone_number ORDER BY timestamp DESC LIMIT 1) as last_text
         FROM $table_name m1
         GROUP BY phone_number
         ORDER BY last_message DESC
         LIMIT 20"
    );
    
    return $customers ?: array();
}

// Webhook to capture incoming messages and save to history
add_action('kwetupizza_after_process_whatsapp_message', function($phone_number, $message) {
    kwetupizza_save_message_to_history($phone_number, $message, 'customer');
}, 10, 2);

// Add this hook to the whatsapp-handler.php file to trigger the action
// Add the following line after processing the message in kwetupizza_handle_whatsapp_message() function:
// do_action('kwetupizza_after_process_whatsapp_message', $from, $message);

// Render the WhatsApp inbox page
function kwetupizza_render_whatsapp_inbox() {
    // Get recent customers
    $customers = kwetupizza_get_recent_whatsapp_customers();
    $admin_phone = get_option('kwetupizza_admin_whatsapp');
    
    // Get customer details for display
    global $wpdb;
    $users_table = $wpdb->prefix . 'kwetupizza_users';
    
    foreach ($customers as &$customer) {
        // Look up user details if available
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT name, email FROM $users_table WHERE phone = %s",
            $customer->phone_number
        ));
        
        if ($user) {
            $customer->name = $user->name;
            $customer->email = $user->email;
        } else {
            $customer->name = 'Customer';
            $customer->email = '';
        }
    }
    ?>
    <div class="wrap kwetupizza-inbox-container">
        <h1><span class="dashicons dashicons-whatsapp"></span> KwetuPizza WhatsApp Inbox</h1>
        
        <div class="kwetupizza-inbox-wrapper">
            <!-- Customer list sidebar -->
            <div class="kwetupizza-customer-list">
                <div class="kwetupizza-search-container">
                    <input type="text" class="kwetupizza-search-input" placeholder="Search customers...">
                </div>
                
                <div class="kwetupizza-customers-container">
                    <?php if (empty($customers)) : ?>
                        <div class="kwetupizza-no-customers">
                            <p>No conversations found</p>
                        </div>
                    <?php else : ?>
                        <?php foreach ($customers as $index => $customer) : ?>
                            <div class="kwetupizza-customer-item <?php echo ($index === 0) ? 'active' : ''; ?>" 
                                 data-phone="<?php echo esc_attr($customer->phone_number); ?>">
                                <div class="kwetupizza-customer-avatar">
                                    <?php echo substr($customer->name, 0, 1); ?>
                                </div>
                                <div class="kwetupizza-customer-info">
                                    <div class="kwetupizza-customer-name">
                                        <?php echo esc_html($customer->name); ?>
                                        <span class="kwetupizza-customer-time">
                                            <?php echo human_time_diff(strtotime($customer->last_message), current_time('timestamp')); ?> ago
                                        </span>
                                    </div>
                                    <div class="kwetupizza-customer-preview">
                                        <?php echo esc_html(substr($customer->last_text, 0, 30) . (strlen($customer->last_text) > 30 ? '...' : '')); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat area -->
            <div class="kwetupizza-chat-area">
                <?php if (empty($customers)) : ?>
                    <div class="kwetupizza-empty-chat">
                        <div class="kwetupizza-empty-chat-content">
                            <div class="dashicons dashicons-whatsapp"></div>
                            <h2>WhatsApp Inbox</h2>
                            <p>No conversations available.</p>
                        </div>
                    </div>
                <?php else : ?>
                    <!-- Chat header -->
                    <div class="kwetupizza-chat-header">
                        <div class="kwetupizza-chat-user-details">
                            <div class="kwetupizza-chat-avatar">
                                <?php echo substr($customers[0]->name, 0, 1); ?>
                            </div>
                            <div class="kwetupizza-chat-user-info">
                                <div class="kwetupizza-chat-username">
                                    <span class="customer-name"><?php echo esc_html($customers[0]->name); ?></span>
                                </div>
                                <div class="kwetupizza-chat-phone">
                                    <span class="customer-phone"><?php echo esc_html($customers[0]->phone_number); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="kwetupizza-chat-actions">
                            <button class="kwetupizza-refresh-chat button" title="Refresh Chat">
                                <span class="dashicons dashicons-update"></span>
                            </button>
                            <button class="kwetupizza-order-history button" title="View Order History">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Chat messages -->
                    <div class="kwetupizza-chat-messages">
                        <!-- Messages will be loaded via AJAX -->
                        <div class="kwetupizza-loading-messages">
                            <div class="spinner is-active"></div>
                            <p>Loading messages...</p>
                        </div>
                    </div>
                    
                    <!-- Chat input -->
                    <div class="kwetupizza-chat-input">
                        <div class="kwetupizza-message-controls">
                            <button class="kwetupizza-attach-button" title="Attach file">
                                <span class="dashicons dashicons-paperclip"></span>
                            </button>
                            <div class="kwetupizza-message-box">
                                <textarea class="kwetupizza-message-textarea" placeholder="Type a message..."></textarea>
                            </div>
                            <button class="kwetupizza-send-button" title="Send message">
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </button>
                        </div>
                        <div class="kwetupizza-quick-replies">
                            <button class="kwetupizza-quick-reply" data-reply="Hi! How can I help you with your order today?">Hi! 👋</button>
                            <button class="kwetupizza-quick-reply" data-reply="Your order has been received and is being prepared now.">Order Received ✅</button>
                            <button class="kwetupizza-quick-reply" data-reply="Your pizza is on the way! It should arrive in approximately 30 minutes.">On the Way 🛵</button>
                            <button class="kwetupizza-quick-reply" data-reply="Thank you for your order! Please let us know if you need anything else.">Thanks! 🙏</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
