<?php
/**
 * License Management Page for KwetuPizza Plugin
 * 
 * Provides UI for activating, deactivating, and viewing license status
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Render the license management page
 */
function kwetupizza_render_license_page() {
    // Process form submission
    $message = '';
    $status_class = '';
    
    if (isset($_POST['kwetupizza_license_action'])) {
        $nonce = isset($_POST['kwetupizza_license_nonce']) ? $_POST['kwetupizza_license_nonce'] : '';
        
        if (wp_verify_nonce($nonce, 'kwetupizza_license_action')) {
            $action = sanitize_text_field($_POST['kwetupizza_license_action']);
            
            // Get license manager instance
            $license_manager = kwetupizza_license_manager();
            
            switch ($action) {
                case 'activate':
                    $license_key = isset($_POST['kwetupizza_license_key']) ? 
                        sanitize_text_field($_POST['kwetupizza_license_key']) : '';
                    
                    $result = $license_manager->activate_license($license_key);
                    
                    if (is_wp_error($result)) {
                        $message = $result->get_error_message();
                        $status_class = 'error';
                    } else {
                        $message = 'License activated successfully!';
                        $status_class = 'success';
                    }
                    break;
                
                case 'deactivate':
                    $result = $license_manager->deactivate_license();
                    
                    if ($result) {
                        $message = 'License deactivated successfully.';
                        $status_class = 'success';
                    } else {
                        $message = 'Failed to deactivate license. Please try again or contact support.';
                        $status_class = 'error';
                    }
                    break;
                
                case 'check':
                    $result = $license_manager->verify_license();
                    
                    if ($result) {
                        $message = 'License verified successfully.';
                        $status_class = 'success';
                    } else {
                        $message = 'License verification failed. Please check your license key.';
                        $status_class = 'warning';
                    }
                    break;
            }
        } else {
            $message = 'Security verification failed. Please try again.';
            $status_class = 'error';
        }
    }
    
    // Display error parameter from URL if present
    if (isset($_GET['error']) && $_GET['error'] === 'feature_not_available') {
        $message = 'This feature is not available with your current license plan. Please upgrade to access it.';
        $status_class = 'error';
    }
    
    // Get license data
    $license_manager = kwetupizza_license_manager();
    $license_key = $license_manager->get_license_key();
    $license_status = $license_manager->get_license_status();
    $license_data = $license_manager->get_license_data();
    $is_active = $license_manager->is_license_active();
    
    // Get license plans
    $license_plans = [
        'free' => [
            'name' => 'Free',
            'price' => '$0',
            'description' => 'Basic features with limited usage',
            'features' => [
                'Basic ordering via WhatsApp',
                'Up to 20 orders per month',
                'Basic menu management'
            ]
        ],
        'standard' => [
            'name' => 'Standard',
            'price' => '$49',
            'description' => 'Standard features for small businesses',
            'features' => [
                'All Free features',
                'Payment gateway integration',
                'SMS notifications',
                'Up to 200 orders per month',
                'Priority customer support'
            ]
        ],
        'professional' => [
            'name' => 'Professional',
            'price' => '$99',
            'description' => 'Advanced features for growing businesses',
            'features' => [
                'All Standard features',
                'Customer management',
                'Custom branding',
                'Analytics dashboard',
                'Up to 1,000 orders per month',
                'Email support'
            ]
        ],
        'business' => [
            'name' => 'Business',
            'price' => '$149',
            'description' => 'Enterprise-level features for larger operations',
            'features' => [
                'All Professional features',
                'Multi-location support',
                'Unlimited orders',
                'White-label options',
                'Priority support',
                'Monthly usage reports'
            ]
        ]
    ];
    
    // Get current plan from license data
    $current_plan = $license_data['plan'] ?? 'free';
    
    // Render the page
    ?>
    <div class="wrap kwetupizza-license-page">
        <h1>KwetuPizza License Management</h1>
        
        <?php if (!empty($message)): ?>
            <div class="notice notice-<?php echo esc_attr($status_class); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="kwetupizza-license-status-card">
            <h2>License Status</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">License Key</th>
                    <td>
                        <?php if (!empty($license_key)): ?>
                            <code><?php echo esc_html(substr($license_key, 0, 6) . '...' . substr($license_key, -4)); ?></code>
                        <?php else: ?>
                            <em>No license key</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Status</th>
                    <td>
                        <?php if ($is_active): ?>
                            <span class="kwetupizza-license-active">Active</span>
                        <?php else: ?>
                            <span class="kwetupizza-license-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Plan</th>
                    <td>
                        <?php echo esc_html($license_plans[$current_plan]['name'] ?? 'Unknown'); ?>
                    </td>
                </tr>
                <?php if (!empty($license_data['expiry'])): ?>
                <tr>
                    <th scope="row">Expiry Date</th>
                    <td>
                        <?php echo esc_html(date('F j, Y', strtotime($license_data['expiry']))); ?>
                        <?php 
                        $days_remaining = max(0, floor((strtotime($license_data['expiry']) - time()) / DAY_IN_SECONDS));
                        if ($days_remaining <= 30) {
                            echo ' <span class="kwetupizza-expiry-warning">(' . esc_html($days_remaining) . ' days remaining)</span>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($license_data['customer_name'])): ?>
                <tr>
                    <th scope="row">Registered To</th>
                    <td>
                        <?php echo esc_html($license_data['customer_name']); ?>
                        <?php if (!empty($license_data['customer_email'])): ?>
                            (<?php echo esc_html($license_data['customer_email']); ?>)
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($license_data['site_count']) && !empty($license_data['max_sites'])): ?>
                <tr>
                    <th scope="row">Site Activations</th>
                    <td>
                        <?php echo esc_html($license_data['site_count']); ?> of <?php echo esc_html($license_data['max_sites']); ?> sites
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th scope="row">Order Limit</th>
                    <td>
                        <?php 
                        $max_orders = $license_manager->get_max_orders();
                        echo ($max_orders < 0) ? 'Unlimited' : esc_html($max_orders) . ' orders per month';
                        ?>
                    </td>
                </tr>
            </table>
            
            <div class="kwetupizza-license-actions">
                <?php if ($is_active): ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('kwetupizza_license_action', 'kwetupizza_license_nonce'); ?>
                        <input type="hidden" name="kwetupizza_license_action" value="deactivate">
                        <input type="submit" class="button button-secondary" value="Deactivate License">
                    </form>
                    
                    <form method="post" action="" style="margin-left: 10px;">
                        <?php wp_nonce_field('kwetupizza_license_action', 'kwetupizza_license_nonce'); ?>
                        <input type="hidden" name="kwetupizza_license_action" value="check">
                        <input type="submit" class="button button-secondary" value="Check License">
                    </form>
                <?php else: ?>
                    <form method="post" action="">
                        <?php wp_nonce_field('kwetupizza_license_action', 'kwetupizza_license_nonce'); ?>
                        <input type="hidden" name="kwetupizza_license_action" value="activate">
                        <input type="text" name="kwetupizza_license_key" placeholder="Enter your license key" value="<?php echo esc_attr($license_key); ?>" class="regular-text">
                        <input type="submit" class="button button-primary" value="Activate License">
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="kwetupizza-plans-section">
            <h2>Upgrade Your License</h2>
            <p>Choose the best plan for your business needs. Upgrade to unlock additional features and higher limits.</p>
            
            <div class="kwetupizza-license-plans">
                <?php foreach ($license_plans as $plan_id => $plan): ?>
                    <div class="kwetupizza-license-plan<?php echo ($current_plan === $plan_id) ? ' current-plan' : ''; ?>">
                        <h3><?php echo esc_html($plan['name']); ?></h3>
                        <div class="plan-price"><?php echo esc_html($plan['price']); ?></div>
                        <div class="plan-description"><?php echo esc_html($plan['description']); ?></div>
                        
                        <ul class="plan-features">
                            <?php foreach ($plan['features'] as $feature): ?>
                                <li><?php echo esc_html($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <?php if ($current_plan === $plan_id): ?>
                            <a href="#" class="button button-primary disabled">Current Plan</a>
                        <?php elseif ($plan_id === 'free'): ?>
                            <a href="#" class="button button-secondary disabled">Free Plan</a>
                        <?php else: ?>
                            <a href="https://kwetupizza.com/pricing/?plan=<?php echo esc_attr($plan_id); ?>&utm_source=plugin&utm_medium=license_page" 
                               class="button button-primary" target="_blank">
                                <?php echo ($current_plan === 'free') ? 'Purchase' : 'Upgrade'; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
            .kwetupizza-license-status-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin-top: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .kwetupizza-license-active {
                color: #46b450;
                font-weight: bold;
            }
            .kwetupizza-license-inactive {
                color: #dc3232;
                font-weight: bold;
            }
            .kwetupizza-expiry-warning {
                color: #f56e28;
            }
            .kwetupizza-license-actions {
                margin-top: 20px;
                display: flex;
            }
            
            /* License Plans styling */
            .kwetupizza-plans-section {
                margin-top: 30px;
            }
            .kwetupizza-license-plans {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-top: 20px;
            }
            .kwetupizza-license-plan {
                flex: 1;
                min-width: 220px;
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                text-align: center;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .kwetupizza-license-plan:hover {
                transform: translateY(-5px);
                box-shadow: 0 5px 15px rgba(0,0,0,.08);
            }
            .kwetupizza-license-plan h3 {
                margin-top: 0;
            }
            .kwetupizza-license-plan .plan-price {
                font-size: 24px;
                font-weight: bold;
                margin: 10px 0;
            }
            .kwetupizza-license-plan .plan-description {
                color: #666;
                margin-bottom: 15px;
            }
            .kwetupizza-license-plan .plan-features {
                text-align: left;
                margin: 15px 0;
                padding-left: 20px;
                min-height: 150px;
            }
            .kwetupizza-license-plan .plan-features li {
                margin-bottom: 5px;
            }
            .kwetupizza-license-plan .button {
                display: block;
                width: 100%;
                text-align: center;
            }
            .kwetupizza-license-plan.current-plan {
                border: 2px solid #2271b1;
                position: relative;
            }
            .kwetupizza-license-plan.current-plan::after {
                content: "Current";
                position: absolute;
                top: -10px;
                right: -10px;
                background: #2271b1;
                color: white;
                padding: 5px 10px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: bold;
            }
        </style>
    </div>
    <?php
} 