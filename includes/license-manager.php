<?php
/**
 * License Manager for KwetuPizza Plugin
 * 
 * Handles license verification, activation, deactivation and storage
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * KwetuPizza License Manager class
 */
class KwetuPizza_License_Manager {
    /**
     * Instance of the class
     * 
     * @var KwetuPizza_License_Manager
     */
    private static $instance = null;
    
    /**
     * License key option name
     */
    const LICENSE_KEY_OPTION = 'kwetupizza_license_key';
    
    /**
     * License data option name
     */
    const LICENSE_DATA_OPTION = 'kwetupizza_license_data';
    
    /**
     * License check transient name
     */
    const LICENSE_CHECK_TRANSIENT = 'kwetupizza_license_check';
    
    /**
     * Get the singleton instance
     * 
     * @return KwetuPizza_License_Manager
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
        // Schedule daily license check
        if (!wp_next_scheduled('kwetupizza_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'kwetupizza_daily_license_check');
        }
        
        // Hook into the scheduled event
        add_action('kwetupizza_daily_license_check', array($this, 'scheduled_license_check'));
        
        // Check license on plugin activation
        register_activation_hook(KWETUPIZZA_PLUGIN_FILE, array($this, 'check_license_on_activation'));
        
        // Check the license on admin init (but not too frequently)
        add_action('admin_init', array($this, 'maybe_check_license'));
    }
    
    /**
     * Check license on plugin activation
     */
    public function check_license_on_activation() {
        // If we have a license key, verify it
        if ($this->get_license_key()) {
            $this->verify_license();
        }
    }
    
