<?php
/**
 * Admin page for viewing and managing all communities
 */

if (!defined('ABSPATH')) exit;
if (!sc_is_superadmin()) {
    echo '<p>' . __('Access denied.', 'science-communities') . '</p>';
    return;
}

global $wpdb;
$table = $wpdb->prefix . 'science_communities';

// Handle single delete
if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_community_' . $_GET['delete'])) {
        if (sc_delete_community(sanitize_text_field($_GET['delete']))) {
            echo '<div class="notice notice-success"><p>' . __('Community deleted successfully.', 'science-communities') . '</p></div>';
        }
    }
}

settings_errors('pkn_messages');

$communities = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('All Science Communities', 'science-communities'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=pkn-import'); ?>" class="page-title-action">
        <?php _e('Import from Excel', 'science-communities'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <form method="post" action="">
        <?php wp_nonce_field('sc_bulk_delete', 'sc_bulk_delete_nonce'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="action">
                    <option value="-1"><?php _e('Bulk Actions', 'science-communities'); ?></option>
                    <option value="delete"><?php _e('Delete', 'science-communities'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'science-communities'); ?>">
            </div>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf(__('%s items', 'science-communities'), number_format_i18n(count($communities))); ?></span>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <th><?php _e('ID', 'science-communities'); ?></th>
                    <th><?php _e('Name', 'science-communities'); ?></th>
                    <th><?php _e('Faculty', 'science-communities'); ?></th>
                    <th><?php _e('Status', 'science-communities'); ?></th>
                    <th><?php _e('Tags', 'science-communities'); ?></th>
                    <th><?php _e('Actions', 'science-communities'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($communities as $community): 
                    $tags = sc_get_community_tags($community->community_id);
                ?>
                <tr>
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="community_ids[]" value="<?php echo esc_attr($community->community_id); ?>">
                    </th>
                    <td><strong><?php echo esc_html($community->community_id); ?></strong></td>
                    <td>
                        <strong><?php echo esc_html($community->name); ?></strong>
                        <?php if ($community->logo): ?>
                        <br><img src="<?php echo esc_url($community->logo); ?>" style="max-width: 50px; max-height: 50px; margin-top: 5px;">
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(sc_get_faculty_name($community->faculty_id)); ?></td>
                    <td>
                        <?php 
                        $status_class = $community->is_archived ? 'archived' : $community->status;
                        ?>
                        <span class="status-badge status-<?php echo esc_attr($status_class); ?>">
                            <?php echo esc_html(sc_get_status_display($community->status, $community->is_archived)); ?>
                        </span>
                    </td>
                    <td>
                        <?php foreach ($tags as $tag): ?>
                            <span class="tag"><?php echo esc_html($tag->tag_name); ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'id' => $community->community_id), site_url('/sc-admin/'))); ?>" class="button button-small">
                            <?php _e('Edit', 'science-communities'); ?>
                        </a>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('delete' => $community->community_id)), 'delete_community_' . $community->community_id)); ?>" 
                           class="button button-small" 
                           onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this community?', 'science-communities'); ?>')">
                            <?php _e('Delete', 'science-communities'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
</div>

<script>
document.getElementById('cb-select-all').addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('input[name="community_ids[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = document.getElementById('cb-select-all').checked;
    });
});
</script>

<style>
<style>
.tag {
    display: inline-block;
    padding: 2px 8px;
    margin: 2px;
    background: #f0f0f0;
    border-radius: 3px;
    font-size: 11px;
}
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.status-active {
    background: #d4edda;
    color: #155724;
}
.status-limited {
    background: #fff3cd;
    color: #856404;
}
.status-suspended {
    background: #f8d7da;
    color: #721c24;
}
.status-archived {
    background: #e2e3e5;
    color: #383d41;
}
</style>