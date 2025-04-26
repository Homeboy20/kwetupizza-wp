<?php
// Location: /wp-content/plugins/kwetu-pizza-plugin/includes/user-detail.php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Function to render the user detail page
function kwetupizza_render_user_detail() {
    global $wpdb;

    // Get the user ID from URL parameters
    if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
        echo '<div class="wrap"><h1>Invalid User ID</h1></div>';
        return;
    }

    $user_id = intval($_GET['user_id']);
    $users_table = $wpdb->prefix . 'kwetupizza_users';
    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $user_id));

    if (!$user) {
        echo '<div class="wrap"><h1>User Not Found</h1></div>';
        return;
    }

    ?>
    <div class="wrap">
        <h1>User Detail: <?php echo esc_html($user->name); ?></h1>
        <a href="<?php echo admin_url('admin.php?page=kwetupizza-user-management'); ?>" class="button">Back to User Management</a>
        <hr>

        <h2>Contact Information</h2>
        <p><strong>Name:</strong> <?php echo esc_html($user->name); ?></p>
        <p><strong>Email:</strong> <?php echo esc_html($user->email); ?></p>
        <p><strong>Phone:</strong> <?php echo esc_html($user->phone); ?></p>
        <p><strong>Role:</strong> <?php echo esc_html(ucfirst($user->role)); ?></p>

        <hr>

        <h2>Order History</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Transaction ID</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Delivery Address</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $orders_table = $wpdb->prefix . 'kwetupizza_orders';
                $orders = $wpdb->get_results($wpdb->prepare("SELECT * FROM $orders_table WHERE customer_phone = %s ORDER BY order_date DESC", $user->phone));

                if ($orders):
                    foreach ($orders as $order):
                        ?>
                        <tr>
                            <td><?php echo esc_html($order->id); ?></td>
                            <td><?php echo esc_html($order->tx_ref ?: 'N/A'); ?></td>
                            <td><?php echo esc_html($order->order_date); ?></td>
                            <td><?php echo esc_html(number_format($order->total, 2)) . ' TZS'; ?></td>
                            <td><?php echo esc_html(ucfirst($order->status)); ?></td>
                            <td><?php echo esc_html($order->delivery_address); ?></td>
                        </tr>
                        <?php
                    endforeach;
                else:
                    ?>
                    <tr>
                        <td colspan="6">No orders found for this user.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <hr>

        <h2>Transaction History</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Currency</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                    <th>Provider</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
                $transactions = $wpdb->get_results($wpdb->prepare("
                    SELECT t.* FROM $transactions_table t
                    JOIN $orders_table o ON t.order_id = o.id
                    WHERE o.customer_phone = %s
                    ORDER BY t.transaction_date DESC
                ", $user->phone));

                if ($transactions):
                    foreach ($transactions as $transaction):
                        ?>
                        <tr>
                            <td><?php echo esc_html($transaction->id); ?></td>
                            <td><?php echo esc_html($transaction->order_id); ?></td>
                            <td><?php echo esc_html($transaction->transaction_date); ?></td>
                            <td><?php echo esc_html(number_format($transaction->amount, 2)) . ' ' . esc_html($transaction->currency); ?></td>
                            <td><?php echo esc_html($transaction->currency); ?></td>
                            <td><?php echo esc_html($transaction->payment_method); ?></td>
                            <td><?php echo esc_html(ucfirst($transaction->payment_status)); ?></td>
                            <td><?php echo esc_html($transaction->payment_provider); ?></td>
                        </tr>
                        <?php
                    endforeach;
                else:
                    ?>
                    <tr>
                        <td colspan="8">No transactions found for this user.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <hr>

        <h2>Send Notification</h2>
        <div id="notification-error-message" style="color: red; display: none;"></div>
        <form id="notification-form">
            <?php wp_nonce_field('kwetupizza_send_notification_nonce', 'kwetupizza_send_notification_nonce_field'); ?>
            <input type="hidden" name="notification_user_id" value="<?php echo esc_attr($user->id); ?>">
            <label for="notification_message">Message:</label>
            <textarea name="notification_message" id="notification_message" rows="5" required></textarea><br>
            <button type="submit" class="button button-primary">Send Notification</button>
        </form>

        <script>
            jQuery(document).ready(function($) {
                // Handle Notification Form Submission
                $('#notification-form').submit(function(e) {
                    e.preventDefault();
                    var formData = $(this).serialize();
                    // Send notification via Ajax
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: formData + '&action=kwetupizza_send_user_notification',
                        success: function(response) {
                            if (response.success) {
                                alert('Notification sent successfully.');
                                $('#notification_message').val(''); // Clear the message
                                $('#notification-error-message').hide();
                            } else {
                                $('#notification-error-message').text(response.data).show();
                            }
                        }
                    });
                });
            });
        </script>
    </div>
    <?php
}

// Register the User Detail page in the admin menu
function kwetupizza_add_user_detail_menu() {
    add_submenu_page(
        'kwetupizza-user-management', // Parent slug
        'User Detail',                // Page title
        'User Detail',                // Menu title
        'manage_options',             // Capability
        'kwetupizza-user-detail',     // Menu slug
        'kwetupizza_render_user_detail' // Function to display the page
    );
}
add_action('admin_menu', 'kwetupizza_add_user_detail_menu');

// AJAX Handler to Send Notification (used in user-detail.php)
function kwetupizza_send_user_notification() {
    // Check for required fields and verify nonce
    if (
        !isset($_POST['notification_user_id']) ||
        !isset($_POST['notification_message']) ||
        !isset($_POST['kwetupizza_send_notification_nonce_field']) ||
        !wp_verify_nonce($_POST['kwetupizza_send_notification_nonce_field'], 'kwetupizza_send_notification_nonce')
    ) {
        wp_send_json_error('Invalid request.');
    }

    $user_id = intval($_POST['notification_user_id']);
    $message = sanitize_textarea_field($_POST['notification_message']);

    if (empty($message)) {
        wp_send_json_error('Message cannot be empty.');
        return;
    }

    global $wpdb;
    $users_table = $wpdb->prefix . 'kwetupizza_users';

    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $user_id));

    if (!$user) {
        wp_send_json_error('User not found.');
    }

    $phone_number = $user->phone;

    // Send WhatsApp message
    $whatsapp_sent = kwetupizza_send_whatsapp_message($phone_number, 'text', '', [$message]);

    // Send SMS message
    $sms_sent = kwetupizza_send_sms($phone_number, $message);

    if ($whatsapp_sent || $sms_sent) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to send notification.');
    }
}
add_action('wp_ajax_kwetupizza_send_user_notification', 'kwetupizza_send_user_notification');
?>
