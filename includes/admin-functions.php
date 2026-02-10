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
    if (empty($data['name']) || empty($data['shortdescription'])) {
        return 'Name and short description are required';
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
        'logo' => esc_url_raw($data['logo'] ?? ''),
        'faculty_id' => isset($data['faculty_id']) && !empty($data['faculty_id']) ? intval($data['faculty_id']) : null,
        'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'active',
        'is_archived' => isset($data['is_archived']) ? intval((bool) $data['is_archived']) : 0,
    );

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
 * Rate-limit community edit/create operations per user.
 *
 * @param int $user_id
 * @param string $target_context
 * @return array{allowed:bool,message:string}
 */
function sc_can_user_edit_now($user_id, $target_context = '') {
    $user_id = intval($user_id);
    if ($user_id <= 0) {
        return array(
            'allowed' => false,
            'message' => __('You must be logged in to perform this action.', 'science-communities')
        );
    }

    // Superadmins are not rate-limited.
    if (sc_is_superadmin()) {
        return array('allowed' => true, 'message' => '');
    }

    $window_seconds = 20;
    $limit = 5;
    $transient_key = 'sc_edit_window_' . $user_id;
    $count = intval(get_transient($transient_key));

    if ($count >= $limit) {
        return array(
            'allowed' => false,
            'message' => __('Too many save attempts in a short time. Please wait a moment and try again.', 'science-communities')
        );
    }

    set_transient($transient_key, $count + 1, $window_seconds);

    return array('allowed' => true, 'message' => '');
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
        'can_upload' => ($uploads_today < 3),
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
                __('Upload limit reached. You have uploaded %d files in the last 24 hours. Maximum is 3.', 'science-communities'),
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
 * Import communities from Excel file
 *
 * @param string $file_path Path to the Excel file
 * @return array Result with success status and count
 */
function sc_import_from_excel($file_path) {
    // Check if file exists
    if (!file_exists($file_path)) {
        return array('success' => false, 'message' => __('File not found.', 'science-communities'));
    }
    
    // Load PhpSpreadsheet library (included in WordPress via SheetJS CDN alternative)
    // For now, we'll use a CSV conversion approach
    
    // Try to read as CSV first
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return array('success' => false, 'message' => __('Could not open file.', 'science-communities'));
    }
    
    global $wpdb;
    $communities_table = $wpdb->prefix . 'science_communities';
    $faculties_table = $wpdb->prefix . 'science_faculties';
    $tags_table = $wpdb->prefix . 'science_tags';
    
    $imported_count = 0;
    $row_number = 0;
    $headers = array();
    
    while (($row = fgetcsv($handle, 0, ',')) !== FALSE) {
        $row_number++;
        
        // First row is headers
        if ($row_number === 1) {
            $headers = array_map('strtolower', array_map('trim', $row));
            continue;
        }
        
        // Create associative array from row
        $data = array();
        foreach ($headers as $index => $header) {
            $data[$header] = isset($row[$index]) ? trim($row[$index]) : '';
        }
        
        // Required fields
        if (empty($data['name'])) {
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
        
        // Prepare community data
        $community_data = array(
            'name' => $data['name'],
            'shortdescription' => isset($data['shortdescription']) ? $data['shortdescription'] : '',
            'description' => isset($data['description']) ? $data['description'] : '',
            'webpage' => isset($data['webpage']) ? esc_url_raw($data['webpage']) : '',
            'facebook' => isset($data['facebook']) ? esc_url_raw($data['facebook']) : '',
            'instagram' => isset($data['instagram']) ? esc_url_raw($data['instagram']) : '',
            'tiktok' => isset($data['tiktok']) ? esc_url_raw($data['tiktok']) : '',
            'discord' => isset($data['discord']) ? esc_url_raw($data['discord']) : '',
            'logo' => isset($data['logo']) ? esc_url_raw($data['logo']) : '',
            'faculty_id' => $faculty_id
        );
        
        // Check if community already exists by name
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT community_id FROM $communities_table WHERE name = %s",
            $community_data['name']
        ));
        
        if ($existing) {
            // Update existing community
            $community_data['community_id'] = $existing;
            sc_save_community($community_data);
        } else {
            // Create new community
            $new_id = sc_create_community($community_data);
            if ($new_id) {
                $imported_count++;
                
                // Register admin role
                sc_register_community_admin_role($new_id);
            }
        }
        
        // Handle tags
        if (!empty($data['tags'])) {
            $tags = array_map('trim', explode(',', $data['tags']));
            $community_id = $existing ?: $new_id;
            if ($community_id) {
                sc_update_community_tags($community_id, $tags);
            }
        }
    }
    
    fclose($handle);
    
    return array('success' => true, 'count' => $imported_count);
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

