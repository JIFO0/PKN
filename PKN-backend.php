<?php
/**
 * Plugin Name: PKN Backend
 * Description: Plugin do zarządzania kołami naukowymi na PKN
 * Version: Alpha 0.853
 * Author: Iwo laskowski & PKN TEAM
 * Text Domain: pkn-backend
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SC_PLUGIN_VERSION', '1.0.0');
define('SC_VERSION', '1.0.0');

// Include required files
require_once SC_PLUGIN_PATH . 'includes/functions.php';
require_once SC_PLUGIN_PATH . 'includes/admin-functions.php';
require_once SC_PLUGIN_PATH . 'includes/auth.php';
require_once SC_PLUGIN_PATH . 'includes/error-logger.php';

// Enable debug mode (set to false in production)
define('SC_DEBUG_MODE', true);

// Register activation hook
register_activation_hook(__FILE__, 'sc_activate_plugin');
/**
 * Temporary function to add superadmin role - COMMENTED OUT AFTER FIRST RUN
 */
/*
function sc_add_superadmin_role_temp() {
    if (!get_role('superadmin')) {
        add_role('superadmin', 'Super Administrator', array(
            'read' => true,
            'edit_posts' => true,
            'delete_posts' => true,
        ));
    }
    
    $user = get_user_by('id', 1);
    if ($user) {
        $user->add_role('superadmin');
    }
}
add_action('init', 'sc_add_superadmin_role_temp');
*/
// Fix WordPress JSON errors caused by early output
add_action('init', 'sc_clean_output_for_json', 1);
function sc_clean_output_for_json() {
    if (defined('DOING_AJAX') && DOING_AJAX) {
        // Clean any output buffer before AJAX
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
    }
}

// Check for BOM and whitespace
add_action('init', 'sc_check_file_encoding', 1);
function sc_check_file_encoding() {
    $files = [
        SC_PLUGIN_PATH . 'PKN-backend.php',
        SC_PLUGIN_PATH . 'includes/functions.php',
        SC_PLUGIN_PATH . 'includes/admin-functions.php',
        SC_PLUGIN_PATH . 'includes/auth.php',
        SC_PLUGIN_PATH . 'includes/error-logger.php',
    ];
    
    foreach ($files as $file) {
        if (!file_exists($file)) continue;
        
        $content = file_get_contents($file);
        $filename = basename($file);
        
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            error_log("!!! UTF-8 BOM found in $filename - REMOVE IT!");
        }
        
        if (preg_match('/^\s+<\?php/', $content)) {
            error_log("!!! Whitespace before <?php in $filename - REMOVE IT!");
        }
        
        if (preg_match('/\?>\s*$/', $content)) {
            error_log("!!! Trailing closing ?> tag found in $filename - REMOVE IT!");
        }
    }
}
// Register deactivation hook
register_deactivation_hook(__FILE__, 'sc_deactivate_plugin');

/**
 * Plugin activation function
 */
function sc_activate_plugin() {
    // Create necessary database tables
    sc_create_tables();

    // Insert default faculties
    sc_insert_default_faculties();

    // Ensure required frontend/admin pages exist
    sc_ensure_required_pages();
    
    // Flush rewrite rules on activation only
    flush_rewrite_rules();
}

/**
 * Plugin deactivation function
 */
function sc_deactivate_plugin() {
    // Flush rewrite rules on deactivation
    flush_rewrite_rules();
}


/**
 * Ensure required pages with plugin shortcodes exist.
 *
 * @return array<string,int> Map: shortcode => page ID
 */
