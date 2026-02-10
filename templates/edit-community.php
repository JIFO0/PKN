<?php
/**
 * Template for editing a science community
 *
 * Allows superadmins and community admins to edit community details.
 */

// Clean up output buffer for display
ob_end_flush();


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if user is logged in and has permission to edit communities
if (!is_user_logged_in()) {
    echo '<p>' . __('You must be logged in to edit a community.', 'science-communities') . '</p>';
    return;
}

// Get the community ID from the URL
$community_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';

// Redirect if no community ID is provided
if (empty($community_id)) {
    wp_redirect(home_url('/admin-panel/'));
    exit;
}

// Get the community data
$community = sc_get_community_by_id($community_id);

// Redirect if community not found
if (!$community) {
    wp_redirect(home_url('/admin-panel/'));
    exit;
}

// Check if the current user can edit this community
if (!sc_user_can_edit_community($community_id)) {
    echo '<p>' . __('You do not have permission to edit this community.', 'science-communities') . '</p>';
    return;
}

// Handle form submission
$success_message = '';
$error_message = '';
error_log('==== ADD COMMUNITY FORM SUBMISSION ====');
error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('sc_community_add_flag isset: ' . (isset($_POST['sc_community_add_flag']) ? 'YES' : 'NO'));
error_log('sc_community_edit_flag isset: ' . (isset($_POST['sc_community_edit_flag']) ? 'YES' : 'NO'));
error_log('POST keys: ' . implode(', ', array_keys($_POST)));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_community_add_flag'])) {
    // Verify nonce
    if (!isset($_POST['sc_add_community_nonce']) || !wp_verify_nonce($_POST['sc_add_community_nonce'], 'sc_add_community')) {
        $error_message = __('Security check failed. Please try again.', 'science-communities');
    } else {
        // Rate limiting check
        $rate_check = sc_can_user_edit_now(get_current_user_id(), $community_id);
        
        if (!$rate_check['allowed']) {
            $error_message = $rate_check['message'];
        } else {
            // Prepare data
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
                'faculty_id' => isset($_POST['faculty_id']) && !empty($_POST['faculty_id']) ? intval($_POST['faculty_id']) : null,
                'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active',
                'is_archived' => isset($_POST['is_archived']) ? 1 : 0,
                'tags' => isset($_POST['tags']) ? array_map('sanitize_text_field', $_POST['tags']) : array(),
            );

            // Save
            $result = sc_save_community($data);

            // Build redirect URL
            $redirect_url = add_query_arg(
                array(
                    'action' => 'edit',
                    'id' => $community_id
                ),
                site_url('/sc-admin/')
            );
            
            if ($result === true) {
                $redirect_url = add_query_arg('updated', '1', $redirect_url);
            } else {
                $redirect_url = add_query_arg('error', urlencode($result), $redirect_url);
            }
            
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}

// Check for success/error messages from redirect
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $success_message = __('Community details updated successfully.', 'science-communities');
}
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}

// Get all available tags for the tag selector
$all_tags = sc_get_all_tags();




