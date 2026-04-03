<?php
/**
 * Community activity dashboard.
 */

if (!defined('ABSPATH')) exit;
if (!sc_is_superadmin()) {
    echo '<div class="wrap"><p>' . esc_html__('Access denied.', 'science-communities') . '</p></div>';
    return;
}

$data = sc_get_dashboard_data();
?>
<div class="wrap">
    <h1><?php esc_html_e('Community Activity Dashboard', 'science-communities'); ?></h1>

    <h2><?php esc_html_e('Recent Edits (Last 10)', 'science-communities'); ?></h2>
    <table class="widefat striped">
        <thead><tr><th>ID</th><th><?php esc_html_e('Community', 'science-communities'); ?></th><th><?php esc_html_e('Action', 'science-communities'); ?></th><th><?php esc_html_e('Field', 'science-communities'); ?></th><th><?php esc_html_e('Date', 'science-communities'); ?></th></tr></thead>
        <tbody>
            <?php foreach ($data['recent_edits'] as $row): ?>
            <tr>
                <td><?php echo esc_html($row->community_id); ?></td>
                <td><?php echo esc_html($row->name ?: '—'); ?></td>
                <td><?php echo esc_html($row->action); ?></td>
                <td><?php echo esc_html($row->field_name ?: '—'); ?></td>
                <td><?php echo esc_html($row->created_at); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2><?php esc_html_e('Most Viewed Communities', 'science-communities'); ?></h2>
    <ul><?php foreach ($data['most_viewed'] as $row): ?><li><?php echo esc_html($row->name . ' (' . $row->views . ')'); ?></li><?php endforeach; ?></ul>

    <h2><?php esc_html_e('Communities Without Logos', 'science-communities'); ?></h2>
    <ul><?php foreach ($data['without_logos'] as $row): ?><li><?php echo esc_html($row->name . ' [' . $row->community_id . ']'); ?></li><?php endforeach; ?></ul>

    <h2><?php esc_html_e('Communities Missing Descriptions', 'science-communities'); ?></h2>
    <ul><?php foreach ($data['missing_descriptions'] as $row): ?><li><?php echo esc_html($row->name . ' [' . $row->community_id . ']'); ?></li><?php endforeach; ?></ul>

    <h2><?php esc_html_e('Tag Usage Statistics', 'science-communities'); ?></h2>
    <table class="widefat striped">
        <thead><tr><th><?php esc_html_e('Tag', 'science-communities'); ?></th><th><?php esc_html_e('Usage', 'science-communities'); ?></th></tr></thead>
        <tbody>
            <?php foreach ($data['tag_usage'] as $row): ?>
            <tr><td><?php echo esc_html($row->tag_name); ?></td><td><?php echo esc_html($row->usage_count); ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
