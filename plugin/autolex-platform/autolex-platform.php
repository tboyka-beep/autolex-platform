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

function autolex_platform()
{
    $platform = Autolex_Platform::instance();
    Autolex_Theme_Data_Bridge::instance()->register();
    Autolex_Home_Recent_Updates::instance()->register();
    Autolex_EEA_Failure_Telemetry::instance();
    Autolex_Safety_Gate::instance();
    Autolex_Vehicle_Comparison::instance();
    Autolex_Vehicle_Relations::instance();
    Autolex_Vehicle_SEO::instance();
    Autolex_Portal::instance();
    Autolex_Comparison_Page::instance();
    Autolex_Operations_Center::instance();
    return $platform;
}

/** Loads the single Autolex 4.2 light visual stack after the portal base CSS. */
function autolex_enqueue_visual_layer()
{
    if (is_admin()) {
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
        'autolex-home-41' => array(
            'path' => 'assets/css/autolex-home-41.css',
            'deps' => array('autolex-vehicle-experience-light'),
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
add_action('wp_enqueue_scripts', 'autolex_enqueue_visual_layer', 140);
