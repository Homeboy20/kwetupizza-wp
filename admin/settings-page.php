<?php
// Add the settings page for KwetuPizza

// Include common functions which contain the security functions
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/common-functions.php';

function kwetupizza_render_settings_page() {
    // Get webhook URLs
    $whatsapp_webhook_url = home_url('/wp-json/kwetupizza/v1/whatsapp-webhook');
    $flutterwave_webhook_url = home_url('/wp-json/kwetupizza/v1/flutterwave-webhook');
    
    // Initialize update status
    $update_status = '';
    
    // Check if a specific section is being saved
    if (isset($_POST['kwetupizza_save_section']) && isset($_POST['kwetupizza_section'])) {
        $section = sanitize_text_field($_POST['kwetupizza_section']);
        $nonce = isset($_POST['kwetupizza_settings_nonce']) ? $_POST['kwetupizza_settings_nonce'] : '';
        
        if (wp_verify_nonce($nonce, 'kwetupizza_settings_action')) {
            // Process different section saves
            switch ($section) {
                case 'restaurant':
                    if (isset($_POST['kwetupizza_location'])) {
                        update_option('kwetupizza_location', sanitize_text_field($_POST['kwetupizza_location']));
                    }
                    if (isset($_POST['kwetupizza_currency'])) {
                        update_option('kwetupizza_currency', sanitize_text_field($_POST['kwetupizza_currency']));
                    }
                    if (isset($_POST['kwetupizza_delivery_area'])) {
                        update_option('kwetupizza_delivery_area', sanitize_textarea_field($_POST['kwetupizza_delivery_area']));
                    }
                    if (isset($_POST['kwetupizza_customer_support_number'])) {
                        update_option('kwetupizza_customer_support_number', sanitize_text_field($_POST['kwetupizza_customer_support_number']));
                    }
                    if (isset($_POST['kwetupizza_inactivity_timeout'])) {
                        update_option('kwetupizza_inactivity_timeout', intval($_POST['kwetupizza_inactivity_timeout']));
                    }
                    update_option('kwetupizza_enable_auto_reviews', isset($_POST['kwetupizza_enable_auto_reviews']) ? 1 : 0);
                    if (isset($_POST['kwetupizza_review_delay'])) {
                        update_option('kwetupizza_review_delay', intval($_POST['kwetupizza_review_delay']));
                    }
                    
                    // Save branding settings
                    if (isset($_POST['kwetupizza_business_name'])) {
                        update_option('kwetupizza_business_name', sanitize_text_field($_POST['kwetupizza_business_name']));
                    }
                    if (isset($_POST['kwetupizza_business_tagline'])) {
                        update_option('kwetupizza_business_tagline', sanitize_text_field($_POST['kwetupizza_business_tagline']));
                    }
                    if (isset($_POST['kwetupizza_business_logo'])) {
                        update_option('kwetupizza_business_logo', esc_url_raw($_POST['kwetupizza_business_logo']));
                    }
                    if (isset($_POST['kwetupizza_primary_color'])) {
                        update_option('kwetupizza_primary_color', sanitize_hex_color($_POST['kwetupizza_primary_color']));
                    }
                    if (isset($_POST['kwetupizza_secondary_color'])) {
                        update_option('kwetupizza_secondary_color', sanitize_hex_color($_POST['kwetupizza_secondary_color']));
                    }
                    
                    $update_status = 'Restaurant settings updated successfully!';
                    break;
                    
                case 'whatsapp':
                    if (isset($_POST['kwetupizza_whatsapp_token'])) {
                        kwetupizza_update_secure_option('kwetupizza_whatsapp_token', sanitize_text_field($_POST['kwetupizza_whatsapp_token']));
                    }
                    if (isset($_POST['kwetupizza_whatsapp_business_account_id'])) {
                        kwetupizza_update_secure_option('kwetupizza_whatsapp_business_account_id', sanitize_text_field($_POST['kwetupizza_whatsapp_business_account_id']));
                    }
                    if (isset($_POST['kwetupizza_whatsapp_phone_id'])) {
                        kwetupizza_update_secure_option('kwetupizza_whatsapp_phone_id', sanitize_text_field($_POST['kwetupizza_whatsapp_phone_id']));
                    }
                    if (isset($_POST['kwetupizza_whatsapp_verify_token'])) {
                        kwetupizza_update_secure_option('kwetupizza_whatsapp_verify_token', sanitize_text_field($_POST['kwetupizza_whatsapp_verify_token']));
                    }
                    if (isset($_POST['kwetupizza_whatsapp_app_secret'])) {
                        kwetupizza_update_secure_option('kwetupizza_whatsapp_app_secret', sanitize_text_field($_POST['kwetupizza_whatsapp_app_secret']));
                    }
                    if (isset($_POST['kwetupizza_whatsapp_api_version'])) {
                        update_option('kwetupizza_whatsapp_api_version', sanitize_text_field($_POST['kwetupizza_whatsapp_api_version']));
                    }
                    update_option('kwetupizza_enable_logging', isset($_POST['kwetupizza_enable_logging']) ? 1 : 0);
                    $update_status = 'WhatsApp settings updated successfully!';
                    break;
                    
                case 'payment':
                    if (isset($_POST['kwetupizza_flw_public_key'])) {
                        kwetupizza_update_secure_option('kwetupizza_flw_public_key', sanitize_text_field($_POST['kwetupizza_flw_public_key']));
                    }
                    if (isset($_POST['kwetupizza_flw_secret_key'])) {
                        kwetupizza_update_secure_option('kwetupizza_flw_secret_key', sanitize_text_field($_POST['kwetupizza_flw_secret_key']));
                    }
                    if (isset($_POST['kwetupizza_flw_encryption_key'])) {
                        kwetupizza_update_secure_option('kwetupizza_flw_encryption_key', sanitize_text_field($_POST['kwetupizza_flw_encryption_key']));
                    }
                    if (isset($_POST['kwetupizza_flw_webhook_secret'])) {
                        kwetupizza_update_secure_option('kwetupizza_flw_webhook_secret', sanitize_text_field($_POST['kwetupizza_flw_webhook_secret']));
                    }
                    $update_status = 'Payment settings updated successfully!';
                    break;
                    
                case 'sms':
                    if (isset($_POST['kwetupizza_nextsms_username'])) {
                        kwetupizza_update_secure_option('kwetupizza_nextsms_username', sanitize_text_field($_POST['kwetupizza_nextsms_username']));
                    }
                    if (isset($_POST['kwetupizza_nextsms_password'])) {
                        kwetupizza_update_secure_option('kwetupizza_nextsms_password', sanitize_text_field($_POST['kwetupizza_nextsms_password']));
                    }
                    if (isset($_POST['kwetupizza_nextsms_sender_id'])) {
                        kwetupizza_update_secure_option('kwetupizza_nextsms_sender_id', sanitize_text_field($_POST['kwetupizza_nextsms_sender_id']));
                    }
                    if (isset($_POST['kwetupizza_admin_sms_number'])) {
                        update_option('kwetupizza_admin_sms_number', sanitize_text_field($_POST['kwetupizza_admin_sms_number']));
                    }
                    $update_status = 'SMS settings updated successfully!';
                    break;
                    
                case 'monetization':
                    // Save Order Fee settings
                    update_option('kwetupizza_enable_order_fees', isset($_POST['kwetupizza_enable_order_fees']) ? 1 : 0);
                    if (isset($_POST['kwetupizza_fee_type'])) {
                        update_option('kwetupizza_fee_type', sanitize_text_field($_POST['kwetupizza_fee_type']));
                    }
                    if (isset($_POST['kwetupizza_fee_amount'])) {
                        update_option('kwetupizza_fee_amount', floatval($_POST['kwetupizza_fee_amount']));
                    }
                    if (isset($_POST['kwetupizza_fee_label'])) {
                        update_option('kwetupizza_fee_label', sanitize_text_field($_POST['kwetupizza_fee_label']));
                    }
                    
                    // Save Affiliate settings
                    update_option('kwetupizza_enable_affiliate', isset($_POST['kwetupizza_enable_affiliate']) ? 1 : 0);
                    if (isset($_POST['kwetupizza_affiliate_disclosure'])) {
                        update_option('kwetupizza_affiliate_disclosure', sanitize_textarea_field($_POST['kwetupizza_affiliate_disclosure']));
                    }
                    
                    // Save Premium Features settings
                    update_option('kwetupizza_enable_premium', isset($_POST['kwetupizza_enable_premium']) ? 1 : 0);
                    if (isset($_POST['kwetupizza_priority_fee'])) {
                        update_option('kwetupizza_priority_fee', floatval($_POST['kwetupizza_priority_fee']));
                    }
                    if (isset($_POST['kwetupizza_custom_pizza_fee'])) {
                        update_option('kwetupizza_custom_pizza_fee', floatval($_POST['kwetupizza_custom_pizza_fee']));
                    }
                    
                    $update_status = 'Monetization settings updated successfully!';
                    break;
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>KwetuPizza Plugin Settings</h1>
        
        <?php if (!empty($update_status)): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($update_status); ?></p>
            </div>
        <?php endif; ?>
        
    <style>
        /* Basic styling for two-column layout */
        .kwetu-settings-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .kwetu-settings-left, .kwetu-settings-right {
            width: 48%;
        }

        @media (max-width: 768px) {
            .kwetu-settings-left, .kwetu-settings-right {
                width: 100%;
            }
        }

        /* Styling for tabs in the right column */
        .kwetu-settings-right .nav-tabs {
            display: flex;
            border-bottom: 2px solid #ddd;
        }

        .kwetu-settings-right .nav-tabs li {
            margin-right: 10px;
            list-style: none;
        }

        .kwetu-settings-right .nav-tabs li a {
            display: block;
            padding: 10px;
            border: 1px solid #ddd;
            border-bottom: none;
            text-decoration: none;
        }

        .kwetu-settings-right .nav-tabs li.active a {
            background-color: #f1f1f1;
            border-color: #ddd;
            border-bottom: none;
        }

        .kwetu-settings-right .tab-content {
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: -1px;
        }

        /* Hide tab panes by default */
        .kwetu-settings-right .tab-pane {
            display: none;
        }

        /* Show active tab pane */
        .kwetu-settings-right .tab-pane.active {
            display: block;
        }
        
        /* Webhook URL display */
        .webhook-url {
            background: #f5f5f5;
            padding: 10px;
            border: 1px solid #ddd;
            margin: 10px 0;
            font-family: monospace;
            position: relative;
            border-radius: 3px;
        }
        
        .webhook-url .copy-btn {
            position: absolute;
            right: 10px;
            top: 8px;
        }
    </style>

    <div class="kwetu-settings-container">
        <!-- Left Column: Restaurant Configuration -->
        <div class="kwetu-settings-left">
            <h2>Restaurant Configurations</h2>
            <form method="post" action="">
                <?php wp_nonce_field('kwetupizza_settings_action', 'kwetupizza_settings_nonce'); ?>
                <input type="hidden" name="kwetupizza_section" value="restaurant">
                <table class="form-table">
                    <tr>
                        <th scope="row">Business Name</th>
                        <td><input type="text" name="kwetupizza_business_name" value="<?php echo esc_attr(get_option('kwetupizza_business_name', 'KwetuPizza')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Business Tagline</th>
                        <td><input type="text" name="kwetupizza_business_tagline" value="<?php echo esc_attr(get_option('kwetupizza_business_tagline', 'Delicious pizzas delivered to your door')); ?>" /></td>
                    </tr>
                    
                    <?php if (kwetupizza_is_feature_available('custom_branding')): ?>
                    <tr>
                        <th scope="row">Business Logo</th>
                        <td>
                            <div class="logo-preview-wrapper" style="margin-bottom: 10px;">
                                <?php 
                                $logo_url = get_option('kwetupizza_business_logo');
                                if (!empty($logo_url)) {
                                    echo '<img src="' . esc_url($logo_url) . '" style="max-width: 200px; max-height: 100px;" />';
                                }
                                ?>
                            </div>
                            <input type="text" name="kwetupizza_business_logo" id="kwetupizza_business_logo" value="<?php echo esc_attr(get_option('kwetupizza_business_logo')); ?>" class="regular-text">
                            <input type="button" name="upload-btn" id="upload-btn" class="button-secondary" value="Upload Logo">
                            <p class="description">Upload or provide URL for your business logo</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Brand Primary Color</th>
                        <td>
                            <input type="text" name="kwetupizza_primary_color" class="color-picker" value="<?php echo esc_attr(get_option('kwetupizza_primary_color', '#FF5A5F')); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Brand Secondary Color</th>
                        <td>
                            <input type="text" name="kwetupizza_secondary_color" class="color-picker" value="<?php echo esc_attr(get_option('kwetupizza_secondary_color', '#00A699')); ?>" />
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th scope="row">Custom Branding</th>
                        <td>
                            <div class="premium-feature-notice" style="background: #f8f8f8; padding: 15px; border-left: 4px solid #ffba00;">
                                <h4 style="margin-top: 0;">Premium Feature</h4>
                                <p>Custom branding including logo and colors is available with Professional and Business licenses.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <th scope="row">Restaurant Location</th>
                        <td><input type="text" name="kwetupizza_location" value="<?php echo esc_attr(get_option('kwetupizza_location')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Base Currency</th>
                        <td><input type="text" name="kwetupizza_currency" value="<?php echo esc_attr(get_option('kwetupizza_currency', 'TZS')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Delivery Area</th>
                        <td><textarea name="kwetupizza_delivery_area" rows="3" cols="50"><?php echo esc_textarea(get_option('kwetupizza_delivery_area')); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row">Customer Support Number</th>
                        <td>
                            <input type="text" name="kwetupizza_customer_support_number" value="<?php echo esc_attr(get_option('kwetupizza_customer_support_number')); ?>" />
                            <p class="description">Phone number for customer support, displayed in help messages.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Conversation Timeout</th>
                        <td>
                            <input type="number" name="kwetupizza_inactivity_timeout" value="<?php echo esc_attr(get_option('kwetupizza_inactivity_timeout', 3)); ?>" min="1" max="60" /> minutes
                            <p class="description">Time after which an inactive conversation will be reset.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enable Auto Reviews</th>
                        <td>
                            <input type="checkbox" name="kwetupizza_enable_auto_reviews" value="1" <?php checked(get_option('kwetupizza_enable_auto_reviews'), 1); ?> />
                            <p class="description">Automatically send review requests after order completion.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Review Request Delay</th>
                        <td>
                            <input type="number" name="kwetupizza_review_delay" value="<?php echo esc_attr(get_option('kwetupizza_review_delay', 1)); ?>" min="1" max="72" /> hours
                            <p class="description">Time to wait after order completion before sending review request.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="kwetupizza_save_section" class="button-primary" value="Save Restaurant Settings">
                </p>
            </form>
        </div>

        <!-- Right Column: Integrations with Tabs -->
        <div class="kwetu-settings-right">
            <h2>Integrations</h2>

            <ul class="nav-tabs">
                <li class="active"><a href="#tab2" data-tab="tab2">WhatsApp Provider</a></li>
                <li><a href="#tab3" data-tab="tab3">Payment Settings</a></li>
                <li><a href="#tab4" data-tab="tab4">SMS Settings</a></li>
                <li><a href="#tab5" data-tab="tab5">Admin Notifications</a></li>
                <li><a href="#tab6" data-tab="tab6">Monetization</a></li>
            </ul>

            <div class="tab-content">
                <!-- WhatsApp Cloud API Integration -->
                <div id="tab2" class="tab-pane active">
                    <h3>WhatsApp Cloud API Integration</h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('kwetupizza_settings_action', 'kwetupizza_settings_nonce'); ?>
                        <input type="hidden" name="kwetupizza_section" value="whatsapp">
                        
                        <h4>WhatsApp Webhook URL</h4>
                        <div class="webhook-url">
                            <?php echo esc_url($whatsapp_webhook_url); ?>
                            <button type="button" class="button copy-btn" data-clipboard-text="<?php echo esc_attr($whatsapp_webhook_url); ?>">Copy</button>
                        </div>
                        <p class="description">Configure this URL in your WhatsApp Business API dashboard to receive messages.</p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Access Token</th>
                                <td><input type="text" name="kwetupizza_whatsapp_token" value="<?php echo esc_attr(kwetupizza_get_secure_option('kwetupizza_whatsapp_token')); ?>" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row">Business Account ID</th>
                                <td>
                                    <input type="text" name="kwetupizza_whatsapp_business_account_id" value="<?php echo esc_attr(kwetupizza_get_secure_option('kwetupizza_whatsapp_business_account_id')); ?>" class="regular-text" />
                                    <p class="description">Your WhatsApp Business Account ID (used for modern API integration)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Phone ID</th>
                                <td>
                                    <input type="text" name="kwetupizza_whatsapp_phone_id" value="<?php echo esc_attr(kwetupizza_get_secure_option('kwetupizza_whatsapp_phone_id')); ?>" class="regular-text" />
                                    <p class="description">Legacy field - Business Account ID is recommended for new setups</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">App Secret</th>
                                <td>
                                    <input type="password" name="kwetupizza_whatsapp_app_secret" value="<?php echo esc_attr(kwetupizza_get_secure_option('kwetupizza_whatsapp_app_secret')); ?>" class="regular-text" />
                                    <p class="description">Your WhatsApp app secret used for signature verification</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Verify Token</th>
                                <td>
                                    <input type="text" name="kwetupizza_whatsapp_verify_token" value="<?php echo esc_attr(kwetupizza_get_secure_option('kwetupizza_whatsapp_verify_token')); ?>" class="regular-text" />
                                    <p class="description">This token must match the one you set in your WhatsApp Business API webhook configuration.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">API Version</th>
                                <td><input type="text" name="kwetupizza_whatsapp_api_version" value="<?php echo esc_attr(get_option('kwetupizza_whatsapp_api_version', 'v15.0')); ?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row">Enable Debug Logging</th>
                                <td>
                                    <input type="checkbox" name="kwetupizza_enable_logging" value="1" <?php checked(get_option('kwetupizza_enable_logging'), 1); ?> />
                                    <p class="description">Enable detailed logging for debugging.</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="kwetupizza_save_section" class="button-primary" value="Save WhatsApp Settings">
                        </p>
                    </form>
                    
                    <!-- WhatsApp Webhook Helper -->
                    <div class="whatsapp-webhook-helper">
                        <h4>WhatsApp Webhook Configuration Helper</h4>
                        <?php kwetupizza_render_whatsapp_webhook_helper(); ?>
                    </div>
                </div>

                <!-- Flutterwave Payment Integration -->
                <div id="tab3" class="tab-pane">
                    <h3>Flutterwave Payment Gateway</h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('kwetupizza_settings_action', 'kwetupizza_settings_nonce'); ?>
                        <input type="hidden" name="kwetupizza_section" value="payment">
                        
                        <h4>Flutterwave Webhook URL</h4>
                        <div class="webhook-url">
                            <?php echo esc_url($flutterwave_webhook_url); ?>
                            <button type="button" class="button copy-btn" data-clipboard-text="<?php echo esc_attr($flutterwave_webhook_url); ?>">Copy</button>
                        </div>
                        <p class="description">Set this URL in your Flutterwave dashboard to receive payment notifications.</p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Public Key</th>
                                <td><input type="text" name="kwetupizza_flw_public_key" value="<?php echo esc_attr(kwetupizza_get_secure_option('kwetupizza_flw_public_key')); ?>" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row">Secret Key</th>
                                <td><input type="text" name="kwetupizza_flw_secret_key" value="<?php echo esc_attr(kwetupizza_get_secure_option('kwetupizza_flw_secret_key')); ?>" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row">Encryption Key</th>
                                <td><input type="text" name="kwetupizza_flw_encryption_key" value="<?php echo esc_attr(kwetupizza_get_secure_option('kwetupizza_flw_encryption_key')); ?>" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row">Webhook Secret</th>
                                <td>
                                    <input type="text" name="kwetupizza_flw_webhook_secret" value="<?php echo esc_attr(kwetupizza_get_secure_option('kwetupizza_flw_webhook_secret')); ?>" class="regular-text" />
                                    <p class="description">Please ensure this matches the 'Secret Hash' set in your Flutterwave Dashboard under Webhook settings.</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="kwetupizza_save_section" class="button-primary" value="Save Payment Settings">
                        </p>
                    </form>
                </div>

                <!-- NextSMS Bulk SMS Integration -->
                <div id="tab4" class="tab-pane">
                    <h3>NextSMS Integration</h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('kwetupizza_settings_action', 'kwetupizza_settings_nonce'); ?>
                        <input type="hidden" name="kwetupizza_section" value="sms">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Username</th>
                                <td><input type="text" name="kwetupizza_nextsms_username" value="<?php echo esc_attr(kwetupizza_get_secure_option('kwetupizza_nextsms_username')); ?>" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row">Password</th>
                                <td><input type="password" name="kwetupizza_nextsms_password" value="<?php echo esc_attr(kwetupizza_get_secure_option('kwetupizza_nextsms_password')); ?>" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row">Sender ID</th>
                                <td>
                                    <input type="text" name="kwetupizza_nextsms_sender_id" value="<?php echo esc_attr(kwetupizza_get_secure_option('kwetupizza_nextsms_sender_id', 'KwetuPizza')); ?>" class="regular-text" />
                                    <p class="description">The sender name that will appear on SMS messages (up to 11 characters).</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Admin SMS Number</th>
                                <td>
                                    <input type="text" name="kwetupizza_admin_sms_number" value="<?php echo esc_attr(get_option('kwetupizza_admin_sms_number')); ?>" class="regular-text" />
                                    <p class="description">Phone number for admin notifications via SMS.</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="kwetupizza_save_section" class="button-primary" value="Save SMS Settings">
                        </p>
                    </form>
                    
                    <?php 
                    // Add hook for plugins to add content after the settings form
                    do_action('kwetupizza_after_settings_form'); 
                    ?>
                </div>
                
                <!-- Monetization Settings -->
                <div id="tab6" class="tab-pane">
                    <h3>Monetization Settings</h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('kwetupizza_settings_action', 'kwetupizza_settings_nonce'); ?>
                        <input type="hidden" name="kwetupizza_section" value="monetization">
                        
                        <h4>Order Fee Settings</h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Order Fees</th>
                                <td>
                                    <input type="checkbox" name="kwetupizza_enable_order_fees" value="1" <?php checked(get_option('kwetupizza_enable_order_fees'), 1); ?> />
                                    <p class="description">Enable charging a small fee on each order.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Fee Type</th>
                                <td>
                                    <select name="kwetupizza_fee_type">
                                        <option value="fixed" <?php selected(get_option('kwetupizza_fee_type'), 'fixed'); ?>>Fixed Fee</option>
                                        <option value="percentage" <?php selected(get_option('kwetupizza_fee_type'), 'percentage'); ?>>Percentage Fee</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Fee Amount</th>
                                <td>
                                    <input type="number" step="0.01" name="kwetupizza_fee_amount" value="<?php echo esc_attr(get_option('kwetupizza_fee_amount', '1.00')); ?>" min="0" />
                                    <p class="description">Amount for fixed fee (e.g., 1.00) or percentage (e.g., 5 for 5%)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Fee Label</th>
                                <td>
                                    <input type="text" name="kwetupizza_fee_label" value="<?php echo esc_attr(get_option('kwetupizza_fee_label', 'Service Fee')); ?>" class="regular-text" />
                                    <p class="description">Label displayed to customers for the fee</p>
                                </td>
                            </tr>
                        </table>
                        
                        <h4>Affiliate Marketing</h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Affiliate Links</th>
                                <td>
                                    <input type="checkbox" name="kwetupizza_enable_affiliate" value="1" <?php checked(get_option('kwetupizza_enable_affiliate'), 1); ?> />
                                    <p class="description">Enable affiliate links in customer communications</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Affiliate Disclosure Text</th>
                                <td>
                                    <textarea name="kwetupizza_affiliate_disclosure" rows="3" cols="50"><?php echo esc_textarea(get_option('kwetupizza_affiliate_disclosure', 'Some links may be affiliate links that provide us with a small commission at no cost to you.')); ?></textarea>
                                    <p class="description">Disclosure text shown with affiliate links</p>
                                </td>
                            </tr>
                        </table>
                        
                        <h4>Premium Features</h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Premium Features</th>
                                <td>
                                    <input type="checkbox" name="kwetupizza_enable_premium" value="1" <?php checked(get_option('kwetupizza_enable_premium'), 1); ?> />
                                    <p class="description">Offer premium features to customers for additional fees</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Priority Delivery Fee</th>
                                <td>
                                    <input type="number" step="0.01" name="kwetupizza_priority_fee" value="<?php echo esc_attr(get_option('kwetupizza_priority_fee', '5.00')); ?>" min="0" />
                                    <p class="description">Additional fee for priority delivery</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Custom Pizza Fee</th>
                                <td>
                                    <input type="number" step="0.01" name="kwetupizza_custom_pizza_fee" value="<?php echo esc_attr(get_option('kwetupizza_custom_pizza_fee', '3.00')); ?>" min="0" />
                                    <p class="description">Additional fee for custom pizza orders</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="kwetupizza_save_section" class="button-primary" value="Save Monetization Settings">
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        jQuery(document).ready(function($) {
            // Initialize WordPress color picker
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker();
            }
            
            // Initialize tabs
            $('.nav-tabs a').click(function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                // Hide all tab panes
                $('.tab-pane').removeClass('active');
                
                // Show the selected tab pane
                $(target).addClass('active');
                
                // Update active state on tabs
                $('.nav-tabs li').removeClass('active');
                $(this).parent().addClass('active');
            });
            
            // Set initial active tab
            $('.nav-tabs li:first-child a').click();
            
            // Handle logo upload
            $('#upload-btn').click(function(e) {
                e.preventDefault();
                var image = wp.media({
                    title: 'Upload Business Logo',
                    multiple: false
                }).open()
                .on('select', function() {
                    var uploaded_image = image.state().get('selection').first();
                    var image_url = uploaded_image.toJSON().url;
                    $('#kwetupizza_business_logo').val(image_url);
                    $('.logo-preview-wrapper').html('<img src="' + image_url + '" style="max-width: 200px; max-height: 100px;" />');
                });
            });
            
            // Copy webhook URL functionality
            $('.copy-btn').click(function() {
                var webhookUrl = $(this).closest('.webhook-url').find('code').text();
                navigator.clipboard.writeText(webhookUrl).then(function() {
                    alert('Webhook URL copied to clipboard!');
                });
            });
        });
    </script>
    
    <?php kwetupizza_add_health_check_button(); ?>
    <?php
}

// Register the settings (for backward compatibility)
function kwetupizza_register_settings() {
    // Register settings for schema
    register_setting('kwetupizza_settings_group', 'kwetupizza_location');
    register_setting('kwetupizza_settings_group', 'kwetupizza_currency');
    register_setting('kwetupizza_settings_group', 'kwetupizza_delivery_area');
    register_setting('kwetupizza_settings_group', 'kwetupizza_customer_support_number');
    register_setting('kwetupizza_settings_group', 'kwetupizza_inactivity_timeout');
    register_setting('kwetupizza_settings_group', 'kwetupizza_enable_auto_reviews');
    register_setting('kwetupizza_settings_group', 'kwetupizza_review_delay');

    // WhatsApp Cloud API settings
    register_setting('kwetupizza_settings_group', 'kwetupizza_whatsapp_token');
    register_setting('kwetupizza_settings_group', 'kwetupizza_whatsapp_phone_id');
    register_setting('kwetupizza_settings_group', 'kwetupizza_whatsapp_verify_token');
    register_setting('kwetupizza_settings_group', 'kwetupizza_whatsapp_api_version');
    register_setting('kwetupizza_settings_group', 'kwetupizza_enable_logging');

    // Flutterwave settings
    register_setting('kwetupizza_settings_group', 'kwetupizza_flw_public_key');
    register_setting('kwetupizza_settings_group', 'kwetupizza_flw_secret_key');
    register_setting('kwetupizza_settings_group', 'kwetupizza_flw_encryption_key');
    register_setting('kwetupizza_settings_group', 'kwetupizza_flw_webhook_secret');
    
    // NextSMS settings
    register_setting('kwetupizza_settings_group', 'kwetupizza_nextsms_username');
    register_setting('kwetupizza_settings_group', 'kwetupizza_nextsms_password');
    register_setting('kwetupizza_settings_group', 'kwetupizza_nextsms_sender_id');
    register_setting('kwetupizza_settings_group', 'kwetupizza_admin_sms_number');
    
    // Monetization settings
    register_setting('kwetupizza_settings_group', 'kwetupizza_enable_order_fees');
    register_setting('kwetupizza_settings_group', 'kwetupizza_fee_type');
    register_setting('kwetupizza_settings_group', 'kwetupizza_fee_amount');
    register_setting('kwetupizza_settings_group', 'kwetupizza_fee_label');
    register_setting('kwetupizza_settings_group', 'kwetupizza_enable_affiliate');
    register_setting('kwetupizza_settings_group', 'kwetupizza_affiliate_disclosure');
    register_setting('kwetupizza_settings_group', 'kwetupizza_enable_premium');
    register_setting('kwetupizza_settings_group', 'kwetupizza_priority_fee');
    register_setting('kwetupizza_settings_group', 'kwetupizza_custom_pizza_fee');
}
add_action('admin_init', 'kwetupizza_register_settings');

// Add function to verify encryption is working
function kwetupizza_encryption_health_check() {
    // Create a test string
    $test_string = 'encryption_test_' . time();
    
    // Try encrypting and decrypting it
    try {
        $encrypted = kwetupizza_encrypt_data($test_string);
        if (empty($encrypted)) {
            return [
                'status' => 'error',
                'message' => 'Encryption failed - empty result'
            ];
        }
        
        $decrypted = kwetupizza_decrypt_data($encrypted);
        if ($decrypted !== $test_string) {
            return [
                'status' => 'error',
                'message' => 'Decryption failed - result does not match original'
            ];
        }
        
        return [
            'status' => 'success',
            'message' => 'Encryption is working correctly'
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Exception: ' . $e->getMessage()
        ];
    }
}

// Add AJAX endpoint for encryption health check
add_action('wp_ajax_kwetupizza_encryption_health_check', 'kwetupizza_encryption_health_check_callback');

function kwetupizza_encryption_health_check_callback() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kwetupizza_encryption_health_check_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    // Check user capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $result = kwetupizza_encryption_health_check();
    
    if ($result['status'] === 'success') {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
}

// Add the health check button to the migration section
add_action('kwetupizza_after_settings_form', 'kwetupizza_add_health_check_button');

function kwetupizza_add_health_check_button() {
    ?>
    <div class="kwetu-health-check-section" style="margin-top: 20px; padding: 15px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;">
        <h3>Security Health Check</h3>
        <p>Verify that the encryption system is working correctly.</p>
        <button id="kwetu-encryption-health-check" class="button button-secondary">Run Encryption Test</button>
        <span id="kwetu-health-check-status" style="margin-left: 10px; display: none;"></span>
        
        <script>
        jQuery(document).ready(function($) {
            $('#kwetu-encryption-health-check').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const $status = $('#kwetu-health-check-status');
                
                $button.prop('disabled', true);
                $status.text('Testing...').show().css('color', '');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kwetupizza_encryption_health_check',
                        nonce: '<?php echo wp_create_nonce('kwetupizza_encryption_health_check_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.text('✅ ' + response.data).css('color', 'green');
                        } else {
                            $status.text('❌ ' + response.data).css('color', 'red');
                        }
                    },
                    error: function() {
                        $status.text('❌ Server error occurred').css('color', 'red');
                    },
                    complete: function() {
                        setTimeout(function() {
                            $button.prop('disabled', false);
                        }, 1000);
                    }
                });
            });
        });
        </script>
    </div>
    
    <div class="kwetu-webhook-token-section" style="margin-top: 20px; padding: 15px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 4px;">
        <h3>Webhook Security Token</h3>
        <p>This token is required when accessing credentials via the API. Keep it secret!</p>
        <div class="webhook-url">
            <?php echo esc_html(get_option('kwetupizza_webhook_security_token')); ?>
            <button type="button" class="button copy-btn" data-clipboard-text="<?php echo esc_attr(get_option('kwetupizza_webhook_security_token')); ?>">Copy</button>
        </div>
        <p class="description">Use this token when making API calls that require encrypted credentials.</p>
    </div>
    <?php
}

