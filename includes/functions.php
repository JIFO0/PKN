<?php
/**
 * General functions for the Science Communities plugin
 * 
 * This file contains functions for searching, retrieving, and displaying communities.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


// Prevent accidental double-loading of this file.
if (defined('SC_FUNCTIONS_FILE_LOADED')) {
    return;
}
define('SC_FUNCTIONS_FILE_LOADED', true);

/**
 * Search communities based on search term and tags with fuzzy matching
 * 
 * @param string $search_term The search term to look for in names and short descriptions
 * @param array $tags Array of tag IDs or names to filter by
 * @param boolean $fuzzy Whether to use fuzzy matching (default: true)
 * @return array Array of community objects
 */
function sc_search_communities($search_term = '', $tags = array(), $fuzzy = true) {
    global $wpdb;
    
    error_log('==== SEARCH COMMUNITIES DEBUG ====');
    error_log('Search term: "' . $search_term . '"');
    error_log('Tags: ' . print_r($tags, true));
    error_log('Fuzzy: ' . ($fuzzy ? 'YES' : 'NO'));

    $communities_table = $wpdb->prefix . 'science_communities';
    $tags_table = $wpdb->prefix . 'science_tags';
    $relationships_table = $wpdb->prefix . 'science_community_tags'; 
    $faculties_table = $wpdb->prefix . 'science_faculties';
    
    // Base query parts
    $select = "SELECT DISTINCT c.* FROM $communities_table AS c";
    $where = array();
    $join = "";
    
    // Handle search term with fuzzy matching
    if (!empty($search_term)) {
        if ($fuzzy && strlen($search_term) >= 3) {
            // For fuzzy search, get all communities and filter in PHP
            // This is more performance-friendly than complex SQL
            $all_communities = $wpdb->get_results("SELECT * FROM $communities_table ORDER BY name ASC");
            $fuzzy_matches = array();
            
            foreach ($all_communities as $community) {
                $name_distance = sc_levenshtein_distance(
                    strtolower($search_term), 
                    strtolower($community->name)
                );
                $desc_distance = sc_levenshtein_distance(
                    strtolower($search_term), 
                    strtolower($community->shortdescription)
                );
                
                // Check if search term appears as substring (exact match)
                $name_contains = stripos($community->name, $search_term) !== false;
                $desc_contains = stripos($community->shortdescription, $search_term) !== false;
                
                // Match if:
                // 1. Exact substring match, OR
                // 2. Levenshtein distance <= 1 for words of similar length
                $search_len = strlen($search_term);
                $name_len = strlen($community->name);
                $desc_len = strlen($community->shortdescription);
                
                if ($name_contains || $desc_contains || 
                    ($name_distance <= 1 && abs($search_len - $name_len) <= 2) ||
                    ($desc_distance <= 1 && abs($search_len - $desc_len) <= 2)) {
                    $fuzzy_matches[] = $community->community_id;
                }
            }
            
            if (!empty($fuzzy_matches)) {
                $placeholders = implode(',', array_fill(0, count($fuzzy_matches), '%s'));
                $where[] = $wpdb->prepare(
                    "c.community_id IN ($placeholders)",
                    $fuzzy_matches
                );
            } else {
                // No matches found
                $where[] = "1=0";
            }
        } else {
            // Standard search without fuzzy matching
            $search_like = '%' . $wpdb->esc_like($search_term) . '%';
            $where[] = $wpdb->prepare("(c.name LIKE %s OR c.shortdescription LIKE %s OR c.description LIKE %s)", $search_like, $search_like, $search_like);
        }
    }
    
    // Add tag filtering if tags are provided
    if (!empty($tags)) {
        $join .= " INNER JOIN $relationships_table AS r ON c.community_id = r.community_id";
        $join .= " INNER JOIN $tags_table AS t ON r.tag_id = t.id";
        
        $tag_conditions = array();
        
        // Handle numeric tag IDs or tag names
        foreach ($tags as $tag) {
            if (is_numeric($tag)) {
                $tag_conditions[] = $wpdb->prepare("t.id = %d", $tag);
            } else {
                $tag_conditions[] = $wpdb->prepare("t.tag_name = %s", $tag);
            }
        }
        
        // If we have multiple tags, ensure communities have ALL selected tags
        if (count($tag_conditions) > 1) {
            $tag_count = count($tag_conditions);
            $tag_condition_sql = implode(' OR ', $tag_conditions);
            
            $where[] = $wpdb->prepare(
                "c.community_id IN (
                    SELECT r1.community_id 
                    FROM $relationships_table AS r1
                    INNER JOIN $tags_table AS t1 ON r1.tag_id = t1.id
                    WHERE $tag_condition_sql
                    GROUP BY r1.community_id
                    HAVING COUNT(DISTINCT t1.id) = %d
                )",
                $tag_count
            );
            
            // Remove the earlier join since we're using a subquery
            $join = "";
        } elseif (count($tag_conditions) == 1) {
            $where[] = $tag_conditions[0];
        }
    }
    
    // Construct the final query
    $query = $select . $join;
    
    if (!empty($where)) {
        $query .= " WHERE " . implode(" AND ", $where);
    }
    
    $query .= " ORDER BY c.name ASC";
    
    // Execute the query
    $results = $wpdb->get_results($query);
    
    error_log('Search SQL: ' . $query);
    error_log('Search SQL error: ' . $wpdb->last_error);
    error_log('Search raw results count: ' . (is_array($results) ? count($results) : 0));

    // Format the results
    $communities = array();
    foreach ($results as $result) {
        $community = array(
            'id' => $result->community_id,
            'name' => $result->name,
            'shortdescription' => $result->shortdescription,
            'logo' => $result->logo,
            'tags' => sc_get_community_tags($result->community_id)
        );
        
        $communities[] = $community;
    }
    
    return $communities;
}


