<?php
/**
 * Admin page for managing tags and faculties
 */

if (!defined('ABSPATH')) exit;
if (!sc_is_superadmin()) {
    echo '<p>' . __('Access denied.', 'science-communities') . '</p>';
    return;
}

global $wpdb;
$tags_table = $wpdb->prefix . 'science_tags';
$faculties_table = $wpdb->prefix . 'science_faculties';

$tags = $wpdb->get_results("SELECT * FROM $tags_table ORDER BY tag_name ASC");
$faculties = $wpdb->get_results("SELECT * FROM $faculties_table ORDER BY faculty_name ASC");
?>

<div class="wrap">
    <h1><?php _e('Manage Tags & Faculties', 'science-communities'); ?></h1>
    
    <div style="display: flex; gap: 20px;">
        <div style="flex: 1;">
            <div class="card">
                <h2><?php _e('Tags', 'science-communities'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Tag Name', 'science-communities'); ?></th>
                            <th><?php _e('Communities Using', 'science-communities'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tags as $tag): 
                            $count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}science_community_tags WHERE tag_id = %d",
                                $tag->id
                            ));
                        ?>
                        <tr>
                            <td><?php echo esc_html($tag->tag_name); ?></td>
                            <td><?php echo number_format_i18n($count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div style="flex: 1;">
            <div class="card">
                <h2><?php _e('Faculties', 'science-communities'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Faculty Name', 'science-communities'); ?></th>
                            <th><?php _e('Communities', 'science-communities'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faculties as $faculty): 
                            $count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}science_communities WHERE faculty_id = %d",
                                $faculty->id
                            ));
                        ?>
                        <tr>
                            <td><?php echo esc_html($faculty->faculty_name); ?></td>
                            <td><?php echo number_format_i18n($count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>