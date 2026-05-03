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
sc_track_search_term_for_results($search_query, $communities);

// Get the detail page URL (assumes a page with the shortcode [science_community_detail])
$detail_page_url = sc_get_page_url_by_shortcode('science_community_detail', site_url('/details/'));
$search_page_url = sc_get_page_url_by_shortcode('science_communities_search', home_url('/science-communities/'));?>

<div class="sc-results-container">
    <div class="sc-results-header">
        <h1 class="sc-results-title">
            <?php 
            if (!empty($search_query)) {
                printf(
                    sc_t('search_results_for'), 
                    esc_html($search_query)
                );
            } else {
                echo esc_html(sc_t('all_science_communities'));
            }
            ?>
        </h1>
        <?php sc_render_lang_toggle(); ?>
        
        <?php if (!empty($selected_tags)): ?>
        <div class="sc-active-filters">
            <span class="sc-filter-label"><?php echo esc_html(sc_t('filtered_by')); ?></span>
            <?php foreach ($selected_tags as $tag): ?>
            <span class="sc-active-tag"><?php echo esc_html($tag); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="sc-result-count">
            <?php 
            printf(
                _n(
                    sc_t('community_found'), 
                    sc_t('communities_found'), 
                    count($communities), 
                    'science-communities'
                ), 
                number_format_i18n(count($communities))
            ); 
            ?>
        </div>
        
        <a href="<?php echo esc_url($search_page_url); ?>" class="sc-new-search">
            <?php echo esc_html(sc_t('new_search')); ?>
        </a>
    </div>

    <?php if (empty($communities)): ?>
    <div class="sc-no-results">
        <p><?php echo esc_html(sc_t('no_search_results')); ?></p>
        <p><?php echo esc_html(sc_t('try_different_keywords')); ?></p>
    </div>
    <?php else: ?>
    <div class="sc-results-list">
        <?php foreach ($communities as $community): ?>
        <div class="sc-result-item">
            <div class="sc-result-content">
                <?php if (!empty($community['logo'])): ?>
                <div class="sc-result-logo">
                    <img src="<?php echo esc_url($community['logo']); ?>" 
                         alt="<?php echo esc_attr(sprintf(sc_t('logo_of'), $community['name'])); ?>">
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
                <?php if (!empty($community['contact_email']) && !empty($community['open_for_applications'])): ?>
                <button type="button" class="sc-apply-button" data-community-id="<?php echo esc_attr($community['id']); ?>"><?php echo esc_html__('Apply to join', 'science-communities'); ?></button>
                <?php endif; ?>
                <a href="<?php echo esc_url(add_query_arg('id', $community['id'], $detail_page_url)); ?>"
                   class="sc-view-details" 
                   aria-label="<?php echo esc_attr(sprintf(sc_t('view_details_of'), $community['name'])); ?>">
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
<form id="sc-apply-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none;">
    <input type="hidden" name="action" value="sc_submit_join_application">
    <?php wp_nonce_field('sc_join_application', 'sc_join_application_nonce'); ?>
    <input type="hidden" name="community_id" id="sc-apply-community-id">
    <input type="hidden" name="applicant_name" id="sc-apply-name">
    <input type="hidden" name="applicant_email" id="sc-apply-email">
    <input type="hidden" name="applicant_info" id="sc-apply-info">
    <input type="hidden" name="applicant_contact" id="sc-apply-contact">
</form>
<script>
document.querySelectorAll('.sc-apply-button').forEach(btn=>btn.addEventListener('click',()=>{
 const name=prompt('Your name:'); if(!name)return;
 const info=prompt('Tell us about yourself:'); if(!info)return;
 const email=prompt('Contact email:'); if(!email)return;
 const contact=prompt('Optional contact (Discord/Facebook/Instagram):','')||'';
 document.getElementById('sc-apply-community-id').value=btn.dataset.communityId;
 document.getElementById('sc-apply-name').value=name;
 document.getElementById('sc-apply-email').value=email;
 document.getElementById('sc-apply-info').value=info;
 document.getElementById('sc-apply-contact').value=contact;
 document.getElementById('sc-apply-form').submit();
}));
</script>