/**
 * Function to get decrypted credentials for specific webhook handlers and services
 * This is a secure way to provide services with the credentials they need
 * 
 * @param string $service_name Name of the service requesting credentials (whatsapp, flutterwave, etc.)
 * @param string $callback_type Type of callback (webhook, verification, etc.)
 * @param string $webhook_token Special token to verify the request is from a valid source
 * @return array Array of credentials for the requested service
 */
function kwetupizza_get_service_credentials($service_name, $callback_type = 'webhook', $webhook_token = '') {
    // Verify the webhook token matches our internal security token (if provided)
    if (!empty($webhook_token) && $webhook_token !== get_option('kwetupizza_webhook_security_token')) {
        return ['error' => 'Invalid security token'];
    }
    
    // Return credentials based on the service
    switch ($service_name) {
        case 'whatsapp':
            return [
                'token' => kwetupizza_get_secure_option('kwetupizza_whatsapp_token'),
                'business_account_id' => kwetupizza_get_secure_option('kwetupizza_whatsapp_business_account_id'),
                'phone_id' => kwetupizza_get_secure_option('kwetupizza_whatsapp_phone_id'),
                'app_secret' => kwetupizza_get_secure_option('kwetupizza_whatsapp_app_secret'),
                'verify_token' => kwetupizza_get_secure_option('kwetupizza_whatsapp_verify_token'),
                'api_version' => get_option('kwetupizza_whatsapp_api_version', 'v15.0')
            ];
            
        case 'flutterwave':
            return [
                'public_key' => kwetupizza_get_secure_option('kwetupizza_flw_public_key'),
                'secret_key' => kwetupizza_get_secure_option('kwetupizza_flw_secret_key'),
                'encryption_key' => kwetupizza_get_secure_option('kwetupizza_flw_encryption_key'),
                'webhook_secret' => kwetupizza_get_secure_option('kwetupizza_flw_webhook_secret')
            ];
            
        case 'nextsms':
            return [
                'username' => kwetupizza_get_secure_option('kwetupizza_nextsms_username'),
                'password' => kwetupizza_get_secure_option('kwetupizza_nextsms_password'),
                'sender_id' => kwetupizza_get_secure_option('kwetupizza_nextsms_sender_id', 'KwetuPizza')
            ];
            
        default:
            return ['error' => 'Unknown service'];
    }
}

