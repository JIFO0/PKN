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
        'title' => esc_html(sc_t('website')),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>'
    ),
    'facebook' => array(
        'title' => esc_html(sc_t('facebook')),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>'
    ),
    'instagram' => array(
        'title' => esc_html(sc_t('instagram')),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>'
    ),
    'tiktok' => array(
        'title' => esc_html(sc_t('tiktok')),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"></path><path d="M13 12h7a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-2a6 6 0 0 1-6-6v0"></path><path d="M9 18v2a3 3 0 0 0 3 3v0a3 3 0 0 0 3-3v-6"></path></svg>'
    ),
    'discord' => array(
        'title' => esc_html(sc_t('discord')),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 10a1 1 0 1 1-2 0a1 1 0 0 1 2 0z"></path><path d="M15 10a1 1 0 1 1 2 0a1 1 0 0 1-2 0z"></path><path d="M20 11c0-5-3-9-9-9c-5.3 0-8.3 3-9.8 6.4C0 10.7 0 13.5 0 16.5c0 0 1.2 3.5 6 3.5c0 0 .7-1 1.3-1.8c-2.5-.7-3.3-2-3.3-2c.7 .7 1.7 1 3 1.5c1.3 .4 2.7 .6 4 .6c1.3 0 2.7-.2 4-.6c1.3-.4 2.3-.8 3-1.5c0 0-.8 1.3-3.3 2c.6 .8 1.3 1.8 1.3 1.8c4.8 0 6-3.5 6-3.5c0-3-.3-5.7-1-7.8"></path></svg>'
    )
 );

$social_preview_settings = wp_parse_args(get_option('sc_social_preview_settings', array()), array(
    'enabled' => '1',
    'facebook' => '1',
    'discord' => '1',
    'tiktok' => '1',
    'instagram_card' => '1',
    'facebook_width' => '340',
    'facebook_height' => '500',
    'discord_width' => '350',
    'discord_height' => '500',
    'discord_theme' => 'dark',
));

function sc_render_social_preview_embed($platform, $value, $settings = array()) {
    if (empty($value)) {
        return '';
    }

    switch ($platform) {
        case 'facebook':
            $src = 'https://www.facebook.com/plugins/page.php?href=' . rawurlencode($value) . '&tabs=timeline&width=' . intval($settings['facebook_width']) . '&height=' . intval($settings['facebook_height']) . '&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=true';
            return '<iframe loading="lazy" src="' . esc_url($src) . '" width="' . intval($settings['facebook_width']) . '" height="' . intval($settings['facebook_height']) . '" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowfullscreen="true"></iframe>';
        case 'discord':
            if (!preg_match('/\d{17,20}/', (string) $value, $m)) {
                return '';
            }
            $src = 'https://discord.com/widget?id=' . rawurlencode($m[0]) . '&theme=' . rawurlencode($settings['discord_theme']);
            return '<iframe loading="lazy" src="' . esc_url($src) . '" width="' . intval($settings['discord_width']) . '" height="' . intval($settings['discord_height']) . '" frameborder="0" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"></iframe>';
        case 'tiktok':
            return '<blockquote class="tiktok-embed" cite="' . esc_url($value) . '"><section></section></blockquote>';
        case 'instagram':
            $host = wp_parse_url($value, PHP_URL_HOST);
            $path = trim((string) wp_parse_url($value, PHP_URL_PATH), '/');
            $username = strtok($path, '/');
            return '<div class="sc-instagram-preview-card"><strong>@' . esc_html($username ?: $value) . '</strong><p>' . esc_html(sc_t('instagram_profile_preview')) . '</p><a href="' . esc_url($value) . '" target="_blank" rel="noopener noreferrer">Instagram</a></div>';
    }
    return '';
}

// Check if user can edit this community
$can_edit = sc_user_can_edit_community($community_id);
$event_images = function_exists('sc_get_community_images') ? sc_get_community_images($community_id, 'event') : array();
$team_images = function_exists('sc_get_community_images') ? sc_get_community_images($community_id, 'team') : array();
$gallery_images = function_exists('sc_get_community_images') ? sc_get_community_images($community_id, 'gallery') : array();

?>

