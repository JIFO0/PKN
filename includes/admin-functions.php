<?php
/**
 * Admin functions for Science Communities plugin
 *
 * Handles administrative functions like community ID generation,
 * permission checks, and update logic.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if the current user has permission to edit a specific community
 *
 * @param string $community_id The community ID to check permissions for
 * @return boolean True if user has permission, false otherwise
 */
function sc_user_can_edit_community($community_id) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return false;
    }
    
    $current_user = wp_get_current_user();
    
    // Check if user has superadmin tag (can edit all communities)
    if (in_array('superadmin', (array) $current_user->roles)) {
        return true;
    }
    
    // Check if user has the specific community admin role (e.g., "iqfvz-admin")
    $community_admin_role = $community_id . '-admin';
    if (in_array($community_admin_role, (array) $current_user->roles)) {
        return true;
    }
    
    return false;
}

/**
 * Check if the current user can edit any community
 *
 * @return boolean True if user can edit at least one community, false otherwise
 */
function sc_user_can_edit_any_community() {
    if (!is_user_logged_in()) {
        return false;
    }
    
    $current_user = wp_get_current_user();
    
    // Check if user has superadmin role
    if (in_array('superadmin', (array) $current_user->roles)) {
        return true;
    }
    
    // Check if user has any community admin roles
    $user_roles = (array) $current_user->roles;
    foreach ($user_roles as $role) {
        if (strlen($role) === 11 && substr($role, -6) === '-admin') {
            return true;
        }
    }
    
    return false;
}

/**
 * Get a list of communities that the current user can edit
 *
 * @return array List of community IDs, names, and edit URLs
 */
function sc_get_editable_communities() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'science_communities';
    $current_user = wp_get_current_user();
    $communities = array();
    
    // If superadmin, get all communities
    if (in_array('superadmin', (array) $current_user->roles)) {
        $communities = $wpdb->get_results(
            "SELECT community_id, name FROM $table_name ORDER BY name ASC",
            ARRAY_A
        );
    } else {
        // Get user roles
        $user_roles = (array) $current_user->roles;
        
        // Filter roles to find community admin roles (ending with '-admin')
        $admin_roles = array_filter($user_roles, function($role) {
            return (strlen($role) === 11 && substr($role, -6) === '-admin');
        });
        
        // Extract community IDs from admin roles
        $community_ids = array();
        foreach ($admin_roles as $role) {
            $community_ids[] = substr($role, 0, 5);
        }
        
        // If user has community admin roles, get those communities
        if (!empty($community_ids)) {
            $placeholders = implode(',', array_fill(0, count($community_ids), '%s'));
            $communities = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT community_id, name FROM $table_name WHERE community_id IN ($placeholders) ORDER BY name ASC",
                    $community_ids
                ),
                ARRAY_A
            );
        }
    }

    // Add edit URLs to communities
    foreach ($communities as &$community) {
        $community['edit_url'] = add_query_arg(
            array(
                'action' => 'edit',
                'id' => $community['community_id']
            ),
            sc_get_admin_page_url()
        );
    }
    
    return $communities;
}

/**
 * Save updated community details to the database
 *
 * @param array $data Community data to save
 * @return boolean|string True on success, error message on failure
 */
