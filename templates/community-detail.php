<?php
/**
 * Template for displaying detailed information about a single community
 *
 * Shows comprehensive information about a science community including
 * description, social links, and other metadata.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get community ID from URL parameter
$community_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';

// Redirect to search page if no ID provided
if (empty($community_id)) {
    wp_safe_redirect(sc_get_page_url_by_shortcode('science_communities_search', home_url('/science-communities/')));
    exit;
}

// Get community data
$community = sc_get_community_by_id($community_id);

// Redirect to search page if community not found
if (!$community) {
    wp_safe_redirect(sc_get_page_url_by_shortcode('science_communities_search', home_url('/science-communities/')));
    exit;
}

sc_track_community_view($community_id);

// Prepare social links array for easier iteration
$social_links = array(
    'webpage' => array(
        'title' => __('Website', 'science-communities'),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>'
    ),
    'facebook' => array(
        'title' => __('Facebook', 'science-communities'),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>'
    ),
    'instagram' => array(
        'title' => __('Instagram', 'science-communities'),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>'
    ),
    'tiktok' => array(
        'title' => __('TikTok', 'science-communities'),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"></path><path d="M13 12h7a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-2a6 6 0 0 1-6-6v0"></path><path d="M9 18v2a3 3 0 0 0 3 3v0a3 3 0 0 0 3-3v-6"></path></svg>'
    ),
    'discord' => array(
        'title' => __('Discord', 'science-communities'),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 10a1 1 0 1 1-2 0a1 1 0 0 1 2 0z"></path><path d="M15 10a1 1 0 1 1 2 0a1 1 0 0 1-2 0z"></path><path d="M20 11c0-5-3-9-9-9c-5.3 0-8.3 3-9.8 6.4C0 10.7 0 13.5 0 16.5c0 0 1.2 3.5 6 3.5c0 0 .7-1 1.3-1.8c-2.5-.7-3.3-2-3.3-2c.7 .7 1.7 1 3 1.5c1.3 .4 2.7 .6 4 .6c1.3 0 2.7-.2 4-.6c1.3-.4 2.3-.8 3-1.5c0 0-.8 1.3-3.3 2c.6 .8 1.3 1.8 1.3 1.8c4.8 0 6-3.5 6-3.5c0-3-.3-5.7-1-7.8"></path></svg>'
    )
);

// Check if user can edit this community
$can_edit = sc_user_can_edit_community($community_id);

?>

<div class="sc-community-detail">
    <div class="sc-detail-header">
        <div class="sc-detail-navigation">
            <a href="<?php echo esc_url(sc_get_page_url_by_shortcode('science_communities_search', home_url('/science-communities/'))); ?>" class="sc-back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                <?php _e('Back to Search', 'science-communities'); ?>
            </a>
        </div>
        
        <?php if ($can_edit): ?>
        <div class="sc-admin-actions">
            <a href="<?php echo esc_url(add_query_arg(
                array('action' => 'edit', 'id' => $community_id),
                sc_get_admin_page_url()
            )); ?>" class="sc-edit-button">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <?php _e('Edit Community', 'science-communities'); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="sc-detail-main">
        <div class="sc-detail-content">
            <div class="sc-detail-title-area">
                <?php if (!empty($community['logo'])): ?>
                <div class="sc-detail-logo">
                    <img src="<?php echo esc_url($community['logo']); ?>" 
                         alt="<?php echo esc_attr(sprintf(__('Logo of %s', 'science-communities'), $community['name'])); ?>">
                </div>
                <?php endif; ?>
                
                <h1 class="sc-detail-title"><?php echo esc_html($community['name']); ?></h1>
                
                <?php if (!empty($community['tags']) && is_array($community['tags'])): ?>
                <div class="sc-detail-tags">
                    <?php foreach ($community['tags'] as $tag): ?>
                    <span class="sc-detail-tag"><?php echo esc_html($tag); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($community['shortdescription'])): ?>
            <div class="sc-detail-short-description">
                <?php echo esc_html($community['shortdescription']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($community['description'])): ?>
            <div class="sc-detail-description">
                <?php echo wp_kses_post($community['description']); ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="sc-detail-sidebar">
            <div class="sc-detail-social-links">
                <h2 class="sc-detail-section-title"><?php _e('Connect', 'science-communities'); ?></h2>
                
                <ul class="sc-social-list">
                    <?php foreach ($social_links as $key => $link): ?>
                        <?php if (!empty($community[$key])): ?>
                        <li class="sc-social-item sc-social-<?php echo esc_attr($key); ?>">
                            <a href="<?php echo esc_url(add_query_arg(array(
                                'sc_track_social' => 1,
                                'community_id' => $community_id,
                                'platform' => $key,
                                'redirect_to' => rawurlencode($community[$key])
                            ), home_url('/'))); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo $link['icon']; ?>
                                <span><?php echo esc_html($link['title']); ?></span>
                            </a>
                        </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="sc-detail-id">
                <span class="sc-detail-id-label"><?php _e('Community ID:', 'science-communities'); ?></span>
                <span class="sc-detail-id-value"><?php echo esc_html($community_id); ?></span>
            </div>
        </div>
    </div>
</div>
