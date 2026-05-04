<?php
/**
 * Plugin updater for GitHub build artifacts.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SC_UPDATER_GITHUB_OWNER', 'YOUR_GITHUB_OWNER');
define('SC_UPDATER_GITHUB_REPO', 'PKN');
define('SC_UPDATER_MANIFEST_URL', sprintf(
    'https://raw.githubusercontent.com/%s/%s/main/builds/latest.json',
    SC_UPDATER_GITHUB_OWNER,
    SC_UPDATER_GITHUB_REPO
));

/**
 * Register updater hooks.
 */
function sc_register_plugin_updater() {
    add_filter('pre_set_site_transient_update_plugins', 'sc_check_for_plugin_updates');
    add_filter('plugins_api', 'sc_plugin_info_popup', 20, 3);
    add_filter('upgrader_post_install', 'sc_after_plugin_install', 10, 3);
}

/**
 * Check GitHub manifest for newer plugin version.
 */
function sc_check_for_plugin_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_file = plugin_basename(SC_PLUGIN_PATH . 'PKN-backend.php');
    $manifest = sc_fetch_update_manifest();

    if (empty($manifest['version']) || empty($manifest['package_url'])) {
        return $transient;
    }

    if (version_compare($manifest['version'], SC_PLUGIN_VERSION, '>')) {
        $transient->response[$plugin_file] = (object) array(
            'slug' => 'pkn-backend',
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

/**
 * Provide plugin information modal details.
 */
function sc_plugin_info_popup($result, $action, $args) {
    if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'pkn-backend') {
        return $result;
    }

    $manifest = sc_fetch_update_manifest();

    if (empty($manifest['version'])) {
        return $result;
    }

    return (object) array(
        'name' => 'PKN Backend',
        'slug' => 'pkn-backend',
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

/**
 * Ensure plugin is installed to proper directory name.
 */
function sc_after_plugin_install($response, $hook_extra, $result) {
    global $wp_filesystem;

    $plugin_dir = WP_PLUGIN_DIR . '/pkn-backend';

    if (!empty($result['destination']) && $result['destination'] !== $plugin_dir) {
        $wp_filesystem->move($result['destination'], $plugin_dir);
        $result['destination'] = $plugin_dir;
    }

    return $result;
}

/**
 * Get cached manifest or fetch from GitHub.
 */
function sc_fetch_update_manifest() {
    $cache_key = 'sc_updater_manifest_cache';
    $cached = get_transient($cache_key);

    if (is_array($cached)) {
        return $cached;
    }

    $response = wp_remote_get(SC_UPDATER_MANIFEST_URL, array('timeout' => 15));

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

    set_transient($cache_key, $manifest, 6 * HOUR_IN_SECONDS);

    return $manifest;
}