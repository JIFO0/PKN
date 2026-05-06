<?php
/**
 * Plugin Name: PKN Backend
 * Description: Plugin do zarządzania kołami naukowymi na PKN
 * Version: Alpha 0.948
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
require_once SC_PLUGIN_PATH . 'includes/lang.php';
require_once SC_PLUGIN_PATH . 'includes/admin-functions.php';
require_once SC_PLUGIN_PATH . 'includes/auth.php';
require_once SC_PLUGIN_PATH . 'includes/error-logger.php';
require_once SC_PLUGIN_PATH . 'includes/statistics.php';
require_once SC_PLUGIN_PATH . 'includes/forum.php';
require_once SC_PLUGIN_PATH . 'includes/updater.php';

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
        ), '|');
    }
    
    $user = get_user_by('id', 1);
    if ($user) {
        $user->add_role('superadmin');
    }
}
add_action('init', 'sc_add_superadmin_role_temp');
*/
// Fix WordPress JSON errors caused by early output
add_action('plugins_loaded', 'sc_register_plugin_updater');
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
        'science_communities_statistics' => array(
            'title' => 'Community Statistics',
            'slug' => 'community-statistics',
            'content' => '[science_communities_statistics]'
        ),
        'science_communities_forum' => array(
            'title' => 'PKN Forum',
            'slug' => 'sc-forum',
            'content' => '[science_communities_forum]'
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
        'science_communities_statistics',
        'science_communities_forum',
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

function sc_handle_social_tracking_redirect() {
    if (!isset($_GET['sc_track_social']) || (int) $_GET['sc_track_social'] !== 1) {
        return;
    }

    $community_id = isset($_GET['community_id']) ? sanitize_text_field($_GET['community_id']) : '';
    $platform = isset($_GET['platform']) ? sanitize_key($_GET['platform']) : '';
    $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(rawurldecode($_GET['redirect_to'])) : '';

    if (!empty($community_id) && !empty($platform)) {
        sc_track_social_click($community_id, $platform);
    }

    if (!empty($redirect_to)) {
        wp_safe_redirect($redirect_to);
        exit;
    }
}
add_action('template_redirect', 'sc_handle_social_tracking_redirect');

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
        other_links TEXT,
        logo VARCHAR(512),
        contact_email VARCHAR(255),
        open_for_applications TINYINT(1) DEFAULT 1,
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
    
    // 8. Community applications
    $sql = "CREATE TABLE {$wpdb->prefix}science_community_applications (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        community_id VARCHAR(5) NOT NULL,
        applicant_name VARCHAR(255) NOT NULL,
        applicant_email VARCHAR(255) NOT NULL,
        applicant_info TEXT NOT NULL,
        applicant_contact VARCHAR(255) NULL,
        applicant_user_id bigint(20) UNSIGNED NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY community_id (community_id),
        KEY created_at (created_at),
        KEY is_read (is_read)
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

    // 9. Import/update history
    $sql = "CREATE TABLE {$wpdb->prefix}science_communities_update_history (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        actor_name VARCHAR(255) NOT NULL,
        actor_user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        action VARCHAR(50) NOT NULL DEFAULT 'import',
        filename VARCHAR(255),
        communities_created int(11) NOT NULL DEFAULT 0,
        communities_updated int(11) NOT NULL DEFAULT 0,
        communities_deleted int(11) NOT NULL DEFAULT 0,
        communities_skipped int(11) NOT NULL DEFAULT 0,
        notes TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY actor_user_id (actor_user_id),
        KEY action (action),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta($sql);

    // 10. Statistics/events table
    sc_create_statistics_table();

    // 11. Community galleries
    $sql = "CREATE TABLE {$wpdb->prefix}science_community_images (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        community_id VARCHAR(5) NOT NULL,
        category VARCHAR(32) NOT NULL,
        image_url TEXT NOT NULL,
        sort_order int(11) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY community_id (community_id),
        KEY category (category)
    ) $charset_collate;";
    dbDelta($sql);

    // 12. Contact requests from community admins to superadmins
    $sql = "CREATE TABLE {$wpdb->prefix}science_contact_requests (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        community_id VARCHAR(5) NOT NULL,
        requester_id bigint(20) UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY community_id (community_id),
        KEY requester_id (requester_id),
        KEY status (status)
    ) $charset_collate;";
    dbDelta($sql);

    // 12-13. Forum tables
    sc_forum_create_tables();
    sc_forum_maybe_install();
}

