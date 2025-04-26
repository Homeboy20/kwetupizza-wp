<?php
// Location: /wp-content/plugins/kwetu-pizza-plugin/includes/user-management.php

// Ensure this file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
require_once plugin_dir_path(__DIR__) . 'includes/database-functions.php';

// Function to render the user management page
function kwetupizza_render_user_management() {
    global $wpdb;
    $users_table = $wpdb->prefix . 'kwetupizza_users';

    // Fetch all users
    $users = $wpdb->get_results("SELECT * FROM $users_table ORDER BY role ASC");

    ?>
    <div class="wrap">
        <h1>User Management</h1>
        <button class="button button-primary add-user">Add New User</button>
        <table class="wp-list-table widefat fixed striped table-view-list posts">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo esc_html($user->id); ?></td>
                            <td><?php echo esc_html($user->name); ?></td>
                            <td><?php echo esc_html($user->email); ?></td>
                            <td><?php echo esc_html($user->phone); ?></td>
                            <td><?php echo esc_html(ucfirst($user->role)); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=kwetupizza-user-detail&user_id=' . esc_attr($user->id)); ?>" class="button button-secondary">View</a>
                                <button class="button button-secondary edit-user" data-id="<?php echo esc_attr($user->id); ?>">Edit</button>
                                <button class="button button-danger delete-user" data-id="<?php echo esc_attr($user->id); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Add/Edit User Modal -->
        <div id="user-modal" class="kwetupizza-modal">
            <div class="kwetupizza-modal-content">
                <span class="close-modal">&times;</span>
                <h2 id="modal-title">Add New User</h2>
                <div id="user-error-message" style="color: red; display: none;"></div>
                <form id="user-form">
                    <?php wp_nonce_field('kwetupizza_save_user_nonce', 'kwetupizza_save_user_nonce_field'); ?>
                    <input type="hidden" name="user_id" id="user_id">
                    <label for="name">Name:</label>
                    <input type="text" name="name" id="name" required><br>
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" required><br>
                    <label for="phone">Phone:</label>
                    <input type="text" name="phone" id="phone" required><br>
                    <label for="role">Role:</label>
                    <select name="role" id="role">
                        <option value="customer">Customer</option>
                        <option value="admin">Admin</option>
                        <option value="delivery_staff">Delivery Staff</option>
                    </select><br>
                    <button type="submit" class="button button-primary">Save User</button>
                </form>
            </div>
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
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 40%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .close-modal {
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close-modal:hover,
        .close-modal:focus {
            color: red;
            cursor: pointer;
        }

        input[type="text"], input[type="email"], select {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            box-sizing: border-box;
        }

        button {
            margin-top: 10px;
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            // Open modal for adding a new user
            $('.add-user').click(function() {
                $('#user-form')[0].reset();
                $('#user_id').val('');
                $('#modal-title').text('Add New User');
                $('#user-modal').fadeIn(); // Show the modal
                $('#user-error-message').hide(); // Hide error message if previously shown
            });

            // Close modal
            $('.close-modal').click(function() {
                $('#user-modal').fadeOut(); // Hide the modal
            });

            $(window).click(function(event) {
                if ($(event.target).hasClass('kwetupizza-modal')) {
                    $('#user-modal').fadeOut(); // Hide modal when clicking outside of the content
                }
            });

            // Edit user button action
            $('.edit-user').click(function() {
                var userId = $(this).data('id');
                // Fetch user data via Ajax
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'kwetupizza_get_user',
                        user_id: userId
                    },
                    success: function(response) {
                        if (response.success) {
                            var user = response.data;
                            $('#user_id').val(user.id);
                            $('#name').val(user.name);
                            $('#email').val(user.email);
                            $('#phone').val(user.phone);
                            $('#role').val(user.role);
                            $('#modal-title').text('Edit User');
                            $('#user-modal').fadeIn(); // Show the modal
                        } else {
                            $('#user-error-message').text('Error fetching user details').show();
                        }
                    }
                });
            });

            // Submit user form (Add/Edit)
            $('#user-form').submit(function(e) {
                e.preventDefault();
                var formData = $(this).serialize();
                // Save user via Ajax
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: formData + '&action=kwetupizza_save_user',
                    success: function(response) {
                        if (response.success) {
                            location.reload(); // Reload page to show updated data
                        } else {
                            $('#user-error-message').text(response.data).show();
                        }
                    }
                });
            });

            // Delete user button action
            $('.delete-user').click(function() {
                var userId = $(this).data('id');
                if (confirm('Are you sure you want to delete this user?')) {
                    // Delete user via Ajax
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'kwetupizza_delete_user',
                            user_id: userId
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload(); // Reload page to remove deleted user
                            } else {
                                alert('Error deleting user.');
                            }
                        }
                    });
                }
            });
        });
    </script>
    <?php
}

// Register AJAX handlers

// Fetch User Details (used for Edit)
function kwetupizza_get_user() {
    global $wpdb;
    $user_id = intval($_POST['user_id']);
    $users_table = $wpdb->prefix . 'kwetupizza_users';

    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $user_id));

    if ($user) {
        wp_send_json_success($user);
    } else {
        wp_send_json_error('User not found.');
    }
}
add_action('wp_ajax_kwetupizza_get_user', 'kwetupizza_get_user');

// Save User (Add/Edit)
function kwetupizza_save_user() {
    // Verify nonce for security
    if (
        !isset($_POST['kwetupizza_save_user_nonce_field']) ||
        !wp_verify_nonce($_POST['kwetupizza_save_user_nonce_field'], 'kwetupizza_save_user_nonce')
    ) {
        wp_send_json_error('Invalid nonce.');
        return;
    }

    global $wpdb;
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $role = sanitize_text_field($_POST['role']);

    if (empty($name) || empty($email) || empty($phone)) {
        wp_send_json_error('All fields are required.');
        return;
    }

    if (!is_email($email)) {
        wp_send_json_error('Invalid email address.');
        return;
    }

    if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
        wp_send_json_error('Invalid phone number format.');
        return;
    }

    $users_table = $wpdb->prefix . 'kwetupizza_users';

    if ($user_id) {
        // Update existing user
        $updated = $wpdb->update(
            $users_table,
            array(
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role' => $role
            ),
            array('id' => $user_id)
        );

        if ($updated !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to update user.');
        }
    } else {
        // Insert new user
        $inserted = $wpdb->insert(
            $users_table,
            array(
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role' => $role
            )
        );

        if ($inserted !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to add user.');
        }
    }
}
add_action('wp_ajax_kwetupizza_save_user', 'kwetupizza_save_user');

// AJAX Handler to Delete User
add_action('wp_ajax_kwetupizza_delete_user', 'kwetupizza_delete_user');

// Function to add User Management to admin menu
function kwetupizza_add_admin_menu() {
    // Add user management submenu
    add_submenu_page(
        'kwetupizza-dashboard',               // Parent slug
        'User Management',                   // Page title
        'User Management',                   // Menu title
        'manage_options',                    // Capability required
        'kwetupizza-user-management',        // Menu slug
        'kwetupizza_render_user_management'  // Callback function
    );
}
add_action('admin_menu', 'kwetupizza_add_admin_menu');



?>
