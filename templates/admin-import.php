<?php
/**
 * Admin page for importing communities from Excel
 */

if (!defined('ABSPATH')) exit;
if (!sc_is_superadmin()) {
    echo '<p>' . __('Access denied.', 'science-communities') . '</p>';
    return;
}

settings_errors('pkn_messages');
if (isset($_GET['history_cleared']) && $_GET['history_cleared'] === '1') {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Update history cleared and logged.', 'science-communities') . '</p></div>';
}
$update_status = function_exists('sc_get_update_status') ? sc_get_update_status() : array('current_version' => SC_PLUGIN_VERSION, 'remote_version' => '', 'has_update' => false);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Import Communities from Excel', 'science-communities'); ?></h1>
    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pkn-import&sc_export=1'), 'sc_export_communities')); ?>" class="page-title-action">
        <?php _e('Export SC CSV (|)', 'science-communities'); ?>
    </a>
    <hr class="wp-header-end">
    
    <div class="card" style="max-width: 800px;">
        <h2><?php _e('Plugin Updates', 'science-communities'); ?></h2>
        <p><strong><?php _e('Installed version:', 'science-communities'); ?></strong> <?php echo esc_html($update_status['current_version']); ?></p>
        <p><strong><?php _e('GitHub version:', 'science-communities'); ?></strong> <?php echo esc_html($update_status['remote_version'] ?: __('Unavailable', 'science-communities')); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="sc_run_plugin_update">
            <?php wp_nonce_field('sc_manual_plugin_update', 'sc_manual_plugin_update_nonce'); ?>
            <button type="submit" class="button button-primary" <?php disabled(empty($update_status['has_update'])); ?>>
                <?php echo !empty($update_status['has_update']) ? esc_html__('Update plugin now', 'science-communities') : esc_html__('Plugin is up to date', 'science-communities'); ?>
            </button>
        </form>
    </div>

    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2><?php _e('Upload Excel File', 'science-communities'); ?></h2>
        
        <form method="post" enctype="multipart/form-data" id="sc-import-form">
            <?php wp_nonce_field('sc_import_excel', 'sc_import_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="importer_name"><?php _e('Your name', 'science-communities'); ?></label></th>
                    <td>
                        <input type="text" name="importer_name" id="importer_name" class="regular-text" required>
                        <p class="description"><?php _e('This name will be saved in the update history for this import.', 'science-communities'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="excel_file"><?php _e('Excel File', 'science-communities'); ?></label></th>
                    <td>
                        <input type="file" name="excel_file" id="excel_file" accept=".csv,.xlsx,.xls" required>
                        <p class="description">
                            <?php _e('Upload a CSV or Excel file (recommended: CSV with | delimiter and UTF-8). The file should have the following columns:', 'science-communities'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="upload_info"><?php _e('Upload info', 'science-communities'); ?></label></th>
                    <td>
                        <textarea name="upload_info" id="upload_info" class="large-text" rows="3" placeholder="<?php esc_attr_e('Optional notes about this upload, data source, or reason for importing.', 'science-communities'); ?>"></textarea>
                        <p class="description"><?php _e('Saved in update history together with the import summary.', 'science-communities'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e('Import Communities', 'science-communities'); ?>">
            </p>
        </form>
    </div>
    
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2><?php _e('Required Excel Format', 'science-communities'); ?></h2>
        
        <p><?php _e('Your Excel file should have these columns (first row as headers):', 'science-communities'); ?></p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Column Name', 'science-communities'); ?></th>
                    <th><?php _e('Required', 'science-communities'); ?></th>
                    <th><?php _e('Description', 'science-communities'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $columns = array(
                    array('community_id', __('Yes', 'science-communities'), __('Five-character community ID. Rows with community_id equal to 0 are not imported.', 'science-communities')),
                    array('name', __('Yes', 'science-communities'), __('Community name.', 'science-communities')),
                    array('shortdescription', __('No', 'science-communities'), __('Brief 1–2 sentence description.', 'science-communities')),
                    array('description', __('No', 'science-communities'), __('Long formatted description.', 'science-communities')),
                    array('faculty', __('No', 'science-communities'), __('Faculty name.', 'science-communities')),
                    array('webpage', __('No', 'science-communities'), __('Website URL.', 'science-communities')),
                    array('facebook', __('No', 'science-communities'), __('Facebook URL.', 'science-communities')),
                    array('instagram', __('No', 'science-communities'), __('Instagram URL.', 'science-communities')),
                    array('discord', __('No', 'science-communities'), __('Discord URL.', 'science-communities')),
                    array('inne', __('No', 'science-communities'), __('Other links. Separate multiple links with commas.', 'science-communities')),
                    array('mail', __('No', 'science-communities'), __('Application contact email address.', 'science-communities')),
                    array('logo', __('No', 'science-communities'), __('Logo image URL.', 'science-communities')),
                    array('tags', __('No', 'science-communities'), __('Tags. Separate multiple tags with commas.', 'science-communities')),
                    array('status', __('No', 'science-communities'), __('Use -1 for archived/suspended, 0 for suspended, a value between 0 and 1 for limited activity, and 1 for active.', 'science-communities')),
                );
                foreach ($columns as $column):
                ?>
                    <tr>
                        <td><code><?php echo esc_html($column[0]); ?></code></td>
                        <td><?php echo $column[1] === __('Yes', 'science-communities') ? '<strong>' . esc_html($column[1]) . '</strong>' : esc_html($column[1]); ?></td>
                        <td><?php echo esc_html($column[2]); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p style="margin-top: 20px;">
            <strong><?php _e('Note:', 'science-communities'); ?></strong>
            <?php _e('If a community with the same community_id already exists, it will be updated. Legacy files without community_id are matched by name.', 'science-communities'); ?>
        </p>
    </div>

    <div class="card" style="max-width: 1000px; margin-top: 20px;">
        <h2><?php _e('Update history', 'science-communities'); ?></h2>
        <?php
        global $wpdb;
        $history_table = $wpdb->prefix . 'science_communities_update_history';
        $history_entries = $wpdb->get_results("SELECT * FROM $history_table ORDER BY created_at DESC LIMIT 25", ARRAY_A);
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'science-communities'); ?></th>
                    <th><?php _e('Name', 'science-communities'); ?></th>
                    <th><?php _e('Action', 'science-communities'); ?></th>
                    <th><?php _e('File', 'science-communities'); ?></th>
                    <th><?php _e('Created', 'science-communities'); ?></th>
                    <th><?php _e('Updated', 'science-communities'); ?></th>
                    <th><?php _e('Deleted', 'science-communities'); ?></th>
                    <th><?php _e('Skipped', 'science-communities'); ?></th>
                    <th><?php _e('Info', 'science-communities'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history_entries)): ?>
                    <tr><td colspan="9"><?php _e('No update history yet.', 'science-communities'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($history_entries as $entry): ?>
                        <tr>
                            <td><?php echo esc_html($entry['created_at']); ?></td>
                            <td><?php echo esc_html($entry['actor_name']); ?></td>
                            <td><?php echo esc_html($entry['action'] === 'history_cleared' ? __('History cleared', 'science-communities') : __('Import', 'science-communities')); ?></td>
                            <td><?php echo esc_html($entry['filename']); ?></td>
                            <td><?php echo esc_html((int) $entry['communities_created']); ?></td>
                            <td><?php echo esc_html((int) $entry['communities_updated']); ?></td>
                            <td><?php echo esc_html((int) $entry['communities_deleted']); ?></td>
                            <td><?php echo esc_html((int) $entry['communities_skipped']); ?></td>
                            <td><?php echo esc_html($entry['notes']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 16px;">
            <input type="hidden" name="action" value="sc_clear_update_history">
            <?php wp_nonce_field('sc_clear_update_history', 'sc_clear_update_history_nonce'); ?>
            <label for="history_actor_name"><strong><?php _e('Your name', 'science-communities'); ?></strong></label>
            <input type="text" id="history_actor_name" name="history_actor_name" class="regular-text" required>
            <button type="submit" class="button" onclick="return confirm('<?php echo esc_js(__('Clear update history? A new entry recording this action will be created.', 'science-communities')); ?>');"><?php _e('Clear update history', 'science-communities'); ?></button>
        </form>
    </div>
</div>
<?php
$upload_dir = wp_upload_dir();
$log_file = trailingslashit($upload_dir['basedir']) . 'sc-community-import.log';
if (file_exists($log_file)):
    $log_lines = @file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_lines = is_array($log_lines) ? array_slice($log_lines, -20) : array();
?>
<div class="card" style="max-width: 800px; margin-top: 20px;">
    <h2><?php _e('Last import log entries', 'science-communities'); ?></h2>
    <pre style="background:#111;color:#f3f3f3;padding:12px;max-height:280px;overflow:auto;"><?php echo esc_html(implode("\n", $log_lines)); ?></pre>
</div>
<?php endif; ?>
