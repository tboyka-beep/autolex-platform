<?php
/**
 * Plugin Name: Autolex Platform
 * Plugin URI: https://autolex.hu/
 * Description: Az Autolex autós adatplatform központi WordPress-bővítménye.
 * Version: 4.0.0
 * Author: BCS / Autolex
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: autolex-platform
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AUTOLEX_PLATFORM_VERSION', '4.0.0');
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
 * Loads the single Autolex 4.1 light visual stack after the portal base CSS.
 */
function autolex_enqueue_visual_layer()
{
    if (is_admin()) {
        return;
    }

    $catalog_relative_path = 'assets/css/autolex-catalog-light.css';
    $catalog_absolute_path = AUTOLEX_PLATFORM_DIR . $catalog_relative_path;

    if (is_readable($catalog_absolute_path)) {
        wp_enqueue_style(
            'autolex-catalog-light',
            plugins_url($catalog_relative_path, AUTOLEX_PLATFORM_FILE),
            array('autolex-portal-3'),
            (string) filemtime($catalog_absolute_path)
        );
    }

    $vehicle_relative_path = 'assets/css/autolex-vehicle-experience-light.css';
    $vehicle_absolute_path = AUTOLEX_PLATFORM_DIR . $vehicle_relative_path;

    if (is_readable($vehicle_absolute_path)) {
        wp_enqueue_style(
            'autolex-vehicle-experience-light',
            plugins_url($vehicle_relative_path, AUTOLEX_PLATFORM_FILE),
            array('autolex-catalog-light'),
            (string) filemtime($vehicle_absolute_path)
        );
    }

    wp_register_style('autolex-design-system-41', false, array('autolex-catalog-light'), AUTOLEX_PLATFORM_VERSION);
    wp_enqueue_style('autolex-design-system-41');
    wp_add_inline_style(
        'autolex-design-system-41',
        ':root{--alx3-bg:#f6f8fc;--alx3-bg-soft:#fbfcfe;--alx3-panel:#fff;--alx3-panel-soft:#eef3fa;--alx3-panel-strong:#172033;--alx3-line:#dce4ef;--alx3-line-dark:#c7d2e2;--alx3-text:#172033;--alx3-muted:#657189;--alx3-accent:#1769e0;--alx3-accent-dark:#0f4faf;--alx3-teal:#087f8c;--alx3-green:#21865a;--alx3-amber:#a96d12;--alx3-red:#c43d4b;--alx3-blue:#1769e0;--alx3-radius-sm:12px;--alx3-radius:18px;--alx3-radius-lg:28px;--alx3-shadow-sm:0 10px 30px rgba(23,32,51,.07);--alx3-shadow:0 24px 70px rgba(23,32,51,.12);--alx3-shadow-hover:0 30px 76px rgba(23,32,51,.17)}html{background:#f6f8fc}body.autolex-portal-3{background:radial-gradient(circle at 8% 0,rgba(23,105,224,.08),transparent 30rem),#f6f8fc;color:#172033}body.autolex-portal-3:before,body.autolex-portal-3:after{display:none!important}body.autolex-portal-3 .site-main,body.autolex-portal-3 .content-area,body.autolex-portal-3 #main{background:transparent!important}body.autolex-portal-3 :focus-visible{outline:3px solid rgba(23,105,224,.35)!important;outline-offset:3px}.alx3-section:before{background:linear-gradient(90deg,#1769e0,#087f8c,transparent 76%)}.alx3-hero{background:linear-gradient(135deg,#fff 0%,#f4f7fc 58%,#e8f0fb 100%)}.alx3-hero h1 em,.alx3-kicker b,.alx3-section-head span{color:#1769e0}.alx3-hero-search button{background:linear-gradient(135deg,#1769e0,#0f4faf);box-shadow:0 12px 28px rgba(23,105,224,.24)}body.autolex-portal-3 .ct-header,body.autolex-portal-3 #header{position:sticky!important;top:0;z-index:999;background:rgba(255,255,255,.88)!important;backdrop-filter:saturate(165%) blur(18px);-webkit-backdrop-filter:saturate(165%) blur(18px)}body.autolex-portal-3 .ct-header [data-row],body.autolex-portal-3 #header [data-row]{background:transparent!important}body.autolex-portal-3 .ct-header [data-row="middle"],body.autolex-portal-3 #header [data-row="middle"]{min-height:74px;background:rgba(255,255,255,.94)!important;border-bottom:1px solid #dce4ef!important;box-shadow:0 8px 30px rgba(23,32,51,.07)!important}body.autolex-portal-3 .site-branding{display:flex;align-items:center;gap:12px}body.autolex-portal-3 .site-title,body.autolex-portal-3 .site-title a{color:#172033!important;font-weight:900!important;letter-spacing:-.035em;text-decoration:none}body.autolex-portal-3 .site-description{color:#657189!important;font-size:12px!important;font-weight:700!important;letter-spacing:.06em;text-transform:uppercase}body.autolex-portal-3 .ct-header nav>ul,body.autolex-portal-3 #header nav>ul{display:flex;align-items:center;gap:6px}body.autolex-portal-3 .ct-header nav>ul>li>a,body.autolex-portal-3 #header nav>ul>li>a{min-height:42px;padding:0 14px!important;border-radius:999px;color:#344056!important;font-size:14px;font-weight:800;letter-spacing:-.01em;transition:background-color .18s ease,color .18s ease,transform .18s ease}body.autolex-portal-3 .ct-header nav>ul>li>a:hover,body.autolex-portal-3 .ct-header nav>ul>li.current-menu-item>a,body.autolex-portal-3 .ct-header nav>ul>li.current_page_item>a,body.autolex-portal-3 #header nav>ul>li>a:hover{background:#eaf2fd!important;color:#1769e0!important;transform:translateY(-1px)}body.autolex-portal-3 .ct-header .ct-button,body.autolex-portal-3 #header .ct-button{border-radius:999px!important;background:linear-gradient(135deg,#1769e0,#0f4faf)!important;box-shadow:0 10px 24px rgba(23,105,224,.22)!important;font-weight:800!important}body.autolex-portal-3 .ct-header-trigger{width:44px;height:44px;border:1px solid #dce4ef;border-radius:14px;background:#fff;color:#172033;box-shadow:0 8px 22px rgba(23,32,51,.08)}body.autolex-portal-3 [data-id="mobile-menu"]{background:#fff!important;border-left:1px solid #dce4ef;box-shadow:-24px 0 60px rgba(23,32,51,.14)}body.autolex-portal-3 [data-id="mobile-menu"] a{padding:13px 16px!important;border-radius:14px;color:#172033!important;font-weight:800}body.autolex-portal-3 [data-id="mobile-menu"] .current-menu-item>a,body.autolex-portal-3 [data-id="mobile-menu"] a:hover{background:#eaf2fd!important;color:#1769e0!important}body.autolex-portal-3 footer.ct-footer{margin-top:64px;background:linear-gradient(180deg,#eef3fa 0%,#e7edf6 100%)!important;border-top:1px solid #dce4ef!important;color:#344056!important}body.autolex-portal-3 footer.ct-footer a{color:#344056!important;text-decoration:none}body.autolex-portal-3 footer.ct-footer a:hover{color:#1769e0!important}body.autolex-portal-3 footer.ct-footer [data-row]{border-color:#dce4ef!important}.alx3-section,.alx3-metrics article,.alx3-make-grid a,.alx3-capability-grid article,.alx3-card,.alx3-pipeline{border-color:#dce4ef;background-color:#fff}.alx3-make-grid a>span,.alx3-capability-grid i{background:#eaf2fd;color:#1769e0;border-color:#cbdcf5}@media(max-width:999px){body.autolex-portal-3 .ct-header [data-row="middle"],body.autolex-portal-3 #header [data-row="middle"]{min-height:66px}body.autolex-portal-3 .site-description{display:none}}@media(max-width:689px){body.autolex-portal-3 .ct-header [data-row="middle"],body.autolex-portal-3 #header [data-row="middle"]{min-height:60px}body.autolex-portal-3 .site-title,body.autolex-portal-3 .site-title a{font-size:20px!important}body.autolex-portal-3 footer.ct-footer{margin-top:44px}}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.alx3-section *,.alx3-card,.alx3-make-grid a,.alx3-capability-grid article,body.autolex-portal-3 .ct-header nav a{animation:none!important;transition:none!important}}'
    );
}

add_action('plugins_loaded', 'autolex_platform');
add_action('wp_enqueue_scripts', 'autolex_enqueue_visual_layer', 140);
