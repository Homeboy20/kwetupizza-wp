<?php
/**
 * Migration script to encrypt existing credentials
 * 
 * This script encrypts existing API credentials and tokens in the WordPress database
 * using the new encryption functions.
 * 
 * Usage: 
 * 1. Visit WP Admin > KwetuPizza > Settings
 * 2. Click on the "Encrypt Existing Credentials" button
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include common functions for encryption/decryption 
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/common-functions.php';

// Register the migration hook
add_action('admin_init', 'kwetupizza_register_migration_hook');

/**
 * Register the AJAX action for credential migration
 */
function kwetupizza_register_migration_hook() {
    add_action('wp_ajax_kwetupizza_encrypt_credentials', 'kwetupizza_encrypt_existing_credentials');
}

/**
 * AJAX callback to encrypt existing credentials
 */
function kwetupizza_encrypt_existing_credentials() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kwetupizza_encrypt_credentials_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }

    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    // List of sensitive options to encrypt
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

    $migrated = [];
    $skipped = [];

    foreach ($sensitive_options as $option_name) {
        // Get the current plaintext value
        $plaintext_value = get_option($option_name, '');
        
        if (empty($plaintext_value)) {
            $skipped[] = $option_name;
            continue;
        }

        // Check if it's already encrypted
        try {
            // If decryption works, it's likely already encrypted
            $decrypted = kwetupizza_decrypt_data($plaintext_value);
            if (!empty($decrypted)) {
                $skipped[] = $option_name . ' (already encrypted)';
                continue;
            }
        } catch (Exception $e) {
            // Not encrypted or invalid format, proceed with encryption
        }

        // Encrypt the value
        $encrypted_value = kwetupizza_encrypt_data($plaintext_value);
        
        // Update the option with the encrypted value
        update_option($option_name, $encrypted_value);
        
        $migrated[] = $option_name;
    }

    // Send response
    wp_send_json_success([
        'message' => 'Credential encryption completed',
        'migrated' => $migrated,
        'skipped' => $skipped
    ]);
}

/**
 * Add migration button to settings page
 */
add_action('kwetupizza_after_settings_form', 'kwetupizza_add_migration_button');
function kwetupizza_add_migration_button() {
    ?>
    <div class="kwetu-migration-section" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
        <h3>Security Migration</h3>
        <p>Encrypt existing API credentials and tokens in the database for improved security.</p>
        <button id="kwetu-encrypt-credentials" class="button button-primary">Encrypt Existing Credentials</button>
        <span id="kwetu-migration-status" style="margin-left: 10px; display: none;"></span>
        
        <script>
        jQuery(document).ready(function($) {
            $('#kwetu-encrypt-credentials').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Are you sure you want to encrypt all stored credentials? This process cannot be reversed.')) {
                    return;
                }
                
                const $button = $(this);
                const $status = $('#kwetu-migration-status');
                
                $button.prop('disabled', true);
                $status.text('Processing...').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kwetupizza_encrypt_credentials',
                        nonce: '<?php echo wp_create_nonce('kwetupizza_encrypt_credentials_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let statusMsg = response.data.message + '<br>';
                            if (response.data.migrated.length > 0) {
                                statusMsg += 'Encrypted: ' + response.data.migrated.join(', ') + '<br>';
                            }
                            if (response.data.skipped.length > 0) {
                                statusMsg += 'Skipped: ' + response.data.skipped.join(', ');
                            }
                            $status.html(statusMsg).css('color', 'green');
                        } else {
                            $status.text('Error: ' + response.data).css('color', 'red');
                        }
                    },
                    error: function() {
                        $status.text('Server error occurred').css('color', 'red');
                    },
                    complete: function() {
                        setTimeout(function() {
                            $button.prop('disabled', false);
                        }, 2000);
                    }
                });
            });
        });
        </script>
    </div>
    <?php
} 