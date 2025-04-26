<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Render the review management page
function kwetupizza_render_review_management() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kwetupizza_reviews';
    
    // Create table if it doesn't exist
    kwetupizza_maybe_create_reviews_table();
    
    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['review_id']) && current_user_can('manage_options')) {
        $review_id = intval($_GET['review_id']);
        $wpdb->delete($table_name, ['id' => $review_id], ['%d']);
        echo '<div class="notice notice-success"><p>Review deleted successfully.</p></div>';
    }
    
    // Get reviews with pagination
    $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $per_page = 20;
    $offset = ($current_page - 1) * $per_page;
    
    // Apply filters if set
    $where = "1=1";
    $params = [];
    
    if (isset($_GET['rating']) && is_numeric($_GET['rating'])) {
        $rating = intval($_GET['rating']);
        $where .= " AND rating = %d";
        $params[] = $rating;
    }
    
    if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
        $date_from = sanitize_text_field($_GET['date_from']);
        $where .= " AND created_at >= %s";
        $params[] = $date_from . ' 00:00:00';
    }
    
    if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
        $date_to = sanitize_text_field($_GET['date_to']);
        $where .= " AND created_at <= %s";
        $params[] = $date_to . ' 23:59:59';
    }
    
    // Prepare query with filters
    $query = $wpdb->prepare(
        "SELECT r.*, o.customer_name 
         FROM $table_name AS r
         LEFT JOIN {$wpdb->prefix}kwetupizza_orders AS o ON r.order_id = o.id
         WHERE $where
         ORDER BY r.created_at DESC
         LIMIT %d, %d",
        array_merge($params, [$offset, $per_page])
    );
    
    // Count total for pagination
    $count_query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE $where",
        $params
    );
    
    $reviews = $wpdb->get_results($query);
    $total_reviews = $wpdb->get_var($count_query);
    $total_pages = ceil($total_reviews / $per_page);
    
    // Calculate average rating
    $avg_rating = $wpdb->get_var("SELECT AVG(rating) FROM $table_name");
    $avg_rating = $avg_rating ? round($avg_rating, 1) : 0;
    
    // Rating distribution
    $ratings = [];
    for ($i = 1; $i <= 5; $i++) {
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE rating = %d", $i));
        $ratings[$i] = $count;
    }
    
    // Get current filter parameters for pagination links
    $filter_params = [];
    if (isset($_GET['rating']) && is_numeric($_GET['rating'])) {
        $filter_params['rating'] = intval($_GET['rating']);
    }
    if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
        $filter_params['date_from'] = sanitize_text_field($_GET['date_from']);
    }
    if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
        $filter_params['date_to'] = sanitize_text_field($_GET['date_to']);
    }
    
    // Display the page
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Customer Reviews</h1>
        
        <div class="overview-container">
            <div class="overview-box">
                <h3>Average Rating</h3>
                <div class="average-rating">
                    <span class="rating-value"><?php echo esc_html($avg_rating); ?></span>
                    <span class="rating-stars">
                        <?php 
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= round($avg_rating)) {
                                echo '★';
                            } else {
                                echo '☆';
                            }
                        }
                        ?>
                    </span>
                    <span class="rating-count">(<?php echo esc_html($total_reviews); ?> reviews)</span>
                </div>
            </div>
            
            <div class="overview-box">
                <h3>Rating Distribution</h3>
                <div class="rating-distribution">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div class="rating-bar">
                            <div class="rating-label"><?php echo esc_html($i); ?> ★</div>
                            <?php 
                            $percentage = $total_reviews > 0 ? ($ratings[$i] / $total_reviews) * 100 : 0;
                            ?>
                            <div class="rating-bar-fill">
                                <div class="bar-inner" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                            </div>
                            <div class="rating-count"><?php echo esc_html($ratings[$i]); ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
        <!-- Filter Form -->
        <div class="tablenav top">
            <form method="get" action="">
                <input type="hidden" name="page" value="kwetupizza-reviews">
                
                <div class="alignleft actions">
                    <select name="rating">
                        <option value="">All Ratings</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?php echo esc_attr($i); ?>" <?php selected(isset($_GET['rating']) && $_GET['rating'] == $i); ?>>
                                <?php echo esc_html($i); ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <label>From: 
                        <input type="date" name="date_from" value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>">
                    </label>
                    
                    <label>To: 
                        <input type="date" name="date_to" value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>">
                    </label>
                    
                    <input type="submit" class="button" value="Filter">
                    <?php if (!empty($filter_params)): ?>
                        <a href="<?php echo admin_url('admin.php?page=kwetupizza-reviews'); ?>" class="button">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Reviews Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Order ID</th>
                    <th>Rating</th>
                    <th>Comment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reviews): ?>
                    <?php foreach ($reviews as $review): ?>
                        <tr>
                            <td><?php echo esc_html($review->id); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($review->created_at))); ?></td>
                            <td>
                                <?php 
                                echo !empty($review->customer_name) ? esc_html($review->customer_name) : 'Unknown';
                                echo '<br><small>' . esc_html($review->phone_number) . '</small>';
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=kwetupizza-orders&action=view&order_id=' . $review->order_id); ?>">
                                    #<?php echo esc_html($review->order_id); ?>
                                </a>
                            </td>
                            <td>
                                <div class="star-rating">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $review->rating ? '★' : '☆';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td><?php echo esc_html($review->comment ?: 'No comment provided'); ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=kwetupizza-reviews&action=delete&review_id=' . $review->id), 'delete_review'); ?>" 
                                   class="delete" 
                                   onclick="return confirm('Are you sure you want to delete this review?');">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No reviews found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo esc_html($total_reviews); ?> items</span>
                    <span class="pagination-links">
                        <?php
                        // First page
                        if ($current_page > 1) {
                            $first_url = add_query_arg(array_merge(['page' => 'kwetupizza-reviews', 'paged' => 1], $filter_params), admin_url('admin.php'));
                            echo '<a class="first-page button" href="' . esc_url($first_url) . '"><span class="screen-reader-text">First page</span><span aria-hidden="true">«</span></a>';
                            
                            // Previous page
                            $prev_url = add_query_arg(array_merge(['page' => 'kwetupizza-reviews', 'paged' => $current_page - 1], $filter_params), admin_url('admin.php'));
                            echo '<a class="prev-page button" href="' . esc_url($prev_url) . '"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">‹</span></a>';
                        } else {
                            echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
                            echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
                        }
                        
                        // Current page indicator
                        echo '<span class="paging-input">' . $current_page . ' of <span class="total-pages">' . $total_pages . '</span></span>';
                        
                        // Next page
                        if ($current_page < $total_pages) {
                            $next_url = add_query_arg(array_merge(['page' => 'kwetupizza-reviews', 'paged' => $current_page + 1], $filter_params), admin_url('admin.php'));
                            echo '<a class="next-page button" href="' . esc_url($next_url) . '"><span class="screen-reader-text">Next page</span><span aria-hidden="true">›</span></a>';
                            
                            // Last page
                            $last_url = add_query_arg(array_merge(['page' => 'kwetupizza-reviews', 'paged' => $total_pages], $filter_params), admin_url('admin.php'));
                            echo '<a class="last-page button" href="' . esc_url($last_url) . '"><span class="screen-reader-text">Last page</span><span aria-hidden="true">»</span></a>';
                        } else {
                            echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                            echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
                        }
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
        .overview-container {
            display: flex;
            margin-bottom: 20px;
            gap: 20px;
        }
        .overview-box {
            background: #fff;
            padding: 15px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            flex: 1;
        }
        .average-rating {
            display: flex;
            align-items: center;
            font-size: 16px;
        }
        .rating-value {
            font-size: 32px;
            font-weight: bold;
            margin-right: 10px;
        }
        .rating-stars {
            color: #FFD700;
            font-size: 24px;
            margin-right: 10px;
        }
        .rating-distribution {
            margin-top: 10px;
        }
        .rating-bar {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        .rating-label {
            width: 50px;
        }
        .rating-bar-fill {
            flex-grow: 1;
            background: #eee;
            height: 15px;
            margin: 0 10px;
            border-radius: 10px;
            overflow: hidden;
        }
        .bar-inner {
            background: #FFD700;
            height: 100%;
        }
        .star-rating {
            color: #FFD700;
            font-size: 16px;
        }
    </style>
    <?php
} 