    /**
     * Check the license if it hasn't been checked recently
     */
    public function maybe_check_license() {
        // Don't check if we're not on the admin pages or doing AJAX
        if (wp_doing_ajax() || !is_admin()) {
            return;
        }
        
        // Check if we've already checked the license recently
        if (get_transient(self::LICENSE_CHECK_TRANSIENT)) {
            return;
        }
        
        // If we have a license key, verify it
        if ($this->get_license_key()) {
            $this->verify_license();
            
            // Set a transient so we don't check again for a while (6 hours)
            set_transient(self::LICENSE_CHECK_TRANSIENT, 1, 6 * HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Scheduled license check
     */
    public function scheduled_license_check() {
        // If we have a license key, verify it
        if ($this->get_license_key()) {
            $this->verify_license();
        }
    }
    
    /**
     * Get the license key
     * 
     * @return string|bool The license key or false if not set
     */
    public function get_license_key() {
        return get_option(self::LICENSE_KEY_OPTION, false);
    }
    
    /**
     * Get license data
     * 
     * @return array License data
     */
    public function get_license_data() {
        $default_data = array(
            'status' => 'inactive',
            'plan' => 'free',
            'last_check' => 0,
        );
        
        return get_option(self::LICENSE_DATA_OPTION, $default_data);
    }
    
    /**
     * Check if the license is active
     * 
     * @return bool
     */
    public function is_license_active() {
        $license_data = $this->get_license_data();
        return isset($license_data['status']) && $license_data['status'] === 'active';
    }
    
    /**
     * Check if the license is expired
     * 
     * @return bool
     */
    public function is_license_expired() {
        $license_data = $this->get_license_data();
        
        // If no expiry is set or status is not active, it's not expired
        if (!isset($license_data['status']) || $license_data['status'] !== 'active') {
            return false;
        }
        
        if (!isset($license_data['expiry'])) {
            return false;
        }
        
        // Check if the license is expired
        $expiry_timestamp = strtotime($license_data['expiry']);
        $current_timestamp = current_time('timestamp');
        
        return $expiry_timestamp <= $current_timestamp;
    }
    
    /**
     * Get license plan
     * 
     * @return string The license plan (free, standard, professional, business)
     */
    public function get_license_plan() {
        $license_data = $this->get_license_data();
        return isset($license_data['plan']) ? $license_data['plan'] : 'free';
    }
    
    /**
     * Check if the current plan has a specific feature
     * 
     * @param string $feature The feature to check
     * @return bool
     */
    public function plan_has_feature($feature) {
        $plan = $this->get_license_plan();
        
        // All plans have basic features
        $basic_features = array(
            'menu_integration',
            'whatsapp_integration',
        );
        
        if (in_array($feature, $basic_features)) {
            return true;
        }
        
        // Standard plan features
        $standard_features = array(
            'custom_integration',
            'analytics',
        );
        
        if (in_array($feature, $standard_features) && in_array($plan, array('standard', 'professional', 'business'))) {
            return true;
        }
        
        // Professional plan features
        $professional_features = array(
            'advanced_analytics',
            'multilocation',
            'api_access',
        );
        
        if (in_array($feature, $professional_features) && in_array($plan, array('professional', 'business'))) {
            return true;
        }
        
        // Business plan features
        $business_features = array(
            'white_label',
            'custom_development',
            'priority_support',
        );
        
        if (in_array($feature, $business_features) && $plan === 'business') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Activate a license
     * 
     * @param string $license_key The license key to activate
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function activate_license($license_key) {
        if (empty($license_key)) {
            return new WP_Error('empty_license_key', 'Please enter a license key.');
        }
        
        // Save the license key first
        update_option(self::LICENSE_KEY_OPTION, trim($license_key));
        
        // For now, use a simple validation without API
        $valid_format = preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $license_key);
        
        if (!$valid_format) {
            return new WP_Error('invalid_license_format', 'Invalid license key format. Expected format: XXXX-XXXX-XXXX-XXXX');
        }
        
        // Store dummy license data for now
        $license_data = array(
            'status' => 'active',
            'plan' => 'professional',
            'last_check' => time(),
            'expiry' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'activations_left' => 5,
            'site_count' => 1,
            'max_sites' => 5
        );
        
        update_option(self::LICENSE_DATA_OPTION, $license_data);
        return true;
    }
    
    /**
     * Deactivate the license
     * 
     * @return bool True on success, false on failure
     */
    public function deactivate_license() {
        // Update the license data to inactive
        $license_data = $this->get_license_data();
        $license_data['status'] = 'inactive';
        
        update_option(self::LICENSE_DATA_OPTION, $license_data);
        delete_option(self::LICENSE_KEY_OPTION);
        
        return true;
    }
    
    /**
     * Verify the license with the API
     * 
     * @return bool True if license is valid, false otherwise
     */
    public function verify_license() {
        $license_key = $this->get_license_key();
        
        if (!$license_key) {
            // No license key, update the license data
            $this->update_license_data(array(
                'status' => 'inactive',
                'plan' => 'free',
                'last_check' => time()
            ));
            
            return false;
        }
        
        // For now, perform a simple local validation
        $valid_format = preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $license_key);
        
        if (!$valid_format) {
            // Invalid format, update the license data
            $this->update_license_data(array(
                'status' => 'invalid',
                'plan' => 'free',
                'last_check' => time()
            ));
            
            return false;
        }
        
        // Assume the license is active if it passes basic validation
        // In a real implementation, this would call the license server API
        $this->update_license_data(array(
            'status' => 'active',
            'plan' => 'professional',
            'last_check' => time(),
            'expiry' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'activations_left' => 5,
            'site_count' => 1,
            'max_sites' => 5
        ));
        
        return true;
    }
    
    /**
     * Update license data
     * 
     * @param array $data The license data to update
     */
    private function update_license_data($data) {
        $current_data = $this->get_license_data();
        $new_data = array_merge($current_data, $data);
        
        update_option(self::LICENSE_DATA_OPTION, $new_data);
    }
    
    /**
     * Get a human-readable status text
     * 
     * @return string
     */
    public function get_license_status_text() {
        $license_data = $this->get_license_data();
        $status = isset($license_data['status']) ? $license_data['status'] : 'inactive';
        
        switch ($status) {
            case 'active':
                return 'Active';
            case 'inactive':
                return 'Inactive';
            case 'expired':
                return 'Expired';
            case 'disabled':
                return 'Disabled';
            case 'invalid':
                return 'Invalid';
            default:
                return 'Unknown';
        }
    }
    
    /**
     * Get the expiry date in a formatted string
     * 
     * @param string $format The date format
     * @return string|bool The formatted date or false if no expiry
     */
    public function get_expiry_date($format = 'F j, Y') {
        $license_data = $this->get_license_data();
        
        if (!isset($license_data['expiry'])) {
            return false;
        }
        
        return date_i18n($format, strtotime($license_data['expiry']));
    }
    
    /**
     * Get the number of days until license expiry
     * 
     * @return int|bool The number of days or false if no expiry
     */
    public function get_days_until_expiry() {
        $license_data = $this->get_license_data();
        
        if (!isset($license_data['expiry'])) {
            return false;
        }
        
        $expiry_timestamp = strtotime($license_data['expiry']);
        $current_timestamp = current_time('timestamp');
        
        // If already expired, return 0
        if ($expiry_timestamp <= $current_timestamp) {
            return 0;
        }
        
        $seconds_remaining = $expiry_timestamp - $current_timestamp;
        return ceil($seconds_remaining / DAY_IN_SECONDS);
    }
}

/**
 * Main function to get the license manager instance
 * 
 * @return KwetuPizza_License_Manager
 */
function kwetupizza_license_manager() {
    return KwetuPizza_License_Manager::instance();
} 