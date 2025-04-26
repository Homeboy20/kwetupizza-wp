<?php
include_once plugin_dir_path(__DIR__) . 'includes/common-functions.php';
include_once plugin_dir_path(__DIR__) . 'includes/database-functions.php';

// Enqueue necessary scripts and styles
function kwetupizza_enqueue_admin_assets() {
    wp_enqueue_script('jquery');
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js', array('jquery'), '4.5.2', true);
    wp_enqueue_style('kwetupizza-custom-css', plugin_dir_url(__FILE__) . 'assets/css/kwetupizza-custom.css'); // Custom styling for WordPress dashboard.

    // Localize script to pass nonces
    wp_localize_script('jquery', 'kwetupizza_ajax_obj', array(
        'create_order_nonce' => wp_create_nonce('kwetupizza_create_order_nonce'),
        'update_order_nonce' => wp_create_nonce('kwetupizza_update_order_nonce'),
        'delete_order_nonce' => wp_create_nonce('kwetupizza_delete_order_nonce'),
        'get_order_nonce'    => wp_create_nonce('kwetupizza_get_order_nonce'),
        'send_payment_nonce' => wp_create_nonce('kwetupizza_send_payment_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'kwetupizza_enqueue_admin_assets');

// Function to render the order management page
function kwetupizza_render_order_management() {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $products_table = $wpdb->prefix . 'kwetupizza_products';
    $users_table = $wpdb->prefix . 'kwetupizza_users';

    // Fetch all orders, products, and users
    $orders = $wpdb->get_results("SELECT * FROM $orders_table ORDER BY order_date DESC");
    $products = $wpdb->get_results("SELECT * FROM $products_table");
    $users = $wpdb->get_results("SELECT * FROM $users_table");

    if ($orders === false) {
        error_log("Error fetching orders: " . $wpdb->last_error);
    }

    ?>
    <div class="wrap">
        <h1 class="mb-4">Order Management</h1>
        <button class="btn btn-success mb-4" data-toggle="modal" data-target="#createOrderModal">Add New Order</button>
        <table class="table table-bordered table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Order Date</th>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Total (Tzs)</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo esc_html($order->id); ?></td>
                            <td><?php echo esc_html($order->order_date); ?></td>
                            <td><?php echo esc_html($order->customer_name); ?></td>
                            <td><?php echo esc_html($order->customer_phone); ?></td>
                            <td><?php echo esc_html($order->delivery_address); ?></td>
                            <td><?php echo esc_html(number_format($order->total, 2)); ?></td>
                            <td><?php echo esc_html(ucfirst($order->status)); ?></td>
                            <td>
                                <button class="btn btn-primary edit-order" data-id="<?php echo esc_attr($order->id); ?>" data-toggle="modal" data-target="#editOrderModal">Edit</button>
                                <button class="btn btn-danger delete-order" data-id="<?php echo esc_attr($order->id); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No orders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Create Order Modal -->
        <div class="modal fade" id="createOrderModal" tabindex="-1" role="dialog" aria-labelledby="createOrderModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="createOrderModalLabel" class="modal-title">Add New Order</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <form id="create-order-form">
                        <div class="modal-body">
                            <?php wp_nonce_field('kwetupizza_create_order_nonce', 'kwetupizza_create_order_nonce_field'); ?>
                            <label for="new_customer_phone">Customer Phone:</label>
                            <input type="text" name="customer_phone" id="new_customer_phone" class="form-control" required>

                            <label for="new_customer_name">Customer Name:</label>
                            <input type="text" name="customer_name" id="new_customer_name" class="form-control" required>

                            <label for="new_delivery_address">Address:</label>
                            <input type="text" name="delivery_address" id="new_delivery_address" class="form-control" required>

                            <label for="new_products">Add Products:</label>
                            <select id="new_products" class="form-control mb-2">
                                <option value="">Select a product</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo esc_attr($product->id); ?>" data-price="<?php echo esc_attr($product->price); ?>"><?php echo esc_html($product->product_name . " - " . number_format($product->price, 2) . " TZS"); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-secondary add-product-btn">Add Product</button>
                            <ul id="selected-products" class="list-group mt-2"></ul>
                            <div class="mt-3">
                                <label>Total (Tzs):</label>
                                <span id="order-total">0.00</span>
                            </div>

                            <label for="new_status" class="mt-3">Status:</label>
                            <select name="status" id="new_status" class="form-control">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Order</button>
                            <button type="button" class="btn btn-warning send-payment-request">Send Payment Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Order Modal -->
        <div class="modal fade" id="editOrderModal" tabindex="-1" role="dialog" aria-labelledby="editOrderModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="editOrderModalLabel" class="modal-title">Edit Order</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <form id="edit-order-form">
                        <div class="modal-body">
                            <?php wp_nonce_field('kwetupizza_update_order_nonce', 'kwetupizza_update_order_nonce_field'); ?>
                            <input type="hidden" name="order_id" id="edit_order_id">

                            <label for="edit_customer_phone">Customer Phone:</label>
                            <input type="text" name="customer_phone" id="edit_customer_phone" class="form-control" required>

                            <label for="edit_customer_name">Customer Name:</label>
                            <input type="text" name="customer_name" id="edit_customer_name" class="form-control" required>

                            <label for="edit_delivery_address">Address:</label>
                            <input type="text" name="delivery_address" id="edit_delivery_address" class="form-control" required>

                            <label for="edit_products">Add Products:</label>
                            <select id="edit_products" class="form-control mb-2">
                                <option value="">Select a product</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo esc_attr($product->id); ?>" data-price="<?php echo esc_attr($product->price); ?>"><?php echo esc_html($product->product_name . " - " . number_format($product->price, 2) . " TZS"); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-secondary add-edit-product-btn">Add Product</button>
                            <ul id="edit-selected-products" class="list-group mt-2"></ul>
                            <div class="mt-3">
                                <label>Total (Tzs):</label>
                                <span id="edit-order-total">0.00</span>
                            </div>

                            <label for="edit_status" class="mt-3">Status:</label>
                            <select name="status" id="edit_status" class="form-control">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- JavaScript to handle adding products, editing, deleting, and sending orders -->
        <script>
        jQuery(document).ready(function($) {
            var selectedProducts = [];

            // Add product to new order
            $('.add-product-btn').click(function() {
                var productSelect = $('#new_products');
                var productId = productSelect.val();
                var productName = productSelect.find('option:selected').text();
                var productPrice = parseFloat(productSelect.find('option:selected').data('price'));

                if (productId && productPrice) {
                    selectedProducts.push({ id: productId, name: productName, price: productPrice });
                    $('#selected-products').append('<li class="list-group-item d-flex justify-content-between align-items-center">' + productName + '<span class="remove-product text-danger" data-id="' + productId + '">&times;</span></li>');
                    updateTotal();
                }
            });

            // Remove product from new order
            $('#selected-products').on('click', '.remove-product', function() {
                var productId = $(this).data('id');
                selectedProducts = selectedProducts.filter(function(product) {
                    return product.id != productId;
                });
                $(this).parent().remove();
                updateTotal();
            });

            // Function to update total
            function updateTotal() {
                var total = selectedProducts.reduce(function(acc, product) {
                    return acc + product.price;
                }, 0);
                $('#order-total').text(total.toFixed(2));
            }

            // Handle create order form submission
            $('#create-order-form').submit(function(e) {
                e.preventDefault();
                var formData = $(this).serializeArray();
                var products = selectedProducts.map(function(product) {
                    return product.id;
                });

                formData.push({ name: 'products', value: products });
                formData.push({ name: 'action', value: 'kwetupizza_create_order' });
                formData.push({ name: 'kwetupizza_create_order_nonce_field', value: kwetupizza_ajax_obj.create_order_nonce });

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $('#createOrderModal').modal('hide');
                            alert('Order created successfully!');
                            location.reload(); // Reload to show the new order
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('AJAX Error: ' + error);
                    }
                });
            });

            // Handle send payment request button
            $('.send-payment-request').click(function() {
                var customerPhone = $('#new_customer_phone').val();
                var totalAmount = parseFloat($('#order-total').text());

                if (!customerPhone || isNaN(totalAmount) || totalAmount <= 0) {
                    alert('Please ensure customer phone and valid total amount are set.');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'kwetupizza_send_payment_request',
                        customer_phone: customerPhone,
                        amount: totalAmount,
                        security: kwetupizza_ajax_obj.send_payment_nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Payment request sent successfully.');
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('AJAX Error: ' + error);
                    }
                });
            });

            // Edit Order: Open modal and populate data
            $('.edit-order').click(function() {
                var orderId = $(this).data('id');

                // Fetch order details via AJAX
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'kwetupizza_get_order',
                        order_id: orderId,
                        security: kwetupizza_ajax_obj.get_order_nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var order = response.data;

                            // Populate the edit form
                            $('#edit_order_id').val(order.id);
                            $('#edit_customer_phone').val(order.customer_phone);
                            $('#edit_customer_name').val(order.customer_name);
                            $('#edit_delivery_address').val(order.delivery_address);
                            $('#edit_status').val(order.status);

                            // Reset selected products
                            $('#edit-selected-products').empty();
                            selectedEditProducts = [];
                            order.products.forEach(function(product) {
                                selectedEditProducts.push({ id: product.id, name: product.name, price: parseFloat(product.price) });
                                $('#edit-selected-products').append('<li class="list-group-item d-flex justify-content-between align-items-center">' + product.name + '<span class="remove-edit-product text-danger" data-id="' + product.id + '">&times;</span></li>');
                            });

                            // Update total
                            var total = order.products.reduce(function(acc, product) {
                                return acc + parseFloat(product.price);
                            }, 0);
                            $('#edit-order-total').text(total.toFixed(2));

                            // Show the edit modal
                            $('#editOrderModal').modal('show');
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('AJAX Error: ' + error);
                    }
                });
            });

            // Add product to edit order
            var selectedEditProducts = [];
            $('.add-edit-product-btn').click(function() {
                var productSelect = $('#edit_products');
                var productId = productSelect.val();
                var productName = productSelect.find('option:selected').text();
                var productPrice = parseFloat(productSelect.find('option:selected').data('price'));

                if (productId && productPrice) {
                    selectedEditProducts.push({ id: productId, name: productName, price: productPrice });
                    $('#edit-selected-products').append('<li class="list-group-item d-flex justify-content-between align-items-center">' + productName + '<span class="remove-edit-product text-danger" data-id="' + productId + '">&times;</span></li>');
                    updateEditTotal();
                }
            });

            // Remove product from edit order
            $('#edit-selected-products').on('click', '.remove-edit-product', function() {
                var productId = $(this).data('id');
                selectedEditProducts = selectedEditProducts.filter(function(product) {
                    return product.id != productId;
                });
                $(this).parent().remove();
                updateEditTotal();
            });

            // Function to update edit total
            function updateEditTotal() {
                var total = selectedEditProducts.reduce(function(acc, product) {
                    return acc + product.price;
                }, 0);
                $('#edit-order-total').text(total.toFixed(2));
            }

            // Handle edit order form submission
            $('#edit-order-form').submit(function(e) {
                e.preventDefault();
                var formData = $(this).serializeArray();
                var products = selectedEditProducts.map(function(product) {
                    return product.id;
                });

                formData.push({ name: 'products', value: products });
                formData.push({ name: 'action', value: 'kwetupizza_update_order' });
                formData.push({ name: 'kwetupizza_update_order_nonce_field', value: kwetupizza_ajax_obj.update_order_nonce });

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $('#editOrderModal').modal('hide');
                            alert('Order updated successfully!');
                            location.reload(); // Reload to show updated order
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('AJAX Error: ' + error);
                    }
                });
            });

            // Handle delete order button
            $('.delete-order').click(function() {
                var orderId = $(this).data('id');
                if (confirm('Are you sure you want to delete this order?')) {
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'kwetupizza_delete_order',
                            order_id: orderId,
                            security: kwetupizza_ajax_obj.delete_order_nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Order deleted successfully.');
                                location.reload(); // Reload to remove deleted order
                            } else {
                                alert('Error: ' + response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            alert('AJAX Error: ' + error);
                        }
                    });
                }
            });
        });
        </script>
    </div>

    <?php
}

