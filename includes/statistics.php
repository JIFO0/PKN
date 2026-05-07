<?php
/**
 * Statistics and activity helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_create_statistics_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table = $wpdb->prefix . 'science_community_statistics';
    $sql = "CREATE TABLE $table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        community_id VARCHAR(5) NOT NULL,
        event_type VARCHAR(50) NOT NULL,
        event_value VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY community_id (community_id),
        KEY event_type (event_type),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function sc_track_stat_event($community_id, $event_type, $event_value = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'science_community_statistics';

    if (empty($community_id) || empty($event_type)) {
        return false;
    }

    return $wpdb->insert(
        $table,
        array(
            'community_id' => sanitize_text_field($community_id),
            'event_type' => sanitize_key($event_type),
            'event_value' => sanitize_text_field($event_value),
        ),
        array('%s', '%s', '%s')
    ) !== false;
}

function sc_track_community_view($community_id) {
    return sc_track_stat_event($community_id, 'view');
}

function sc_track_social_click($community_id, $platform) {
    return sc_track_stat_event($community_id, 'social_click', $platform);
}

function sc_track_search_term_for_results($search_term, $communities) {
    if (empty($search_term) || empty($communities)) {
        return;
    }

    foreach ((array) $communities as $community) {
        $community_id = is_array($community) ? ($community['id'] ?? '') : ($community->community_id ?? '');
        if (!empty($community_id)) {
            sc_track_stat_event($community_id, 'search_term', $search_term);
        }
    }
}

function sc_get_tag_usage_statistics($community_ids = array()) {
    global $wpdb;
    $tags_table = $wpdb->prefix . 'science_tags';
    $relations_table = $wpdb->prefix . 'science_community_tags';

    $community_ids = array_values(array_filter(array_map('sanitize_text_field', (array) $community_ids)));
    $join_filter_sql = '';

    if (!empty($community_ids)) {
        $placeholders = implode(',', array_fill(0, count($community_ids), '%s'));
        $join_filter_sql = $wpdb->prepare(" AND r.community_id IN ($placeholders)", $community_ids);
    }

    return $wpdb->get_results(
        "SELECT t.tag_name, COUNT(r.community_id) AS usage_count
        FROM $tags_table t
        LEFT JOIN $relations_table r ON t.id = r.tag_id" . $join_filter_sql . "
        GROUP BY t.id, t.tag_name
        ORDER BY usage_count DESC, t.tag_name ASC"
    );
}

function sc_get_dashboard_data() {
    global $wpdb;

    $communities_table = $wpdb->prefix . 'science_communities';
    $audit_table = $wpdb->prefix . 'science_communities_audit';
    $stats_table = $wpdb->prefix . 'science_community_statistics';

    return array(
        'recent_edits' => $wpdb->get_results(
            "SELECT a.community_id, c.name, a.action, a.field_name, a.created_at
             FROM $audit_table a
             LEFT JOIN $communities_table c ON c.community_id = a.community_id
             ORDER BY a.created_at DESC
             LIMIT 10"
        ),
        'most_viewed' => $wpdb->get_results(
            "SELECT c.community_id, c.name, COUNT(s.id) AS views
             FROM $stats_table s
             INNER JOIN $communities_table c ON c.community_id = s.community_id
             WHERE s.event_type = 'view'
             GROUP BY c.community_id, c.name
             ORDER BY views DESC, c.name ASC
             LIMIT 10"
        ),
        'without_logos' => $wpdb->get_results(
            "SELECT community_id, name
             FROM $communities_table
             WHERE logo IS NULL OR logo = ''
             ORDER BY name ASC"
        ),
        'missing_descriptions' => $wpdb->get_results(
            "SELECT community_id, name
             FROM $communities_table
             WHERE description IS NULL OR description = ''
             ORDER BY name ASC"
        ),
        'tag_usage' => sc_get_tag_usage_statistics(),
    );
}

function sc_get_statistics_data($community_ids = array()) {
    global $wpdb;
    $communities_table = $wpdb->prefix . 'science_communities';
    $stats_table = $wpdb->prefix . 'science_community_statistics';

    $community_ids = array_values(array_filter(array_map('sanitize_text_field', (array) $community_ids)));
    $community_filter_sql = '';

    if (!empty($community_ids)) {
        $placeholders = implode(',', array_fill(0, count($community_ids), '%s'));
        $community_filter_sql = $wpdb->prepare(" AND c.community_id IN ($placeholders)", $community_ids);
    }

    return array(
        'views_per_community' => $wpdb->get_results(
            "SELECT c.community_id, c.name, COUNT(s.id) AS total_views
             FROM $communities_table c
             LEFT JOIN $stats_table s ON s.community_id = c.community_id AND s.event_type = 'view'
             WHERE 1=1" . $community_filter_sql . "
             GROUP BY c.community_id, c.name
             ORDER BY total_views DESC, c.name ASC"
        ),
        'social_clicks' => $wpdb->get_results(
            "SELECT c.community_id, c.name, s.event_value AS platform, COUNT(s.id) AS total_clicks
             FROM $stats_table s
             INNER JOIN $communities_table c ON c.community_id = s.community_id
             WHERE s.event_type = 'social_click'" . $community_filter_sql . "
             GROUP BY c.community_id, c.name, s.event_value
             ORDER BY total_clicks DESC, c.name ASC"
        ),
        'search_terms' => $wpdb->get_results(
            "SELECT c.community_id, c.name, s.event_value AS search_term, COUNT(s.id) AS hits
             FROM $stats_table s
             INNER JOIN $communities_table c ON c.community_id = s.community_id
             WHERE s.event_type = 'search_term'" . $community_filter_sql . "
             GROUP BY c.community_id, c.name, s.event_value
             ORDER BY hits DESC, c.name ASC"
        ),
        'tag_popularity' => sc_get_tag_usage_statistics($community_ids),
    );
}