function sc_ensure_required_pages() {
    $required_pages = array(
        'science_communities_search' => array(
            'title' => 'PKN Search',
            'slug' => 'sc-search',
            'content' => '[science_communities_search]'
        ),
        'science_communities_results' => array(
            'title' => 'PKN Results',
            'slug' => 'results',
            'content' => '[science_communities_results]'
        ),
        'science_community_detail' => array(
            'title' => 'PKN Detail',
            'slug' => 'detail',
            'content' => '[science_community_detail]'
        ),
        'science_communities_admin' => array(
            'title' => 'PKN Admin',
            'slug' => 'sc-admin',
            'content' => '[science_communities_admin]'
        ),
        'science_communities_list' => array(
            'title' => 'PKN Communities List',
            'slug' => 'sc-list',
            'content' => '[science_communities_list]'
        ),
    );

    $page_map = array();

    foreach ($required_pages as $shortcode => $page_data) {
        $page_id = function_exists('sc_find_page_id_by_shortcode') ? sc_find_page_id_by_shortcode($shortcode) : 0;

        if (empty($page_id)) {
            $existing = get_page_by_path($page_data['slug'], OBJECT, 'page');

            if ($existing && !empty($existing->ID)) {
                $page_id = (int) $existing->ID;

                if (!has_shortcode((string) $existing->post_content, $shortcode)) {
                    wp_update_post(array(
                        'ID' => $page_id,
                        'post_content' => trim((string) $existing->post_content . "\n\n" . $page_data['content'])
                    ));
                }
            } else {
                $page_id = wp_insert_post(array(
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'post_title' => $page_data['title'],
                    'post_name' => $page_data['slug'],
                    'post_content' => $page_data['content'],
                ));
                if (is_wp_error($page_id)) {
                    $page_id = 0;
                }
            }
        }

        $page_map[$shortcode] = (int) $page_id;
    }

    update_option('sc_shortcode_page_map', $page_map, false);

    return $page_map;
}

/**
 * Backfill required pages when plugin is already active.
 */
function sc_maybe_ensure_required_pages() {
    if (!is_admin()) {
        return;
    }

    $required_shortcodes = array(
        'science_communities_search',
        'science_communities_results',
        'science_community_detail',
        'science_communities_admin',
        'science_communities_list',
    );

    $page_map = get_option('sc_shortcode_page_map', array());
    $is_missing_any = false;

    foreach ($required_shortcodes as $shortcode) {
        if (empty($page_map[$shortcode]) || !sc_find_page_id_by_shortcode($shortcode)) {
            $is_missing_any = true;
            break;
        }
    }

    $last_version = get_option('sc_pages_ensured_version', '');
    if ($is_missing_any || $last_version !== SC_PLUGIN_VERSION) {
        sc_ensure_required_pages();
        update_option('sc_pages_ensured_version', SC_PLUGIN_VERSION, false);
    }
}
add_action('admin_init', 'sc_maybe_ensure_required_pages');

/**
 * Create database tables
 */
function sc_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // 1. Communities table
    $sql = "CREATE TABLE {$wpdb->prefix}science_communities (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        community_id VARCHAR(5) NOT NULL,
        name VARCHAR(255) NOT NULL,
        shortdescription VARCHAR(512),
        description LONGTEXT,
        webpage VARCHAR(512),
        facebook VARCHAR(512),
        instagram VARCHAR(512),
        tiktok VARCHAR(512),
        discord VARCHAR(512),
        logo VARCHAR(512),
        faculty_id mediumint(9),
        status ENUM('active', 'limited', 'suspended', 'inactive') DEFAULT 'active',
        is_archived TINYINT(1) DEFAULT 0,
        last_verified_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY community_id (community_id),
        KEY status (status),
        KEY is_archived (is_archived),
        KEY faculty_id (faculty_id)
    ) $charset_collate;";
    dbDelta($sql);
    
    // 2. Faculties table
    $sql = "CREATE TABLE {$wpdb->prefix}science_faculties (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        faculty_name VARCHAR(100) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY faculty_name (faculty_name)
    ) $charset_collate;";
    dbDelta($sql);
    
    // 3. Tags table
    $sql = "CREATE TABLE {$wpdb->prefix}science_tags (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tag_name VARCHAR(50) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY tag_name (tag_name)
    ) $charset_collate;";
    dbDelta($sql);
    
    // 4. Community-tags relationship
    $sql = "CREATE TABLE {$wpdb->prefix}science_community_tags (
        community_id VARCHAR(5) NOT NULL,
        tag_id mediumint(9) NOT NULL,
        PRIMARY KEY (community_id, tag_id),
        KEY tag_id (tag_id)
    ) $charset_collate;";
    dbDelta($sql);

    // 5. User roles
    $sql = "CREATE TABLE {$wpdb->prefix}science_community_user_roles (
        user_id bigint(20) UNSIGNED NOT NULL,
        community_id VARCHAR(5) NOT NULL,
        role VARCHAR(20) NOT NULL,
        PRIMARY KEY (user_id, community_id),
        KEY community_id (community_id)
    ) $charset_collate;";
    dbDelta($sql);

    // 6. Audit trail
    $sql = "CREATE TABLE {$wpdb->prefix}science_communities_audit (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        community_id VARCHAR(5) NOT NULL,
        admin_user_id bigint(20) UNSIGNED NOT NULL,
        action VARCHAR(100) NOT NULL,
        field_name VARCHAR(100),
        old_value TEXT,
        new_value TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY community_id (community_id),
        KEY admin_user_id (admin_user_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta($sql);

    // 7. Error log
    $sql = "CREATE TABLE {$wpdb->prefix}science_communities_error_log (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        message TEXT NOT NULL,
        context LONGTEXT,
        user_id bigint(20) UNSIGNED DEFAULT 0,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta($sql);

    // 8. Upload tracking
    $sql = "CREATE TABLE {$wpdb->prefix}science_community_uploads (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        filename VARCHAR(255) NOT NULL,
        filepath TEXT NOT NULL,
        filesize bigint(20) NOT NULL,
        uploaded_by bigint(20) UNSIGNED NOT NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY uploaded_by (uploaded_by),
        KEY uploaded_at (uploaded_at)
    ) $charset_collate;";
    dbDelta($sql);
}