// Register the Order Management page in the admin menu
function kwetupizza_add_order_management_menu() {
    // Add order management submenu
    add_submenu_page(
        'kwetupizza-dashboard',               // Parent slug
        'Order Management',                  // Page title
        'Order Management',                  // Menu title
        'manage_options',                    // Capability required
        'kwetupizza-order-management',       // Menu slug
        'kwetupizza_render_order_management' // Callback function
    );
}
add_action('admin_menu', 'kwetupizza_add_order_management_menu');

// AJAX handler to get an order
add_action('wp_ajax_kwetupizza_get_order', 'kwetupizza_get_order');

// AJAX Handler to Update Order
function kwetupizza_update_order() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized user.');
        wp_die();
    }

    check_ajax_referer('kwetupizza_update_order_nonce', 'security');

    global $wpdb;

    $order_id = intval($_POST['order_id']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $delivery_address = sanitize_text_field($_POST['delivery_address']);
    $status = sanitize_text_field($_POST['status']);
    $product_ids = isset($_POST['products']) ? array_map('intval', $_POST['products']) : array();

    // Validate inputs
    if (empty($customer_phone) || empty($customer_name) || empty($delivery_address) || empty($status)) {
        wp_send_json_error('All fields are required.');
        wp_die();
    }

    if (!preg_match('/^\+?\d{10,15}$/', $customer_phone)) {
        wp_send_json_error('Invalid phone number format.');
        wp_die();
    }

    // Update order details
    $updated = $wpdb->update(
        $wpdb->prefix . 'kwetupizza_orders',
        array(
            'customer_phone' => $customer_phone,
            'customer_name' => $customer_name,
            'delivery_address' => $delivery_address,
            'status' => $status
        ),
        array('id' => $order_id),
        array(
            '%s',
            '%s',
            '%s',
            '%s'
        ),
        array('%d')
    );

    if ($updated !== false) {
        // Update order items
        // First, delete existing items
        $wpdb->delete($wpdb->prefix . 'kwetupizza_order_items', array('order_id' => $order_id));

        // Then, insert new items
        foreach ($product_ids as $product_id) {
            $wpdb->insert(
                $wpdb->prefix . 'kwetupizza_order_items',
                array(
                    'order_id' => $order_id,
                    'product_id' => $product_id,
                    'quantity' => 1, // Adjust if quantity is variable
                    'price' => $wpdb->get_var($wpdb->prepare("SELECT price FROM {$wpdb->prefix}kwetupizza_products WHERE id = %d", $product_id))
                ),
                array(
                    '%d',
                    '%d',
                    '%d',
                    '%f'
                )
            );
        }

        // Fetch the updated order to compare status
        $updated_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d", $order_id));

        if ($updated_order) {
            // Send notification if status is 'completed' or 'cancelled'
            if (in_array($updated_order->status, array('completed', 'cancelled'))) {
                kwetupizza_send_order_status_change_notification($updated_order);
            }
        }

        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to update order.');
    }
}
add_action('wp_ajax_kwetupizza_update_order', 'kwetupizza_update_order');