/**
 * Find first published page ID containing a shortcode.
 *
 * @param string $shortcode Shortcode tag without brackets.
 * @return int
 */
function sc_find_page_id_by_shortcode($shortcode) {
    global $wpdb;

    $shortcode = sanitize_key($shortcode);
    if (empty($shortcode)) {
        return 0;
    }

    $page_map = get_option('sc_shortcode_page_map', array());
    if (!empty($page_map[$shortcode])) {
        $mapped_page_id = (int) $page_map[$shortcode];
        $mapped_page = get_post($mapped_page_id);
        if ($mapped_page && $mapped_page->post_type === 'page' && $mapped_page->post_status === 'publish' && has_shortcode((string) $mapped_page->post_content, $shortcode)) {
            return $mapped_page_id;
        }
    }

    $like = '%' . $wpdb->esc_like('[' . $shortcode) . '%';
    $page_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
        $like
    ));

    return empty($page_id) ? 0 : (int) $page_id;
}

/**
 * Resolve URL of the first published page containing a shortcode.
 *
 * @param string $shortcode Shortcode tag without brackets.
 * @param string $fallback  URL returned when no page with shortcode exists.
 * @return string
 */
function sc_get_page_url_by_shortcode($shortcode, $fallback = '') {
    $shortcode = sanitize_key($shortcode);

    if (empty($shortcode)) {
        return $fallback;
    }

    global $wpdb;
    $like = '%' . $wpdb->esc_like('[' . $shortcode) . '%';
    $page_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
        $like
    ));

    $pages = empty($page_id) ? array() : array((int) $page_id);

    if (!empty($pages)) {
        $url = get_permalink((int) $pages[0]);
        if (!empty($url)) {
            return $url;
        }
    }

    return $fallback;
}

/**
 * Calculate Levenshtein distance between two strings
 * Optimized version with early termination
 * 
 * @param string $str1 First string
 * @param string $str2 Second string
 * @param int $max_distance Maximum distance to calculate (for performance)
 * @return int Levenshtein distance
 */