/**
 * Insert default faculties into the database
 */

function sc_maybe_upgrade_schema() {
    $schema_version = '2026-05-06-import-history-links';
    if (get_option('sc_schema_version') === $schema_version) {
        return;
    }

    sc_create_tables();
    update_option('sc_schema_version', $schema_version);
}
add_action('admin_init', 'sc_maybe_upgrade_schema');

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
    add_shortcode('science_communities_statistics', 'sc_statistics_shortcode');
    add_shortcode('science_communities_debug', 'sc_debug_shortcode');
    add_shortcode('science_communities_forum', 'sc_forum_shortcode');
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
        include SC_PLUGIN_PATH . 'templates/manage-users.php';
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

function sc_statistics_shortcode($atts) {
    if (!is_user_logged_in() || !sc_user_can_edit_any_community()) {
        return '<p>' . __('You do not have permission to access community statistics.', 'science-communities') . '</p>';
    }

    ob_start();
    include SC_PLUGIN_PATH . 'templates/community-statistics.php';
    return ob_get_clean();
}

function sc_forum_shortcode($atts) {
    if (!sc_forum_user_can_access()) {
        return '<p>' . __('You do not have permission to access the forum.', 'science-communities') . '</p>';
    }

    sc_forum_ensure_general_thread();

    ob_start();
    include SC_PLUGIN_PATH . 'templates/forum.php';
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
        has_shortcode($post->post_content, 'science_communities_forum') ||
        strpos($_SERVER['REQUEST_URI'], '/sc-admin/') !== false
    )) {
        wp_enqueue_style('sc-admin-panel-style', SC_PLUGIN_URL . 'assets/css/admin-panel.css', array('sc-ug-globals'), SC_PLUGIN_VERSION);
        wp_enqueue_script('sc-admin-script', SC_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), SC_PLUGIN_VERSION, true);
        wp_enqueue_script('sc-layout-fixes', SC_PLUGIN_URL . 'assets/js/layout-fixes.js', array(), SC_PLUGIN_VERSION . '.' . filemtime(SC_PLUGIN_PATH . 'assets/js/layout-fixes.js'), true);
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
        if (
            has_shortcode($post->post_content, 'science_communities_forum') ||
            strpos($_SERVER['REQUEST_URI'], '/sc-forum') !== false
        ) {
            wp_enqueue_style('sc-forum', SC_PLUGIN_URL . 'assets/css/forum.css', array('sc-ug-globals'), SC_PLUGIN_VERSION . '.' . filemtime(SC_PLUGIN_PATH . 'assets/css/forum.css'));
            wp_enqueue_script('sc-forum-script', SC_PLUGIN_URL . 'assets/js/forum.js', array('jquery'), SC_PLUGIN_VERSION . '.' . filemtime(SC_PLUGIN_PATH . 'assets/js/forum.js'), true);
            wp_localize_script('sc-forum-script', 'scForumData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('science_communities_nonce'),
                'isSuperadmin' => sc_is_superadmin() ? 1 : 0,
            ));
        }
    }
}
add_action('wp_enqueue_scripts', 'sc_enqueue_assets');