// AJAX Handler to Delete Order
function kwetupizza_delete_order() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized user.');
        wp_die();
    }

    check_ajax_referer('kwetupizza_delete_order_nonce', 'security');

    global $wpdb;
    $order_id = intval($_POST['order_id']);

    // Validate order ID
    if (!$order_id) {
        wp_send_json_error('Invalid Order ID.');
    }

    // Delete order items first
    $deleted_items = $wpdb->delete($wpdb->prefix . 'kwetupizza_order_items', array('order_id' => $order_id));

    // Then delete the order
    $deleted_order = $wpdb->delete($wpdb->prefix . 'kwetupizza_orders', array('id' => $order_id));

    if ($deleted_order !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to delete order.');
    }
}
add_action('wp_ajax_kwetupizza_delete_order', 'kwetupizza_delete_order');

// Function to send notifications when order status changes
function kwetupizza_send_order_status_change_notification($order) {
    // Retrieve user details based on customer_phone
    global $wpdb;
    $users_table = $wpdb->prefix . 'kwetupizza_users';
    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE phone = %s", $order->customer_phone));

    if (!$user) {
        error_log("User not found for phone number: " . $order->customer_phone);
        return;
    }

    $message = "Hello " . $user->name . ", your order (ID: " . $order->id . ") has been marked as " . ucfirst($order->status) . ". Thank you for choosing KwetuPizza!";

    // Send WhatsApp message as text
    $whatsapp_sent = kwetupizza_send_whatsapp_message($user->phone, 'text', $message);

    // Send SMS message
    $sms_sent = kwetupizza_send_sms($user->phone, $message);

    if ($whatsapp_sent || $sms_sent) {
        // Optionally log successful notifications
        error_log("Notification sent for Order ID: " . $order->id);
    } else {
        error_log("Failed to send notification for Order ID: " . $order->id);
    }
}

