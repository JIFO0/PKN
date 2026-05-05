<?php
if (!defined('ABSPATH')) {
    exit;
}

$community_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
if (empty($community_id)) {
    wp_safe_redirect(sc_get_page_url_by_shortcode('science_communities_search', home_url('/science-communities/')));
    exit;
}

$community = sc_get_community_by_id($community_id);
if (!$community) {
    wp_safe_redirect(sc_get_page_url_by_shortcode('science_communities_search', home_url('/science-communities/')));
    exit;
}

sc_track_community_view($community_id);

$social_icons = array(
    'discord' => 'https://kola.ug.edu.pl/wp-content/uploads/2026/05/DiscordLogo.png',
    'contact_email' => 'https://kola.ug.edu.pl/wp-content/uploads/2026/05/Envelope.png',
    'facebook' => 'https://kola.ug.edu.pl/wp-content/uploads/2026/05/FacebookLogo.png',
    'instagram' => 'https://kola.ug.edu.pl/wp-content/uploads/2026/05/InstagramLogo.png',
    'tiktok' => 'https://kola.ug.edu.pl/wp-content/uploads/2026/05/TiktokLogo.png',
);

$social_preview_settings = wp_parse_args(get_option('sc_social_preview_settings', array()), array(
    'enabled' => '1',
    'facebook' => '1',
    'facebook_width' => '340',
    'facebook_height' => '500',
));

function sc_build_social_url($community, $platform) {
    if ($platform === 'contact_email') {
        if (empty($community['contact_email'])) {
            return '';
        }
        return 'mailto:' . sanitize_email($community['contact_email']);
    }

    if (empty($community[$platform])) {
        return '';
    }

    return esc_url_raw(add_query_arg(array(
        'sc_track_social' => 1,
        'community_id' => sanitize_text_field($community['id']),
        'platform' => $platform,
        'redirect_to' => rawurlencode($community[$platform]),
    ), home_url('/')));
}

function sc_render_facebook_embed($url, $settings) {
    if (empty($url)) {
        return '';
    }

    $src = 'https://www.facebook.com/plugins/page.php?href=' . rawurlencode($url) . '&tabs=timeline&width=' . intval($settings['facebook_width']) . '&height=' . intval($settings['facebook_height']) . '&small_header=false&adapt_container_width=true&hide_cover=false&show_facepile=true';
    return '<iframe loading="lazy" src="' . esc_url($src) . '" width="' . intval($settings['facebook_width']) . '" height="' . intval($settings['facebook_height']) . '" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowfullscreen="true"></iframe>';
}

$can_edit = sc_user_can_edit_community($community_id);
$event_images = function_exists('sc_get_community_images') ? sc_get_community_images($community_id, 'event') : array();
$team_images = function_exists('sc_get_community_images') ? sc_get_community_images($community_id, 'team') : array();
$gallery_images = function_exists('sc_get_community_images') ? sc_get_community_images($community_id, 'gallery') : array();
$all_gallery_images = array_values(array_unique(array_merge($gallery_images, $event_images, $team_images)));
?>