function sc_save_community($data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'science_communities';

    // Validate required fields
    if (empty($data['name'])) {
        return __('Name is required.', 'science-communities');
    }

    // Sanitize input data
    $sanitized = array(
        'name' => sanitize_text_field($data['name']),
        'shortdescription' => sanitize_textarea_field($data['shortdescription']),
        'description' => wp_kses_post($data['description'] ?? ''),
        'webpage' => esc_url_raw($data['webpage'] ?? ''),
        'facebook' => esc_url_raw($data['facebook'] ?? ''),
        'instagram' => esc_url_raw($data['instagram'] ?? ''),
        'tiktok' => esc_url_raw($data['tiktok'] ?? ''),
        'discord' => esc_url_raw($data['discord'] ?? ''),
        'other_links' => sc_sanitize_links_list($data['other_links'] ?? ''),
        'logo' => esc_url_raw($data['logo'] ?? ''),
        'contact_email' => sanitize_email($data['contact_email'] ?? ''),
        'open_for_applications' => isset($data['open_for_applications']) ? intval((bool) $data['open_for_applications']) : 1,
        'faculty_id' => isset($data['faculty_id']) && !empty($data['faculty_id']) ? intval($data['faculty_id']) : null,
        'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active',
        'is_archived' => isset($data['is_archived']) ? intval((bool) $data['is_archived']) : 0,
    );

    // Archiving can only be edited by superadmins.
    if (!sc_is_superadmin()) {
        unset($sanitized['is_archived']);
    }

    // Check if we're updating an existing community
    if (!empty($data['community_id'])) {
        $community_id = sanitize_text_field($data['community_id']);

        // Check if user has permission to edit this community
        if (!sc_user_can_edit_community($community_id)) {
            return 'You do not have permission to edit this community';
        }

        // Update existing record
        $result = $wpdb->update(
            $table_name,
            $sanitized,
            array('community_id' => $community_id)
        );

        if ($result === false) {
            return 'Database error: ' . $wpdb->last_error;
        }

        // Update tags using the proper relationship table function
        if (isset($data['tags']) && is_array($data['tags'])) {
            sc_update_community_tags($community_id, $data['tags']);
        }

        if (isset($data['event_images']) && is_array($data['event_images'])) {
            sc_save_community_images($community_id, 'event', $data['event_images']);
        }
        if (isset($data['team_images']) && is_array($data['team_images'])) {
            sc_save_community_images($community_id, 'team', $data['team_images']);
        }
        if (isset($data['gallery_images']) && is_array($data['gallery_images'])) {
            sc_save_community_images($community_id, 'gallery', $data['gallery_images']);
        }

        return true;
    }

    // New community - only superadmins can create
    if (!sc_is_superadmin()) {
        return 'You do not have permission to create communities';
    }

    $community_id = sc_generate_community_id();
    $sanitized['community_id'] = $community_id;

    // Insert new record
    $result = $wpdb->insert($table_name, $sanitized);
    if (!$result) {
        return 'Database error: ' . $wpdb->last_error;
    }

    // Ensure dedicated community admin role exists for future assignment
    sc_register_community_admin_role($community_id);

    // Add tags
    if (isset($data['tags']) && is_array($data['tags'])) {
        sc_update_community_tags($community_id, $data['tags']);
    }

    if (isset($data['event_images']) && is_array($data['event_images'])) {
        sc_save_community_images($community_id, 'event', $data['event_images']);
    }
    if (isset($data['team_images']) && is_array($data['team_images'])) {
        sc_save_community_images($community_id, 'team', $data['team_images']);
    }
    if (isset($data['gallery_images']) && is_array($data['gallery_images'])) {
        sc_save_community_images($community_id, 'gallery', $data['gallery_images']);
    }

    return true;
}


/**
 * Register a new user role for community admins
 *
 * @param string $community_id The community ID
 * @return boolean True on success
 */
function sc_register_community_admin_role($community_id) {
    $role_name = $community_id . '-admin';
    
    // Check if role already exists
    if (!get_role($role_name)) {
        // Add role with same capabilities as subscribers but with a custom name
        add_role(
            $role_name,
            'Admin of ' . $community_id,
            array(
                'read' => true,
                'edit_community_' . $community_id => true
            )
        );
    }
    
    return true;
}

/**
 * Get community data by ID
 *
 * @param string $community_id The community ID
 * @return array|false Community data or false if not found
 */
