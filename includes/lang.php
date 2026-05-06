<?php
/**
 * Language helpers for bilingual PL/EN frontend rendering.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize language from ?lang=pl|en and persist via cookie.
 */
function sc_init_language() {
    if (!isset($_GET['lang'])) {
        return;
    }

    $requested_lang = sanitize_text_field(wp_unslash($_GET['lang']));
    if (!in_array($requested_lang, array('pl', 'en'), true)) {
        return;
    }

    setcookie('sc_lang', $requested_lang, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);
    $_COOKIE['sc_lang'] = $requested_lang;

    wp_safe_redirect(remove_query_arg('lang'));
    exit;
}
add_action('init', 'sc_init_language', 0);

/**
 * Return current language code.
 *
 * @return string
 */
function sc_get_lang() {
    $cookie_lang = isset($_COOKIE['sc_lang']) ? sanitize_text_field(wp_unslash($_COOKIE['sc_lang'])) : 'pl';
    return $cookie_lang === 'en' ? 'en' : 'pl';
}

/**
 * Load translation maps.
 *
 * @return array<string, array<string, string>>
 */
function sc_load_translations() {
    return array(
        'pl' => require SC_PLUGIN_PATH . 'lang/pl.php',
        'en' => require SC_PLUGIN_PATH . 'lang/en.php',
    );
}

/**
 * Translate a frontend key.
 *
 * @param string $key Translation key.
 * @return string
 */
function sc_t($key) {
    static $strings = null;

    if ($strings === null) {
        $strings = sc_load_translations();
    }

    $lang = sc_get_lang();

    return $strings[$lang][$key] ?? $strings['pl'][$key] ?? $key;
}

/**
 * Render lightweight PL/EN toggle.
 *
 * @return void
 */
function sc_render_lang_toggle() {
    $current_lang = sc_get_lang();
    $switch_to = $current_lang === 'pl' ? 'en' : 'pl';
    $label = strtoupper($switch_to);

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $base_url = home_url($request_uri);
    $target_url = add_query_arg('lang', $switch_to, remove_query_arg('lang', $base_url));

    echo '<a class="sc-lang-toggle" href="' . esc_url($target_url) . '">' . esc_html($label) . '</a>';
}

function sc_render_lang_header_toggle_shortcode() {
    $current_lang = sc_get_lang();
    $switch_to = $current_lang === 'pl' ? 'en' : 'pl';
    $label = strtoupper($switch_to);

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $base_url = home_url($request_uri);
    $target_url = add_query_arg('lang', $switch_to, remove_query_arg('lang', $base_url));

    return '<span class="sc-lang-header-toggle"><a href="' . esc_url($target_url) . '">' . esc_html($label) . '</a></span>';
}
add_shortcode('sc_lang_header_toggle', 'sc_render_lang_header_toggle_shortcode');
