<?php
/**
 * Template for managing plugin users
 * Superadmins only
 */

if (!defined('ABSPATH')) exit;

if (!sc_is_superadmin()) {
    echo '<p>' . __('Access denied.', 'science-communities') . '</p>';
    return;
}

$success_message = '';
$error_message = '';
$communities = sc_get_editable_communities();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_add_admin'])) {
    if (!isset($_POST['sc_add_admin_nonce']) || !wp_verify_nonce($_POST['sc_add_admin_nonce'], 'sc_add_admin')) {
        $error_message = __('Security check failed.', 'science-communities');
    } else {
        $username = sanitize_user($_POST['username'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $community_id = sanitize_text_field($_POST['community_id'] ?? '');
        $password_raw = (string) ($_POST['password'] ?? '');

        if (empty($username) || empty($email) || empty($community_id)) {
            $error_message = __('All fields are required.', 'science-communities');
        } else {
            $user_id = username_exists($username);
            if (!$user_id && email_exists($email) == false) {
                $password = !empty($password_raw) ? $password_raw : wp_generate_password();
                $user_id = wp_create_user($username, $password, $email);
                if (!is_wp_error($user_id)) {
                    $user = get_user_by('id', $user_id);
                    if ($user) {
                        $user->add_role('subscriber');
                    }
                    wp_new_user_notification($user_id, null, 'both');
                }
            } elseif (!$user_id) {
                $user_id = email_exists($email);
            }

            if (is_wp_error($user_id)) {
                $error_message = $user_id->get_error_message();
            } elseif ($user_id) {
                sc_assign_community_admin($user_id, $community_id);
                $success_message = __('User saved and assigned successfully.', 'science-communities');
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_import_admin_csv'])) {
    if (!isset($_POST['sc_import_admin_csv_nonce']) || !wp_verify_nonce($_POST['sc_import_admin_csv_nonce'], 'sc_import_admin_csv')) {
        $error_message = __('Security check failed.', 'science-communities');
    } elseif (empty($_FILES['users_csv']['tmp_name'])) {
        $error_message = __('Please provide a CSV file.', 'science-communities');
    } else {
        $handle = fopen($_FILES['users_csv']['tmp_name'], 'r');
        $row = 0;
        $created_count = 0;
        $assigned_count = 0;
        $delimiter = sc_detect_csv_delimiter($_FILES['users_csv']['tmp_name']);
        $headers = array();
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row++;

            if ($row === 1) {
                $headers = array_map(function($header) {
                    $header = strtolower(trim((string) $header));
                    return preg_replace('/[^a-z0-9_]/', '', $header);
                }, $data);

                if (in_array('username', $headers, true)) {
                    continue;
                }

                // No header row: process this row as data with legacy positional format.
                $headers = array('username', 'email', 'community_id', 'password');
            }

            $row_data = array();
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $row_data[$header] = isset($data[$index]) ? trim((string) $data[$index]) : '';
            }

            $username = sanitize_user($row_data['username'] ?? '');
            $email = sanitize_email($row_data['email'] ?? '');
            $community_id = sanitize_text_field($row_data['community_id'] ?? '');
            $csv_password = isset($row_data['password']) ? trim((string) $row_data['password']) : '';

            if (empty($username) || empty($email) || empty($community_id)) {
                continue;
            }

            $user_id = username_exists($username);
            if (!$user_id && email_exists($email) == false) {
                $password = !empty($csv_password) ? $csv_password : wp_generate_password();
                $user_id = wp_create_user($username, $password, $email);
                if (!is_wp_error($user_id)) {
                    $created_count++;
                    $user = get_user_by('id', $user_id);
                    if ($user) {
                        $user->add_role('subscriber');
                    }
                }
            } elseif (!$user_id) {
                $user_id = email_exists($email);
            }

            if ($user_id && !is_wp_error($user_id)) {
                if (sc_assign_community_admin($user_id, $community_id)) {
                    $assigned_count++;
                }
            }
        }
        fclose($handle);
        $success_message = sprintf(__('CSV processed. Created: %d, Assigned: %d', 'science-communities'), $created_count, $assigned_count);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_delete_user'])) {
    if (!isset($_POST['sc_delete_user_nonce']) || !wp_verify_nonce($_POST['sc_delete_user_nonce'], 'sc_delete_user')) {
        $error_message = __('Security check failed.', 'science-communities');
    } else {
        $user_id = intval($_POST['user_id'] ?? 0);
        $user = get_user_by('id', $user_id);
        if ($user) {
            $roles = (array) $user->roles;
            $is_superadmin = in_array('superadmin', $roles, true);
            $is_page_admin = false;
            foreach ($roles as $role) {
                if (substr($role, -6) === '-admin') {
                    $is_page_admin = true;
                    break;
                }
            }

            if ($is_superadmin || $is_page_admin) {
                $error_message = __('This user cannot be deleted (superadmin or page admin).', 'science-communities');
            } else {
                require_once ABSPATH . 'wp-admin/includes/user.php';
                wp_delete_user($user_id);
                $success_message = __('User deleted.', 'science-communities');
            }
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_manage_assignment'])) {
    if (!isset($_POST['sc_manage_assignment_nonce']) || !wp_verify_nonce($_POST['sc_manage_assignment_nonce'], 'sc_manage_assignment')) {
        $error_message = __('Security check failed.', 'science-communities');
    } else {
        $selected_user_id = intval($_POST['selected_user_id'] ?? 0);
        $selected_community_id = sanitize_text_field($_POST['selected_community_id'] ?? '');
        $action_type = sanitize_text_field($_POST['assignment_action'] ?? '');

        if (!$selected_user_id || empty($selected_community_id) || !in_array($action_type, array('assign', 'unassign'), true)) {
            $error_message = __('Please select a user, a community, and action.', 'science-communities');
        } elseif ($action_type === 'assign') {
            sc_assign_community_admin($selected_user_id, $selected_community_id);
            $success_message = __('Assignment saved.', 'science-communities');
        } else {
            $user = get_user_by('id', $selected_user_id);
            if ($user) {
                $user->remove_role($selected_community_id . '-admin');
                $success_message = __('Assignment removed.', 'science-communities');
            }
        }
    }
}

$users = get_users(array('orderby' => 'display_name', 'order' => 'ASC'));
?>

<div class="sc-manage-users">
    <h1><?php echo esc_html(sc_t('user_management')); ?></h1>

    <?php if (!empty($success_message)): ?>
    <div class="notice notice-success"><p><?php echo esc_html($success_message); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="notice notice-error"><p><?php echo esc_html($error_message); ?></p></div>
    <?php endif; ?>

    <form method="post" class="sc-add-admin-form sc-user-form">
        <?php wp_nonce_field('sc_add_admin', 'sc_add_admin_nonce'); ?>
        <input type="hidden" name="sc_add_admin" value="1">
        <h2><?php echo esc_html(sc_t('create_user_manually')); ?></h2>

        <p><input type="text" name="username" placeholder="<?php echo esc_attr(sc_t('username')); ?>" required></p>
        <p><input type="email" name="email" placeholder="<?php echo esc_attr(sc_t('email')); ?>" required></p>
        <p><input type="text" name="password" placeholder="<?php echo esc_attr(sc_t('password_optional')); ?>"></p>
        <p>
            <input type="text" id="sc-community-search" placeholder="Search community..." style="width: 320px;">
            <select name="community_id" id="sc-community-select" required>
                <option value=""><?php echo esc_html(sc_t('select_community')); ?></option>
                <?php foreach ($communities as $community): ?>
                <option value="<?php echo esc_attr($community['community_id']); ?>">
                    <?php echo esc_html($community['name']); ?> (<?php echo esc_html($community['community_id']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </p>

        <button type="submit" class="button button-primary"><?php echo esc_html(sc_t('create_assign')); ?></button>
    </form>

    <hr>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('sc_import_admin_csv', 'sc_import_admin_csv_nonce'); ?>
        <input type="hidden" name="sc_import_admin_csv" value="1">
        <h2><?php _e('Import users from CSV', 'science-communities'); ?></h2>
        <p><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pkn-manage-users&sc_export_accounts=1'), 'sc_export_accounts')); ?>"><?php _e('Export users CSV', 'science-communities'); ?></a></p>
        <p><?php _e('CSV format: username,email,community_id,password(optional)', 'science-communities'); ?></p>
        <p><input type="file" name="users_csv" accept=".csv" required></p>
        <button type="submit" class="button"><?php _e('Import CSV', 'science-communities'); ?></button>
    </form>

    <hr>



    <hr>

    <form method="post" class="sc-assignment-form sc-user-form">
        <?php wp_nonce_field('sc_manage_assignment', 'sc_manage_assignment_nonce'); ?>
        <input type="hidden" name="sc_manage_assignment" value="1">
        <h2><?php echo esc_html(sc_t('assign_unassign_admin')); ?></h2>
        <p>
            <input list="sc-users-list" name="selected_user_id" placeholder="<?php echo esc_attr(sc_t('type_select_user_id')); ?>" required>
            <datalist id="sc-users-list">
                <?php foreach ($users as $user): ?>
                <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?></option>
                <?php endforeach; ?>
            </datalist>
        </p>
        <p>
            <input type="text" id="sc-community-search-2" placeholder="Search community...">
            <select name="selected_community_id" id="sc-community-select-2" required>
                <option value=""><?php echo esc_html(sc_t('select_community')); ?></option>
                <?php foreach ($communities as $community): ?>
                <option value="<?php echo esc_attr($community['community_id']); ?>"><?php echo esc_html($community['name']); ?> (<?php echo esc_html($community['community_id']); ?>)</option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <button type="submit" name="assignment_action" value="assign" class="button button-primary"><?php echo esc_html(sc_t('assign')); ?></button>
            <button type="submit" name="assignment_action" value="unassign" class="button"><?php echo esc_html(sc_t('unassign')); ?></button>
        </p>
    </form>

    <h2><?php echo esc_html(sc_t('users')); ?></h2>
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th><?php _e('User', 'science-communities'); ?></th>
                <th><?php echo esc_html(sc_t('roles')); ?></th>
                <th><?php echo esc_html(sc_t('action')); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?></td>
                <td><?php echo esc_html(implode(', ', (array) $user->roles)); ?></td>
                <td>
                    <form method="post" onsubmit="return confirm('Delete this user?');">
                        <?php wp_nonce_field('sc_delete_user', 'sc_delete_user_nonce'); ?>
                        <input type="hidden" name="sc_delete_user" value="1">
                        <input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>">
                        <button type="submit" class="button"><?php echo esc_html(sc_t('delete')); ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function wireSearch(searchId, selectId){
        const searchInput = document.getElementById(searchId);
        const select = document.getElementById(selectId);
        if (!searchInput || !select) return;
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            Array.from(select.options).forEach((opt, index) => {
                if (index === 0) return;
                opt.hidden = term && !opt.text.toLowerCase().includes(term);
            });
        });
    }
    wireSearch('sc-community-search', 'sc-community-select');
    wireSearch('sc-community-search-2', 'sc-community-select-2');
});
</script>