function sc_enqueue_wp_admin_assets($hook) {
    if (strpos($hook, 'pkn-') === false) {
        return;
    }
    wp_enqueue_script('sc-admin-script', SC_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), SC_PLUGIN_VERSION, true);
    wp_localize_script('sc-admin-script', 'scienceCommunitiesData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('science_communities_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'sc_enqueue_wp_admin_assets');

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

    // Forum
    add_action('wp_ajax_sc_forum_get_threads', 'sc_forum_ajax_get_threads');
    add_action('wp_ajax_sc_forum_get_messages', 'sc_forum_ajax_get_messages');
    add_action('wp_ajax_sc_forum_create_thread', 'sc_forum_ajax_create_thread');
    add_action('wp_ajax_sc_forum_post_message', 'sc_forum_ajax_post_message');
    add_action('wp_ajax_sc_forum_edit_message', 'sc_forum_ajax_edit_message');
    add_action('wp_ajax_sc_forum_delete_message', 'sc_forum_ajax_delete_message');
    add_action('wp_ajax_sc_forum_close_thread', 'sc_forum_ajax_close_thread');
    add_action('wp_ajax_sc_forum_report_message', 'sc_forum_ajax_report_message');
}
add_action('init', 'sc_register_ajax_handlers');
add_action('init', 'sc_forum_maybe_install');

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
 * Extract facebook page username or id from URL/handle.
 */
function sc_extract_facebook_identifier($value) {
    $value = trim((string) $value);
    if ($value === '') return '';
    if (preg_match('#^https?://#i', $value)) {
        $path = wp_parse_url($value, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', (string) $path)));
        if (!empty($segments)) {
            $first = $segments[0];
            if (in_array(strtolower($first), array('pages','pg')) && !empty($segments[1])) {
                return sanitize_text_field($segments[1]);
            }
            return sanitize_text_field($first);
        }
    }
    return sanitize_text_field(ltrim($value, '@/'));
}

function sc_fetch_facebook_page_data($facebook_input) {
    $identifier = sc_extract_facebook_identifier($facebook_input);
    if ($identifier === '') return new WP_Error('missing_identifier', 'Missing Facebook page URL or username.');

    $cache_key = 'sc_fb_' . md5($identifier);
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    $token = trim((string) get_option('sc_facebook_app_token', ''));
    $endpoint = 'https://graph.facebook.com/v19.0/' . rawurlencode($identifier);
    $query = array('fields' => 'name,about,description,cover,picture.type(large)');
    if ($token !== '') $query['access_token'] = $token;

    $response = wp_remote_get(add_query_arg($query, $endpoint), array('timeout' => 20));
    if (is_wp_error($response)) return $response;
    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code >= 400 || isset($body['error'])) {
        $fallback_url = 'https://graph.facebook.com/' . rawurlencode($identifier) . '/picture?type=large&redirect=true';
        return array('name' => '', 'about' => '', 'description' => '', 'cover_url' => '', 'picture_url' => esc_url_raw($fallback_url), 'fallback_only' => true);
    }

    $data = array(
        'name' => sanitize_text_field($body['name'] ?? ''),
        'about' => sanitize_textarea_field($body['about'] ?? ''),
        'description' => sanitize_textarea_field($body['description'] ?? ''),
        'cover_url' => esc_url_raw($body['cover']['source'] ?? ''),
        'picture_url' => esc_url_raw($body['picture']['data']['url'] ?? ''),
        'fallback_only' => false,
    );
    set_transient($cache_key, $data, 7 * DAY_IN_SECONDS);
    return $data;
}

