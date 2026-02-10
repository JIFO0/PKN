<?php
/**
 * Template for displaying a filterable list of all communities
 *
 * Shows all communities with interactive filters
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get all available tags and faculties
global $wpdb;
$tags_table = $wpdb->prefix . 'science_tags';
$faculties_table = $wpdb->prefix . 'science_faculties';

$all_tags = $wpdb->get_results("SELECT id, tag_name FROM $tags_table ORDER BY tag_name ASC");
$all_faculties = $wpdb->get_results("SELECT id, faculty_name FROM $faculties_table ORDER BY faculty_name ASC");

// Get filter parameters
$selected_tags = isset($_GET['filter_tags']) ? array_map('intval', (array)$_GET['filter_tags']) : array();
$selected_faculties = isset($_GET['filter_faculties']) ? array_map('intval', (array)$_GET['filter_faculties']) : array();
$search_term = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
$sort_order = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'name_asc';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

// Build query
$communities_table = $wpdb->prefix . 'science_communities';
$relationships_table = $wpdb->prefix . 'science_community_tags';

$where_clauses = array();
$join_clauses = "";

// Status filter (exclude archived by default)
if ($status_filter === 'all') {
    $where_clauses[] = "c.is_archived = 0";
} elseif ($status_filter === 'archived') {
    $where_clauses[] = "c.is_archived = 1";
} elseif ($status_filter === 'active') {
    $where_clauses[] = "c.status = 'active' AND c.is_archived = 0";
}

// Search term
if (!empty($search_term)) {
    $search_like = '%' . $wpdb->esc_like($search_term) . '%';
    $where_clauses[] = $wpdb->prepare(
        "(c.name LIKE %s OR c.shortdescription LIKE %s)",
        $search_like,
        $search_like
    );
}

// Faculty filter
if (!empty($selected_faculties)) {
    $faculty_placeholders = implode(',', array_fill(0, count($selected_faculties), '%d'));
    $where_clauses[] = $wpdb->prepare(
        "c.faculty_id IN ($faculty_placeholders)",
        $selected_faculties
    );
}

// Tag filter (communities must have ALL selected tags)
if (!empty($selected_tags)) {
    $tag_count = count($selected_tags);
    $tag_placeholders = implode(',', array_fill(0, $tag_count, '%d'));
    
    $where_clauses[] = $wpdb->prepare(
        "c.community_id IN (
            SELECT r.community_id 
            FROM $relationships_table AS r
            WHERE r.tag_id IN ($tag_placeholders)
            GROUP BY r.community_id
            HAVING COUNT(DISTINCT r.tag_id) = %d
        )",
        array_merge($selected_tags, array($tag_count))
    );
}

// Build final query
$query = "SELECT c.* FROM $communities_table AS c";

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

error_log('Final SQL: ' . $wpdb->last_query);
error_log('SQL Error: ' . $wpdb->last_error);
error_log('Results count: ' . count($results));
if (!empty($results)) {
    error_log('First result: ' . print_r($results[0], true));
}
error_log('==== END SEARCH DEBUG ====');

// Add sorting
switch ($sort_order) {
    case 'name_desc':
        $query .= " ORDER BY c.name DESC";
        break;
    case 'newest':
        $query .= " ORDER BY c.created_at DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY c.created_at ASC";
        break;
    case 'name_asc':
    default:
        $query .= " ORDER BY c.name ASC";
        break;
}

$communities = $wpdb->get_results($query);

// Get detail page URL
$detail_page_url = site_url('/details/');
?>

<div class="sc-list-filters">
        <form method="get" class="sc-filter-form" id="sc-filter-form">
            <!-- Search Section -->
            <div class="sc-filter-section">
                <label class="sc-filter-label">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <?php _e('Search', 'science-communities'); ?>
                </label>
                <input 
                    type="text" 
                    name="filter_search" 
                    class="sc-filter-search"
                    value="<?php echo esc_attr($search_term); ?>" 
                    placeholder="<?php esc_attr_e('Search communities...', 'science-communities'); ?>"
                >
            </div>

            <!-- Sort Section -->
            <div class="sc-filter-section">
                <label class="sc-filter-label">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 6h18M7 12h10M10 18h4"></path>
                    </svg>
                    <?php _e('Sort By', 'science-communities'); ?>
                </label>
                <select name="sort" class="sc-filter-select">
                    <option value="name_asc" <?php selected($sort_order, 'name_asc'); ?>>
                        <?php _e('Name (A-Z)', 'science-communities'); ?>
                    </option>
                    <option value="name_desc" <?php selected($sort_order, 'name_desc'); ?>>
                        <?php _e('Name (Z-A)', 'science-communities'); ?>
                    </option>
                    <option value="newest" <?php selected($sort_order, 'newest'); ?>>
                        <?php _e('Newest First', 'science-communities'); ?>
                    </option>
                    <option value="oldest" <?php selected($sort_order, 'oldest'); ?>>
                        <?php _e('Oldest First', 'science-communities'); ?>
                    </option>
                </select>
            </div>

            <!-- Status Filter -->
            <div class="sc-filter-section">
                <label class="sc-filter-label">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <?php _e('Status', 'science-communities'); ?>
                </label>
                <select name="status" class="sc-filter-select">
                    <option value="all" <?php selected($status_filter, 'all'); ?>>
                        <?php _e('Active Communities', 'science-communities'); ?>
                    </option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>>
                        <?php _e('Active Only', 'science-communities'); ?>
                    </option>
                    <option value="archived" <?php selected($status_filter, 'archived'); ?>>
                        <?php _e('Archived', 'science-communities'); ?>
                    </option>
                </select>
            </div>

            <?php if (!empty($all_tags)): ?>
            <div class="sc-filter-section">
                <label class="sc-filter-label">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                        <line x1="7" y1="7" x2="7.01" y2="7"></line>
                    </svg>
                    <?php _e('Filter by Tags', 'science-communities'); ?>
                </label>
                <div class="sc-filter-tags">
                    <?php foreach ($all_tags as $tag): ?>
                    <label class="sc-filter-tag-item <?php echo in_array($tag->id, $selected_tags) ? 'selected' : ''; ?>">
                        <input 
                            type="checkbox" 
                            name="filter_tags[]" 
                            value="<?php echo esc_attr($tag->id); ?>"
                            <?php checked(in_array($tag->id, $selected_tags)); ?>
                        >
                        <span><?php echo esc_html($tag->tag_name); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($all_faculties)): ?>
            <div class="sc-filter-section">
                <label class="sc-filter-label">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <?php _e('Filter by Faculty', 'science-communities'); ?>
                </label>
                <div class="sc-filter-faculties">
                    <?php foreach ($all_faculties as $faculty): ?>
                    <label class="sc-filter-faculty-item <?php echo in_array($faculty->id, $selected_faculties) ? 'selected' : ''; ?>">
                        <input 
                            type="checkbox" 
                            name="filter_faculties[]" 
                            value="<?php echo esc_attr($faculty->id); ?>"
                            <?php checked(in_array($faculty->id, $selected_faculties)); ?>
                        >
                        <span><?php echo esc_html($faculty->faculty_name); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="sc-filter-actions">
                <button type="submit" class="sc-filter-apply">
                    <?php _e('Apply Filters', 'science-communities'); ?>
                </button>
                <button type="button" class="sc-filter-clear" id="sc-filter-clear">
                    <?php _e('Clear All', 'science-communities'); ?>
                </button>
            </div>
        </form>

        <div class="sc-filter-summary">
            <span class="sc-result-count">
                <?php 
                printf(
                    _n(
                        '%s community found', 
                        '%s communities found', 
                        count($communities), 
                        'science-communities'
                    ), 
                    '<strong>' . number_format_i18n(count($communities)) . '</strong>'
                ); 
                ?>
            </span>
            
            <?php if (!empty($selected_tags) || !empty($selected_faculties) || !empty($search_term) || $sort_order !== 'name_asc' || $status_filter !== 'all'): ?>
            <span class="sc-active-filters-count">
                <?php 
                $filter_count = count($selected_tags) + count($selected_faculties) + (!empty($search_term) ? 1 : 0);
                if ($sort_order !== 'name_asc') $filter_count++;
                if ($status_filter !== 'all') $filter_count++;
                printf(
                    _n(
                        '%s active filter', 
                        '%s active filters', 
                        $filter_count, 
                        'science-communities'
                    ), 
                    $filter_count
                ); 
                ?>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($communities)): ?>
    <div class="sc-no-results">
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <h3><?php _e('No communities found', 'science-communities'); ?></h3>
        <p><?php _e('Try adjusting your filters or search term.', 'science-communities'); ?></p>
    </div>
    <?php else: ?>
    <div class="sc-list-grid">
        <?php foreach ($communities as $community): 
            $tags = sc_get_community_tags($community->community_id);
        ?>
        <div class="sc-list-card">
            <?php if (!empty($community->logo)): ?>
            <div class="sc-card-logo">
                <img src="<?php echo esc_url($community->logo); ?>" 
                     alt="<?php echo esc_attr(sprintf(__('Logo of %s', 'science-communities'), $community->name)); ?>">
            </div>
            <?php endif; ?>
            
            <div class="sc-card-content">
                <h3 class="sc-card-title">
                    <a href="<?php echo esc_url(add_query_arg('id', $community->community_id, $detail_page_url)); ?>">
                        <?php echo esc_html($community->name); ?>
                    </a>
                </h3>
                
                <?php if (!empty($community->shortdescription)): ?>
                <p class="sc-card-description">
                    <?php echo esc_html(wp_trim_words($community->shortdescription, 20)); ?>
                </p>
                <?php endif; ?>
                
                <?php if (!empty($tags)): ?>
                <div class="sc-card-tags">
                    <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                    <span class="sc-card-tag"><?php echo esc_html($tag->tag_name); ?></span>
                    <?php endforeach; ?>
                    <?php if (count($tags) > 3): ?>
                    <span class="sc-card-tag-more">+<?php echo count($tags) - 3; ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="sc-card-footer">
                <a href="<?php echo esc_url(add_query_arg('id', $community->community_id, $detail_page_url)); ?>" 
                   class="sc-card-link">
                    <?php _e('View Details', 'science-communities'); ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle filter checkbox styling
    const checkboxes = document.querySelectorAll('.sc-filter-tag-item input, .sc-filter-faculty-item input');
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            this.closest('label').classList.toggle('selected', this.checked);
        });
    });
    
    // Handle clear filters
    document.getElementById('sc-filter-clear').addEventListener('click', function() {
        document.querySelectorAll('.sc-filter-form input[type="checkbox"]').forEach(function(cb) {
            cb.checked = false;
            cb.closest('label').classList.remove('selected');
        });
        document.querySelector('.sc-filter-search').value = '';
        document.getElementById('sc-filter-form').submit();
    });
    
    // Auto-submit on filter change (optional - remove if you prefer manual apply)
    document.querySelectorAll('.sc-filter-form input[type="checkbox"]').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            // Uncomment the line below to auto-submit on change
            // document.getElementById('sc-filter-form').submit();
        });
    });
});
</script>