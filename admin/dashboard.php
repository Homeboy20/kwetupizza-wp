<?php
//Dashboard Query Adjustments

// Include the encryption functions
require_once plugin_dir_path(dirname(__FILE__)) . 'admin/settings-page.php';

if (!function_exists('kwetupizza_render_dashboard')) {
    function kwetupizza_render_dashboard() {
        global $wpdb;

    // Updated query for top-selling products
    $top_selling_products = $wpdb->get_results(
        "SELECT p.product_name, COUNT(oi.id) as total_sales
        FROM {$wpdb->prefix}kwetupizza_order_items oi
        JOIN {$wpdb->prefix}kwetupizza_products p ON oi.product_id = p.id
        GROUP BY p.product_name
        ORDER BY total_sales DESC
        LIMIT 5"
    );

    // Updated query for payment provider analysis
    $payment_providers = $wpdb->get_results(
        "SELECT payment_provider, COUNT(*) as total_transactions
        FROM {$wpdb->prefix}kwetupizza_transactions
        GROUP BY payment_provider"
    );


        // Fetch order growth data from the database (grouped by month)
        $order_growth_data = $wpdb->get_results("
            SELECT MONTH(created_at) as month, COUNT(*) as total_orders
            FROM {$wpdb->prefix}kwetupizza_orders
            WHERE YEAR(created_at) = YEAR(CURDATE())
            GROUP BY MONTH(created_at)
        ");

        $order_months = [];
        $order_counts = [];
        foreach ($order_growth_data as $data) {
            $order_months[] = date('F', mktime(0, 0, 0, $data->month, 10)); // Convert month number to month name
            $order_counts[] = $data->total_orders;
        }

        // Fetch popular products data from the database
        $popular_products_data = $wpdb->get_results("
            SELECT p.product_name, COUNT(o.id) as total_sales
            FROM {$wpdb->prefix}kwetupizza_order_items o
            JOIN {$wpdb->prefix}kwetupizza_products p ON o.product_id = p.id
            GROUP BY p.product_name
            ORDER BY total_sales DESC
            LIMIT 5
        ");

        $popular_product_names = [];
        $popular_product_sales = [];
        foreach ($popular_products_data as $data) {
            $popular_product_names[] = $data->product_name;
            $popular_product_sales[] = $data->total_sales;
        }

        // Fetch transaction volume breakdown data from the database
        $transaction_volume_data = $wpdb->get_results("
            SELECT payment_method, COUNT(*) as total_transactions
            FROM {$wpdb->prefix}kwetupizza_transactions
            GROUP BY payment_method
        ");

        $transaction_labels = [];
        $transaction_counts = [];
        foreach ($transaction_volume_data as $data) {
            $transaction_labels[] = ucfirst($data->payment_method);
            $transaction_counts[] = $data->total_transactions;
        }

        // Check credential encryption status
        $credential_options = [
            'WhatsApp' => [
                'kwetupizza_whatsapp_token' => 'WhatsApp Token',
                'kwetupizza_whatsapp_business_account_id' => 'WhatsApp Business Account ID',
                'kwetupizza_whatsapp_phone_id' => 'WhatsApp Phone ID (Legacy)',
                'kwetupizza_whatsapp_app_secret' => 'WhatsApp App Secret',
                'kwetupizza_whatsapp_verify_token' => 'WhatsApp Verify Token',
            ],
            'Flutterwave' => [
                'kwetupizza_flw_public_key' => 'Public Key',
                'kwetupizza_flw_secret_key' => 'Secret Key',
                'kwetupizza_flw_encryption_key' => 'Encryption Key',
                'kwetupizza_flw_webhook_secret' => 'Webhook Secret',
            ],
            'NextSMS' => [
                'kwetupizza_nextsms_username' => 'Username',
                'kwetupizza_nextsms_password' => 'Password',
                'kwetupizza_nextsms_sender_id' => 'Sender ID',
            ]
        ];

        // Check if the encryption is working
        $encryption_working = function_exists('kwetupizza_encrypt_data') && function_exists('kwetupizza_decrypt_data');
        $encryption_key_exists = !empty(get_option('kwetupizza_encryption_key'));
        
        // Try a test encryption if functions exist
        $encryption_test_result = false;
        if ($encryption_working) {
            $test_string = 'test_encryption_' . time();
            $encrypted = kwetupizza_encrypt_data($test_string);
            $decrypted = kwetupizza_decrypt_data($encrypted);
            $encryption_test_result = ($decrypted === $test_string);
        }
        
        // Check if credentials exist and if they're encrypted
        $credential_status = [];
        foreach ($credential_options as $service => $options) {
            $credential_status[$service] = [];
            foreach ($options as $option_name => $option_label) {
                $value = get_option($option_name);
                
                $status = [
                    'name' => $option_label,
                    'option' => $option_name,
                    'exists' => !empty($value),
                    'encrypted' => false
                ];
                
                // Check if it's encrypted (try to decrypt it)
                if ($encryption_working && !empty($value)) {
                    try {
                        $decrypted = kwetupizza_decrypt_data($value);
                        // If decryption doesn't throw an exception and returns something, it's likely encrypted
                        $status['encrypted'] = !empty($decrypted);
                    } catch (Exception $e) {
                        $status['encrypted'] = false;
                    }
                }
                
                $credential_status[$service][] = $status;
            }
        }
    ?>
    <div class="wrap">
        <h1>KwetuPizza Business Insights Dashboard</h1>
        
        <!-- Credentials Security Status -->
        <div class="dashboard-security-status">
            <h2>API Credentials Security Status</h2>
            <div class="card">
                <h3>Encryption System Status</h3>
                <?php if ($encryption_working && $encryption_key_exists && $encryption_test_result): ?>
                    <div class="status-indicator success">
                        <span class="dashicons dashicons-yes"></span> Encryption system is properly configured and working
                    </div>
                <?php else: ?>
                    <div class="status-indicator error">
                        <span class="dashicons dashicons-no"></span> Encryption system is not properly configured
                        <ul>
                            <?php if (!$encryption_working): ?>
                                <li>Encryption functions are not available</li>
                            <?php endif; ?>
                            <?php if (!$encryption_key_exists): ?>
                                <li>Encryption key is not set</li>
                            <?php endif; ?>
                            <?php if (!$encryption_test_result && $encryption_working): ?>
                                <li>Encryption test failed</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <p class="description">
                    Your API credentials should be encrypted for security. 
                    <a href="<?php echo admin_url('admin.php?page=kwetupizza-settings'); ?>">Go to settings</a> to configure encryption.
                </p>
            </div>
            
            <div class="credentials-grid">
                <?php foreach ($credential_status as $service => $credentials): ?>
                    <div class="card">
                        <h3><?php echo $service; ?> Credentials</h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Credential</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($credentials as $credential): ?>
                                    <tr>
                                        <td><?php echo $credential['name']; ?></td>
                                        <td>
                                            <?php if (!$credential['exists']): ?>
                                                <span class="dashicons dashicons-no"></span> Not set
                                            <?php elseif ($credential['encrypted']): ?>
                                                <span class="dashicons dashicons-yes"></span> Encrypted
                                            <?php else: ?>
                                                <span class="dashicons dashicons-warning"></span> Not encrypted
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!$encryption_working || !$encryption_key_exists || !$encryption_test_result): ?>
                <div class="card security-action">
                    <h3>Security Action Required</h3>
                    <p>Your API credentials are not properly secured. Please take the following actions:</p>
                    <ol>
                        <li>Go to the <a href="<?php echo admin_url('admin.php?page=kwetupizza-settings'); ?>">Settings page</a></li>
                        <li>Scroll to the bottom and run the encryption test</li>
                        <li>Click "Encrypt Existing Credentials" to secure your API keys</li>
                    </ol>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="dashboard-container">
            <div class="dashboard-section">
                <h2>Order Growth</h2>
                <canvas id="orderGrowthChart" width="400" height="200"></canvas>
            </div>
            <div class="dashboard-section">
                <h2>Popular Products</h2>
                <canvas id="popularProductsChart" width="400" height="200"></canvas>
            </div>
            <div class="dashboard-section">
                <h2>Transaction Volume Breakdown</h2>
                <canvas id="transactionVolumeChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <style>
        .dashboard-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            margin-top: 20px;
            margin-bottom: 40px;
        }
        .dashboard-section {
            width: 30%;
            text-align: center;
            margin-bottom: 30px;
        }
        .dashboard-security-status {
            margin-bottom: 40px;
        }
        .card {
            background: #fff;
            border: 1px solid #ddd;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 3px;
        }
        .credentials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .status-indicator {
            padding: 10px;
            border-radius: 3px;
            margin: 10px 0;
            font-weight: bold;
        }
        .status-indicator.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-indicator.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .security-action {
            background-color: #fff3cd;
            border-color: #ffeeba;
        }
        .security-action h3 {
            color: #856404;
        }
        .description {
            color: #666;
            font-style: italic;
        }
        .dashicons-yes {
            color: #46b450;
        }
        .dashicons-no {
            color: #dc3232;
        }
        .dashicons-warning {
            color: #ffb900;
        }
    </style>
    
    <!-- Include Chart.js from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Order Growth Chart
        var ctx1 = document.getElementById('orderGrowthChart').getContext('2d');
        var orderGrowthChart = new Chart(ctx1, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($order_months); ?>,
                datasets: [{
                    label: 'Order Growth',
                    data: <?php echo json_encode($order_counts); ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                }]
            },
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Order Growth Over Time'
                },
            }
        });

        // Popular Products Chart
        var ctx2 = document.getElementById('popularProductsChart').getContext('2d');
        var popularProductsChart = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($popular_product_names); ?>,
                datasets: [{
                    label: 'Popular Products',
                    data: <?php echo json_encode($popular_product_sales); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(153, 102, 255, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Popular Products'
                },
            }
        });

        // Transaction Volume Chart
        var ctx3 = document.getElementById('transactionVolumeChart').getContext('2d');
        var transactionVolumeChart = new Chart(ctx3, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($transaction_labels); ?>,
                datasets: [{
                    label: 'Transaction Volume',
                    data: <?php echo json_encode($transaction_counts); ?>,
                    backgroundColor: [
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Transaction Volume Breakdown'
                },
            }
        });
    </script>
    <?php
    }
}

// Load Dashicons in admin
function kwetupizza_enqueue_admin_scripts() {
    wp_enqueue_style('dashicons');
    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
}
add_action('admin_enqueue_scripts', 'kwetupizza_enqueue_admin_scripts');
