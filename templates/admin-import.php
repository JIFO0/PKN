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
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Import Communities from Excel', 'science-communities'); ?></h1>
    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=pkn-import&sc_export=1'), 'sc_export_communities')); ?>" class="page-title-action">
        <?php _e('Export SC CSV (|)', 'science-communities'); ?>
    </a>
    <hr class="wp-header-end">
    
    <div class="card" style="max-width: 800px;">
        <h2><?php _e('Upload Excel File', 'science-communities'); ?></h2>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('sc_import_excel', 'sc_import_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="excel_file"><?php _e('Excel File', 'science-communities'); ?></label></th>
                    <td>
                        <input type="file" name="excel_file" id="excel_file" accept=".csv,.xlsx,.xls" required>
                        <p class="description">
                            <?php _e('Upload a CSV or Excel file (recommended: CSV with | delimiter and UTF-8). The file should have the following columns:', 'science-communities'); ?>
                        </p>
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
                <tr>
                    <td><code>name</code></td>
                    <td><strong><?php _e('Yes', 'science-communities'); ?></strong></td>
                    <td><?php _e('Community name', 'science-communities'); ?></td>
                </tr>
                <tr>
                    <td><code>shortdescription</code></td>
                    <td><?php _e('No', 'science-communities'); ?></td>
                    <td><?php _e('Brief description', 'science-communities'); ?></td>
                </tr>
                <tr>
                    <td><code>description</code></td>
                    <td><?php _e('No', 'science-communities'); ?></td>
                    <td><?php _e('Full description', 'science-communities'); ?></td>
                </tr>
                <tr>
                    <td><code>faculty</code></td>
                    <td><?php _e('No', 'science-communities'); ?></td>
                    <td><?php _e('Faculty name', 'science-communities'); ?></td>
                </tr>
                <tr>
                    <td><code>webpage</code></td>
                    <td><?php _e('No', 'science-communities'); ?></td>
                    <td><?php _e('Website URL', 'science-communities'); ?></td>
                </tr>
                <tr>
                    <td><code>facebook</code></td>
                    <td><?php _e('No', 'science-communities'); ?></td>
                    <td><?php _e('Facebook URL', 'science-communities'); ?></td>
                </tr>
                <tr>
                    <td><code>instagram</code></td>
                    <td><?php _e('No', 'science-communities'); ?></td>
                    <td><?php _e('Instagram URL', 'science-communities'); ?></td>
                </tr>
                <tr>
                    <td><code>tiktok</code></td>
                    <td><?php _e('No', 'science-communities'); ?></td>
                    <td><?php _e('TikTok URL', 'science-communities'); ?></td>
                </tr>
                <tr>
                    <td><code>discord</code></td>
                    <td><?php _e('No', 'science-communities'); ?></td>
                    <td><?php _e('Discord URL', 'science-communities'); ?></td>
                </tr>
                <tr>
                    <td><code>logo</code></td>
                    <td><?php _e('No', 'science-communities'); ?></td>
                    <td><?php _e('Logo image URL', 'science-communities'); ?></td>
                </tr>
                <tr>
                    <td><code>tags</code></td>
                    <td><?php _e('No', 'science-communities'); ?></td>
                    <td><?php _e('Tags separated by commas inside one field (e.g., "Science, Technology, Research")', 'science-communities'); ?></td>
                </tr>
            </tbody>
        </table>
        
        <p style="margin-top: 20px;">
            <strong><?php _e('Note:', 'science-communities'); ?></strong>
            <?php _e('If a community with the same name already exists, it will be updated. Communities are matched by name.', 'science-communities'); ?>
        </p>
    </div>
</div>
