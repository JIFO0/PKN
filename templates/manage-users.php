<?php
/**
 * Template for managing community admin users
 * Only accessible to superadmins
 */

if (!defined('ABSPATH')) exit;

if (!sc_is_superadmin()) {
    echo '<p>' . __('Access denied.', 'science-communities') . '</p>';
    return;
}

// Handle adding new admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_add_admin'])) {
    if (wp_verify_nonce($_POST['sc_add_admin_nonce'], 'sc_add_admin')) {
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $community_id = sanitize_text_field($_POST['community_id']);
        
        // Create user or get existing
        $user_id = username_exists($username);
        if (!$user_id && email_exists($email) == false) {
            $password = wp_generate_password();
            $user_id = wp_create_user($username, $password, $email);
            wp_new_user_notification($user_id, null, 'both');
        } elseif (!$user_id) {
            $user_id = email_exists($email);
        }
        
        if ($user_id) {
            sc_assign_community_admin($user_id, $community_id);
            $success_message = __('Admin added successfully!', 'science-communities');
        }
    }
}

$communities = sc_get_editable_communities();
?>

<div class="sc-manage-users">
    <h1><?php _e('Manage Community Admins', 'science-communities'); ?></h1>
    
    <?php if (!empty($success_message)): ?>
    <div class="sc-notice sc-notice-success"><?php echo esc_html($success_message); ?></div>
    <?php endif; ?>
    
    <form method="post" class="sc-add-admin-form">
        <?php wp_nonce_field('sc_add_admin', 'sc_add_admin_nonce'); ?>
        <input type="hidden" name="sc_add_admin" value="1">
        
        <h2><?php _e('Add New Community Admin', 'science-communities'); ?></h2>
        
        <div class="sc-form-group">
            <label for="username"><?php _e('Username', 'science-communities'); ?></label>
            <input type="text" name="username" required>
            <small><?php _e('If user exists, they will be assigned to the community', 'science-communities'); ?></small>
        </div>
        
        <div class="sc-form-group">
            <label for="email"><?php _e('Email', 'science-communities'); ?></label>
            <input type="email" name="email" required>
        </div>
        
        <div class="sc-form-group">
            <label for="community_id"><?php _e('Community', 'science-communities'); ?></label>
            <select name="community_id" required>
                <option value=""><?php _e('Select Community', 'science-communities'); ?></option>
                <?php foreach ($communities as $community): ?>
                <option value="<?php echo esc_attr($community['community_id']); ?>">
                    <?php echo esc_html($community['name']); ?> (<?php echo esc_html($community['community_id']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="sc-submit-button">
            <?php _e('Add Admin', 'science-communities'); ?>
        </button>
    </form>
    
    <hr>
    
    <h2><?php _e('Existing Community Admins', 'science-communities'); ?></h2>
    <?php sc_display_community_admins_table(); ?>
</div>