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

// Single source of truth: form submits to admin-post.php handler only
// No POST processing here - all handled in PKN-backend.php sc_handle_add_community()
$success_message = '';
$error_message = '';

// Form action points to WordPress admin-post endpoint
$form_action = admin_url('admin-post.php');

// Display messages from redirect query params
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $success_message = __('Community created successfully.', 'science-communities');
}
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
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
    <form method="post" action="<?php echo esc_url($form_action); ?>" class="sc-edit-community-form">
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
            <input type="url" id="sc-logo" name="logo" placeholder="https://...">
    
            <div class="sc-logo-upload sc-upload-section">
                <p class="description">
                    <?php echo esc_html__('Or upload a logo (PNG, JPG, JPEG, WebP - Max 2MB, 2048x2048px)', 'science-communities'); ?>
                </p>
                <label for="sc-logo-upload" class="sc-upload-button">
                    <?php echo esc_html__('Choose File', 'science-communities'); ?>
                </label>
                <input type="file" id="sc-logo-upload" accept=".png,.jpg,.jpeg,.webp" style="display:none;">
        
                <div class="sc-upload-progress" style="display:none;">
                    <div class="sc-progress-bar"></div>
                </div>
                <div class="sc-upload-message"></div>
            </div>
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
            <input type="text" name="new_tags" class="regular-text" placeholder="<?php echo esc_attr__('Add new tags, separated by commas', 'science-communities'); ?>">
            <p class="description"><?php echo esc_html__('New tags are saved only when this community uses them, so unused tags are not kept.', 'science-communities'); ?></p>
        </div>

        <div class="sc-form-group">
            <label for="sc-gallery-images"><?php echo esc_html__('Community Gallery (one URL per line)', 'science-communities'); ?></label>
            <textarea id="sc-gallery-images" name="gallery_images" rows="6"></textarea>
            <div class="sc-logo-upload sc-gallery-upload">
                <label for="sc-gallery-upload" class="sc-upload-button">
                    <?php echo esc_html__('Upload Gallery Images', 'science-communities'); ?>
                </label>
                <input type="file" id="sc-gallery-upload" accept=".png,.jpg,.jpeg,.webp" multiple style="display:none;">
                <div class="sc-upload-info"><?php echo esc_html__('Select multiple images; uploaded links are added automatically.', 'science-communities'); ?></div>
                <div class="sc-upload-progress sc-gallery-progress" style="display:none;">
                    <div class="sc-progress-bar"></div>
                </div>
                <div class="sc-upload-message sc-gallery-upload-message"></div>
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
