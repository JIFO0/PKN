<?php
/**
 * Template for displaying search results
 *
 * Shows community summaries with links to detail pages based on search criteria
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get search parameters
$search_query = isset($_GET['sc_search']) ? sanitize_text_field($_GET['sc_search']) : '';
$selected_tags = isset($_GET['sc_tags']) ? array_map('sanitize_text_field', (array)$_GET['sc_tags']) : array();

// Get search results from the database
$communities = sc_search_communities($search_query, $selected_tags);

// Get the detail page URL (assumes a page with the shortcode [science_community_detail])
$detail_page_url = sc_get_page_url_by_shortcode('science_community_detail', site_url('/details/'));
$search_page_url = sc_get_page_url_by_shortcode('science_communities_search', home_url('/science-communities/'));?>

<div class="sc-results-container">
    <div class="sc-results-header">
        <h1 class="sc-results-title">
            <?php 
            if (!empty($search_query)) {
                printf(
                    __('Search results for "%s"', 'science-communities'), 
                    esc_html($search_query)
                );
            } else {
                _e('All Science Communities', 'science-communities');
            }
            ?>
        </h1>
        
        <?php if (!empty($selected_tags)): ?>
        <div class="sc-active-filters">
            <span class="sc-filter-label"><?php _e('Filtered by:', 'science-communities'); ?></span>
            <?php foreach ($selected_tags as $tag): ?>
            <span class="sc-active-tag"><?php echo esc_html($tag); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="sc-result-count">
            <?php 
            printf(
                _n(
                    '%s community found', 
                    '%s communities found', 
                    count($communities), 
                    'science-communities'
                ), 
                number_format_i18n(count($communities))
            ); 
            ?>
        </div>
        
        <a href="<?php echo esc_url($search_page_url); ?>" class="sc-new-search">
            <?php _e('New Search', 'science-communities'); ?>
        </a>
    </div>

    <?php if (empty($communities)): ?>
    <div class="sc-no-results">
        <p><?php _e('No science communities found matching your search criteria.', 'science-communities'); ?></p>
        <p><?php _e('Try using different keywords or removing some filters.', 'science-communities'); ?></p>
    </div>
    <?php else: ?>
    <div class="sc-results-list">
        <?php foreach ($communities as $community): ?>
        <div class="sc-result-item">
            <div class="sc-result-content">
                <?php if (!empty($community['logo'])): ?>
                <div class="sc-result-logo">
                    <img src="<?php echo esc_url($community['logo']); ?>" 
                         alt="<?php echo esc_attr(sprintf(__('Logo of %s', 'science-communities'), $community['name'])); ?>">
                </div>
                <?php endif; ?>
                
                <div class="sc-result-info">
                    <h2 class="sc-result-name">
                        <?php echo esc_html($community['name']); ?>
                    </h2>
                    
                    <?php if (!empty($community['shortdescription'])): ?>
                    <div class="sc-result-description">
                        <?php echo wp_kses_post($community['shortdescription']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($community['tags']) && is_array($community['tags'])): ?>
                    <div class="sc-result-tags">
                    <?php foreach ($community['tags'] as $tag): ?>
                        <span class="sc-result-tag"><?php echo esc_html(is_object($tag) ? $tag->tag_name : $tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="sc-result-actions">
                <a href="<?php echo esc_url(add_query_arg('id', $community['id'], $detail_page_url)); ?>"
                   class="sc-view-details" 
                   aria-label="<?php echo esc_attr(sprintf(__('View details of %s', 'science-communities'), $community['name'])); ?>">
                    <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"></path>
                    </svg>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>