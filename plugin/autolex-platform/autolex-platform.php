<?php
/**
 * Plugin Name: Autolex Platform
 * Plugin URI: https://autolex.hu/
 * Description: Az Autolex autós adatplatform központi WordPress-bővítménye.
 * Version: 3.3.0
 * Author: BCS / Autolex
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: autolex-platform
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AUTOLEX_PLATFORM_VERSION', '3.3.0');
define('AUTOLEX_PLATFORM_FILE', __FILE__);
define('AUTOLEX_PLATFORM_DIR', plugin_dir_path(__FILE__));

require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-platform.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eu-catalog.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eea-importer.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-catalog-browser.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-engine-catalog.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eea-sync.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-maintenance-evidence.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-safety-gate.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eurostat.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eafo.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-vehicle-comparison.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-vehicle-relations.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-vehicle-seo.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-portal.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-comparison-page.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-operations-center.php';

register_activation_hook(__FILE__, array('Autolex_Platform', 'activate'));

/**
 * Returns the shared Autolex Platform instance.
 *
 * @return Autolex_Platform
 */
function autolex_platform()
{
    $platform = Autolex_Platform::instance();
    Autolex_Safety_Gate::instance();
    Autolex_Vehicle_Comparison::instance();
    Autolex_Vehicle_Relations::instance();
    Autolex_Vehicle_SEO::instance();
    Autolex_Portal::instance();
    Autolex_Comparison_Page::instance();
    Autolex_Operations_Center::instance();
    return $platform;
}

/**
 * Loads the optional premium visual layer after the portal base stylesheet.
 * The body scope keeps the stylesheet inert outside Autolex portal screens.
 */
function autolex_enqueue_visual_layer()
{
    $relative_path = 'assets/css/autolex-visual-night.css';
    $absolute_path = AUTOLEX_PLATFORM_DIR . $relative_path;

    if (!is_readable($absolute_path)) {
        return;
    }

    wp_enqueue_style(
        'autolex-visual-night',
        plugins_url($relative_path, AUTOLEX_PLATFORM_FILE),
        array('autolex-portal-3'),
        (string) filemtime($absolute_path)
    );

    $detail_relative_path = 'assets/css/autolex-vehicle-detail-premium.css';
    $detail_absolute_path = AUTOLEX_PLATFORM_DIR . $detail_relative_path;

    if (is_readable($detail_absolute_path)) {
        wp_enqueue_style(
            'autolex-vehicle-detail-premium',
            plugins_url($detail_relative_path, AUTOLEX_PLATFORM_FILE),
            array('autolex-visual-night'),
            (string) filemtime($detail_absolute_path)
        );
    }

    $adaptive_relative_path = 'assets/css/autolex-adaptive-theme.css';
    $adaptive_absolute_path = AUTOLEX_PLATFORM_DIR . $adaptive_relative_path;

    if (is_readable($adaptive_absolute_path)) {
        wp_enqueue_style(
            'autolex-adaptive-theme',
            plugins_url($adaptive_relative_path, AUTOLEX_PLATFORM_FILE),
            array('autolex-visual-night'),
            (string) filemtime($adaptive_absolute_path)
        );
    }

    $catalog_relative_path = 'assets/css/autolex-catalog-light.css';
    $catalog_absolute_path = AUTOLEX_PLATFORM_DIR . $catalog_relative_path;

    if (is_readable($catalog_absolute_path)) {
        wp_enqueue_style(
            'autolex-catalog-light',
            plugins_url($catalog_relative_path, AUTOLEX_PLATFORM_FILE),
            array('autolex-adaptive-theme'),
            (string) filemtime($catalog_absolute_path)
        );
    }
}

add_action('plugins_loaded', 'autolex_platform');
add_action('wp_enqueue_scripts', 'autolex_enqueue_visual_layer', 40);