<div class="sc-edit-community">
    <h1><?php echo esc_html__('Edit Community', 'science-communities'); ?></h1>

    <?php if (!empty($success_message)): ?>
        <div class="sc-notice sc-notice-success">
            <?php echo esc_html($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="sc-notice sc-notice-error">
            <?php echo esc_html($error_message); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="" class="sc-edit-community-form">
        <input type="hidden" name="action" value="sc_edit_community">
        <input type="hidden" name="sc_community_edit_flag" value="1">
        <input type="hidden" name="community_id" value="<?php echo esc_attr($community_id); ?>">
        <?php wp_nonce_field('sc_edit_community', 'sc_edit_community_nonce'); ?>

        <div class="sc-form-group">
            <label for="sc-name"><?php echo esc_html__('Community Name', 'science-communities'); ?></label>
            <input type="text" id="sc-name" name="name" value="<?php echo esc_attr($community['name']); ?>" required>
        </div>

        <div class="sc-form-group">
            <label for="sc-shortdescription"><?php echo esc_html__('Short Description', 'science-communities'); ?></label>
            <textarea id="sc-shortdescription" name="shortdescription" required><?php echo esc_textarea($community['shortdescription']); ?></textarea>
        </div>

        <div class="sc-form-group">
            <label for="sc-description"><?php echo esc_html__('Description', 'science-communities'); ?></label>
            <textarea id="sc-description" name="description"><?php echo esc_textarea($community['description']); ?></textarea>
        </div>

        <div class="sc-form-group">
            <label for="sc-webpage"><?php echo esc_html__('Website URL', 'science-communities'); ?></label>
            <input type="url" id="sc-webpage" name="webpage" value="<?php echo esc_url($community['webpage']); ?>">
        </div>

        <div class="sc-form-group">
            <label for="sc-facebook"><?php echo esc_html__('Facebook URL', 'science-communities'); ?></label>
            <input type="url" id="sc-facebook" name="facebook" value="<?php echo esc_url($community['facebook']); ?>">
        </div>

        <div class="sc-form-group">
            <label for="sc-instagram"><?php echo esc_html__('Instagram URL', 'science-communities'); ?></label>
            <input type="url" id="sc-instagram" name="instagram" value="<?php echo esc_url($community['instagram']); ?>">
        </div>

        <div class="sc-form-group">
            <label for="sc-tiktok"><?php echo esc_html__('TikTok URL', 'science-communities'); ?></label>
            <input type="url" id="sc-tiktok" name="tiktok" value="<?php echo esc_url($community['tiktok']); ?>">
        </div>

        <div class="sc-form-group">
            <label for="sc-discord"><?php echo esc_html__('Discord URL', 'science-communities'); ?></label>
            <input type="url" id="sc-discord" name="discord" value="<?php echo esc_url($community['discord']); ?>">
        </div>

        <div class="sc-form-group">
            <label for="sc-logo"><?php echo esc_html__('Logo URL', 'science-communities'); ?></label>
            <input type="url" id="sc-logo" name="logo" value="<?php echo esc_url($community['logo']); ?>">
            
            <!-- Logo upload section -->
            <div class="sc-logo-upload">
                <p class="sc-upload-or"><?php _e('— OR —', 'science-communities'); ?></p>
                <label for="sc-logo-upload" class="sc-upload-button">
                    <?php _e('Upload New Logo', 'science-communities'); ?>
                </label>
                <input type="file" id="sc-logo-upload" accept="image/png,image/jpeg,image/jpg,image/webp" style="display:none;">
                <div class="sc-upload-info">
                    <?php 
                    $upload_status = sc_can_user_upload(get_current_user_id());
                    if ($upload_status['is_superadmin']) {
                        _e('Unlimited uploads (Superadmin)', 'science-communities');
                    } else {
                        printf(
                            __('Uploads today: %d/3 (max 2MB, 2048x2048px)', 'science-communities'),
                            $upload_status['uploads_today']
                        );
                    }
                    ?>
                </div>
                <div class="sc-upload-progress" style="display:none;">
                    <div class="sc-progress-bar"></div>
                </div>
                <div class="sc-upload-message"></div>
            </div>
        </div>
            <div class="sc-form-group">
            <label for="sc-faculty"><?php echo esc_html__('Faculty', 'science-communities'); ?></label>
            <select id="sc-faculty" name="faculty_id">
                <option value=""><?php echo esc_html__('Select Faculty', 'science-communities'); ?></option>
                <?php
                global $wpdb;
                $faculties_table = $wpdb->prefix . 'science_faculties';
                $faculties = $wpdb->get_results("SELECT id, faculty_name FROM $faculties_table ORDER BY faculty_name ASC");
                
                foreach ($faculties as $faculty):
                ?>
                    <option value="<?php echo esc_attr($faculty->id); ?>" 
                        <?php selected($community['faculty_id'], $faculty->id); ?>>
                        <?php echo esc_html($faculty->faculty_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
            <div class="sc-form-group">
            <label for="sc-status"><?php echo esc_html__('Status działalności', 'science-communities'); ?></label>
            <select id="sc-status" name="status" required>
                <option value="active" <?php selected($community['status'], 'active'); ?>>
                    <?php echo esc_html__('Działa', 'science-communities'); ?>
                </option>
                <option value="limited" <?php selected($community['status'], 'limited'); ?>>
                    <?php echo esc_html__('Ograniczono działalność', 'science-communities'); ?>
                </option>
                <option value="suspended" <?php selected($community['status'], 'suspended'); ?>>
                    <?php echo esc_html__('Zawieszono', 'science-communities'); ?>
                </option>
            </select>
        </div>

        <div class="sc-form-group">
            <label for="sc-archived">
                <input type="checkbox" id="sc-archived" name="is_archived" value="1" 
                    <?php checked($community['is_archived'], 1); ?>>
                <?php echo esc_html__('Oznacz jako archiwalne', 'science-communities'); ?>
            </label>
            <p class="description">
                <?php echo esc_html__('Archiwalne koła są ukryte domyślnie w wynikach wyszukiwania.', 'science-communities'); ?>
            </p>
        </div>
        </div>
        <?php if (sc_is_superadmin()): ?>
<div class="sc-form-section">
    <h3><?php _e('Community Administrators', 'science-communities'); ?></h3>
    
    <?php
    // Get current admins for this community
    $role_name = $community_id . '-admin';
    $admins = get_users(array('role__in' => array($role_name)));
    ?>
    
    <?php if (!empty($admins)): ?>
    <ul class="sc-admin-list">
        <?php foreach ($admins as $admin): ?>
        <li>
            <?php echo esc_html($admin->user_login); ?> (<?php echo esc_html($admin->user_email); ?>)
            <a href="<?php echo esc_url(add_query_arg(array(
                'action' => 'remove-admin',
                'user_id' => $admin->ID,
                'community_id' => $community_id,
                '_wpnonce' => wp_create_nonce('remove_admin')
            ))); ?>" class="sc-remove-admin">
                <?php _e('Remove', 'science-communities'); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p><?php _e('No administrators assigned yet.', 'science-communities'); ?></p>
    <?php endif; ?>
    
    <p>
        <a href="<?php echo esc_url(add_query_arg('action', 'manage-users', site_url('/sc-admin/'))); ?>">
            <?php _e('Add Administrator', 'science-communities'); ?>
        </a>
    </p>
