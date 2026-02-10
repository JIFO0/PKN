<?php
/**
 * Error logging and display functionality
 */

if (!defined('ABSPATH')) {
    exit;
}
// Temporarily disable automatic logging if table doesn't exist
function sc_table_exists($table_name) {
    global $wpdb;
    return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
}
/**
 * Log an error to the custom log file
 */
function sc_log_error($message, $context = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'science_communities_error_log';
    
    // Check if table exists before attempting to log
    if (!sc_table_exists($table_name)) {
        error_log("PKN Backend: " . $message . " | Context: " . print_r($context, true));
        return;
    }
    
    $wpdb->insert(
        $table_name,
        array(
            'message' => $message,
            'context' => maybe_serialize($context),
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'created_at' => current_time('mysql')
        )
    );
}

/**
 * Custom error handler
 */
function sc_error_handler($errno, $errstr, $errfile, $errline) {
    // Only log errors from our plugin
    if (strpos($errfile, SC_PLUGIN_PATH) === false) {
        return false;
    }
    
    $error_types = array(
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice'
    );
    
    $type = $error_types[$errno] ?? 'Unknown';
    
    sc_log_error(
        "$type: $errstr",
        array(
            'file' => str_replace(SC_PLUGIN_PATH, '', $errfile),
            'line' => $errline,
            'type' => $type,
            'errno' => $errno
        )
    );
    
    return false; // Let PHP handle it too
}

/**
 * Custom exception handler
 */
function sc_exception_handler($exception) {
    sc_log_error(
        $exception->getMessage(),
        array(
            'file' => str_replace(SC_PLUGIN_PATH, '', $exception->getFile()),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        )
    );
}

// Set error handlers
set_error_handler('sc_error_handler');
set_exception_handler('sc_exception_handler');

/**
 * Add admin menu for error log viewer
 */
function sc_add_error_log_menu() {
    if (!sc_is_superadmin()) {
        return;
    }
    
    add_menu_page(
        'PKN Error Log',
        'PKN Errors',
        'manage_options',
        'sc-error-log',
        'sc_render_error_log_page',
        'dashicons-warning',
        100
    );
}
add_action('admin_menu', 'sc_add_error_log_menu');

/**
 * Render the error log page
 */
function sc_render_error_log_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'science_communities_error_log';
    
    // Handle clear log action
    if (isset($_POST['sc_clear_log']) && wp_verify_nonce($_POST['sc_clear_log_nonce'], 'sc_clear_log')) {
        $wpdb->query("TRUNCATE TABLE $table_name");
        echo '<div class="notice notice-success"><p>Error log cleared successfully.</p></div>';
    }
    
    // Get filter
    $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    
    // Build query
    $where = array();
    if ($filter === 'today') {
        $where[] = "DATE(created_at) = CURDATE()";
    } elseif ($filter === 'week') {
        $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    }
    
    if (!empty($search)) {
        $where[] = $wpdb->prepare("message LIKE %s", '%' . $wpdb->esc_like($search) . '%');
    }
    
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get logs
    $logs = $wpdb->get_results("
        SELECT * FROM $table_name 
        $where_sql
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    
    $total_errors = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    ?>
    <div class="wrap">
        <h1>PKN Backend Error Log</h1>
        
        <div style="background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #dc3232;">
            <strong>Total Errors Logged:</strong> <?php echo number_format($total_errors); ?>
        </div>
        
        <div style="background: white; padding: 15px; margin-bottom: 20px;">
            <form method="get" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="page" value="sc-error-log">
                
                <select name="filter" style="padding: 5px;">
                    <option value="all" <?php selected($filter, 'all'); ?>>All Time</option>
                    <option value="today" <?php selected($filter, 'today'); ?>>Today</option>
                    <option value="week" <?php selected($filter, 'week'); ?>>Last 7 Days</option>
                </select>
                
                <input type="text" name="search" value="<?php echo esc_attr($search); ?>" 
                       placeholder="Search errors..." style="padding: 5px; flex: 1; max-width: 300px;">
                
                <button type="submit" class="button">Filter</button>
                
                <a href="?page=sc-error-log" class="button">Reset</a>
            </form>
        </div>
        
        <div style="background: white; padding: 15px; margin-bottom: 10px;">
            <form method="post" onsubmit="return confirm('Are you sure you want to clear all error logs?');">
                <?php wp_nonce_field('sc_clear_log', 'sc_clear_log_nonce'); ?>
                <button type="submit" name="sc_clear_log" class="button button-secondary">
                    Clear All Logs
                </button>
            </form>
        </div>
        
        <?php if (empty($logs)): ?>
            <div style="background: white; padding: 40px; text-align: center;">
                <p style="color: #666; font-size: 16px;">Brak błęddów!!! Yeepeee! 🎉🎉🎉 No chyba że są...</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 150px;">Date/Time</th>
                        <th>Error Message</th>
                        <th style="width: 200px;">Location</th>
                        <th style="width: 100px;">User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): 
                        $context = maybe_unserialize($log->context);
                        $user = get_user_by('id', $log->user_id);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo date('Y-m-d', strtotime($log->created_at)); ?></strong><br>
                            <small style="color: #666;"><?php echo date('H:i:s', strtotime($log->created_at)); ?></small>
                        </td>
                        <td>
                            <div style="margin-bottom: 8px;">
                                <strong style="color: #dc3232;"><?php echo esc_html($log->message); ?></strong>
                            </div>
                            <?php if (!empty($context)): ?>
                            <details style="margin-top: 5px;">
                                <summary style="cursor: pointer; color: #0073aa;">View Details</summary>
                                <pre style="background: #f5f5f5; padding: 10px; margin-top: 5px; overflow-x: auto; font-size: 11px;"><?php echo esc_html(print_r($context, true)); ?></pre>
                            </details>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($context['file'])): ?>
                                <code style="font-size: 11px;"><?php echo esc_html($context['file']); ?></code>
                                <?php if (!empty($context['line'])): ?>
                                    <br><small>Line: <?php echo esc_html($context['line']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #999;">�</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user): ?>
                                <?php echo esc_html($user->user_login); ?>
                            <?php else: ?>
                                <span style="color: #999;">Guest</span>
                            <?php endif; ?>
                            <br>
                            <small style="color: #666;"><?php echo esc_html(substr($log->ip_address, 0, 20)); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (count($logs) >= 100): ?>
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-top: 20px;">
                <strong>Note:</strong> Showing last 100 errors only. Use filters to narrow down results.
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}