function sc_get_community_by_id($community_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'science_communities';
    
    $community = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE community_id = %s",
            $community_id
        ),
        ARRAY_A
    );
    
    if ($community) {
        // Get tags for this community
        $tags_table = $wpdb->prefix . 'science_tags';
        $rel_table = $wpdb->prefix . 'science_community_tags';
        
        $tags = $wpdb->get_col($wpdb->prepare(
            "SELECT t.tag_name 
            FROM $tags_table t
            INNER JOIN $rel_table r ON t.id = r.tag_id
            WHERE r.community_id = %s
            ORDER BY t.tag_name ASC",
            $community_id
        ));
        
        $community['tags'] = $tags;
    }
    
    return $community;
}

/**
 * Check if user can perform an edit action right now (rate limiting for edits)
 *
 * @param int $user_id User ID
 * @param string $community_id Community ID being edited, or 'new' for creating
 * @return array Array with 'allowed' (bool) and 'message' (string)
 */
function sc_can_user_edit_now($user_id, $community_id = '') {
    global $wpdb;
    $table_audit = $wpdb->prefix . 'science_communities_audit';
    
    // Superadmins have no limits
    $user = get_user_by('id', $user_id);
    if ($user && in_array('superadmin', (array) $user->roles)) {
        return array('allowed' => true, 'message' => '');
    }
    
    // Check edits in last hour (prevent spam/abuse)
    $edits_last_hour = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_audit 
        WHERE admin_user_id = %d 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        $user_id
    ));
    
    // Allow up to 10 edits per hour for regular admins
    if ($edits_last_hour >= 10) {
        return array(
            'allowed' => false,
            'message' => __('Rate limit exceeded. You can make up to 10 edits per hour. Please try again later.', 'science-communities')
        );
    }
    
    return array('allowed' => true, 'message' => '');
}

/**
 * Check if user can upload files (rate limiting)
 *
 * @param int $user_id The user ID
 * @return array Array with 'can_upload' boolean and 'uploads_today' count
 */
function sc_can_user_upload($user_id) {
    global $wpdb;
    $table_uploads = $wpdb->prefix . 'science_community_uploads';
    
    // Superadmins have no limits
    $user = get_user_by('id', $user_id);
    if ($user && in_array('superadmin', (array) $user->roles)) {
        return array('can_upload' => true, 'uploads_today' => 0, 'is_superadmin' => true);
    }
    
    // Check uploads in last 24 hours
    $uploads_today = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_uploads 
        WHERE uploaded_by = %d 
        AND uploaded_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        $user_id
    ));
    
    return array(
        'can_upload' => ($uploads_today < 12),
        'uploads_today' => intval($uploads_today),
        'is_superadmin' => false
    );
}

/**
 * Handle file upload for community logos
 *
 * @param array $file The $_FILES array element
 * @param int $user_id The user ID uploading
 * @return array Array with 'success', 'url', and 'message'
 */