/**
 * Insert default faculties into the database
 */
function sc_insert_default_faculties() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'science_faculties';
    
    $faculties = array(
        'Wydział Ekonomiczny',
        'Wydział Biologii',
        'Wydział Zarządzania',
        'Wydział Oceanografii i Geografii',
        'Wydział Matematyki, Fizyki i Informatyki',
        'Wydział Historyczny',
        'Wydział Nauk Społecznych',
        'Wydział Chemii',
        'Wydział Filologiczny',
        'Wydział Prawa i Administracji',
        'Wydział Biotechnologii UG i GUMed',
        'Międzywydziałowe',
        'Międzyuczelniane',
        'Nieznane'
    );
    
    foreach ($faculties as $faculty) {
        // Check if faculty already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE faculty_name = %s",
            $faculty
        ));
        
        // Insert only if it doesn't exist
        if (!$exists) {
            $wpdb->insert(
                $table_name,
                array('faculty_name' => $faculty),
                array('%s')
            );
        }
    }
}

/**
 * Register shortcodes
 */
function sc_register_shortcodes() {
    add_shortcode('science_communities_search', 'sc_search_form_shortcode');
    add_shortcode('science_community_detail', 'sc_community_detail_shortcode');
    add_shortcode('science_communities_admin', 'sc_admin_panel_shortcode');
    add_shortcode('science_communities_results', 'sc_search_results_shortcode');
    add_shortcode('science_communities_add', 'sc_add_community_shortcode');
    add_shortcode('science_communities_list', 'sc_community_list_shortcode');
    add_shortcode('science_communities_debug', 'sc_debug_shortcode');
}
add_action('init', 'sc_register_shortcodes');

/**
 * Search form shortcode callback
 */
function sc_search_form_shortcode($atts) {
    ob_start();
    include SC_PLUGIN_PATH . 'templates/search-form.php';
    return ob_get_clean();
}

/**
 * Community detail shortcode callback
 */
function sc_community_detail_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => '',
    ), $atts, 'science_community_detail');
    
    // Get ID from URL parameter if not provided in shortcode
    if (empty($atts['id']) && isset($_GET['id'])) {
        $atts['id'] = sanitize_text_field($_GET['id']);
    }
    
    if (empty($atts['id'])) {
        return '<p>' . __('No community ID specified.', 'science-communities') . '</p>';
    }
    
    ob_start();
    include SC_PLUGIN_PATH . 'templates/community-detail.php';
    return ob_get_clean();
}

/**
 * Admin panel shortcode callback
 */