// Generate a random webhook security token if not already set
function kwetupizza_maybe_generate_webhook_token() {
    $token = get_option('kwetupizza_webhook_security_token');
    if (empty($token)) {
        $token = wp_generate_password(32, true, true);
        update_option('kwetupizza_webhook_security_token', $token);
    }
    return $token;
}
add_action('admin_init', 'kwetupizza_maybe_generate_webhook_token');

/**
 * Show admin notices about credential encryption status
 */
function kwetupizza_admin_encryption_notice() {
    // Only show on KwetuPizza admin pages
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'kwetupizza') === false) {
        return;
    }
    
    // Don't show on dashboard since it already shows this info
    if ($screen->id === 'toplevel_page_kwetupizza-dashboard') {
        return;
    }
    
    // Check if credentials are encrypted
    $encryption_key_exists = !empty(get_option('kwetupizza_encryption_key'));
    $encryption_working = function_exists('kwetupizza_encrypt_data') && function_exists('kwetupizza_decrypt_data');
    
    if (!$encryption_key_exists || !$encryption_working) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>KwetuPizza Security Notice:</strong> 
                Your API credentials are not encrypted. 
                <a href="<?php echo admin_url('admin.php?page=kwetupizza-settings'); ?>">Go to settings</a> to secure your credentials.
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'kwetupizza_admin_encryption_notice');