function sc_handle_logo_upload($file, $user_id) {
    // Check if user can upload
    $upload_check = sc_can_user_upload($user_id);
    if (!$upload_check['can_upload']) {
        return array(
            'success' => false,
            'message' => sprintf(
                __('Upload limit reached. You have uploaded %d files in the last 24 hours. Maximum is 12.', 'science-communities'),
                $upload_check['uploads_today']
            )
        );
    }
    
    // Check file errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return array('success' => false, 'message' => __('File upload error.', 'science-communities'));
    }
    
    // Validate file type
    $allowed_types = array('image/png', 'image/jpeg', 'image/jpg', 'image/webp');
    // Validate MIME type using multiple methods
    // Validate MIME type using multiple methods with fallback for XAMPP
    $mime_type = false;
    if (function_exists('finfo_open') && function_exists('finfo_file')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    // Fallback to WordPress function if finfo failed
    if ($mime_type === false) {
        $wp_filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $mime_type = $wp_filetype['type'];
    }

    // Final fallback to getimagesize
    if (!$mime_type || $mime_type === 'application/octet-stream') {
        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info && isset($image_info['mime'])) {
            $mime_type = $image_info['mime'];
        }
    }

    // Also check file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = array('png', 'jpg', 'jpeg', 'webp');
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return array('success' => false, 'message' => __('Invalid file extension.', 'science-communities'));
    }
    
    if (!in_array($mime_type, $allowed_types)) {
        return array('success' => false, 'message' => __('Invalid file type. Only PNG, JPG, JPEG, and WebP are allowed.', 'science-communities'));
    }
    
    // Validate file size (2MB max)
    if ($file['size'] > 2 * 1024 * 1024) {
        return array('success' => false, 'message' => __('File too large. Maximum size is 2MB.', 'science-communities'));
    }
    
    // Check image dimensions
    $image_info = getimagesize($file['tmp_name']);
    if (!$image_info) {
        return array('success' => false, 'message' => __('Invalid image file.', 'science-communities'));
    }
    
    if ($image_info[0] > 2048 || $image_info[1] > 2048) {
        return array('success' => false, 'message' => __('Image dimensions too large. Maximum is 2048x2048 pixels.', 'science-communities'));
    }
    
    // Use WordPress upload handler
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    
    $upload_overrides = array('test_form' => false);
    $movefile = wp_handle_upload($file, $upload_overrides);
    
    if ($movefile && !isset($movefile['error'])) {
        // Log upload in database
        global $wpdb;
        $table_uploads = $wpdb->prefix . 'science_community_uploads';
        
        $wpdb->insert(
            $table_uploads,
            array(
                'filename' => basename($movefile['file']),
                'filepath' => $movefile['file'],
                'filesize' => $file['size'],
                'uploaded_by' => $user_id
            )
        );
        
        return array(
            'success' => true,
            'url' => $movefile['url'],
            'message' => __('File uploaded successfully.', 'science-communities')
        );
    } else {
        return array(
            'success' => false,
            'message' => $movefile['error']
        );
    }
}


/**
 * Normalize CSV header labels to plugin field names.
 *
 * @param string $header
 * @return string
 */
function sc_normalize_import_header($header) {
    $header = (string) $header;
    $header = preg_replace('/^\xEF\xBB\xBF/u', '', $header);
    $header = strtolower(trim($header));
    $header = preg_replace('/^ï»¿/', '', $header);
    $header = preg_replace('/\s+/', '_', $header);

    $aliases = array(
        'nazwa' => 'name',
        'nazwa_kola' => 'name',
        'community_name' => 'name',
        'short_description' => 'shortdescription',
        'opis_krotki' => 'shortdescription',
        'opis' => 'description',
        'faculty_name' => 'faculty',
        'wydzial' => 'faculty',
        'www' => 'webpage',
        'strona' => 'webpage',
        'strona_www' => 'webpage',
        'inne' => 'other_links',
        'other' => 'other_links',
        'other_links' => 'other_links',
        'mail' => 'contact_email',
        'email' => 'contact_email',
    );

    return isset($aliases[$header]) ? $aliases[$header] : $header;
}