function sc_levenshtein_distance($str1, $str2, $max_distance = 2) {
    $len1 = strlen($str1);
    $len2 = strlen($str2);
    
    // Early termination if difference is too large
    if (abs($len1 - $len2) > $max_distance) {
        return $max_distance + 1;
    }
    
    // Use PHP's built-in levenshtein for short strings (it's optimized in C)
    if ($len1 <= 255 && $len2 <= 255) {
        return levenshtein($str1, $str2);
    }
    
    // For longer strings, use a simplified version
    // This shouldn't happen often with community names
    $matrix = array();
    
    // Initialize first row and column
    for ($i = 0; $i <= $len1; $i++) {
        $matrix[$i][0] = $i;
    }
    for ($j = 0; $j <= $len2; $j++) {
        $matrix[0][$j] = $j;
    }
    
    // Fill the matrix
    for ($i = 1; $i <= $len1; $i++) {
        for ($j = 1; $j <= $len2; $j++) {
            $cost = ($str1[$i - 1] === $str2[$j - 1]) ? 0 : 1;
            $matrix[$i][$j] = min(
                $matrix[$i - 1][$j] + 1,      // deletion
                $matrix[$i][$j - 1] + 1,      // insertion
                $matrix[$i - 1][$j - 1] + $cost  // substitution
            );
        }
    }
    
    return $matrix[$len1][$len2];
}

/**
 * Get a single community by ID
 * 
 * @param string $community_id The community ID
 * @return object|null Community object or null if not found
 */
function sc_get_community($community_id) {
    global $wpdb;
    
    $communities_table = $wpdb->prefix . 'science_communities';
    
    $query = $wpdb->prepare(
        "SELECT * FROM $communities_table WHERE community_id = %s LIMIT 1",
        $community_id
    );
    
    $community = $wpdb->get_row($query);
    
    if ($community) {
        // Add tags to the community object
        $community->tags = sc_get_community_tags($community_id);
    }
    
    return $community;
}

/**
 * Get all tags for a community
 * 
 * @param string $community_id The community ID
 * @return array Array of tag objects with id and name
 */
function sc_get_community_tags($community_id) {
    global $wpdb;
    
    $tags_table = $wpdb->prefix . 'science_tags';
    $relationships_table = $wpdb->prefix . 'science_community_tags';
    
    $query = $wpdb->prepare(
        "SELECT t.id, t.tag_name 
        FROM $tags_table AS t
        INNER JOIN $relationships_table AS r ON t.id = r.tag_id
        WHERE r.community_id = %s
        ORDER BY t.tag_name ASC",
        $community_id
    );
    
    return $wpdb->get_results($query);
}

/**
 * Get all available tags
 * 
 * @return array Array of all tag objects
 */
function sc_get_all_tags() {
    global $wpdb;
    
    $tags_table = $wpdb->prefix . 'science_tags';
    
    $query = "SELECT id, tag_name FROM $tags_table ORDER BY tag_name ASC";
    
    return $wpdb->get_results($query);
}

/**
 * Generate a random 5-character community ID
 * 
 * @return string A unique 5-character ID
 */
function sc_generate_community_id() {
    global $wpdb;
    
    $communities_table = $wpdb->prefix . 'science_communities';
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $char_length = strlen($characters) - 1;
    
    // Try up to 10 times to generate a unique ID
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $id = '';
        
        // Generate a random 5-character string
        for ($i = 0; $i < 5; $i++) {
            $id .= $characters[mt_rand(0, $char_length)];
        }
        
        // Check if this ID already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $communities_table WHERE community_id = %s",
            $id
        ));
        
        // If it doesn't exist, return it
        if ($exists == 0) {
            return $id;
        }
    }
    
    // If we couldn't generate a unique ID after 10 attempts, add a timestamp
    // This should be extremely rare
    return substr($characters[mt_rand(0, $char_length)] . time(), 0, 5);
}