// AJAX Handler to Send Payment Request
function kwetupizza_send_payment_request() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized user.');
        wp_die();
    }

    check_ajax_referer('kwetupizza_send_payment_nonce', 'security');

    global $wpdb;
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $amount = floatval($_POST['amount']);

    // Retrieve Flutterwave credentials from the database
    $flutterwave_secret_key = get_option('kwetupizza_flw_secret_key');
    if (empty($flutterwave_secret_key)) {
        error_log('Flutterwave Secret Key is not set in the database.');
        wp_send_json_error('Payment configuration is missing. Please contact the administrator.');
        return;
    }

    // Prepare the Flutterwave payment data
    $flutterwave_payment_data = array(
        'tx_ref' => 'kwetupizza_' . time(),
        'amount' => $amount,
        'currency' => 'TZS',
        'redirect_url' => 'https://your-redirect-url.com/', // Replace with your actual redirect URL
        'payment_options' => 'mobilemoneytanzania',
        'customer' => array(
            'phonenumber' => $customer_phone,
            'email' => 'customer@example.com', // Modify if you have the customer's email
            'name' => 'KwetuPizza Customer'
        ),
        'customizations' => array(
            'title' => 'KwetuPizza Order Payment',
            'description' => 'Payment for your recent KwetuPizza order.'
        )
    );

    // Send the payment request to Flutterwave
    $response = wp_remote_post('https://api.flutterwave.com/v3/payments', array(
        'method'    => 'POST',
        'headers'   => array(
            'Authorization' => 'Bearer ' . $flutterwave_secret_key,
            'Content-Type'  => 'application/json'
        ),
        'body'      => json_encode($flutterwave_payment_data),
        'timeout'   => 60,
    ));

    if (is_wp_error($response)) {
        error_log('Flutterwave Payment Request Error: ' . $response->get_error_message());
        wp_send_json_error('Failed to send payment request.');
    } else {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['status']) && $body['status'] === 'success') {
            wp_send_json_success();
        } else {
            error_log('Flutterwave API Error: ' . print_r($body['error'], true));
            wp_send_json_error('Failed to send payment request. Please check your payment credentials.');
        }
    }
}
add_action('wp_ajax_kwetupizza_send_payment_request', 'kwetupizza_send_payment_request');
?>