function sc_import_log($message) {
    $upload_dir = wp_upload_dir();
    $log_file = trailingslashit($upload_dir['basedir']) . 'sc-community-import.log';
    $timestamp = current_time('mysql');
    error_log('[SC IMPORT] ' . $message);
    @file_put_contents($log_file, '[' . $timestamp . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Detect CSV delimiter from first line.
 *
 * @param string $file_path
 * @return string
 */
function sc_detect_csv_delimiter($file_path) {
    $sample = (string) file_get_contents($file_path, false, null, 0, 4096);

    $delimiters = array(',', ';', "	", '|');
    $best = ',';
    $best_count = -1;

    foreach ($delimiters as $delimiter) {
        $count = substr_count($sample, $delimiter);
        if ($count > $best_count) {
            $best_count = $count;
            $best = $delimiter;
        }
    }

    return $best;
}

/**
 * Check if imported row has no data.
 *
 * @param array $row
 * @return bool
 */
function sc_is_empty_import_row($row) {
    foreach ((array) $row as $cell) {
        if (trim((string) $cell) !== '') {
            return false;
        }
    }

    return true;
}



function sc_sanitize_links_list($links_raw) {
    $links_raw = is_array($links_raw) ? implode(',', $links_raw) : (string) $links_raw;
    $links_raw = trim($links_raw);
    if ($links_raw === '') {
        return '';
    }

    $parts = preg_split('/[,\n\r]+/', $links_raw);
    $links = array();
    foreach ((array) $parts as $part) {
        $url = esc_url_raw(trim((string) $part));
        if ($url !== '') {
            $links[] = $url;
        }
    }

    return implode("\n", array_values(array_unique($links)));
}

function sc_get_links_list($links_raw) {
    $links_raw = (string) $links_raw;
    if (trim($links_raw) === '') {
        return array();
    }

    $parts = preg_split('/[,\n\r]+/', $links_raw);
    $links = array();
    foreach ((array) $parts as $part) {
        $url = esc_url(trim((string) $part));
        if ($url !== '') {
            $links[] = $url;
        }
    }

    return array_values(array_unique($links));
}

function sc_parse_import_status($status_raw) {
    $status_raw = trim(str_replace(',', '.', (string) $status_raw));
    if ($status_raw === '') {
        return array('status' => 'active', 'is_archived' => 0);
    }

    if (is_numeric($status_raw)) {
        $status_value = (float) $status_raw;
        if ($status_value < 0) {
            return array('status' => 'suspended', 'is_archived' => 1);
        }
        if ($status_value == 0.0) {
            return array('status' => 'suspended', 'is_archived' => 0);
        }
        if ($status_value > 0.0 && $status_value < 1.0) {
            return array('status' => 'limited', 'is_archived' => 0);
        }
        return array('status' => 'active', 'is_archived' => 0);
    }

    $status_key = sanitize_key($status_raw);
    if (in_array($status_key, array('active', 'limited', 'suspended', 'inactive'), true)) {
        return array('status' => $status_key, 'is_archived' => 0);
    }

    return array('status' => 'active', 'is_archived' => 0);
}

function sc_record_update_history($args) {
    global $wpdb;
    $table = $wpdb->prefix . 'science_communities_update_history';

    $defaults = array(
        'actor_name' => '',
        'actor_user_id' => get_current_user_id(),
        'action' => 'import',
        'filename' => '',
        'communities_created' => 0,
        'communities_updated' => 0,
        'communities_deleted' => 0,
        'communities_skipped' => 0,
        'notes' => '',
    );
    $args = wp_parse_args((array) $args, $defaults);

    $actor_name = sanitize_text_field($args['actor_name']);
    if ($actor_name === '') {
        $user = wp_get_current_user();
        $actor_name = $user && $user->exists() ? $user->display_name : __('Unknown', 'science-communities');
    }

    return (bool) $wpdb->insert($table, array(
        'actor_name' => $actor_name,
        'actor_user_id' => (int) $args['actor_user_id'],
        'action' => sanitize_key($args['action']),
        'filename' => sanitize_file_name($args['filename']),
        'communities_created' => (int) $args['communities_created'],
        'communities_updated' => (int) $args['communities_updated'],
        'communities_deleted' => (int) $args['communities_deleted'],
        'communities_skipped' => (int) $args['communities_skipped'],
        'notes' => sanitize_textarea_field($args['notes']),
    ));
}

function sc_parse_import_tags($tags_raw) {
    $tags_raw = trim((string) $tags_raw);
    if ($tags_raw === '') {
        return array();
    }

    // Supported separators: | , ; /
    $parts = preg_split('/[|,;\/]+/', $tags_raw);
    return sc_normalize_tags_input(array_map('trim', (array) $parts));
}

function sc_cleanup_broken_semicolon_tags() {
    global $wpdb;
    $tags_table = $wpdb->prefix . 'science_tags';
    $relations_table = $wpdb->prefix . 'science_community_tags';

    $broken_ids = $wpdb->get_col("SELECT id FROM $tags_table WHERE tag_name LIKE '%;%'");
    if (empty($broken_ids)) {
        return 0;
    }

    foreach ($broken_ids as $tag_id) {
        $wpdb->delete($relations_table, array('tag_id' => (int) $tag_id), array('%d'));
        $wpdb->delete($tags_table, array('id' => (int) $tag_id), array('%d'));
    }

    return count($broken_ids);
}

/**
 * Import communities from Excel file
 *
 * @param string $file_path Path to the Excel file
 * @return array Result with success status and count
 */
function sc_import_from_excel($file_path, $args = array()) {
    sc_import_log('Import started. File: ' . basename((string) $file_path));
    $removed_broken_tags = sc_cleanup_broken_semicolon_tags();
    if ($removed_broken_tags > 0) {
        sc_import_log('Removed broken tags containing semicolons: ' . (int) $removed_broken_tags);
    }
    // Check if file exists
    if (!file_exists($file_path)) {
        sc_import_log('Import failed: file not found.');
        return array('success' => false, 'message' => __('File not found.', 'science-communities'));
    }
    // Load PhpSpreadsheet library (included in WordPress via SheetJS CDN alternative)
    // For now, we'll use a CSV conversion approach
    
    // Try to read as CSV first
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        sc_import_log('Import failed: could not open file.');
        return array('success' => false, 'message' => __('Could not open file.', 'science-communities'));
    }
    
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle); // No BOM, go back to start
    }

    global $wpdb;
    $communities_table = $wpdb->prefix . 'science_communities';
    $faculties_table = $wpdb->prefix . 'science_faculties';
    $tags_table = $wpdb->prefix . 'science_tags';
    
    $imported_count = 0;
    $row_number = 0;
    $headers = array();
    
    $delimiter = sc_detect_csv_delimiter($file_path);
    sc_import_log('Detected delimiter: "' . $delimiter . '"');
    $updated_count = 0;
    $skipped_missing_name = 0;
    $skipped_zero_id = 0;
    $skipped_count = 0;
    $processed_rows = 0;

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $row_number++;
        
        // First row is headers
        if ($row_number === 1) {
            $headers = array_map('sc_normalize_import_header', $row);
            sc_import_log('Headers: ' . implode(', ', $headers));
            continue;
        }
        
        if (sc_is_empty_import_row($row)) {
            continue;
        }
        $processed_rows++;

        // Create associative array from row
        $data = array();
        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $data[$header] = isset($row[$index]) ? trim((string) $row[$index]) : '';
        }

        $name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
        $provided_community_id = isset($data['community_id']) ? sanitize_text_field($data['community_id']) : '';

        if ($provided_community_id === '0') {
            $skipped_zero_id++;
            $skipped_count++;
            continue;
        }

        // Required fields
        if ($name === '') {
            $skipped_missing_name++;
            $skipped_count++;
            continue; // Skip rows without name
        }
        
        // Handle faculty
        $faculty_id = null;
        if (!empty($data['faculty'])) {
            $faculty_name = $data['faculty'];
            $existing_faculty = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $faculties_table WHERE faculty_name = %s",
                $faculty_name
            ));
            
            if ($existing_faculty) {
                $faculty_id = $existing_faculty;
            } else {
                $wpdb->insert($faculties_table, array('faculty_name' => $faculty_name));
                $faculty_id = $wpdb->insert_id;
            }
        }
        
        $status_data = sc_parse_import_status($data['status'] ?? '');

        // Prepare community data
        $community_data = array(
            'community_id' => $provided_community_id,
            'name' => $name,
            'shortdescription' => isset($data['shortdescription']) ? sanitize_textarea_field($data['shortdescription']) : '',
            'description' => isset($data['description']) ? wp_kses_post($data['description']) : '',
            'webpage' => isset($data['webpage']) ? esc_url_raw($data['webpage']) : '',
            'facebook' => isset($data['facebook']) ? esc_url_raw($data['facebook']) : '',
            'instagram' => isset($data['instagram']) ? esc_url_raw($data['instagram']) : '',
            'tiktok' => isset($data['tiktok']) ? esc_url_raw($data['tiktok']) : '',
            'discord' => isset($data['discord']) ? esc_url_raw($data['discord']) : '',
            'other_links' => sc_sanitize_links_list($data['other_links'] ?? ''),
            'logo' => isset($data['logo']) ? esc_url_raw($data['logo']) : '',
            'contact_email' => isset($data['contact_email']) ? sanitize_email($data['contact_email']) : '',
            'faculty_id' => $faculty_id,
            'status' => $status_data['status'],
            'is_archived' => $status_data['is_archived'],
        );
        
        // Check if community already exists by provided ID, then by name for legacy imports.
        $existing = '';
        if ($provided_community_id !== '') {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT community_id FROM $communities_table WHERE community_id = %s",
                $provided_community_id
            ));
        }
        if (!$existing) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT community_id FROM $communities_table WHERE name = %s",
                $community_data['name']
            ));
        }
        
        $community_id = '';

        if ($existing) {
            // Update existing community
            $community_data['community_id'] = $existing;
            $save_result = sc_save_community($community_data);
            if ($save_result === true) {
                $community_id = $existing;
                $updated_count++;
            } else {
                $skipped_count++;
                sc_import_log('Skipped update for ' . $name . ': ' . (string) $save_result);
            }
        } else {
            // Create new community
            $new_id = sc_create_community($community_data);
            if ($new_id) {
                $community_id = $new_id;
                $imported_count++;
                
                // Register admin role
                sc_register_community_admin_role($new_id);
            }
        }
        
        // Handle tags
        if (!empty($community_id) && !empty($data['tags'])) {
            $tags = sc_parse_import_tags($data['tags']);
            sc_update_community_tags($community_id, $tags);
        }
    }
    
    fclose($handle);
    sc_import_log(sprintf('Import finished. Processed: %d, created: %d, updated: %d, skipped(no name): %d, skipped(id 0): %d', $processed_rows, $imported_count, $updated_count, $skipped_missing_name, $skipped_zero_id));

    sc_record_update_history(array(
        'actor_name' => $args['actor_name'] ?? '',
        'action' => 'import',
        'filename' => $args['filename'] ?? basename((string) $file_path),
        'communities_created' => $imported_count,
        'communities_updated' => $updated_count,
        'communities_deleted' => 0,
        'communities_skipped' => $skipped_count,
        'notes' => trim((string) ($args['upload_info'] ?? '')) !== ''
            ? trim((string) ($args['upload_info'] ?? '')) . "
" . sprintf(__('Processed rows: %d. Skipped without name: %d. Skipped with community_id 0: %d.', 'science-communities'), $processed_rows, $skipped_missing_name, $skipped_zero_id)
            : sprintf(__('Processed rows: %d. Skipped without name: %d. Skipped with community_id 0: %d.', 'science-communities'), $processed_rows, $skipped_missing_name, $skipped_zero_id),
    ));
    
    return array('success' => true, 'count' => $imported_count, 'created' => $imported_count, 'updated' => $updated_count, 'deleted' => 0, 'skipped' => $skipped_count);
}