/**
 * Create a new community
 * 
 * @param array $community_data Array of community data
 * @return string|bool The new community ID on success, false on failure
 */
function sc_create_community($community_data) {
    global $wpdb;
    
    $communities_table = $wpdb->prefix . 'science_communities';
    
    // Generate a unique community ID
    $community_id = sc_generate_community_id();
    
    // Prepare data for insertion
    $data = array(
        'community_id' => $community_id,
        'name' => sanitize_text_field($community_data['name']),
        'shortdescription' => sanitize_textarea_field($community_data['shortdescription'] ?? ''),
        'description' => sanitize_textarea_field($community_data['description'] ?? ''),
        'webpage' => esc_url_raw($community_data['webpage'] ?? ''),
        'facebook' => esc_url_raw($community_data['facebook'] ?? ''),
        'instagram' => esc_url_raw($community_data['instagram'] ?? ''),
        'tiktok' => esc_url_raw($community_data['tiktok'] ?? ''),
        'discord' => esc_url_raw($community_data['discord'] ?? ''),
        'logo' => esc_url_raw($community_data['logo'] ?? ''),
    );
    
    // Insert the community
    $result = $wpdb->insert($communities_table, $data);
    
    if (!$result) {
        return false;
    }
    
    // Process tags if provided
    if (!empty($community_data['tags'])) {
        sc_update_community_tags($community_id, $community_data['tags']);
    }
    
    return $community_id;
}

/**
 * Update an existing community
 * 
 * @param array $community_data Array of community data
 * @return bool True on success, false on failure
 */
function sc_update_community($community_data) {
    global $wpdb;
    
    $communities_table = $wpdb->prefix . 'science_communities';
    
    // Get the community ID
    $community_id = sanitize_text_field($community_data['community_id']);
    
    // Prepare data for update
    $data = array();
    $fields = array(
        'name', 'shortdescription', 'description', 'webpage', 
        'facebook', 'instagram', 'tiktok', 'discord', 'logo'
    );
    
    foreach ($fields as $field) {
        if (isset($community_data[$field])) {
            if ($field === 'webpage' || $field === 'facebook' || $field === 'instagram' || 
                $field === 'tiktok' || $field === 'discord' || $field === 'logo') {
                $data[$field] = esc_url_raw($community_data[$field]);
            } elseif ($field === 'shortdescription' || $field === 'description') {
                $data[$field] = sanitize_textarea_field($community_data[$field]);
            } else {
                $data[$field] = sanitize_text_field($community_data[$field]);
            }
        }
    }
    
    // Only update if there's data to update
    if (!empty($data)) {
        $result = $wpdb->update(
            $communities_table,
            $data,
            array('community_id' => $community_id)
        );
        
        if ($result === false) {
            return false;
        }
    }
    
    // Process tags if provided
    if (isset($community_data['tags'])) {
        sc_update_community_tags($community_id, $community_data['tags']);
    }
    
    return true;
}

/**
 * Update tags for a community
 * 
 * @param string $community_id The community ID
 * @param array $tags Array of tag IDs or tag names
 * @return bool True on success
 */
function sc_update_community_tags($community_id, $tags) {
    global $wpdb;
    
    $tags_table = $wpdb->prefix . 'science_tags';
    $relationships_table = $wpdb->prefix . 'science_community_tags';

    $wpdb->delete(
        $relationships_table,
        array('community_id' => $community_id)
    );
    
    foreach ($tags as $tag) {
        $tag_id = null;
        
        // If the tag is numeric, treat it as a tag ID
        if (is_numeric($tag)) {
            $tag_id = intval($tag);
            
            // Verify the tag exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $tags_table WHERE id = %d",
                $tag_id
            ));
            
            if ($exists == 0) {
                continue; // Skip if tag doesn't exist
            }
        } else {
            // Otherwise, treat it as a tag name
            $tag_name = sanitize_text_field($tag);
            
            // Check if the tag already exists
            $existing_tag = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $tags_table WHERE tag_name = %s",
                $tag_name
            ));
            
            if ($existing_tag) {
                $tag_id = $existing_tag;
            } else {
                // Create the tag if it doesn't exist
                $wpdb->insert(
                    $tags_table,
                    array('tag_name' => $tag_name)
                );
                
                $tag_id = $wpdb->insert_id;
            }
        }
        
        // Add the relationship
        if ($tag_id) {
            $wpdb->insert(
                $relationships_table,
                array(
                    'community_id' => $community_id,
                    'tag_id' => $tag_id
                )
            );
        }
    }
    
    return true;
}

