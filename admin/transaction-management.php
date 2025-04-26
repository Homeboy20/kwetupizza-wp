<?php
// Function to render the transaction management page
function kwetupizza_render_transaction_management() {
    global $wpdb;
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';

    // Fetch all transactions
    $transactions = $wpdb->get_results("SELECT * FROM $transactions_table ORDER BY transaction_date DESC");

    ?>
    <div class="wrap">
        <h1>Transaction Management</h1>
        <table class="wp-list-table widefat fixed striped table-view-list posts">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Order ID</th>
                    <th>Transaction Date</th>
                    <th>Payment Method</th>
                    <th>Payment Status</th>
                    <th>Amount (Tzs)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions): ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo esc_html($transaction->id); ?></td>
                            <td><?php echo esc_html($transaction->order_id); ?></td>
                            <td><?php echo esc_html($transaction->transaction_date); ?></td>
                            <td><?php echo esc_html($transaction->payment_method); ?></td>
                            <td><?php echo esc_html($transaction->payment_status); ?></td>
                            <td><?php echo esc_html(number_format($transaction->amount, 2)); ?></td>
                            <td>
                                <button class="button edit-transaction" data-id="<?php echo esc_attr($transaction->id); ?>">Edit</button>
                                <button class="button delete-transaction" data-id="<?php echo esc_attr($transaction->id); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No transactions found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Edit Transaction Modal -->
        <div id="edit-transaction-modal" style="display: none;">
            <h2>Edit Transaction</h2>
            <form id="edit-transaction-form">
                <input type="hidden" name="transaction_id" id="transaction_id">
                <label for="edit_order_id">Order ID:</label>
                <input type="text" name="order_id" id="edit_order_id" readonly><br>
                <label for="edit_payment_method">Payment Method:</label>
                <input type="text" name="payment_method" id="edit_payment_method"><br>
                <label for="edit_payment_status">Payment Status:</label>
                <select name="payment_status" id="edit_payment_status">
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="failed">Failed</option>
                </select><br>
                <label for="edit_amount">Amount (Tzs):</label>
                <input type="text" name="amount" id="edit_amount"><br>
                <button type="submit" class="button button-primary">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            // Edit Transaction button action
            $('.edit-transaction').click(function() {
                var transactionId = $(this).data('id');
                // Fetch transaction data via Ajax
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'kwetupizza_get_transaction',
                        transaction_id: transactionId
                    },
                    success: function(response) {
                        if (response.success) {
                            var transaction = response.data;
                            $('#transaction_id').val(transaction.id);
                            $('#edit_order_id').val(transaction.order_id);
                            $('#edit_payment_method').val(transaction.payment_method);
                            $('#edit_payment_status').val(transaction.payment_status);
                            $('#edit_amount').val(transaction.amount);
                            $('#edit-transaction-modal').show(); // Show the modal
                        }
                    }
                });
            });

            // Submit edited transaction
            $('#edit-transaction-form').submit(function(e) {
                e.preventDefault();
                var formData = $(this).serialize();
                // Save edited transaction via Ajax
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: formData + '&action=kwetupizza_update_transaction',
                    success: function(response) {
                        if (response.success) {
                            location.reload(); // Reload page to show updated data
                        } else {
                            alert('Failed to update transaction.');
                        }
                    }
                });
            });

            // Delete Transaction button action
            $('.delete-transaction').click(function() {
                var transactionId = $(this).data('id');
                if (confirm('Are you sure you want to delete this transaction?')) {
                    // Delete transaction via Ajax
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'kwetupizza_delete_transaction',
                            transaction_id: transactionId
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload(); // Reload page to remove deleted transaction
                            } else {
                                alert('Failed to delete transaction.');
                            }
                        }
                    });
                }
            });
        });
    </script>
    <?php
}

// Ajax handler to get transaction details
function kwetupizza_get_transaction() {
    global $wpdb;
    $transaction_id = intval($_POST['transaction_id']);
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';

    $transaction = $wpdb->get_row($wpdb->prepare("SELECT * FROM $transactions_table WHERE id = %d", $transaction_id));

    if ($transaction) {
        wp_send_json_success($transaction);
    } else {
        wp_send_json_error('Transaction not found.');
    }
}
add_action('wp_ajax_kwetupizza_get_transaction', 'kwetupizza_get_transaction');

// Ajax handler to update transaction
function kwetupizza_update_transaction() {
    global $wpdb;
    $transaction_id = intval($_POST['transaction_id']);
    $payment_method = sanitize_text_field($_POST['payment_method']);
    $payment_status = sanitize_text_field($_POST['payment_status']);
    $amount = floatval($_POST['amount']);

    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';

    $updated = $wpdb->update(
        $transactions_table,
        array(
            'payment_method' => $payment_method,
            'payment_status' => $payment_status,
            'amount' => $amount,
        ),
        array('id' => $transaction_id)
    );

    if ($updated !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to update transaction.');
    }
}
add_action('wp_ajax_kwetupizza_update_transaction', 'kwetupizza_update_transaction');

// Ajax handler to delete transaction
function kwetupizza_delete_transaction() {
    global $wpdb;
    $transaction_id = intval($_POST['transaction_id']);
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';

    $deleted = $wpdb->delete($transactions_table, array('id' => $transaction_id));

    if ($deleted !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to delete transaction.');
    }
}
add_action('wp_ajax_kwetupizza_delete_transaction', 'kwetupizza_delete_transaction');
?>