/**
 * Debug function - logs detailed information
 */
function sc_debug($message, $data = array()) {
    if (!defined('SC_DEBUG_MODE') || !SC_DEBUG_MODE) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'science_communities_error_log';
    
    // Check if table exists
    if (!sc_table_exists($table_name)) {
        error_log("PKN DEBUG: " . $message . " | " . print_r($data, true));
        return;
    }
    
    $wpdb->insert(
        $table_name,
        array(
            'message' => '[DEBUG] ' . $message,
            'context' => maybe_serialize(array_merge($data, array(
                'memory_usage' => memory_get_usage(true),
                'time' => microtime(true),
                'url' => $_SERVER['REQUEST_URI'] ?? 'CLI'
            ))),
            'user_id' => get_current_user_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'created_at' => current_time('mysql')
        )
    );
}

/**
 * Check system requirements and log issues
 */
function sc_check_system_requirements() {
    $checks = array();
    
    // Check PHP version
    $checks['php_version'] = array(
        'status' => version_compare(PHP_VERSION, '7.0', '>='),
        'value' => PHP_VERSION,
        'required' => '7.0+'
    );
    
    // Check WordPress version
    global $wp_version;
    $checks['wp_version'] = array(
        'status' => version_compare($wp_version, '5.0', '>='),
        'value' => $wp_version,
        'required' => '5.0+'
    );
    
    // Check if user is logged in
    $checks['user_logged_in'] = array(
        'status' => is_user_logged_in(),
        'value' => is_user_logged_in() ? 'Yes' : 'No',
        'required' => 'Yes'
    );
    
    // Check database tables
    global $wpdb;
    $tables = array(
        'science_communities',
        'science_tags',
        'science_community_tags',
        'science_faculties'
    );
    
    foreach ($tables as $table) {
        $full_table = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
        $checks["table_$table"] = array(
            'status' => $exists,
            'value' => $exists ? 'Exists' : 'Missing',
            'required' => 'Exists'
        );
    }
    
    // Check user roles
    $current_user = wp_get_current_user();
    $checks['user_roles'] = array(
        'status' => !empty($current_user->roles),
        'value' => implode(', ', $current_user->roles),
        'required' => 'Any'
    );
    
    // Check if user can access admin
    $checks['can_access_admin'] = array(
        'status' => sc_can_access_admin_panel(),
        'value' => sc_can_access_admin_panel() ? 'Yes' : 'No',
        'required' => 'Yes'
    );
    
    return $checks;
}

