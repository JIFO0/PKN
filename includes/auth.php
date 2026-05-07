<?php
/**
 * Authentication and authorization functions for Science Communities plugin
 *
 * Handles user authentication, role checking, and permission verification
 * for accessing and editing community data.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if current user has the superadmin tag
 *
 * @return boolean True if user has superadmin tag, false otherwise
 */
function sc_is_superadmin() {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return false;
    }
    
    $current_user = wp_get_current_user();
    return in_array('superadmin', (array) $current_user->roles);
}

/**
 * Check if current user is an admin for a specific community
 *
 * @param string $community_id The community ID to check
 * @return boolean True if user is admin for the specified community, false otherwise
 */
function sc_is_community_admin($community_id) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return false;
    }
    
    $current_user = wp_get_current_user();
    $community_admin_role = $community_id . '-admin';
    return in_array($community_admin_role, (array) $current_user->roles);
}

/**
 * Get all communities the current user can administer
 *
 * @return array Array of community IDs the user can administer
 */
function sc_get_user_admin_communities() {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return array();
    }
    
    $current_user = wp_get_current_user();
    $user_roles = (array) $current_user->roles;
    $communities = array();
    
    // If superadmin, get all community IDs
    if (in_array('superadmin', $user_roles)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'science_communities';
        $results = $wpdb->get_col("SELECT community_id FROM $table_name");
        return $results;
    }
    
    // Otherwise, extract community IDs from admin roles
    foreach ($user_roles as $role) {
        if (strlen($role) === 11 && substr($role, -6) === '-admin') {
            $communities[] = substr($role, 0, 5);
        }
    }
    
    return $communities;
}

/**
 * Verify if a request to edit a community is authorized
 *
 * @param string $community_id The community ID to verify access for
 * @param string $nonce_value The nonce value to verify
 * @param string $nonce_action The nonce action
 * @return boolean|string True if authorized, error message if not
 */
function sc_verify_community_edit_request($community_id, $nonce_value, $nonce_action = 'sc_edit_community') {
    // Verify nonce
    if (!wp_verify_nonce($nonce_value, $nonce_action)) {
        return 'Security check failed. Please try again.';
    }
    
    // Check if user can edit this community
    if (!sc_user_can_edit_community($community_id)) {
        return 'You do not have permission to edit this community.';
    }
    
    return true;
}

/**
 * Assign a user as an admin for a specific community
 *
 * @param int $user_id The WordPress user ID
 * @param string $community_id The community ID
 * @return boolean True on success, false on failure
 */
function sc_assign_community_admin($user_id, $community_id) {
    // Validate inputs
    if (empty($user_id) || empty($community_id) || !is_numeric($user_id)) {
        return false;
    }
    
    // Check if the community exists
    global $wpdb;
    $table_name = $wpdb->prefix . 'science_communities';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE community_id = %s",
        $community_id
    ));
    
    if (!$exists) {
        return false;
    }
    
    // Get the user object
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    
    // Make sure the role exists
    $role_name = $community_id . '-admin';
    if (!get_role($role_name)) {
        sc_register_community_admin_role($community_id);
    }
    
    // Assign the role to the user
    $user->add_role($role_name);
    return true;
}

/**
 * Remove a user as an admin for a specific community
 *
 * @param int $user_id The WordPress user ID
 * @param string $community_id The community ID
 * @return boolean True on success, false on failure
 */
function sc_remove_community_admin($user_id, $community_id) {
    // Validate inputs
    if (empty($user_id) || empty($community_id) || !is_numeric($user_id)) {
        return false;
    }
    
    // Get the user object
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    
    // Remove the role from the user
    $role_name = $community_id . '-admin';
    $user->remove_role($role_name);
    return true;
}

/**
 * Check if the current user can access the admin panel
 *
 * @return boolean True if user can access admin panel, false otherwise
 */
function sc_can_access_admin_panel() {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        sc_debug('Access denied: User not logged in');
        return false;
    }
    
    $current_user = wp_get_current_user();
    $user_roles = (array) $current_user->roles;
    
    sc_debug('Checking admin panel access', array(
        'user_id' => $current_user->ID,
        'user_login' => $current_user->user_login,
        'user_roles' => $user_roles
    ));
    
    // If superadmin, allow access
    if (in_array('superadmin', $user_roles)) {
        sc_debug('Access granted: User is superadmin');
        return true;
    }
    
    // Check if user has any community admin roles
    foreach ($user_roles as $role) {
        if (strlen($role) === 11 && substr($role, -6) === '-admin') {
            sc_debug('Access granted: User has community admin role', array('role' => $role));
            return true;
        }
    }
    
    sc_debug('Access denied: No valid roles found', array(
        'user_roles' => $user_roles,
        'checked_for_superadmin' => true,
        'checked_for_community_admin' => true
    ));
    
    return false;
}

/**
 * Create a login form with redirect to admin panel
 *
 * @return string HTML for the login form
 */
function sc_get_login_form() {
    $redirect = add_query_arg(
        array('page' => 'sc-admin-panel'),
        site_url()
    );
    
    $args = array(
        'redirect' => $redirect,
        'form_id' => 'sc-login-form',
        'label_username' => __('Username', 'science-communities'),
        'label_password' => __('Password', 'science-communities'),
        'label_remember' => __('Remember Me', 'science-communities'),
        'label_log_in' => __('Log In', 'science-communities'),
        'echo' => false
    );
    
    return wp_login_form($args);
}

/**
 * Get the current user's display name or username
 *
 * @return string User's display name or empty string if not logged in
 */
function sc_get_current_user_name() {
    if (!is_user_logged_in()) {
        return '';
    }
    
    $current_user = wp_get_current_user();
    return $current_user->display_name ?: $current_user->user_login;
}

