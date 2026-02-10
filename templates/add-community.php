<?php
/**
 * Template for adding a new science community
 *
 * Allows superadmins to create new communities.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if user is logged in and is superadmin
if (!is_user_logged_in() || !sc_is_superadmin()) {
    echo '<p>' . __('You do not have permission to add communities.', 'science-communities') . '</p>';
    return;
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_community_add_flag'])) {
    // Verify nonce
    if (!isset($_POST['sc_add_community_nonce']) || !wp_verify_nonce($_POST['sc_add_community_nonce'], 'sc_add_community')) {
        $error_message = __('Security check failed. Please try again.', 'science-communities');
    } else {
        // Rate limiting check
        $rate_check = sc_can_user_edit_now(get_current_user_id(), 'new');
        
        if (!$rate_check['allowed']) {
            $error_message = $rate_check['message'];
        } else {
            // Prepare data
            $data = array(
                'community_id' => '',
                'name' => sanitize_text_field($_POST['name']),
                'shortdescription' => sanitize_textarea_field($_POST['shortdescription']),
                'description' => wp_kses_post($_POST['description']),
                'webpage' => esc_url_raw($_POST['webpage']),
                'facebook' => esc_url_raw($_POST['facebook']),
                'instagram' => esc_url_raw($_POST['instagram']),
                'tiktok' => esc_url_raw($_POST['tiktok']),
                'discord' => esc_url_raw($_POST['discord']),
                'logo' => esc_url_raw($_POST['logo']),
                'faculty_id' => isset($_POST['faculty_id']) && !empty($_POST['faculty_id']) ? intval($_POST['faculty_id']) : null,
                'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active',
                'is_archived' => isset($_POST['is_archived']) ? 1 : 0,
                'tags' => isset($_POST['tags']) ? array_map('sanitize_text_field', $_POST['tags']) : array(),
            );

            // Save
            $result = sc_save_community($data);
            error_log('Add community save result: ' . print_r($result, true));

            // Build redirect URL
            $redirect_url = add_query_arg(
                array(
                    'action' => 'add'
                ),
                sc_get_admin_page_url()
            );
            
            if ($result === true) {
                $redirect_url = add_query_arg('updated', '1', $redirect_url);
            } else {
                $redirect_url = add_query_arg('error', urlencode($result), $redirect_url);
            }
            
            error_log('Add community redirect URL: ' . $redirect_url);
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}

// Check for success/error messages from redirect
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $success_message = __('Community created successfully.', 'science-communities');
}
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}

// Get all available tags for the tag selector
$all_tags = sc_get_all_tags();
?>

<div class="sc-add-community">
    <h1><?php echo esc_html__('Add New Community', 'science-communities'); ?></h1>

    <?php if (!empty($success_message)): ?>
        <div class="sc-notice sc-notice-success">
            <?php echo esc_html($success_message); ?>
            <p><?php _e('Community has been saved.', 'science-communities'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="sc-notice sc-notice-error">
            <?php echo esc_html($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($success_message)): ?>
    <form method="post" action="" class="sc-edit-community-form">
        <input type="hidden" name="action" value="sc_add_community">
        <input type="hidden" name="sc_community_add_flag" value="1">
        <?php wp_nonce_field('sc_add_community', 'sc_add_community_nonce'); ?>

        <div class="sc-form-group">
            <label for="sc-name"><?php echo esc_html__('Community Name', 'science-communities'); ?> *</label>
            <input type="text" id="sc-name" name="name" required>
        </div>

        <div class="sc-form-group">
            <label for="sc-shortdescription"><?php echo esc_html__('Short Description', 'science-communities'); ?> *</label>
            <textarea id="sc-shortdescription" name="shortdescription" required></textarea>
        </div>

        <div class="sc-form-group">
            <label for="sc-description"><?php echo esc_html__('Description', 'science-communities'); ?></label>
            <textarea id="sc-description" name="description"></textarea>
        </div>

        <div class="sc-form-group">
            <label for="sc-webpage"><?php echo esc_html__('Website URL', 'science-communities'); ?></label>
            <input type="url" id="sc-webpage" name="webpage">
        </div>

        <div class="sc-form-group">
            <label for="sc-facebook"><?php echo esc_html__('Facebook URL', 'science-communities'); ?></label>
            <input type="url" id="sc-facebook" name="facebook">
        </div>

        <div class="sc-form-group">
            <label for="sc-instagram"><?php echo esc_html__('Instagram URL', 'science-communities'); ?></label>
            <input type="url" id="sc-instagram" name="instagram">
        </div>

        <div class="sc-form-group">
            <label for="sc-tiktok"><?php echo esc_html__('TikTok URL', 'science-communities'); ?></label>
            <input type="url" id="sc-tiktok" name="tiktok">
        </div>

        <div class="sc-form-group">
            <label for="sc-discord"><?php echo esc_html__('Discord URL', 'science-communities'); ?></label>
            <input type="url" id="sc-discord" name="discord">
        </div>

        <div class="sc-form-group">
            <label for="sc-logo"><?php echo esc_html__('Logo URL', 'science-communities'); ?></label>
            <input type="url" id="sc-logo" name="logo">
        </div>

        <div class="sc-form-group">
            <label><?php echo esc_html__('Tags', 'science-communities'); ?></label>
            <div class="sc-tags-selector">
                <?php foreach ($all_tags as $tag): ?>
                    <label class="sc-tag-item">
                        <input type="checkbox" name="tags[]" value="<?php echo esc_attr($tag->tag_name); ?>">
                        <span class="sc-tag-name"><?php echo esc_html($tag->tag_name); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sc-form-actions">
            <button type="submit" class="sc-submit-button">
                <?php echo esc_html__('Create Community', 'science-communities'); ?>
            </button>
            <a href="<?php echo esc_url(sc_get_admin_page_url()); ?>" class="sc-cancel-button">
                <?php echo esc_html__('Cancel', 'science-communities'); ?>
            </a>
        </div>
    </form>
    <?php endif; ?>
</div>