</div>
<?php endif; ?>
        <div class="sc-form-group">
            <label><?php echo esc_html__('Tags', 'science-communities'); ?></label>
            <div class="sc-tags-selector">
                <?php foreach ($all_tags as $tag): ?>
                    <label class="sc-tag-item">
                        <input type="checkbox" name="tags[]" value="<?php echo esc_attr($tag->tag_name); ?>"
                            <?php checked(in_array($tag->tag_name, $community['tags'])); ?>>
                        <span class="sc-tag-name"><?php echo esc_html($tag->tag_name); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sc-form-actions">
            <button type="submit" class="sc-submit-button">
                <?php echo esc_html__('Save Changes', 'science-communities'); ?>
            </button>
            <a href="<?php echo esc_url(site_url('/sc-admin/')); ?>" class="sc-cancel-button">
                <?php echo esc_html__('Cancel', 'science-communities'); ?>
            </a>
        </div>
    </form>
</div>
<script>
jQuery(document).ready(function($) {
    const formHandler = {
        init: function() {
            this.$form = $('#sc-edit-form');
            if (this.$form.data('ajax-save')) {
                this.bindFormSubmit();
            }
        },
        bindFormSubmit: function() {
            this.$form.on('submit', (e) => {
                e.preventDefault();
                const $button = this.$form.find('button[type="submit"]');
                const originalText = $button.text();
                $button.prop('disabled', true).text('Saving...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: this.$form.serialize() + '&action=sc_save_community_ajax',
                    dataType: 'json',
                    success: (response) => {
                        this.handleSuccess(response, $button, originalText);
                    },
                    error: (xhr, status, error) => {
                        this.handleError(xhr, status, error, $button, originalText);
                    }
                });
            });
        },
        handleSuccess: function(response, $button, originalText) {
            if (response.success) {
                this.showNotice('success', response.data.message);
            } else {
                this.showNotice('error', response.data);
            }
            $button.prop('disabled', false).text(originalText);
        },
        handleError: function(xhr, status, error, $button, originalText) {
            console.error('AJAX Error:', {xhr, status, error});
            this.showNotice('error', 'An error occurred. Please check the console.');
            $button.prop('disabled', false).text(originalText);
        },
        showNotice: function(type, message) {
            const noticeClass = type === 'success' ? 'sc-notice-success' : 'sc-notice-error';
            this.$form.before(`<div class="sc-notice ${noticeClass}">${message}</div>`);
            $('html, body').animate({scrollTop: 0}, 300);
            setTimeout(() => {
                $(`.${noticeClass}`).fadeOut();
            }, 3000);
        }
    };

    const tagSelector = {
        init: function() {
            this.tagCheckboxes = document.querySelectorAll('.sc-tag-item input[type="checkbox"]');
            this.bindTagEvents();
            this.setInitialTagState();
        },
        bindTagEvents: function() {
            this.tagCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', this.updateTagStyle.bind(this));
            });
        },
        setInitialTagState: function() {
            this.tagCheckboxes.forEach((checkbox) => {
                this.updateTagStyle({target: checkbox});
            });
        },
        updateTagStyle: function(event) {
            const checkbox = event.target;
            const tagItem = checkbox.closest('.sc-tag-item');
            if (checkbox.checked) {
                tagItem.style.background = '#e3f2fd';
                tagItem.style.borderColor = 'var(--color-UG)';
            } else {
                tagItem.style.background = 'white';
                tagItem.style.borderColor = 'transparent';
            }
        }
    };

    const floatingLabels = {
        init: function() {
            this.inputs = document.querySelectorAll('.sc-form-group input, .sc-form-group textarea, .sc-form-group select');
            this.bindInputEvents();
        },
        bindInputEvents: function() {
            this.inputs.forEach((input) => {
                input.addEventListener('focus', this.addFocusClass);
                input.addEventListener('blur', this.removeFocusClass);
            });
        },
        addFocusClass: function() {
            this.parentElement.classList.add('focused');
        },
        removeFocusClass: function() {
            this.parentElement.classList.remove('focused');
        }
    };

    // Initialize all modules
    formHandler.init();
    tagSelector.init();
    floatingLabels.init();
});
</script>