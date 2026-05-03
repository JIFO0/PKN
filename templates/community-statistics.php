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
</div>
