<?php
/**
 * Template for the main admin panel
 *
 * Lists the science communities the logged-in user can edit
 */
 error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!defined('ABSPATH')) {
    die('Direct access not permitted');
}

echo "<!-- Admin panel loading... -->\n";

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
// Debug: Log that admin panel is being loaded
sc_debug('Admin panel template loaded', array(
    'user_id' => get_current_user_id(),
    'is_logged_in' => is_user_logged_in(),
    'get_params' => $_GET,
    'post_params' => isset($_POST) ? array_keys($_POST) : array()
));

// Simplified check for testing
if (!is_user_logged_in()) {
    echo '<div class="sc-admin-login">';
    echo '<h1>Please Log In</h1>';
    echo sc_get_login_form();
    echo '</div>';
    return;
}

if (!sc_is_superadmin()) {
    echo '<div class="sc-no-access">';
    echo '<h1>Access Denied</h1>';
    echo '<p>You need superadmin role. Current user ID: ' . get_current_user_id() . '</p>';
    echo '<p>Roles: ' . implode(', ', wp_get_current_user()->roles) . '</p>';
    echo '</div>';
    return;
}

echo '<p>Access checks passed! Getting communities...</p>';


if (!sc_can_access_admin_panel()) {
    // Display login form if not logged in
    if (!is_user_logged_in()) {
        ?>
        <div class="sc-admin-login">
            <div class="sc-admin-login-header">
                <h1><?php _e('Science Communities Admin', 'science-communities'); ?></h1>
                <p><?php _e('Please log in to access the admin panel.', 'science-communities'); ?></p>
            </div>
            <?php echo sc_get_login_form(); ?>
        </div>
        <?php
        return;
    } else {
        // Display no access message
        ?>
        <div class="sc-no-access">
            <h1><?php _e('Access Denied', 'science-communities'); ?></h1>
            <p><?php _e('You do not have permission to access the Science Communities admin panel.', 'science-communities'); ?></p>
            <p><a href="<?php echo esc_url(home_url()); ?>"><?php _e('Return to Home', 'science-communities'); ?></a></p>
        </div>
        <?php
        return;
    }
}

// Get communities the current user can edit
$editable_communities = sc_get_editable_communities();
$is_superadmin = sc_is_superadmin();
$user_name = sc_get_current_user_name();
?>

<div class="sc-admin-panel">
    <div class="sc-admin-header">
        <h1><?php _e('Science Communities Admin', 'science-communities'); ?></h1>
        <div class="sc-admin-user-info">
            <span class="sc-admin-welcome">
                <?php printf(__('Welcome, %s', 'science-communities'), esc_html($user_name)); ?>
            </span>
            <?php if ($is_superadmin): ?>
            <span class="sc-admin-role">
                <?php _e('Super Administrator', 'science-communities'); ?>
            </span>
            <?php endif; ?>
            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="sc-admin-logout">
                <?php _e('Log Out', 'science-communities'); ?>
            </a>
        </div>
    </div>      
    </div>
    <?php if ($is_superadmin): ?>
    <div class="sc-admin-actions">
        <a href="<?php echo esc_url(add_query_arg('action', 'manage-users', sc_get_admin_page_url())); ?>" class="sc-admin-manage-users" style="flex: 1; text-align: center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <?php _e('Manage Users', 'science-communities'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('action', 'add', sc_get_admin_page_url())); ?>" class="sc-admin-add-new" style="flex: 1; text-align: center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="16"></line>
                <line x1="8" y1="12" x2="16" y2="12"></line>
            </svg>
            <?php _e('Add New Community', 'science-communities'); ?>
        </a>
        <?php endif; ?>
    </div>
    
    <div class="sc-admin-content">
        <div class="sc-admin-communities">
            <h2 class="sc-admin-section-title">
                <?php 
                if ($is_superadmin) {
                    _e('All Science Communities', 'science-communities');
                } else {
                    _e('Your Science Communities', 'science-communities');
                }
                ?>
            </h2>
            
            <?php if (empty($editable_communities)): ?>
            <div class="sc-admin-no-communities">
                <p><?php _e('No communities available for you to edit.', 'science-communities'); ?></p>
                <?php if (!$is_superadmin): ?>
                <p><?php _e('Contact a super administrator if you need access to manage a science community.', 'science-communities'); ?></p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="sc-admin-communities-list">
                <table class="sc-admin-table">
                    <thead>
                        <tr>
                            <th class="sc-admin-table-id"><?php _e('ID', 'science-communities'); ?></th>
                            <th class="sc-admin-table-name"><?php _e('Community Name', 'science-communities'); ?></th>
                            <th class="sc-admin-table-actions"><?php _e('Actions', 'science-communities'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($editable_communities as $community): ?>
                        <tr>
                            <td class="sc-admin-table-id"><?php echo esc_html($community['community_id']); ?></td>
                            <td class="sc-admin-table-name"><?php echo esc_html($community['name']); ?></td>
                            <td class="sc-admin-table-actions">
                                <div class="sc-admin-action-buttons">
                                    <a href="<?php echo esc_url($community['edit_url']); ?>" class="sc-admin-edit-link">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        <span class="sc-admin-action-text"><?php _e('Edit', 'science-communities'); ?></span>
                                    </a>
                                    
                                    <a href="<?php echo esc_url(add_query_arg(
                                            array('id' => $community['community_id']),
                                            site_url('/details/')
                                        )); ?>" class="sc-admin-view-link" target="_blank">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        <span class="sc-admin-action-text"><?php _e('View', 'science-communities'); ?></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Admin panel loaded successfully -->