// Add stub function for feature availability since license manager is removed
function kwetupizza_is_feature_available($feature) {
    // Always return true since we've removed the licensing system
    return true;
}

/**
 * Calculate order fee based on order total and fee settings
 * 
 * @param float $order_total The total order amount
 * @return float The calculated fee amount
 */
function kwetupizza_calculate_order_fee($order_total) {
    // Check if fees are enabled
    if (!get_option('kwetupizza_enable_order_fees', 0)) {
        return 0;
    }
    
    $fee_type = get_option('kwetupizza_fee_type', 'fixed');
    $fee_amount = get_option('kwetupizza_fee_amount', 1.00);
    
    if ($fee_type === 'fixed') {
        return (float)$fee_amount;
    } else {
        // Calculate percentage fee
        return $order_total * ((float)$fee_amount / 100);
    }
}

/**
 * Get monetization options for display in order forms
 * 
 * @return array Monetization options
 */
function kwetupizza_get_premium_options() {
    // Check if premium features are enabled
    if (!get_option('kwetupizza_enable_premium', 0)) {
        return [];
    }
    
    return [
        'priority_delivery' => [
            'label' => 'Priority Delivery',
            'description' => 'Get your order delivered faster',
            'fee' => get_option('kwetupizza_priority_fee', 5.00)
        ],
        'custom_pizza' => [
            'label' => 'Custom Pizza',
            'description' => 'Design your own pizza with custom toppings',
            'fee' => get_option('kwetupizza_custom_pizza_fee', 3.00)
        ]
    ];
}
?>