function sc_ajax_pull_facebook_data() {
    check_ajax_referer('science_communities_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('Login required.');

    $community_id = sanitize_text_field($_POST['community_id'] ?? '');
    if (!$community_id || !sc_user_can_edit_community($community_id)) wp_send_json_error('No permission.');

    $community = sc_get_community_by_id($community_id);
    $fb_input = sanitize_text_field($_POST['facebook'] ?? ($community['facebook'] ?? ''));
    $fb = sc_fetch_facebook_page_data($fb_input);
    if (is_wp_error($fb)) wp_send_json_error($fb->get_error_message());

    global $wpdb;
    $table = $wpdb->prefix . 'science_communities';
    $updates = array('facebook' => esc_url_raw($fb_input), 'updated_at' => current_time('mysql'));
    if (!empty($fb['picture_url'])) $updates['logo'] = $fb['picture_url'];
    if (!empty($fb['about']) && empty($community['shortdescription'])) $updates['shortdescription'] = $fb['about'];
    if (!empty($fb['description']) && empty($community['description'])) $updates['description'] = $fb['description'];
    $wpdb->update($table, $updates, array('community_id' => $community_id));

    wp_send_json_success(array('message' => 'Facebook data pulled successfully.', 'data' => $fb));
}
add_action('wp_ajax_sc_pull_facebook_data', 'sc_ajax_pull_facebook_data');

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
        wp_die(__('Permission denied', 'science-communities'));
    }

    if (!isset($_POST['sc_add_community_nonce']) || !wp_verify_nonce($_POST['sc_add_community_nonce'], 'sc_add_community')) {
        wp_die(__('Security check failed', 'science-communities'));
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
        'other_links' => sc_sanitize_links_list($_POST['other_links'] ?? ''),
        'logo' => esc_url_raw($_POST['logo'] ?? ''),
        'faculty_id' => isset($_POST['faculty_id']) && $_POST['faculty_id'] !== '' ? intval($_POST['faculty_id']) : null,
        'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active',
        'is_archived' => isset($_POST['is_archived']) ? 1 : 0,
        'tags' => isset($_POST['tags']) ? array_map('sanitize_text_field', (array) $_POST['tags']) : array(),
        'gallery_images' => isset($_POST['gallery_images']) ? array_filter(array_map('trim', explode("\n", wp_unslash($_POST['gallery_images'])))) : array(),
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
        wp_die(__('Security check failed', 'science-communities'));
    }
    
    $community_id = isset($_POST['community_id']) ? sanitize_text_field($_POST['community_id']) : '';
    
    if (empty($community_id) || !sc_user_can_edit_community($community_id)) {
        sc_log_error('Unauthorized edit attempt', array(
            'user_id' => get_current_user_id(),
            'community_id' => $community_id
        ));
        wp_die(__('Permission denied', 'science-communities'));
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
        'other_links' => sc_sanitize_links_list($_POST['other_links'] ?? ''),
        'logo' => esc_url_raw($_POST['logo']),
        'contact_email' => sanitize_email($_POST['contact_email'] ?? ''),
        'open_for_applications' => isset($_POST['open_for_applications']) ? 1 : 0,
        'faculty_id' => isset($_POST['faculty_id']) && $_POST['faculty_id'] !== '' ? intval($_POST['faculty_id']) : null,
        'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active',
        'is_archived' => sc_is_superadmin() && isset($_POST['is_archived']) ? 1 : 0,
        'tags' => isset($_POST['tags']) ? array_map('sanitize_text_field', $_POST['tags']) : array(),
        'event_images' => isset($_POST['event_images']) ? array_filter(array_map('trim', explode("\n", wp_unslash($_POST['event_images'])))) : array(),
        'team_images' => isset($_POST['team_images']) ? array_filter(array_map('trim', explode("\n", wp_unslash($_POST['team_images'])))) : array(),
        'gallery_images' => isset($_POST['gallery_images']) ? array_filter(array_map('trim', explode("\n", wp_unslash($_POST['gallery_images'])))) : array(),
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

function sc_handle_submit_contact_request() {
    if (!is_user_logged_in()) {
        wp_die('Login required');
    }

    if (!isset($_POST['sc_contact_request_nonce']) || !wp_verify_nonce($_POST['sc_contact_request_nonce'], 'sc_contact_request')) {
        wp_die(__('Security check failed', 'science-communities'));
    }

    $community_id = isset($_POST['community_id']) ? sanitize_text_field($_POST['community_id']) : '';
    $message = isset($_POST['request_message']) ? sanitize_textarea_field(wp_unslash($_POST['request_message'])) : '';

    if (empty($community_id) || empty($message) || !sc_user_can_edit_community($community_id) || sc_is_superadmin()) {
        wp_die(__('Permission denied', 'science-communities'));
    }

    sc_create_contact_request($community_id, $message, get_current_user_id());

    $redirect_url = add_query_arg(
        array('action' => 'edit', 'id' => $community_id, 'contact_sent' => '1'),
        sc_get_admin_page_url()
    );
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_sc_submit_contact_request', 'sc_handle_submit_contact_request');

function sc_handle_submit_join_application() {
    if (!isset($_POST['sc_join_application_nonce']) || !wp_verify_nonce($_POST['sc_join_application_nonce'], 'sc_join_application')) {
        wp_die(__('Security check failed', 'science-communities'));
    }
    $community_id = sanitize_text_field($_POST['community_id'] ?? '');
    $name = sanitize_text_field($_POST['applicant_name'] ?? '');
    $email = sanitize_email($_POST['applicant_email'] ?? '');
    $info = sanitize_textarea_field(wp_unslash($_POST['applicant_info'] ?? ''));
    $contact = sanitize_text_field($_POST['applicant_contact'] ?? '');
    if (empty($community_id) || empty($name) || empty($email) || empty($info)) {
        wp_die('Missing required fields');
    }
    $rate_key = 'sc_join_apps_' . get_current_user_id();
    $sent = intval(get_transient($rate_key));
    if ($sent >= 10) {
        wp_die('Rate limit exceeded (10/24h).');
    }
    $community = sc_get_community_by_id($community_id);
    if (!$community || empty($community['contact_email']) || empty($community['open_for_applications'])) {
        wp_die('This community is not open for applications.');
    }
    global $wpdb;
    $table = $wpdb->prefix . 'science_community_applications';
    $wpdb->insert($table, array(
        'community_id' => $community_id,
        'applicant_name' => $name,
        'applicant_email' => $email,
        'applicant_info' => $info,
        'applicant_contact' => $contact,
        'applicant_user_id' => get_current_user_id() ?: null,
        'is_read' => 0,
    ));
    set_transient($rate_key, $sent + 1, DAY_IN_SECONDS);
    wp_mail($community['contact_email'], 'New SC join application: ' . $community['name'], "Name: $name\nEmail: $email\nInfo: $info\nContact: $contact");
    wp_safe_redirect(add_query_arg(array('id' => $community_id, 'applied' => '1'), sc_get_page_url_by_shortcode('science_community_detail', site_url('/details/'))));
    exit;
}
add_action('admin_post_sc_submit_join_application', 'sc_handle_submit_join_application');
add_action('admin_post_nopriv_sc_submit_join_application', 'sc_handle_submit_join_application');

function sc_handle_assign_user_to_community() {
    if (!is_user_logged_in() || !sc_is_superadmin()) {
        wp_die(__('Permission denied', 'science-communities'));
    }

    if (!isset($_POST['sc_assign_user_to_community_nonce']) || !wp_verify_nonce($_POST['sc_assign_user_to_community_nonce'], 'sc_assign_user_to_community')) {
        wp_die(__('Security check failed', 'science-communities'));
    }

    $community_id = isset($_POST['community_id']) ? sanitize_text_field($_POST['community_id']) : '';
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if ($community_id && $user_id) {
        sc_assign_community_admin($user_id, $community_id);
    }

    $redirect_url = add_query_arg(
        array('action' => 'edit', 'id' => $community_id, 'assigned' => '1'),
        sc_get_admin_page_url()
    );
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_post_sc_assign_user_to_community', 'sc_handle_assign_user_to_community');


function sc_handle_update_admin_profile() {
    if (!is_user_logged_in() || sc_is_superadmin()) {
        wp_die(__('Permission denied', 'science-communities'));
    }

    if (!isset($_POST['sc_update_admin_profile_nonce']) || !wp_verify_nonce($_POST['sc_update_admin_profile_nonce'], 'sc_update_admin_profile')) {
        wp_die(__('Security check failed', 'science-communities'));
    }

    $display_name = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
    if ($display_name === '') {
        wp_die('Display name is required');
    }

    wp_update_user(array(
        'ID' => get_current_user_id(),
        'display_name' => $display_name,
    ));

    wp_safe_redirect(add_query_arg('profile_updated', '1', sc_get_admin_page_url()));
    exit;
}
add_action('admin_post_sc_update_admin_profile', 'sc_handle_update_admin_profile');

function sc_handle_request_community_removal() {
    if (!is_user_logged_in() || sc_is_superadmin()) {
        wp_die(__('Permission denied', 'science-communities'));
    }

    if (!isset($_POST['sc_request_community_removal_nonce']) || !wp_verify_nonce($_POST['sc_request_community_removal_nonce'], 'sc_request_community_removal')) {
        wp_die(__('Security check failed', 'science-communities'));
    }

    $community_id = isset($_POST['community_id']) ? sanitize_text_field($_POST['community_id']) : '';
    $message = isset($_POST['request_message']) ? sanitize_textarea_field(wp_unslash($_POST['request_message'])) : '';

    if (empty($community_id) || empty($message) || !sc_user_can_edit_community($community_id)) {
        wp_die(__('Permission denied', 'science-communities'));
    }

    sc_create_contact_request($community_id, '[REMOVAL REQUEST] ' . $message, get_current_user_id());
    wp_safe_redirect(add_query_arg('request_sent', '1', sc_get_admin_page_url()));
    exit;
}
add_action('admin_post_sc_request_community_removal', 'sc_handle_request_community_removal');

function sc_handle_submit_general_request() {
    if (!is_user_logged_in() || sc_is_superadmin()) {
        wp_die(__('Permission denied', 'science-communities'));
    }

    if (!isset($_POST['sc_submit_general_request_nonce']) || !wp_verify_nonce($_POST['sc_submit_general_request_nonce'], 'sc_submit_general_request')) {
        wp_die(__('Security check failed', 'science-communities'));
    }

    $community_id = isset($_POST['community_id']) ? sanitize_text_field($_POST['community_id']) : '';
    $message = isset($_POST['request_message']) ? sanitize_textarea_field(wp_unslash($_POST['request_message'])) : '';

    if (empty($community_id) || empty($message) || !sc_user_can_edit_community($community_id)) {
        wp_die(__('Permission denied', 'science-communities'));
    }

    sc_create_contact_request($community_id, '[GENERAL REQUEST] ' . $message, get_current_user_id());
    wp_safe_redirect(add_query_arg('request_sent', '1', sc_get_admin_page_url()));
    exit;
}
add_action('admin_post_sc_submit_general_request', 'sc_handle_submit_general_request');

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

    add_submenu_page(
        'pkn-communities',
        'Activity Dashboard',
        'Activity Dashboard',
        'manage_options',
        'pkn-dashboard',
        'sc_render_dashboard_page'
    );

    add_submenu_page(
        'pkn-communities',
        'Community Statistics',
        'Community Statistics',
        'manage_options',
        'pkn-community-statistics',
        'sc_render_statistics_page'
    );

    add_submenu_page(
        'pkn-communities',
        'User Management',
        'User Management',
        'manage_options',
        'pkn-user-management',
        'sc_render_user_management_page'
    );

    add_submenu_page(
        'pkn-communities',
        'Contact Requests',
        'Contact Requests',
        'manage_options',
        'pkn-contact-requests',
        'sc_render_contact_requests_page'
    );

    add_submenu_page(
        'pkn-communities',
        'Social Sync Settings',
        'Social Sync Settings',
        'manage_options',
        'pkn-social-sync-settings',
        'sc_render_social_settings_page'
    );
}

function sc_render_social_settings_page() {
    if (!current_user_can('manage_options')) { return; }
    if (isset($_POST['sc_social_settings_nonce']) && wp_verify_nonce($_POST['sc_social_settings_nonce'], 'sc_social_settings')) {
        update_option('sc_facebook_app_token', sanitize_text_field($_POST['sc_facebook_app_token'] ?? ''));
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }
    $token = esc_attr(get_option('sc_facebook_app_token', ''));
    echo '<div class="wrap"><h1>Social Media Sync</h1><form method="post">';
    wp_nonce_field('sc_social_settings', 'sc_social_settings_nonce');
    echo '<table class="form-table"><tr><th scope="row">Facebook App Access Token</th><td><input type="text" class="regular-text" name="sc_facebook_app_token" value="' . $token . '" /></td></tr></table>';
    submit_button('Save Settings');
    echo '</form></div>';
}

add_action('admin_menu', 'sc_add_admin_menu');

function sc_render_user_management_page() {
    include SC_PLUGIN_PATH . 'templates/manage-users.php';
}

function sc_render_contact_requests_page() {
    include SC_PLUGIN_PATH . 'templates/contact-requests.php';
}

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
    
    if (empty($_POST['community_ids']) || !is_array($_POST['community_ids'])) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'science_communities';
    $community_ids = array_map('sanitize_text_field', (array) $_POST['community_ids']);
    $bulk_action = isset($_POST['sc_bulk_action']) ? sanitize_key($_POST['sc_bulk_action']) : '';
    $processed_count = 0;
    $message = '';

    foreach ($community_ids as $community_id) {
        if ($bulk_action === 'delete') {
            if (sc_delete_community($community_id)) {
                $processed_count++;
            }
            continue;
        }

        if ($bulk_action === 'archive' || $bulk_action === 'unarchive') {
            $processed_count += (int) $wpdb->update(
                $table,
                array('is_archived' => $bulk_action === 'archive' ? 1 : 0),
                array('community_id' => $community_id),
                array('%d'),
                array('%s')
            );
            continue;
        }

        if ($bulk_action === 'status') {
            $new_status = isset($_POST['bulk_status']) ? sanitize_key($_POST['bulk_status']) : '';
            if (in_array($new_status, array('active', 'limited', 'suspended', 'inactive'), true)) {
                $processed_count += (int) $wpdb->update(
                    $table,
                    array('status' => $new_status),
                    array('community_id' => $community_id),
                    array('%s'),
                    array('%s')
                );
            }
            continue;
        }

        if ($bulk_action === 'faculty') {
            $faculty_id = isset($_POST['bulk_faculty_id']) ? intval($_POST['bulk_faculty_id']) : 0;
            if ($faculty_id > 0) {
                $processed_count += (int) $wpdb->update(
                    $table,
                    array('faculty_id' => $faculty_id),
                    array('community_id' => $community_id),
                    array('%d'),
                    array('%s')
                );
            }
            continue;
        }

        if ($bulk_action === 'add_tags' || $bulk_action === 'remove_tags') {
            $bulk_tag_ids = isset($_POST['bulk_tag_ids']) ? array_map('intval', (array) $_POST['bulk_tag_ids']) : array();
            if (empty($bulk_tag_ids)) {
                continue;
            }

            $existing_tags = sc_get_community_tags($community_id);
            $existing_tag_ids = array_map('intval', wp_list_pluck($existing_tags, 'id'));

            if ($bulk_action === 'add_tags') {
                $next_tag_ids = array_unique(array_merge($existing_tag_ids, $bulk_tag_ids));
            } else {
                $next_tag_ids = array_diff($existing_tag_ids, $bulk_tag_ids);
            }

            sc_update_community_tags($community_id, $next_tag_ids);
            $processed_count++;
        }
    }

    if ($bulk_action === 'delete') {
        $message = sprintf(__('%d communities deleted successfully.', 'science-communities'), $processed_count);
    } elseif ($bulk_action === 'archive') {
        $message = sprintf(__('%d communities archived.', 'science-communities'), $processed_count);
    } elseif ($bulk_action === 'unarchive') {
        $message = sprintf(__('%d communities unarchived.', 'science-communities'), $processed_count);
    } elseif ($bulk_action === 'status') {
        $message = sprintf(__('%d communities updated with a new status.', 'science-communities'), $processed_count);
    } elseif ($bulk_action === 'faculty') {
        $message = sprintf(__('%d communities updated with a new faculty.', 'science-communities'), $processed_count);
    } elseif ($bulk_action === 'add_tags') {
        $message = sprintf(__('%d communities updated with additional tags.', 'science-communities'), $processed_count);
    } elseif ($bulk_action === 'remove_tags') {
        $message = sprintf(__('%d communities updated after removing tags.', 'science-communities'), $processed_count);
    }

    if (!empty($message)) {
        add_settings_error('pkn_messages', 'pkn_message', $message, 'success');
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

    $importer_name = isset($_POST['importer_name']) ? sanitize_text_field(wp_unslash($_POST['importer_name'])) : '';
    if ($importer_name === '') {
        add_settings_error('pkn_messages', 'pkn_message', __('Please enter your name before importing.', 'science-communities'), 'error');
        return;
    }
    
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    
    $file = $_FILES['excel_file'];
    $upload_overrides = array('test_form' => false);
    $movefile = wp_handle_upload($file, $upload_overrides);
    
    if ($movefile && !isset($movefile['error'])) {
        $imported = sc_import_from_excel($movefile['file'], array(
            'actor_name' => $importer_name,
            'filename' => basename($file['name'] ?? $movefile['file']),
        ));
        
        if ($imported['success']) {
            add_settings_error(
                'pkn_messages',
                'pkn_message',
                sprintf(__('Import completed. Created: %1$d, updated: %2$d, skipped: %3$d.', 'science-communities'), (int) $imported['created'], (int) $imported['updated'], (int) $imported['skipped']),
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
 * Handle CSV export.
 */
function sc_handle_communities_export() {
    if (!isset($_GET['sc_export']) || $_GET['sc_export'] !== '1') {
        return;
    }

    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'sc_export_communities')) {
        return;
    }

    if (!sc_is_superadmin()) {
        return;
    }

    global $wpdb;
    $communities_table = $wpdb->prefix . 'science_communities';
    $faculties_table = $wpdb->prefix . 'science_faculties';
    $rows = $wpdb->get_results(
        "SELECT c.*, f.faculty_name
         FROM $communities_table c
         LEFT JOIN $faculties_table f ON c.faculty_id = f.id
         ORDER BY c.name ASC"
    );

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=pkn-communities-' . gmdate('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, array(
        'community_id',
        'name',
        'shortdescription',
        'description',
        'faculty',
        'webpage',
        'facebook',
        'instagram',
        'discord',
        'inne',
        'mail',
        'logo',
        'tags',
        'status',
    ), '|');

    foreach ($rows as $row) {
        $tag_names = wp_list_pluck(sc_get_community_tags($row->community_id), 'tag_name');
        fputcsv($output, array(
            $row->community_id,
            $row->name,
            $row->shortdescription,
            $row->description,
            $row->faculty_name,
            $row->webpage,
            $row->facebook,
            $row->instagram,
            $row->discord,
            str_replace("\n", ', ', (string) ($row->other_links ?? '')),
            $row->contact_email,
            $row->logo,
            implode(', ', $tag_names),
            $row->is_archived ? -1 : ($row->status === 'active' ? 1 : ($row->status === 'limited' ? 0.5 : 0)),
        ), '|');
    }

    fclose($output);
    exit;
}
add_action('admin_init', 'sc_handle_communities_export');

function sc_handle_accounts_export() {
    if (!isset($_GET['sc_export_accounts']) || $_GET['sc_export_accounts'] !== '1') { return; }
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'sc_export_accounts')) { return; }
    if (!sc_is_superadmin()) { return; }

    $users = get_users(array('orderby' => 'display_name', 'order' => 'ASC'));
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=pkn-accounts-' . gmdate('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('username','email','community_id','password'), '|');
    foreach ($users as $user) {
        foreach ((array) $user->roles as $role) {
            if (substr($role, -6) === '-admin') {
                fputcsv($output, array($user->user_login, $user->user_email, substr($role,0,-6), ''), '|');
            }
        }
    }
    fclose($output);
    exit;
}
add_action('admin_init', 'sc_handle_accounts_export');


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

function sc_render_dashboard_page() {
    include SC_PLUGIN_PATH . 'templates/dashboard.php';
}

function sc_render_statistics_page() {
    if (!sc_user_can_edit_any_community()) {
        echo '<div class="wrap"><p>' . esc_html__('Access denied.', 'science-communities') . '</p></div>';
        return;
    }
    include SC_PLUGIN_PATH . 'templates/community-statistics.php';
}
