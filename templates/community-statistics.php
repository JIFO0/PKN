<?php
/**
 * Community statistics page.
 */

if (!defined('ABSPATH')) exit;
if (!is_user_logged_in() || !sc_user_can_edit_any_community()) {
    echo '<p>' . esc_html__('Access denied.', 'science-communities') . '</p>';
    return;
}

$stats = sc_get_statistics_data();
?>
<div class="wrap">
    <h1><?php esc_html_e('Community Statistics', 'science-communities'); ?></h1>

    <h2><?php esc_html_e('Page Views per Community', 'science-communities'); ?></h2>
    <table class="widefat striped">
        <thead><tr><th>ID</th><th><?php esc_html_e('Community', 'science-communities'); ?></th><th><?php esc_html_e('Views', 'science-communities'); ?></th></tr></thead>
        <tbody><?php foreach ($stats['views_per_community'] as $row): ?><tr><td><?php echo esc_html($row->community_id); ?></td><td><?php echo esc_html($row->name); ?></td><td><?php echo esc_html($row->total_views); ?></td></tr><?php endforeach; ?></tbody>
    </table>

    <h2><?php esc_html_e('Social Link Clicks', 'science-communities'); ?></h2>
    <table class="widefat striped">
        <thead><tr><th>ID</th><th><?php esc_html_e('Community', 'science-communities'); ?></th><th><?php esc_html_e('Platform', 'science-communities'); ?></th><th><?php esc_html_e('Clicks', 'science-communities'); ?></th></tr></thead>
        <tbody><?php foreach ($stats['social_clicks'] as $row): ?><tr><td><?php echo esc_html($row->community_id); ?></td><td><?php echo esc_html($row->name); ?></td><td><?php echo esc_html($row->platform); ?></td><td><?php echo esc_html($row->total_clicks); ?></td></tr><?php endforeach; ?></tbody>
    </table>

    <h2><?php esc_html_e('Search Terms Finding Communities', 'science-communities'); ?></h2>
    <table class="widefat striped">
        <thead><tr><th>ID</th><th><?php esc_html_e('Community', 'science-communities'); ?></th><th><?php esc_html_e('Search term', 'science-communities'); ?></th><th><?php esc_html_e('Hits', 'science-communities'); ?></th></tr></thead>
        <tbody><?php foreach ($stats['search_terms'] as $row): ?><tr><td><?php echo esc_html($row->community_id); ?></td><td><?php echo esc_html($row->name); ?></td><td><?php echo esc_html($row->search_term); ?></td><td><?php echo esc_html($row->hits); ?></td></tr><?php endforeach; ?></tbody>
    </table>

    <h2><?php esc_html_e('Tag Popularity', 'science-communities'); ?></h2>
    <table class="widefat striped">
        <thead><tr><th><?php esc_html_e('Tag', 'science-communities'); ?></th><th><?php esc_html_e('Usage', 'science-communities'); ?></th></tr></thead>
        <tbody><?php foreach ($stats['tag_popularity'] as $row): ?><tr><td><?php echo esc_html($row->tag_name); ?></td><td><?php echo esc_html($row->usage_count); ?></td></tr><?php endforeach; ?></tbody>
    </table>
</div>
