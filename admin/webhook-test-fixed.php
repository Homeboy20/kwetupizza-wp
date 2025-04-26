<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add debugging to track function declarations
if (function_exists('kwetupizza_render_webhook_test_page')) {
    // Log the file path where the function was previously defined
    $debug_log_path = plugin_dir_path(dirname(__FILE__)) . 'debug-webhook-function.log';
    file_put_contents(
        $debug_log_path,
        "Function kwetupizza_render_webhook_test_page already defined before loading " . __FILE__ . "\n",
        FILE_APPEND
    );
}

// This is the callback function for the webhook test page
function kwetupizza_render_webhook_test_page() {
    // Get webhook URLs and verify token
    $whatsapp_webhook_url = home_url('/wp-json/kwetupizza/v1/whatsapp-webhook');
    $verify_token = get_option('kwetupizza_whatsapp_verify_token', '');
    
    ?>
    <div class="wrap">
        <h1>WhatsApp Webhook Tester</h1>
        
        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
            <h2>WhatsApp Webhook Configuration</h2>
            <p>Use these settings in your WhatsApp Business Platform dashboard:</p>
            
            <table class="form-table">
                <tr>
                    <th>Callback URL:</th>
                    <td>
                        <code><?php echo esc_url($whatsapp_webhook_url); ?></code>
                    </td>
                </tr>
                <tr>
                    <th>Verify Token:</th>
                    <td>
                        <code><?php echo esc_html($verify_token); ?></code>
                        <?php if (empty($verify_token)): ?>
                            <p class="description" style="color: red;">Warning: No verify token is set. Please set one in the Settings page.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <hr>
            
            <h3>Test Webhook Verification</h3>
            <p>This will simulate a verification request from WhatsApp to your webhook endpoint.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('test_webhook_action', 'webhook_test_nonce'); ?>
                <p>
                    <input type="submit" name="test_whatsapp_webhook" class="button button-primary" value="Test Webhook Verification">
                </p>
            </form>
        </div>
    </div>
    <?php
} 