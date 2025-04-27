<?php
// Location: /wp-content/plugins/kwetu-pizza-plugin/includes/user-detail.php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
require_once plugin_dir_path(__DIR__) . 'includes/database-functions.php';

// Function to render the user detail page
function kwetupizza_render_user_detail() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

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
                    <th>Actions</th>
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
                            <td>
                                <button class="button button-secondary view-order-items" data-order-id="<?php echo esc_attr($order->id); ?>">View Items</button>
                            </td>
                        </tr>
                        <?php
                    endforeach;
                else:
                    ?>
                    <tr>
                        <td colspan="7">No orders found for this user.</td>
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

        <!-- View Order Items Modal -->
        <div id="order-items-modal" class="kwetupizza-modal">
            <div class="kwetupizza-modal-content">
                <span class="close-modal">&times;</span>
                <h2>Order Items for Order ID: <span id="order-items-order-id"></span></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="order-items-table-body">
                        <!-- Order items will be loaded here via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>

        <style>
            /* Modal styles */
            .kwetupizza-modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0,0,0,0.5);
            }

            .kwetupizza-modal-content {
                background-color: #fff;
                margin: 5% auto;
                padding: 20px;
                border-radius: 8px;
                width: 80%;
                max-width: 800px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            }

            .close-modal {
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }

            .close-modal:hover,
            .close-modal:focus {
                color: red;
            }

            input[type="text"], input[type="email"], select, textarea {
                width: 100%;
                padding: 10px;
                margin: 8px 0;
                box-sizing: border-box;
            }

            button {
                margin-top: 10px;
            }

            /* Responsive table */
            .wp-list-table {
                width: 100%;
                overflow-x: auto;
            }
        </style>

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

                // View Order Items button action
                $('.view-order-items').click(function() {
                    var orderId = $(this).data('order-id');
                    $('#order-items-order-id').text(orderId);
                    $('#order-items-table-body').empty(); // Clear previous data

                    // Fetch order items via Ajax
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'kwetupizza_get_order_items',
                            order_id: orderId
                        },
                        success: function(response) {
                            if (response.success) {
                                var items = response.data;
                                if (items.length > 0) {
                                    $.each(items, function(index, item) {
                                        var row = '<tr>' +
                                            '<td>' + item.product_name + '</td>' +
                                            '<td>' + item.quantity + '</td>' +
                                            '<td>' + item.price + ' TZS</td>' +
                                            '<td>' + item.total + ' TZS</td>' +
                                            '</tr>';
                                        $('#order-items-table-body').append(row);
                                    });
                                } else {
                                    $('#order-items-table-body').append('<tr><td colspan="4">No items found for this order.</td></tr>');
                                }
                            } else {
                                $('#order-items-table-body').append('<tr><td colspan="4">Error fetching order items.</td></tr>');
                            }
                            $('#order-items-modal').fadeIn(); // Show the modal
                        }
                    });
                });

                // Close modals when clicking the close button
                $('.close-modal').click(function() {
                    $('.kwetupizza-modal').fadeOut(); // Hide all modals
                });

                // Close modals when clicking outside the modal content
                $(window).click(function(event) {
                    if ($(event.target).hasClass('kwetupizza-modal')) {
                        $('.kwetupizza-modal').fadeOut(); // Hide modal when clicking outside of the content
                    }
                });
            });
        </script>
    </div>
    <?php
}

// AJAX Handler to Send Notification
function kwetupizza_send_user_notification() {
    // ... existing code ...
}
add_action('wp_ajax_kwetupizza_send_notification', 'kwetupizza_send_user_notification');

// AJAX handler to get order items for the modal
add_action('wp_ajax_kwetupizza_get_order_items', 'kwetupizza_get_order_items');

// Function to add User Detail page (hidden from main menu, accessed via links)
function kwetupizza_add_user_detail_menu() {
    add_submenu_page(
        'kwetupizza-dashboard', // Use the correct parent slug
        'User Detail',                       // Page title
        'User Detail',                       // Menu title (not shown)
        'manage_options',                    // Capability required
        'kwetupizza-user-detail',            // Menu slug
        'kwetupizza_render_user_detail'      // Callback function
    );
}
add_action('admin_menu', 'kwetupizza_add_user_detail_menu');

?>