/**
 * Delete a community
 * 
 * @param string $community_id The community ID
 * @return bool True on success, false on failure
 */
function sc_delete_community($community_id) {
    global $wpdb;
    
    $communities_table = $wpdb->prefix . 'science_communities';
    
    // Delete the community (relationships will be deleted via foreign key constraints)
    $result = $wpdb->delete(
        $communities_table,
        array('community_id' => $community_id)
    );
    
    return $result !== false;
}

/**
 * Format a community for display
 * 
 * @param object $community The community object
 * @return array Formatted community array
 */
function sc_format_community_for_display($community) {
    if (!$community) {
        return null;
    }
    
    return array(
        'id' => $community->community_id,
        'name' => $community->name,
        'shortdescription' => $community->shortdescription,
        'description' => $community->description,
        'webpage' => $community->webpage,
        'facebook' => $community->facebook,
        'instagram' => $community->instagram,
        'tiktok' => $community->tiktok,
        'discord' => $community->discord,
        'logo' => $community->logo,
        'tags' => isset($community->tags) ? $community->tags : sc_get_community_tags($community->community_id)
    );
}
/**
 * Get all available faculties
 * 
 * @return array Array of faculty objects
 */
function sc_get_all_faculties() {
    global $wpdb;
    $faculties_table = $wpdb->prefix . 'science_faculties';
    
    return $wpdb->get_results(
        "SELECT id, faculty_name FROM $faculties_table ORDER BY 
        CASE 
            WHEN faculty_name = 'Mi�dzywydzia�owe' THEN 1
            WHEN faculty_name = 'Mi�dzyuczelniane' THEN 2
            ELSE 0
        END,
        faculty_name ASC"
    );
}

/**
 * Get display name for status
 * 
 * @param string $status The status value
 * @param int $is_archived Whether community is archived
 * @return string Display name
 */
function sc_get_status_display($status, $is_archived = 0) {
    if ($is_archived) {
        return __('Archiwalne', 'science-communities');
    }
    
    $statuses = array(
        'active' => __('Dzia�a', 'science-communities'),
        'limited' => __('Ograniczono dzia�alno��', 'science-communities'),
        'suspended' => __('Zawieszono', 'science-communities')
    );
    
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}

/**
 * Get all available statuses
 * 
 * @param bool $include_archived Whether to include archived option
 * @return array Array of status options
 */
function sc_get_all_statuses($include_archived = true) {
    $statuses = array(
        'active' => __('Dzia�a', 'science-communities'),
        'limited' => __('Ograniczono dzia�alno��', 'science-communities'),
        'suspended' => __('Zawieszono', 'science-communities')
    );
    
    if ($include_archived) {
        $statuses['archived'] = __('Archiwalne', 'science-communities');
    }
    
    return $statuses;
}

/**
 * Get faculty name by ID
 * 
 * @param int $faculty_id The faculty ID
 * @return string Faculty name or empty string
 */
function sc_get_faculty_name($faculty_id) {
    if (empty($faculty_id)) {
        return '';
    }
    
    global $wpdb;
    $faculties_table = $wpdb->prefix . 'science_faculties';
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT faculty_name FROM $faculties_table WHERE id = %d",
        $faculty_id
    ));
}