function sc_admin_panel_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>' . __('You must be logged in to access the admin panel.', 'science-communities') . '</p>';
    }
    
    // Check if the user has permission to access the admin panel
    if (!sc_user_can_edit_any_community()) {
        return '<p>' . __('You do not have permission to access the admin panel.', 'science-communities') . '</p>';
    }
    
    // Handle routing based on action parameter
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    
    ob_start();
    
    if ($action === 'add') {
        // Only superadmins can add communities
        if (!sc_is_superadmin()) {
            echo '<p>' . __('You do not have permission to add communities.', 'science-communities') . '</p>';
        } else {
            include SC_PLUGIN_PATH . 'templates/add-community.php';
        }
    } elseif ($action === 'edit') {
        $community_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
        
        // Check if user can edit this specific community
        if (empty($community_id) || !sc_user_can_edit_community($community_id)) {
            echo '<p>' . __('You do not have permission to edit this community.', 'science-communities') . '</p>';
        } else {
            include SC_PLUGIN_PATH . 'templates/edit-community.php';
        }
    } elseif ($action === 'manage-users' && sc_is_superadmin()) {
        // TODO: Add user management template if needed
        echo '<p>User management page - to be implemented</p>';
    } else {
        // Default: show community list
        include SC_PLUGIN_PATH . 'templates/admin-panel.php';
    }
    
    return ob_get_clean();
}

/**
 * Add community shortcode callback
 */
function sc_add_community_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>' . __('You must be logged in to add a community.', 'science-communities') . '</p>';
    }
    
    // Only superadmins can add new communities
    if (!sc_is_superadmin()) {
        return '<p>' . __('You do not have permission to add communities.', 'science-communities') . '</p>';
    }
    
    ob_start();
    include SC_PLUGIN_PATH . 'templates/add-community.php';
    return ob_get_clean();
}

/**
 * Community list with filters shortcode callback
 */
function sc_community_list_shortcode($atts) {
    ob_start();
    include SC_PLUGIN_PATH . 'templates/community-list.php';
    return ob_get_clean();
}

function sc_search_results_shortcode($atts) {
    ob_start();
    include SC_PLUGIN_PATH . 'templates/search-results.php';
    return ob_get_clean();
}

/**
 * Debug shortcode callback
 */
function sc_debug_shortcode($atts) {
    ob_start();
    include SC_PLUGIN_PATH . 'templates/debug-info.php';
    return ob_get_clean();
}

/**
 * Register assets
 */
function sc_enqueue_assets() {
    // Enqueue globals first
    wp_enqueue_style('sc-ug-globals', SC_PLUGIN_URL . 'assets/css/globals.css', array(), SC_PLUGIN_VERSION . '.' . filemtime(SC_PLUGIN_PATH . 'assets/css/globals.css'));
    
    // Then enqueue component styles
    wp_enqueue_style('sc-style', SC_PLUGIN_URL . 'assets/css/style.css', array('sc-ug-globals'), SC_PLUGIN_VERSION . '.' . filemtime(SC_PLUGIN_PATH . 'assets/css/style.css'));
    
    // Enqueue scripts
    wp_enqueue_style('sc-admin-panel-style', SC_PLUGIN_URL . 'assets/css/admin-panel.css', array('sc-ug-globals'), SC_PLUGIN_VERSION . '.' . filemtime(SC_PLUGIN_PATH . 'assets/css/admin-panel.css'));
    
    // Localize data used by admin-script.js uploader.
        
    // Enqueue admin panel assets
    global $post;
    if (is_page() && $post && (
        has_shortcode($post->post_content, 'science_communities_admin') ||
        has_shortcode($post->post_content, 'science_communities_add') ||
        has_shortcode($post->post_content, 'science_communities_search') ||
        has_shortcode($post->post_content, 'science_communities_results') ||
        has_shortcode($post->post_content, 'science_communities_list') ||
        strpos($_SERVER['REQUEST_URI'], '/sc-admin/') !== false
    )) {
        wp_enqueue_style('sc-admin-panel-style', SC_PLUGIN_URL . 'assets/css/admin-panel.css', array('sc-ug-globals'), SC_PLUGIN_VERSION);
        wp_enqueue_script('sc-admin-script', SC_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), SC_PLUGIN_VERSION, true);
        wp_localize_script('sc-admin-script', 'scienceCommunitiesData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('science_communities_nonce')
        ));
        if (file_exists(SC_PLUGIN_PATH . 'assets/css/results.css')) {
            wp_enqueue_style('sc-results', SC_PLUGIN_URL . 'assets/css/results.css', array('sc-ug-globals'), SC_PLUGIN_VERSION . '.' . filemtime(SC_PLUGIN_PATH . 'assets/css/results.css'));
        }
        if (file_exists(SC_PLUGIN_PATH . 'assets/css/search.css')) {
            wp_enqueue_style('sc-search', SC_PLUGIN_URL . 'assets/css/search.css', array('sc-ug-globals'), SC_PLUGIN_VERSION . '.' . filemtime(SC_PLUGIN_PATH . 'assets/css/search.css'));
        }
        if (file_exists(SC_PLUGIN_PATH . 'assets/css/search.css')) {
            wp_enqueue_style('sc-list', SC_PLUGIN_URL . 'assets/css/community-list.css', array('sc-ug-globals'), SC_PLUGIN_VERSION . '.' . filemtime(SC_PLUGIN_PATH . 'assets/css/community-list.css'));
        }
        if (file_exists(SC_PLUGIN_PATH . 'assets/css/community-detail.css')) {
            wp_enqueue_style('sc-detail', SC_PLUGIN_URL . 'assets/css/community-detail.css', array('sc-ug-globals'), SC_PLUGIN_VERSION . '.' . filemtime(SC_PLUGIN_PATH . 'assets/css/community-detail.css'));
        }
    }
}
add_action('wp_enqueue_scripts', 'sc_enqueue_assets');

