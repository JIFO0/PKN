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

global $wpdb;
$tags_table = $wpdb->prefix . 'science_tags';
$faculties_table = $wpdb->prefix . 'science_faculties';
$default_logo_url = 'https://kola.ug.edu.pl/wp-content/uploads/2026/05/deafultlogomain.png';

// Get search parameters
$search_query = isset($_GET['sc_search']) ? sanitize_text_field($_GET['sc_search']) : '';
$selected_tags = isset($_GET['sc_tags']) ? array_map('intval', (array) $_GET['sc_tags']) : array();
$selected_faculties = isset($_GET['sc_faculties']) ? array_map('intval', (array) $_GET['sc_faculties']) : array();
$open_for_applications = !empty($_GET['sc_open_applications']);

// Get search results from the database
$communities = sc_search_communities($search_query, $selected_tags, true, $selected_faculties, $open_for_applications);
sc_track_search_term_for_results($search_query, $communities);

// Get the detail page URL (assumes a page with the shortcode [science_community_detail])
$detail_page_url = sc_get_page_url_by_shortcode('science_community_detail', site_url('/details/'));
$search_page_url = sc_get_page_url_by_shortcode('science_communities_search', home_url('/science-communities/'));
?>

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

        <?php if (!empty($selected_tags) || !empty($selected_faculties) || $open_for_applications): ?>
        <div class="sc-active-filters">
            <span class="sc-filter-label"><?php echo esc_html(sc_t('filtered_by')); ?></span>
            <?php if ($open_for_applications): ?>
                <span class="sc-active-tag"><?php echo esc_html(sc_t('open_for_applications_only')); ?></span>
            <?php endif; ?>
            <?php foreach ($selected_faculties as $faculty_id):
                $faculty = $wpdb->get_row($wpdb->prepare("SELECT faculty_name FROM $faculties_table WHERE id = %d", $faculty_id));
                if ($faculty):
            ?>
                <span class="sc-active-tag"><?php echo esc_html($faculty->faculty_name); ?></span>
            <?php endif; endforeach; ?>
            <?php foreach ($selected_tags as $tag_id):
                $tag = $wpdb->get_row($wpdb->prepare("SELECT tag_name FROM $tags_table WHERE id = %d", $tag_id));
                if ($tag):
            ?>
                <span class="sc-active-tag"><?php echo esc_html($tag->tag_name); ?></span>
            <?php endif; endforeach; ?>
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
        <?php foreach ($communities as $community):
            $logo_url = !empty($community['logo']) ? $community['logo'] : $default_logo_url;
        ?>
        <div class="sc-result-item">
            <div class="sc-result-content">
                <div class="sc-result-logo">
                    <img src="<?php echo esc_url($logo_url); ?>"
                         alt="<?php echo esc_attr(sprintf(sc_t('logo_of'), $community['name'])); ?>">
                </div>

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
                <button type="button" class="sc-apply-button" data-community-id="<?php echo esc_attr($community['id']); ?>"><?php echo esc_html(sc_t('apply_to_join')); ?></button>
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

<div class="sc-apply-modal" id="sc-apply-modal" aria-hidden="true" style="display:none;">
    <div class="sc-apply-modal-content" role="dialog" aria-modal="true" aria-labelledby="sc-apply-modal-title">
        <button type="button" class="sc-apply-close" id="sc-apply-close" aria-label="<?php echo esc_attr(sc_t('close_modal')); ?>">&times;</button>
        <h3 id="sc-apply-modal-title"><?php echo esc_html(sc_t('apply_to_this_community')); ?></h3>
        <form id="sc-apply-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="sc_submit_join_application">
            <?php wp_nonce_field('sc_join_application', 'sc_join_application_nonce'); ?>
            <input type="hidden" name="community_id" id="sc-apply-community-id">
            <div class="sc-apply-field-row">
                <label><span><?php echo esc_html(sc_t('name')); ?></span><input type="text" name="applicant_name" required></label>
                <label><span><?php echo esc_html(sc_t('surname')); ?></span><input type="text" name="applicant_surname"></label>
            </div>
            <label><span><?php echo esc_html(sc_t('email')); ?></span><input type="email" name="applicant_email" required></label>
            <label><span><?php echo esc_html(sc_t('additional_contact_info')); ?></span><textarea name="applicant_contact"></textarea></label>
            <label><span><?php echo esc_html(sc_t('tell_us_about_yourself')); ?></span><textarea name="applicant_info" required></textarea></label>
            <button type="submit" class="sc-search-button"><?php echo esc_html(sc_t('send_application')); ?></button>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('sc-apply-modal');
    const closeBtn = document.getElementById('sc-apply-close');
    const communityInput = document.getElementById('sc-apply-community-id');

    document.querySelectorAll('.sc-apply-button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (communityInput) {
                communityInput.value = btn.dataset.communityId || '';
            }
            if (modal) {
                modal.style.display = 'flex';
                modal.setAttribute('aria-hidden', 'false');
            }
        });
    });

    function closeModal() {
        if (modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }
});
</script>
