<?php
/**
 * Plugin Name: Autolex Platform
 * Plugin URI: https://autolex.hu/
 * Description: Az Autolex autós adatplatform központi WordPress-bővítménye.
 * Version: 4.2.0
 * Author: BCS / Autolex
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: autolex-platform
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AUTOLEX_PLATFORM_VERSION', '4.2.0');
define('AUTOLEX_PLATFORM_FILE', __FILE__);
define('AUTOLEX_PLATFORM_DIR', plugin_dir_path(__FILE__));

require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-platform.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eu-catalog.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eea-importer.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-catalog-browser.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-engine-catalog.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-theme-data-bridge.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-home-recent-updates.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eea-sync.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eea-failure-telemetry.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eea-api-rejection-recovery.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-maintenance-evidence.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-safety-gate.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-safety-gate-inbox.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eurostat.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eafo.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-vehicle-comparison.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-vehicle-relations.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-vehicle-seo.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-vehicle-media.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-public-presentation.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-vehicle-fact-fallback.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-portal.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-comparison-page.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-operations-center.php';

register_activation_hook(__FILE__, array('Autolex_Platform', 'activate'));

function autolex_platform()
{
    $platform = Autolex_Platform::instance();
    Autolex_Theme_Data_Bridge::instance()->register();
    Autolex_Home_Recent_Updates::instance()->register();
    Autolex_EEA_Failure_Telemetry::instance();
    Autolex_EEA_API_Rejection_Recovery::instance();
    Autolex_Safety_Gate::instance();
    Autolex_Safety_Gate_Inbox::instance();
    Autolex_Vehicle_Comparison::instance();
    Autolex_Vehicle_Relations::instance();
    Autolex_Vehicle_SEO::instance();
    Autolex_Vehicle_Media::instance();
    Autolex_Public_Presentation::instance();
    Autolex_Vehicle_Fact_Fallback::instance();
    Autolex_Portal::instance();
    Autolex_Comparison_Page::instance();
    Autolex_Operations_Center::instance();
    return $platform;
}

/** @return bool */
function autolex_uses_dedicated_theme()
{
    return function_exists('get_stylesheet') && get_stylesheet() === 'autolex-theme';
}

/**
 * The plugin-owned 4.2 visual stack is only needed where the portal renderer
 * owns content. Plain theme pages (brands, models, generations, /jarmu/) keep
 * the dedicated theme as their single visual owner.
 *
 * @return bool
 */
function autolex_is_portal_visual_request()
{
    if (is_front_page() || is_page('autok') || is_singular('alx_vehicle')) {
        return true;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
    return false !== strpos('/' . trim($path, '/') . '/', '/auto-adatlap/');
}

/**
 * Homepage data-bridge assets are registered by their data providers before
 * this hook. Remove them from inner pages before WordPress resolves stylesheet
 * dependencies, so a homepage-only dependency cannot leak debug notices into
 * catalogue or hierarchy screenshots.
 *
 * @return void
 */
function autolex_prune_home_only_theme_assets()
{
    if (is_admin() || !autolex_uses_dedicated_theme() || is_front_page()) {
        return;
    }

    wp_dequeue_style('autolex-home-recent-updates');
    wp_dequeue_style('autolex-theme-data-bridge');
}

/** Loads the single Autolex 4.2 light visual stack after the portal base CSS. */
function autolex_enqueue_visual_layer()
{
    if (is_admin()) {
        return;
    }

    if (autolex_uses_dedicated_theme() && !autolex_is_portal_visual_request()) {
        return;
    }

    $styles = array(
        'autolex-catalog-light' => array(
            'path' => 'assets/css/autolex-catalog-light.css',
            'deps' => array('autolex-portal-3'),
        ),
        'autolex-vehicle-experience-light' => array(
            'path' => 'assets/css/autolex-vehicle-experience-light.css',
            'deps' => array('autolex-catalog-light'),
        ),
        'autolex-public-presentation' => array(
            'path' => 'assets/css/autolex-public-presentation.css',
            'deps' => array('autolex-vehicle-experience-light'),
        ),
        'autolex-home-41' => array(
            'path' => 'assets/css/autolex-home-41.css',
            'deps' => array('autolex-public-presentation'),
        ),
    );

    foreach ($styles as $handle => $style) {
        $absolute_path = AUTOLEX_PLATFORM_DIR . $style['path'];
        if (!is_readable($absolute_path)) {
            continue;
        }

        wp_enqueue_style(
            $handle,
            plugins_url($style['path'], AUTOLEX_PLATFORM_FILE),
            $style['deps'],
            (string) filemtime($absolute_path)
        );
    }
}

add_action('plugins_loaded', 'autolex_platform');
add_action('wp_enqueue_scripts', 'autolex_prune_home_only_theme_assets', 139);
add_action('wp_enqueue_scripts', 'autolex_enqueue_visual_layer', 140);