/**
 * Display community admins table
 */
function sc_display_community_admins_table() {
    global $wpdb;
    $communities_table = $wpdb->prefix . 'science_communities';
    
    // Get all communities
    $communities = $wpdb->get_results("SELECT community_id, name FROM $communities_table ORDER BY name ASC");
    
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . __('Community', 'science-communities') . '</th>';
    echo '<th>' . __('Administrators', 'science-communities') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    
    foreach ($communities as $community) {
        $role_name = $community->community_id . '-admin';
        $admins = get_users(array('role__in' => array($role_name)));
        
        echo '<tr>';
        echo '<td><strong>' . esc_html($community->name) . '</strong><br><small>' . esc_html($community->community_id) . '</small></td>';
        echo '<td>';
        
        if (!empty($admins)) {
            echo '<ul style="margin: 0;">';
            foreach ($admins as $admin) {
                echo '<li>' . esc_html($admin->user_login) . ' (' . esc_html($admin->user_email) . ')</li>';
            }
            echo '</ul>';
        } else {
            echo '<em>' . __('No administrators', 'science-communities') . '</em>';
        }
        
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
}

function sc_get_community_images($community_id, $category = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'science_community_images';

    if ($category) {
        return $wpdb->get_col($wpdb->prepare(
            "SELECT image_url FROM $table WHERE community_id = %s AND category = %s ORDER BY sort_order ASC, id ASC",
            $community_id,
            $category
        ));
    }

    return $wpdb->get_results($wpdb->prepare(
        "SELECT category, image_url FROM $table WHERE community_id = %s ORDER BY category ASC, sort_order ASC, id ASC",
        $community_id
    ), ARRAY_A);
}

function sc_save_community_images($community_id, $category, $image_urls) {
    global $wpdb;
    $table = $wpdb->prefix . 'science_community_images';

    $community_id = sanitize_text_field($community_id);
    $category = sanitize_key($category);
    $image_urls = array_values(array_filter(array_map('esc_url_raw', (array) $image_urls)));

    $wpdb->delete(
        $table,
        array('community_id' => $community_id, 'category' => $category),
        array('%s', '%s')
    );

    foreach ($image_urls as $index => $url) {
        if (empty($url)) {
            continue;
        }
        $wpdb->insert(
            $table,
            array(
                'community_id' => $community_id,
                'category' => $category,
                'image_url' => $url,
                'sort_order' => $index,
            ),
            array('%s', '%s', '%s', '%d')
        );
    }
}

function sc_create_contact_request($community_id, $message, $requester_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'science_contact_requests';

    return $wpdb->insert(
        $table,
        array(
            'community_id' => sanitize_text_field($community_id),
            'message' => sanitize_textarea_field($message),
            'requester_id' => intval($requester_id),
            'status' => 'open',
        ),
        array('%s', '%s', '%d', '%s')
    );
}

/**
 * Handle clearing import/update history while preserving an audit entry.
 */
function sc_handle_clear_update_history() {
    if (!sc_is_superadmin()) {
        wp_die(__('Access denied.', 'science-communities'));
    }

    if (!isset($_POST['sc_clear_update_history_nonce']) || !wp_verify_nonce($_POST['sc_clear_update_history_nonce'], 'sc_clear_update_history')) {
        wp_die(__('Security check failed.', 'science-communities'));
    }

    global $wpdb;
    $history_table = $wpdb->prefix . 'science_communities_update_history';
    $actor_name = isset($_POST['history_actor_name']) ? sanitize_text_field(wp_unslash($_POST['history_actor_name'])) : '';
    $deleted = (int) $wpdb->get_var("SELECT COUNT(*) FROM $history_table");
    $wpdb->query("TRUNCATE TABLE $history_table");

    sc_record_update_history(array(
        'actor_name' => $actor_name,
        'action' => 'history_cleared',
        'communities_deleted' => 0,
        'notes' => sprintf(__('History was cleared. Removed history entries: %d.', 'science-communities'), $deleted),
    ));

    wp_safe_redirect(add_query_arg('history_cleared', '1', admin_url('admin.php?page=pkn-import')));
    exit;
}
add_action('admin_post_sc_clear_update_history', 'sc_handle_clear_update_history');
