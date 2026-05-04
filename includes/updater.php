<?php
/**
 * Plugin updater for GitHub build artifacts.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SC_UPDATER_GITHUB_OWNER', 'JIFO0');
define('SC_UPDATER_GITHUB_REPO', 'PKN');
define('SC_UPDATER_SLUG', 'pkn-backend');
define('SC_UPDATER_MANIFEST_URL', sprintf(
    'https://raw.githubusercontent.com/%s/%s/main/builds/latest.json',
    SC_UPDATER_GITHUB_OWNER,
    SC_UPDATER_GITHUB_REPO
));

function sc_register_plugin_updater() {
    add_filter('pre_set_site_transient_update_plugins', 'sc_check_for_plugin_updates');
    add_filter('plugins_api', 'sc_plugin_info_popup', 20, 3);
    add_filter('upgrader_post_install', 'sc_after_plugin_install', 10, 3);
    add_action('admin_post_sc_run_plugin_update', 'sc_handle_manual_plugin_update');
}

function sc_get_plugin_basename() {
    return plugin_basename(SC_PLUGIN_PATH . 'PKN-backend.php');
}

function sc_get_current_plugin_version() {
    $header_version = get_file_data(SC_PLUGIN_PATH . 'PKN-backend.php', array('Version' => 'Version'));
    $current = isset($header_version['Version']) ? trim((string) $header_version['Version']) : '';

    if ($current !== '') {
        return $current;
    }

    return defined('SC_PLUGIN_VERSION') ? (string) SC_PLUGIN_VERSION : '0.0.0';
}

function sc_is_newer_version($candidate, $current) {
    $candidate = trim((string) $candidate);
    $current = trim((string) $current);

    if ($candidate === '' || $current === '') {
        return false;
    }

    $normalized_candidate = preg_replace('/[^0-9.]/', '', $candidate);
    $normalized_current = preg_replace('/[^0-9.]/', '', $current);

    if ($normalized_candidate !== '' && $normalized_current !== '') {
        return version_compare($normalized_candidate, $normalized_current, '>');
    }

    return version_compare($candidate, $current, '>');
}

function sc_check_for_plugin_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_file = sc_get_plugin_basename();
    $manifest = sc_fetch_update_manifest();

    if (empty($manifest['version']) || empty($manifest['package_url'])) {
        return $transient;
    }

    $current_version = sc_get_current_plugin_version();

    if (sc_is_newer_version($manifest['version'], $current_version)) {
        $transient->response[$plugin_file] = (object) array(
            'slug' => SC_UPDATER_SLUG,
            'plugin' => $plugin_file,
            'new_version' => $manifest['version'],
            'url' => $manifest['details_url'] ?? 'https://github.com/' . SC_UPDATER_GITHUB_OWNER . '/' . SC_UPDATER_GITHUB_REPO,
            'package' => $manifest['package_url'],
            'tested' => $manifest['tested'] ?? '',
            'requires_php' => $manifest['requires_php'] ?? '',
        );
    }

    return $transient;
}

function sc_plugin_info_popup($result, $action, $args) {
    if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== SC_UPDATER_SLUG) {
        return $result;
    }

    $manifest = sc_fetch_update_manifest();

    if (empty($manifest['version'])) {
        return $result;
    }

    return (object) array(
        'name' => 'PKN Backend',
        'slug' => SC_UPDATER_SLUG,
        'version' => $manifest['version'],
        'author' => 'PKN TEAM',
        'homepage' => $manifest['details_url'] ?? '',
        'requires' => $manifest['requires_wp'] ?? '',
        'requires_php' => $manifest['requires_php'] ?? '',
        'download_link' => $manifest['package_url'] ?? '',
        'sections' => array(
            'description' => $manifest['description'] ?? 'Automatic updates delivered from GitHub builds.',
            'changelog' => $manifest['changelog'] ?? 'See release notes in the repository.',
        ),
    );
}

function sc_after_plugin_install($response, $hook_extra, $result) {
    global $wp_filesystem;

    $plugin_dir = WP_PLUGIN_DIR . '/pkn-backend';

    if (!empty($result['destination']) && $result['destination'] !== $plugin_dir) {
        $wp_filesystem->move($result['destination'], $plugin_dir);
        $result['destination'] = $plugin_dir;
    }

    return $result;
}

function sc_fetch_update_manifest($force_refresh = false) {
    $cache_key = 'sc_updater_manifest_cache';
    if ($force_refresh) {
        delete_transient($cache_key);
    }

    $cached = get_transient($cache_key);

    if (is_array($cached)) {
        return $cached;
    }

    $response = wp_remote_get(SC_UPDATER_MANIFEST_URL, array('timeout' => 20));

    if (is_wp_error($response)) {
        return array();
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        return array();
    }

    $manifest = json_decode(wp_remote_retrieve_body($response), true);

    if (!is_array($manifest)) {
        return array();
    }

    set_transient($cache_key, $manifest, 2 * HOUR_IN_SECONDS);

    return $manifest;
}

function sc_get_update_status($force_refresh = false) {
    $manifest = sc_fetch_update_manifest($force_refresh);
    $current = sc_get_current_plugin_version();

    return array(
        'current_version' => $current,
        'remote_version' => $manifest['version'] ?? '',
        'has_update' => !empty($manifest['version']) && sc_is_newer_version($manifest['version'], $current),
        'manifest' => $manifest,
    );
}

function sc_handle_manual_plugin_update() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to perform this action.', 'pkn-backend'));
    }

    check_admin_referer('sc_manual_plugin_update', 'sc_manual_plugin_update_nonce');

    $status = sc_get_update_status(true);
    if (empty($status['has_update'])) {
        wp_safe_redirect(add_query_arg('sc_update_status', 'up_to_date', wp_get_referer()));
        exit;
    }

    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $result = $upgrader->upgrade(sc_get_plugin_basename());

    if (is_wp_error($result) || $result === false) {
        wp_safe_redirect(add_query_arg('sc_update_status', 'failed', wp_get_referer()));
        exit;
    }

    wp_safe_redirect(add_query_arg('sc_update_status', 'success', wp_get_referer()));
    exit;
}
