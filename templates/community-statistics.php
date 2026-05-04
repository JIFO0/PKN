<?php
/**
 * Community statistics page.
 */

if (!defined('ABSPATH')) exit;
if (!is_user_logged_in() || !sc_user_can_edit_any_community()) {
    echo '<p>' . esc_html__('Access denied.', 'science-communities') . '</p>';
    return;
}

global $wpdb;
$editable_ids = array_map(function($c){ return $c['community_id']; }, sc_get_editable_communities());
$stats = sc_get_statistics_data($editable_ids);

$apps_table = $wpdb->prefix . 'science_community_applications';
$applications = array();
if (!empty($editable_ids)) {
    $placeholders = implode(',', array_fill(0, count($editable_ids), '%s'));
    $applications = $wpdb->get_results($wpdb->prepare("SELECT * FROM $apps_table WHERE community_id IN ($placeholders) ORDER BY created_at DESC", $editable_ids));
    $wpdb->query($wpdb->prepare("UPDATE $apps_table SET is_read = 1 WHERE community_id IN ($placeholders)", $editable_ids));
}
?>
<div class="wrap sc-statistics-page">
    <h1><?php echo esc_html(sc_t('community_statistics')); ?></h1>
    <?php sc_render_lang_toggle(); ?>

    <h2><?php echo esc_html(sc_t('page_views_per_community')); ?></h2>
    <table class="widefat striped">
        <thead><tr><th>ID</th><th><?php echo esc_html(sc_t('community')); ?></th><th><?php echo esc_html(sc_t('views')); ?></th></tr></thead>
        <tbody><?php foreach ($stats['views_per_community'] as $row): ?><tr><td><?php echo esc_html($row->community_id); ?></td><td><?php echo esc_html($row->name); ?></td><td><?php echo esc_html($row->total_views); ?></td></tr><?php endforeach; ?></tbody>
    </table>

    <h2><?php echo esc_html(sc_t('social_link_clicks')); ?></h2>
    <table class="widefat striped">
        <thead><tr><th>ID</th><th><?php echo esc_html(sc_t('community')); ?></th><th><?php echo esc_html(sc_t('platform')); ?></th><th><?php echo esc_html(sc_t('clicks')); ?></th></tr></thead>
        <tbody><?php foreach ($stats['social_clicks'] as $row): ?><tr><td><?php echo esc_html($row->community_id); ?></td><td><?php echo esc_html($row->name); ?></td><td><?php echo esc_html($row->platform); ?></td><td><?php echo esc_html($row->total_clicks); ?></td></tr><?php endforeach; ?></tbody>
    </table>

    <h2><?php echo esc_html(sc_t('search_terms_finding')); ?></h2>
    <table class="widefat striped">
        <thead><tr><th>ID</th><th><?php echo esc_html(sc_t('community')); ?></th><th><?php echo esc_html(sc_t('search_term')); ?></th><th><?php echo esc_html(sc_t('hits')); ?></th></tr></thead>
        <tbody><?php foreach ($stats['search_terms'] as $row): ?><tr><td><?php echo esc_html($row->community_id); ?></td><td><?php echo esc_html($row->name); ?></td><td><?php echo esc_html($row->search_term); ?></td><td><?php echo esc_html($row->hits); ?></td></tr><?php endforeach; ?></tbody>
    </table>

    <h2><?php echo esc_html(sc_t('tag_popularity')); ?></h2>
    <table class="widefat striped">
        <thead><tr><th><?php echo esc_html(sc_t('tag')); ?></th><th><?php echo esc_html(sc_t('usage')); ?></th></tr></thead>
        <tbody><?php foreach ($stats['tag_popularity'] as $row): ?><tr><td><?php echo esc_html($row->tag_name); ?></td><td><?php echo esc_html($row->usage_count); ?></td></tr><?php endforeach; ?></tbody>
    </table>
    <h2><?php echo esc_html__('Join applications', 'science-communities'); ?></h2>
    <table class="widefat striped">
        <thead><tr><th>Date</th><th>Community</th><th>Name</th><th>Email</th><th>Info</th><th>Optional contact</th></tr></thead>
        <tbody><?php if (empty($applications)): ?><tr><td colspan="6"><?php esc_html_e('No applications yet.', 'science-communities'); ?></td></tr><?php else: foreach ($applications as $a): ?><tr><td><?php echo esc_html($a->created_at); ?></td><td><?php echo esc_html($a->community_id); ?></td><td><?php echo esc_html($a->applicant_name); ?></td><td><?php echo esc_html($a->applicant_email); ?></td><td><?php echo esc_html($a->applicant_info); ?></td><td><?php echo esc_html($a->applicant_contact); ?></td></tr><?php endforeach; endif; ?></tbody>
    </table>
</div>