/**
 * Register AJAX handlers
 */
function sc_register_ajax_handlers() {
    // Search communities
    add_action('wp_ajax_sc_search_communities', 'sc_ajax_search_communities');
    add_action('wp_ajax_nopriv_sc_search_communities', 'sc_ajax_search_communities');
    
    // Update community
    add_action('wp_ajax_sc_update_community', 'sc_ajax_update_community');
    
    // Get tags
    add_action('wp_ajax_sc_get_tags', 'sc_ajax_get_tags');
    add_action('wp_ajax_nopriv_sc_get_tags', 'sc_ajax_get_tags');
}
add_action('init', 'sc_register_ajax_handlers');

/**
 * Update community AJAX handler
 */
function sc_ajax_update_community() {
    if (ob_get_level()) ob_clean();
    error_log('==== AJAX UPDATE COMMUNITY ====');
    check_ajax_referer('science_communities_nonce', 'nonce');
    
    // Basic permission check for logged-in users
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('You do not have general editing permissions');
        wp_die();
    }
    
    if (!isset($_POST['community_id']) || empty($_POST['community_id'])) {
        wp_send_json_error('No community ID provided');
        wp_die();
    }
    
    $community_id = sanitize_text_field($_POST['community_id']);
    
    // Check if user has permission to edit this specific community
    if (!sc_user_can_edit_community($community_id)) {
        wp_send_json_error('You do not have permission to edit this community');
        wp_die();
    }
    
    // Process the update
    $update_result = sc_update_community($_POST);
    
    if ($update_result) {
        wp_send_json_success('Community updated successfully');
    } else {
        wp_send_json_error('Failed to update community');
    }
    
    wp_die();
}

/**
 * Get tags AJAX handler
 */
function sc_ajax_get_tags() {
    check_ajax_referer('science_communities_nonce', 'nonce');
    
    $tags = sc_get_all_tags();
    
    wp_send_json_success($tags);
    wp_die();
}

/**
 * Handle AJAX file upload
 */