<div class="sc-community-detail sc-community-detail-modern">
    <div class="sc-detail-header">
        <a href="<?php echo esc_url(sc_get_page_url_by_shortcode('science_communities_search', home_url('/science-communities/'))); ?>" class="sc-back-link"><?php echo esc_html(sc_t('back_to_search')); ?></a>
        <?php sc_render_lang_toggle(); ?>
        <?php if ($can_edit): ?>
            <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'id' => $community_id), sc_get_admin_page_url())); ?>" class="sc-edit-button"><?php echo esc_html(sc_t('edit_community')); ?></a>
        <?php endif; ?>
    </div>

    <div class="sc-detail-hero">
        <?php if (!empty($community['logo'])): ?>
            <div class="sc-detail-logo"><img src="<?php echo esc_url($community['logo']); ?>" alt="<?php echo esc_attr(sprintf(sc_t('logo_of'), $community['name'])); ?>"></div>
        <?php endif; ?>

        <div class="sc-detail-social-icons sc-detail-social-icons-small">
            <?php foreach ($social_icons as $platform => $icon):
                $link = sc_build_social_url($community, $platform);
                if (empty($link)) { continue; }
            ?>
                <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url($icon); ?>" alt="<?php echo esc_attr($platform); ?>"></a>
            <?php endforeach; ?>
        </div>

        <h1 class="sc-detail-title"><?php echo esc_html($community['name']); ?></h1>
    </div>

    <?php if (!empty($community['shortdescription'])): ?>
        <div class="sc-detail-short-description"><?php echo esc_html($community['shortdescription']); ?></div>
    <?php endif; ?>

    <?php if (!empty($community['description'])): ?>
        <div class="sc-detail-description"><?php echo wp_kses_post($community['description']); ?></div>
    <?php endif; ?>

    <?php if (!empty($all_gallery_images)): ?>
        <div class="sc-detail-gallery sc-detail-gallery-featured">
            <h2><?php echo esc_html__('Gallery', 'science-communities'); ?></h2>
            <div class="sc-detail-gallery-grid sc-detail-gallery-grid-large">
                <?php foreach ($all_gallery_images as $image): ?>
                    <a href="<?php echo esc_url($image); ?>" class="sc-gallery-item" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr__('Community gallery image', 'science-communities'); ?>"></a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="sc-detail-social-block">
        <h2 class="sc-detail-section-title"><?php echo esc_html(sc_t('connect')); ?></h2>
        <div class="sc-detail-social-icons sc-detail-social-icons-large">
            <?php foreach ($social_icons as $platform => $icon): $link = sc_build_social_url($community, $platform); if (empty($link)) { continue; } ?>
                <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url($icon); ?>" alt="<?php echo esc_attr($platform); ?>"></a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($social_preview_settings['enabled']) && $social_preview_settings['enabled'] === '1' && !empty($social_preview_settings['facebook']) && !empty($community['facebook'])): ?>
            <div class="sc-detail-social-previews"><?php echo sc_render_facebook_embed($community['facebook'], $social_preview_settings); ?></div>
        <?php endif; ?>
    </div>

    <div class="sc-detail-actions">
        <button type="button" class="sc-follow-button"><?php echo esc_html__('Follow', 'science-communities'); ?></button>
        <button type="button" class="sc-apply-button"><?php echo esc_html__('Apply', 'science-communities'); ?></button>
    </div>

    <div class="sc-detail-id"><span class="sc-detail-id-value"><?php echo esc_html(sc_t('community_id')); ?> <?php echo esc_html($community_id); ?></span></div>
</div>

<div class="sc-apply-modal" id="sc-apply-modal" style="display:none;">
    <div class="sc-apply-modal-content">
        <button type="button" class="sc-apply-close" id="sc-apply-close">&times;</button>
        <h3><?php echo esc_html__('Apply to this community', 'science-communities'); ?></h3>
        <form id="sc-apply-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="sc_submit_join_application">
            <?php wp_nonce_field('sc_join_application', 'sc_join_application_nonce'); ?>
            <input type="hidden" name="community_id" value="<?php echo esc_attr($community_id); ?>">
            <input type="text" name="applicant_name" placeholder="<?php echo esc_attr__('Name', 'science-communities'); ?>" required>
            <input type="text" name="applicant_surname" placeholder="<?php echo esc_attr__('Surname', 'science-communities'); ?>" required>
            <input type="email" name="applicant_email" placeholder="<?php echo esc_attr__('Email', 'science-communities'); ?>" required>
            <textarea name="applicant_contact" placeholder="<?php echo esc_attr__('Additional contact info (optional)', 'science-communities'); ?>"></textarea>
            <textarea name="applicant_info" placeholder="<?php echo esc_attr__('Tell us about yourself', 'science-communities'); ?>" required></textarea>
            <button type="submit" class="sc-search-button"><?php echo esc_html__('Send application', 'science-communities'); ?></button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var followBtn = document.querySelector('.sc-follow-button');
    var applyBtn = document.querySelector('.sc-apply-button');
    var modal = document.getElementById('sc-apply-modal');
    var closeBtn = document.getElementById('sc-apply-close');

    if (followBtn) {
        followBtn.addEventListener('click', function () {
            alert('Mailing system is being built.');
        });
    }

    if (applyBtn && modal) {
        applyBtn.addEventListener('click', function () { modal.style.display = 'flex'; });
    }

    if (closeBtn && modal) {
        closeBtn.addEventListener('click', function () { modal.style.display = 'none'; });
    }

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
});
</script>
