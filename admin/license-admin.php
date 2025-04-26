<?php
/**
 * License Admin Page for KwetuPizza Plugin
 * 
 * Provides UI for license management
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * KwetuPizza License Admin class
 */
class KwetuPizza_License_Admin {
    /**
     * Instance of the class
     * 
     * @var KwetuPizza_License_Admin
     */
    private static $instance = null;
    
    /**
     * Get the singleton instance
     * 
     * @return KwetuPizza_License_Admin
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Register the license page
        add_action('admin_menu', array($this, 'register_license_page'));
        
        // Register AJAX handlers
        add_action('wp_ajax_kwetupizza_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_kwetupizza_deactivate_license', array($this, 'ajax_deactivate_license'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }
    
    /**
     * Register the license page
     */
    public function register_license_page() {
        add_submenu_page(
            'edit.php?post_type=kwetu_pizza',
            __('License', 'kwetu-pizza-plugin'),
            __('License', 'kwetu-pizza-plugin'),
            'manage_options',
            'kwetupizza-license',
            array($this, 'render_license_page')
        );
    }
    
    /**
     * Render the license page
     */
    public function render_license_page() {
        // Get license information
        $license_manager = kwetupizza_license_manager();
        $license_key = $license_manager->get_license_key();
        $license_data = $license_manager->get_license_data();
        $license_status = $license_manager->get_license_status_text();
        $is_active = $license_manager->is_license_active();
        
        // Determine license status CSS class
        $status_class = 'inactive';
        switch ($license_status) {
            case 'active':
                $status_class = 'active';
                break;
            case 'expired':
            case 'disabled':
                $status_class = 'error';
                break;
            default:
                $status_class = 'inactive';
        }
        
        // Check for expiry
        $expiry_date = '';
        $days_remaining = false;
        
        if ($is_active && isset($license_data['expiry'])) {
            $expiry_date = $license_manager->get_expiry_date();
            $days_remaining = $license_manager->get_days_until_expiry();
        }
        
        // Get the license plan
        $plan = $license_manager->get_license_plan();
        $plan_name = ucfirst($plan);
        ?>
        <div class="wrap kwetupizza-license-page">
            <h1><?php _e('KwetuPizza License Management', 'kwetu-pizza-plugin'); ?></h1>
            
            <div class="kwetupizza-license-wrapper">
                <div class="kwetupizza-license-info">
                    <h2><?php _e('License Information', 'kwetu-pizza-plugin'); ?></h2>
                    
                    <div class="kwetupizza-license-status <?php echo esc_attr($status_class); ?>">
                        <strong><?php _e('Status:', 'kwetu-pizza-plugin'); ?></strong> 
                        <span class="status-text"><?php echo ucfirst(esc_html($license_status)); ?></span>
                    </div>
                    
                    <?php if ($is_active) : ?>
                        <div class="kwetupizza-license-plan">
                            <strong><?php _e('Plan:', 'kwetu-pizza-plugin'); ?></strong> 
                            <span><?php echo esc_html($plan_name); ?></span>
                        </div>
                        
                        <?php if (!empty($expiry_date)) : ?>
                            <div class="kwetupizza-license-expiry">
                                <strong><?php _e('Expires:', 'kwetu-pizza-plugin'); ?></strong> 
                                <span><?php echo esc_html($expiry_date); ?></span>
                                
                                <?php if ($days_remaining !== false && $days_remaining <= 30) : ?>
                                    <span class="expiry-notice">
                                        <?php 
                                        if ($days_remaining <= 0) {
                                            _e('Expired', 'kwetu-pizza-plugin');
                                        } else {
                                            printf(
                                                _n(
                                                    '(Expires in %s day)', 
                                                    '(Expires in %s days)', 
                                                    $days_remaining, 
                                                    'kwetu-pizza-plugin'
                                                ), 
                                                number_format_i18n($days_remaining)
                                            );
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($license_data['customer_name']) && !empty($license_data['customer_name'])) : ?>
                            <div class="kwetupizza-license-customer">
                                <strong><?php _e('Customer:', 'kwetu-pizza-plugin'); ?></strong> 
                                <span><?php echo esc_html($license_data['customer_name']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($license_data['customer_email']) && !empty($license_data['customer_email'])) : ?>
                            <div class="kwetupizza-license-email">
                                <strong><?php _e('Email:', 'kwetu-pizza-plugin'); ?></strong> 
                                <span><?php echo esc_html($license_data['customer_email']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($license_data['site_count']) && isset($license_data['max_sites'])) : ?>
                            <div class="kwetupizza-license-sites">
                                <strong><?php _e('Sites:', 'kwetu-pizza-plugin'); ?></strong> 
                                <span>
                                    <?php 
                                    printf(
                                        __('%1$s of %2$s', 'kwetu-pizza-plugin'), 
                                        number_format_i18n($license_data['site_count']),
                                        $license_data['max_sites'] == -1 ? __('Unlimited', 'kwetu-pizza-plugin') : number_format_i18n($license_data['max_sites'])
                                    ); 
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="kwetupizza-license-form">
                    <h2><?php _e('Manage License', 'kwetu-pizza-plugin'); ?></h2>
                    
                    <form id="kwetupizza-license-form" method="post">
                        <?php wp_nonce_field('kwetupizza_license_nonce', 'license_nonce'); ?>
                        
                        <div class="form-group">
                            <label for="license_key"><?php _e('License Key:', 'kwetu-pizza-plugin'); ?></label>
                            <input type="text" id="license_key" name="license_key" 
                                value="<?php echo esc_attr($license_key); ?>" 
                                placeholder="<?php esc_attr_e('Enter your license key', 'kwetu-pizza-plugin'); ?>"
                                <?php echo $is_active ? 'readonly' : ''; ?>
                                class="regular-text">
                        </div>
                        
                        <div class="submit-buttons">
                            <?php if (!$is_active) : ?>
                                <button type="button" id="activate-license" class="button button-primary">
                                    <?php _e('Activate License', 'kwetu-pizza-plugin'); ?>
                                </button>
                            <?php else : ?>
                                <button type="button" id="deactivate-license" class="button">
                                    <?php _e('Deactivate License', 'kwetu-pizza-plugin'); ?>
                                </button>
                                
                                <?php if ($license_status === 'expired' || ($days_remaining !== false && $days_remaining <= 30)) : ?>
                                    <a href="https://kwetupizza.com/renew-license/?license_key=<?php echo esc_attr($license_key); ?>" 
                                        class="button button-primary" target="_blank">
                                        <?php _e('Renew License', 'kwetu-pizza-plugin'); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="kwetupizza-license-features">
                    <h2><?php _e('Available Features', 'kwetu-pizza-plugin'); ?></h2>
                    
                    <table class="widefat fixed" cellspacing="0">
                        <thead>
                            <tr>
                                <th><?php _e('Feature', 'kwetu-pizza-plugin'); ?></th>
                                <th class="feature-status"><?php _e('Status', 'kwetu-pizza-plugin'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?php _e('Menu Integration', 'kwetu-pizza-plugin'); ?></strong></td>
                                <td class="feature-status">
                                    <?php echo $license_manager->plan_has_feature('menu_integration') ? 
                                        '<span class="dashicons dashicons-yes"></span>' : 
                                        '<span class="dashicons dashicons-no"></span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('WhatsApp Integration', 'kwetu-pizza-plugin'); ?></strong></td>
                                <td class="feature-status">
                                    <?php echo $license_manager->plan_has_feature('whatsapp_integration') ? 
                                        '<span class="dashicons dashicons-yes"></span>' : 
                                        '<span class="dashicons dashicons-no"></span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Custom Integration', 'kwetu-pizza-plugin'); ?></strong></td>
                                <td class="feature-status">
                                    <?php echo $license_manager->plan_has_feature('custom_integration') ? 
                                        '<span class="dashicons dashicons-yes"></span>' : 
                                        '<span class="dashicons dashicons-no"></span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Analytics', 'kwetu-pizza-plugin'); ?></strong></td>
                                <td class="feature-status">
                                    <?php echo $license_manager->plan_has_feature('analytics') ? 
                                        '<span class="dashicons dashicons-yes"></span>' : 
                                        '<span class="dashicons dashicons-no"></span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Advanced Analytics', 'kwetu-pizza-plugin'); ?></strong></td>
                                <td class="feature-status">
                                    <?php echo $license_manager->plan_has_feature('advanced_analytics') ? 
                                        '<span class="dashicons dashicons-yes"></span>' : 
                                        '<span class="dashicons dashicons-no"></span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Multi-location Support', 'kwetu-pizza-plugin'); ?></strong></td>
                                <td class="feature-status">
                                    <?php echo $license_manager->plan_has_feature('multilocation') ? 
                                        '<span class="dashicons dashicons-yes"></span>' : 
                                        '<span class="dashicons dashicons-no"></span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('API Access', 'kwetu-pizza-plugin'); ?></strong></td>
                                <td class="feature-status">
                                    <?php echo $license_manager->plan_has_feature('api_access') ? 
                                        '<span class="dashicons dashicons-yes"></span>' : 
                                        '<span class="dashicons dashicons-no"></span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('White Label', 'kwetu-pizza-plugin'); ?></strong></td>
                                <td class="feature-status">
                                    <?php echo $license_manager->plan_has_feature('white_label') ? 
                                        '<span class="dashicons dashicons-yes"></span>' : 
                                        '<span class="dashicons dashicons-no"></span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Custom Development', 'kwetu-pizza-plugin'); ?></strong></td>
                                <td class="feature-status">
                                    <?php echo $license_manager->plan_has_feature('custom_development') ? 
                                        '<span class="dashicons dashicons-yes"></span>' : 
                                        '<span class="dashicons dashicons-no"></span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Priority Support', 'kwetu-pizza-plugin'); ?></strong></td>
                                <td class="feature-status">
                                    <?php echo $license_manager->plan_has_feature('priority_support') ? 
                                        '<span class="dashicons dashicons-yes"></span>' : 
                                        '<span class="dashicons dashicons-no"></span>'; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="kwetupizza-license-upgrade">
                    <h2><?php _e('Upgrade Your License', 'kwetu-pizza-plugin'); ?></h2>
                    
                    <p><?php _e('Unlock more features by upgrading your license plan.', 'kwetu-pizza-plugin'); ?></p>
                    
                    <a href="https://kwetupizza.com/pricing/" class="button button-primary" target="_blank">
                        <?php _e('View Pricing Plans', 'kwetu-pizza-plugin'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Activate license
            $('#activate-license').on('click', function(e) {
                e.preventDefault();
                
                var license_key = $('#license_key').val();
                
                if (!license_key) {
                    alert('<?php echo esc_js(__('Please enter a license key', 'kwetu-pizza-plugin')); ?>');
                    return;
                }
                
                $(this).prop('disabled', true).text('<?php echo esc_js(__('Activating...', 'kwetu-pizza-plugin')); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kwetupizza_activate_license',
                        license_key: license_key,
                        nonce: $('#license_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php echo esc_js(__('License activated successfully!', 'kwetu-pizza-plugin')); ?>');
                            location.reload();
                        } else {
                            alert(response.data);
                            $('#activate-license').prop('disabled', false).text('<?php echo esc_js(__('Activate License', 'kwetu-pizza-plugin')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('An error occurred. Please try again.', 'kwetu-pizza-plugin')); ?>');
                        $('#activate-license').prop('disabled', false).text('<?php echo esc_js(__('Activate License', 'kwetu-pizza-plugin')); ?>');
                    }
                });
            });
            
            // Deactivate license
            $('#deactivate-license').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('<?php echo esc_js(__('Are you sure you want to deactivate your license?', 'kwetu-pizza-plugin')); ?>')) {
                    return;
                }
                
                $(this).prop('disabled', true).text('<?php echo esc_js(__('Deactivating...', 'kwetu-pizza-plugin')); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kwetupizza_deactivate_license',
                        nonce: $('#license_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php echo esc_js(__('License deactivated successfully!', 'kwetu-pizza-plugin')); ?>');
                            location.reload();
                        } else {
                            alert(response.data);
                            $('#deactivate-license').prop('disabled', false).text('<?php echo esc_js(__('Deactivate License', 'kwetu-pizza-plugin')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('An error occurred. Please try again.', 'kwetu-pizza-plugin')); ?>');
                        $('#deactivate-license').prop('disabled', false).text('<?php echo esc_js(__('Deactivate License', 'kwetu-pizza-plugin')); ?>');
                    }
                });
            });
        });
        </script>
        
        <style type="text/css">
        .kwetupizza-license-wrapper {
            max-width: 1200px;
            margin-top: 20px;
        }
        .kwetupizza-license-info,
        .kwetupizza-license-form,
        .kwetupizza-license-features,
        .kwetupizza-license-upgrade {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 15px;
            margin-bottom: 20px;
        }
        .kwetupizza-license-info > div,
        .kwetupizza-license-form .form-group {
            margin-bottom: 10px;
        }
        .kwetupizza-license-status.active {
            color: green;
        }
        .kwetupizza-license-status.inactive {
            color: orange;
        }
        .kwetupizza-license-status.error {
            color: red;
        }
        .kwetupizza-license-form input[type="text"] {
            width: 100%;
            max-width: 400px;
        }
        .submit-buttons {
            margin-top: 15px;
        }
        .feature-status {
            width: 100px;
            text-align: center;
        }
        .feature-status .dashicons-yes {
            color: green;
        }
        .feature-status .dashicons-no {
            color: #ccc;
        }
        .expiry-notice {
            color: red;
            margin-left: 5px;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for license activation
     */
    public function ajax_activate_license() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kwetupizza_license_nonce')) {
            wp_send_json_error(__('Security check failed.', 'kwetu-pizza-plugin'));
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'kwetu-pizza-plugin'));
        }
        
        // Get license key
        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
        
        if (empty($license_key)) {
            wp_send_json_error(__('Please enter a license key.', 'kwetu-pizza-plugin'));
        }
        
        // Activate license
        $result = kwetupizza_license_manager()->activate_license($license_key);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX handler for license deactivation
     */
    public function ajax_deactivate_license() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kwetupizza_license_nonce')) {
            wp_send_json_error(__('Security check failed.', 'kwetu-pizza-plugin'));
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'kwetu-pizza-plugin'));
        }
        
        // Deactivate license
        $result = kwetupizza_license_manager()->deactivate_license();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success();
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Only show on plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'kwetu_pizza') === false) {
            return;
        }
        
        // Don't show on license page
        if (isset($_GET['page']) && $_GET['page'] === 'kwetupizza-license') {
            return;
        }
        
        $license_manager = kwetupizza_license_manager();
        $is_active = $license_manager->is_license_active();
        $days_remaining = $license_manager->get_days_until_expiry();
        
        // Show notice for inactive license
        if (!$is_active) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php _e('KwetuPizza License Not Active', 'kwetu-pizza-plugin'); ?></strong>
                    <br>
                    <?php _e('Some features may be limited. Please activate your license for full functionality.', 'kwetu-pizza-plugin'); ?>
                    <a href="<?php echo admin_url('edit.php?post_type=kwetu_pizza&page=kwetupizza-license'); ?>" class="button button-small">
                        <?php _e('Activate License', 'kwetu-pizza-plugin'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
        // Show notice for expiring license
        elseif ($days_remaining !== false && $days_remaining <= 30 && $days_remaining > 0) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php _e('KwetuPizza License Expiring Soon', 'kwetu-pizza-plugin'); ?></strong>
                    <br>
                    <?php 
                    printf(
                        _n(
                            'Your license will expire in %s day. Please renew your license to continue receiving updates and support.', 
                            'Your license will expire in %s days. Please renew your license to continue receiving updates and support.',
                            $days_remaining,
                            'kwetu-pizza-plugin'
                        ),
                        number_format_i18n($days_remaining)
                    ); 
                    ?>
                    <a href="<?php echo admin_url('edit.php?post_type=kwetu_pizza&page=kwetupizza-license'); ?>" class="button button-small">
                        <?php _e('View License Details', 'kwetu-pizza-plugin'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
        // Show notice for expired license
        elseif ($license_manager->is_license_expired()) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php _e('KwetuPizza License Expired', 'kwetu-pizza-plugin'); ?></strong>
                    <br>
                    <?php _e('Your license has expired. Please renew your license to continue receiving updates and support.', 'kwetu-pizza-plugin'); ?>
                    <a href="<?php echo admin_url('edit.php?post_type=kwetu_pizza&page=kwetupizza-license'); ?>" class="button button-small">
                        <?php _e('View License Details', 'kwetu-pizza-plugin'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
}

/**
 * Get the license admin instance
 * 
 * @return KwetuPizza_License_Admin
 */
function kwetupizza_license_admin() {
    return KwetuPizza_License_Admin::instance();
}

// Initialize the license admin
kwetupizza_license_admin(); 