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
define('SC_UPDATER_FALLBACK_ZIP_NAME', 'PKN.zip');
define('SC_UPDATER_RELEASE_API_URL', sprintf('https://api.github.com/repos/%s/%s/releases/latest', SC_UPDATER_GITHUB_OWNER, SC_UPDATER_GITHUB_REPO));

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

function sc_normalize_release_version($release) {
    $raw_version = trim((string) ($release['tag_name'] ?? ''));

    if ($raw_version === '' && !empty($release['name'])) {
        $raw_version = trim((string) $release['name']);
    }

    if ($raw_version === '') {
        return '';
    }

    $raw_version = preg_replace('/^v/i', '', $raw_version);

    if (preg_match('/(alpha|beta|rc)\s*[-_ ]*([0-9]+(?:\.[0-9]+)*)/i', $raw_version, $matches)) {
        return ucfirst(strtolower($matches[1])) . ' ' . $matches[2];
    }

    if (preg_match('/([0-9]+(?:\.[0-9]+)+)/', $raw_version, $matches)) {
        return $matches[1];
    }

    return $raw_version;
}

function sc_find_installed_plugin_dir($directory) {
    $directory = trailingslashit($directory);
    $main_file = $directory . 'PKN-backend.php';
    if (file_exists($main_file)) {
        return $directory;
    }

    $candidates = glob($directory . '*/PKN-backend.php');
    if (!empty($candidates)) {
        return trailingslashit(dirname($candidates[0]));
    }

    return $directory;
}

function sc_after_plugin_install($response, $hook_extra, $result) {
    global $wp_filesystem;

    $plugin_dir = WP_PLUGIN_DIR . '/pkn-backend';

    if (empty($result['destination'])) {
        return $result;
    }

     $source_dir = sc_find_installed_plugin_dir($result['destination']);
    $source_main = trailingslashit($source_dir) . 'PKN-backend.php';

    if (!$wp_filesystem->exists($source_main)) {
        return new WP_Error(
            'sc_update_missing_main_file',
            __('PKN update package is invalid: PKN-backend.php was not found at the archive root or inside the pkn-backend folder.', 'pkn-backend')
        );
    }

    $headers = get_file_data($source_main, array('Plugin Name' => 'Plugin Name', 'Version' => 'Version'));
    if (empty($headers['Plugin Name']) || empty($headers['Version'])) {
        return new WP_Error(
            'sc_update_invalid_headers',
            __('PKN update package is invalid: the main plugin file has missing or invalid plugin headers.', 'pkn-backend')
        );
    }

    if (trailingslashit($source_dir) !== trailingslashit($plugin_dir)) {
        if ($wp_filesystem->exists($plugin_dir)) {
            $wp_filesystem->delete($plugin_dir, true);
        }
        if (!$wp_filesystem->move($source_dir, $plugin_dir)) {
            return new WP_Error('sc_update_move_failed', __('Could not move the PKN update into the plugin directory.', 'pkn-backend'));
        }
    }
    $result['destination'] = $plugin_dir;
    $result['destination_name'] = 'pkn-backend';
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

    $response = wp_remote_get(SC_UPDATER_RELEASE_API_URL, array(
        'timeout' => 20,
        'headers' => array('Accept' => 'application/vnd.github+json', 'User-Agent' => 'PKN-Updater')
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return array();
    }

    $release = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($release)) {
        return array();
    }

    $asset_url = '';
    if (!empty($release['assets']) && is_array($release['assets'])) {
        foreach ($release['assets'] as $asset) {
            $asset_name = isset($asset['name']) ? (string) $asset['name'] : '';
            if ($asset_name === '') {
                continue;
            }

            if (preg_match('/^pkn-backend-.*\.zip$/i', $asset_name) || strcasecmp($asset_name, SC_UPDATER_FALLBACK_ZIP_NAME) === 0) {
                $asset_url = $asset['browser_download_url'] ?? '';
                break;
            }
        }
    }

    $manifest = array(
        'version' => sc_normalize_release_version($release),
        'package_url' => $asset_url,
        'details_url' => $release['html_url'] ?? '',
        'description' => !empty($release['body']) ? wp_kses_post(wp_trim_words($release['body'], 80, '...')) : 'Automatic updates delivered from GitHub Releases.',
        'changelog' => $release['body'] ?? 'See release notes in the repository.',
    );

    if (empty($manifest['version']) || empty($manifest['package_url'])) {
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