function sc_ajax_upload_logo() {
    check_ajax_referer('science_communities_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to upload files.');
    }
    
    if (!isset($_FILES['logo_file'])) {
        wp_send_json_error('No file provided.');
    }
    
    $user_id = get_current_user_id();
    $result = sc_handle_logo_upload($_FILES['logo_file'], $user_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_sc_upload_logo', 'sc_ajax_upload_logo');

/**
 * Focused request logging for admin add/edit flow debugging.
 */
function sc_log_admin_flow_request($label) {
    $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
    $referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? sanitize_text_field(wp_unslash($_SERVER['CONTENT_TYPE'])) : '';

    error_log('==== ' . $label . ' ====');
    error_log('REQUEST_METHOD: ' . $method);
    error_log('REQUEST_URI: ' . $uri);
    error_log('REFERER: ' . $referer);
    error_log('CONTENT_TYPE: ' . $content_type);
    error_log('POST keys: ' . implode(', ', array_keys($_POST)));
}

/**
 * Handle add community form submission via admin-post.php.
 */
function sc_handle_add_community() {
    sc_log_admin_flow_request('ADD COMMUNITY ADMIN-POST');

    if (!is_user_logged_in() || !sc_is_superadmin()) {
        wp_die('Permission denied');
    }

    if (!isset($_POST['sc_add_community_nonce']) || !wp_verify_nonce($_POST['sc_add_community_nonce'], 'sc_add_community')) {
        wp_die('Security check failed');
    }

    $rate_check = sc_can_user_edit_now(get_current_user_id(), 'new');
    if (!$rate_check['allowed']) {
        $redirect_url = add_query_arg(
            array(
                'action' => 'add',
                'error' => rawurlencode($rate_check['message'])
            ),
            sc_get_admin_page_url()
        );
        wp_safe_redirect($redirect_url);
        exit;
    }

    $data = array(
        'community_id' => '',
        'name' => sanitize_text_field($_POST['name'] ?? ''),
        'shortdescription' => sanitize_textarea_field($_POST['shortdescription'] ?? ''),
        'description' => wp_kses_post($_POST['description'] ?? ''),
        'webpage' => esc_url_raw($_POST['webpage'] ?? ''),
        'facebook' => esc_url_raw($_POST['facebook'] ?? ''),
        'instagram' => esc_url_raw($_POST['instagram'] ?? ''),
        'tiktok' => esc_url_raw($_POST['tiktok'] ?? ''),
        'discord' => esc_url_raw($_POST['discord'] ?? ''),
        'logo' => esc_url_raw($_POST['logo'] ?? ''),
        'faculty_id' => isset($_POST['faculty_id']) && $_POST['faculty_id'] !== '' ? intval($_POST['faculty_id']) : null,
        'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active',
        'is_archived' => isset($_POST['is_archived']) ? 1 : 0,
        'tags' => isset($_POST['tags']) ? array_map('sanitize_text_field', (array) $_POST['tags']) : array(),
    );

    $result = sc_save_community($data);

    $redirect_url = add_query_arg(array('action' => 'add'), sc_get_admin_page_url());
    if ($result === true) {
        $redirect_url = add_query_arg('updated', '1', $redirect_url);
    } else {
        $redirect_url = add_query_arg('error', rawurlencode((string) $result), $redirect_url);
    }

    error_log('Add save result: ' . print_r($result, true));
    error_log('Add redirect URL: ' . $redirect_url);

    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_sc_add_community', 'sc_handle_add_community');
add_action('admin_post_nopriv_sc_add_community', 'sc_handle_add_community');

/**
 * Handle edit community form submission
 */
function sc_handle_edit_community() {
    sc_log_admin_flow_request('EDIT COMMUNITY ADMIN-POST');

    if (!isset($_POST['sc_edit_community_nonce']) || 
        !wp_verify_nonce($_POST['sc_edit_community_nonce'], 'sc_edit_community')) {
        wp_die('Security check failed');
    }
    
    $community_id = isset($_POST['community_id']) ? sanitize_text_field($_POST['community_id']) : '';
    
    if (empty($community_id) || !sc_user_can_edit_community($community_id)) {
        sc_log_error('Unauthorized edit attempt', array(
            'user_id' => get_current_user_id(),
            'community_id' => $community_id
        ));
        wp_die('Permission denied');
    }

    $data = array(
        'community_id' => $community_id,
        'name' => sanitize_text_field($_POST['name']),
        'shortdescription' => sanitize_textarea_field($_POST['shortdescription']),
        'description' => wp_kses_post($_POST['description']),
        'webpage' => esc_url_raw($_POST['webpage']),
        'facebook' => esc_url_raw($_POST['facebook']),
        'instagram' => esc_url_raw($_POST['instagram']),
        'tiktok' => esc_url_raw($_POST['tiktok']),
        'discord' => esc_url_raw($_POST['discord']),
        'logo' => esc_url_raw($_POST['logo']),
        'tags' => isset($_POST['tags']) ? array_map('sanitize_text_field', $_POST['tags']) : array()
    );

    $result = sc_save_community($data);
    
    $redirect_url = add_query_arg(
        array('action' => 'edit', 'id' => $community_id),
        sc_get_admin_page_url()
    );
    
    if ($result === true) {
        $redirect_url = add_query_arg('updated', '1', $redirect_url);
    } else {
        $redirect_url = add_query_arg('error', urlencode($result), $redirect_url);
    }
    error_log('==== EDIT COMMUNITY REDIRECT DEBUG ====');
    error_log('Community ID: ' . $community_id);
    error_log('Save result: ' . print_r($result, true));
    error_log('Redirect URL: ' . $redirect_url);
    error_log('Headers already sent: ' . (headers_sent($file, $line) ? "YES at $file:$line" : 'NO'));
    wp_redirect($redirect_url);
    error_log('wp_redirect called');
    exit;
}
add_action('admin_post_sc_edit_community', 'sc_handle_edit_community');
add_action('admin_post_nopriv_sc_edit_community', 'sc_handle_edit_community');

/**
 * Add WordPress admin menu for PKN Backend
 */
function sc_add_admin_menu() {
    if (!sc_is_superadmin()) {
        return;
    }
    
    add_menu_page(
        'PKN Communities',
        'PKN Communities',
        'manage_options',
        'pkn-communities',
        'sc_render_admin_page',
        'dashicons-groups',
        30
    );
    
    add_submenu_page(
        'pkn-communities',
        'All Communities',
        'All Communities',
        'manage_options',
        'pkn-communities',
        'sc_render_admin_page'
    );
    
    add_submenu_page(
        'pkn-communities',
        'Import Communities',
        'Import from Excel',
        'manage_options',
        'pkn-import',
        'sc_render_import_page'
    );
    
    add_submenu_page(
        'pkn-communities',
        'Tags & Faculties',
        'Tags & Faculties',
        'manage_options',
        'pkn-tags-faculties',
        'sc_render_tags_faculties_page'
    );
}
add_action('admin_menu', 'sc_add_admin_menu');

/**
 * Handle bulk delete action
 */
function sc_handle_bulk_delete() {
    if (!isset($_POST['sc_bulk_delete_nonce']) || 
        !wp_verify_nonce($_POST['sc_bulk_delete_nonce'], 'sc_bulk_delete')) {
        return;
    }
    
    if (!sc_is_superadmin()) {
        return;
    }
    
    if (isset($_POST['community_ids']) && is_array($_POST['community_ids'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'science_communities';
        $deleted_count = 0;
        
        foreach ($_POST['community_ids'] as $community_id) {
            $community_id = sanitize_text_field($community_id);
            if (sc_delete_community($community_id)) {
                $deleted_count++;
            }
        }
        
        add_settings_error(
            'pkn_messages',
            'pkn_message',
            sprintf(__('%d communities deleted successfully.', 'science-communities'), $deleted_count),
            'success'
        );
    }
}
add_action('admin_init', 'sc_handle_bulk_delete');

/**
 * Handle Excel import
 */
function sc_handle_excel_import() {
    if (!isset($_POST['sc_import_nonce']) || 
        !wp_verify_nonce($_POST['sc_import_nonce'], 'sc_import_excel')) {
        return;
    }
    
    if (!sc_is_superadmin()) {
        return;
    }
    
    if (!isset($_FILES['excel_file'])) {
        add_settings_error('pkn_messages', 'pkn_message', __('No file uploaded.', 'science-communities'), 'error');
        return;
    }
    
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    
    $file = $_FILES['excel_file'];
    $upload_overrides = array('test_form' => false);
    $movefile = wp_handle_upload($file, $upload_overrides);
    
    if ($movefile && !isset($movefile['error'])) {
        $imported = sc_import_from_excel($movefile['file']);
        
        if ($imported['success']) {
            add_settings_error(
                'pkn_messages',
                'pkn_message',
                sprintf(__('%d communities imported successfully.', 'science-communities'), $imported['count']),
                'success'
            );
        } else {
            add_settings_error('pkn_messages', 'pkn_message', $imported['message'], 'error');
        }
        
        // Clean up uploaded file
        @unlink($movefile['file']);
    } else {
        add_settings_error('pkn_messages', 'pkn_message', $movefile['error'], 'error');
    }
}
add_action('admin_init', 'sc_handle_excel_import');

/**
 * Render main admin page
 */
function sc_render_admin_page() {
    include SC_PLUGIN_PATH . 'templates/admin-communities-list.php';
}

/**
 * Render import page
 */
function sc_render_import_page() {
    include SC_PLUGIN_PATH . 'templates/admin-import.php';
}

/**
 * Render tags and faculties page
 */
function sc_render_tags_faculties_page() {
    include SC_PLUGIN_PATH . 'templates/admin-tags-faculties.php';
}