<div class="sc-community-detail">
    <div class="sc-detail-header">
        <div class="sc-detail-navigation">
            <a href="<?php echo esc_url(sc_get_page_url_by_shortcode('science_communities_search', home_url('/science-communities/'))); ?>" class="sc-back-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                <?php echo esc_html(sc_t('back_to_search')); ?>
            </a>
        </div>
        <?php sc_render_lang_toggle(); ?>
        
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
                <?php echo esc_html(sc_t('edit_community')); ?>
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
                         alt="<?php echo esc_attr(sprintf(sc_t('logo_of'), $community['name'])); ?>">
                </div>
                <?php endif; ?>
                
                <h1 class="sc-detail-title"><?php echo esc_html($community['name']); ?></h1>
                <?php if (!empty($community['contact_email']) && !empty($community['open_for_applications'])): ?>
                    <button type="button" class="sc-apply-button" data-community-id="<?php echo esc_attr($community_id); ?>"><?php echo esc_html__('Apply to join', 'science-communities'); ?></button>
                <?php endif; ?>
                
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

            <?php if (!empty($event_images)): ?>
            <div class="sc-detail-gallery">
                <h2><?php echo esc_html(sc_t('event_photos')); ?></h2>
                <div class="sc-detail-gallery-grid">
                    <?php foreach ($event_images as $image): ?>
                        <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(sc_t('event_photo')); ?>">
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($team_images)): ?>
            <div class="sc-detail-gallery">
                <h2><?php echo esc_html(sc_t('team_photos')); ?></h2>
                <div class="sc-detail-gallery-grid">
                    <?php foreach ($team_images as $image): ?>
                        <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(sc_t('team_photo')); ?>">
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($gallery_images)): ?>
            <div class="sc-detail-gallery sc-detail-gallery-featured">
                <h2><?php echo esc_html__('Community Gallery', 'science-communities'); ?></h2>
                <div class="sc-detail-gallery-grid sc-detail-gallery-grid-large">
                    <?php foreach ($gallery_images as $image): ?>
                        <a href="<?php echo esc_url($image); ?>" class="sc-gallery-item" target="_blank" rel="noopener noreferrer">
                            <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr__('Community gallery image', 'science-communities'); ?>">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="sc-detail-sidebar">
            <div class="sc-detail-social-links">
                <h2 class="sc-detail-section-title"><?php echo esc_html(sc_t('connect')); ?></h2>
                
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
            
            <?php if (!empty($social_preview_settings['enabled']) && $social_preview_settings['enabled'] === '1'): ?>
            <div class="sc-detail-social-previews">
                <h2 class="sc-detail-section-title"><?php echo esc_html(sc_t('social_media_previews')); ?></h2>
                <?php if (!empty($social_preview_settings['facebook']) && !empty($community['facebook'])): ?>
                    <?php echo sc_render_social_preview_embed('facebook', $community['facebook'], $social_preview_settings); ?>
                <?php endif; ?>
                <?php if (!empty($social_preview_settings['discord']) && !empty($community['discord'])): ?>
                    <?php echo sc_render_social_preview_embed('discord', $community['discord'], $social_preview_settings); ?>
                <?php endif; ?>
                <?php if (!empty($social_preview_settings['tiktok']) && !empty($community['tiktok'])): ?>
                    <?php echo sc_render_social_preview_embed('tiktok', $community['tiktok'], $social_preview_settings); ?>
                    <script async src="https://www.tiktok.com/embed.js"></script>
                <?php endif; ?>
                <?php if (!empty($social_preview_settings['instagram_card']) && !empty($community['instagram'])): ?>
                    <?php echo sc_render_social_preview_embed('instagram', $community['instagram'], $social_preview_settings); ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="sc-detail-id">
                <span class="sc-detail-id-label"><?php echo esc_html(sc_t('community_id')); ?></span>
                <span class="sc-detail-id-value"><?php echo esc_html($community_id); ?></span>
            </div>
        </div>
    </div>
</div>
<form id="sc-apply-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none;">
    <input type="hidden" name="action" value="sc_submit_join_application">
    <?php wp_nonce_field('sc_join_application', 'sc_join_application_nonce'); ?>
    <input type="hidden" name="community_id" id="sc-apply-community-id" value="<?php echo esc_attr($community_id); ?>">
    <input type="hidden" name="applicant_name" id="sc-apply-name"><input type="hidden" name="applicant_email" id="sc-apply-email"><input type="hidden" name="applicant_info" id="sc-apply-info"><input type="hidden" name="applicant_contact" id="sc-apply-contact">
</form>
<script>
document.querySelectorAll('.sc-apply-button').forEach(btn=>btn.addEventListener('click',()=>{const name=prompt('Your name:');if(!name)return;const info=prompt('Tell us about yourself:');if(!info)return;const email=prompt('Contact email:');if(!email)return;const contact=prompt('Optional contact (Discord/Facebook/Instagram):','')||'';document.getElementById('sc-apply-name').value=name;document.getElementById('sc-apply-email').value=email;document.getElementById('sc-apply-info').value=info;document.getElementById('sc-apply-contact').value=contact;document.getElementById('sc-apply-form').submit();}));
</script>
