<?php
/**
 * Plugin Name: Autolex Platform
 * Plugin URI: https://autolex.hu/
 * Description: Az Autolex autós adatplatform központi WordPress-bővítménye.
 * Version: 2.8.0
 * Author: BCS / Autolex
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: autolex-platform
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AUTOLEX_PLATFORM_VERSION', '2.8.0');
define('AUTOLEX_PLATFORM_FILE', __FILE__);
define('AUTOLEX_PLATFORM_DIR', plugin_dir_path(__FILE__));

require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-platform.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eu-catalog.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eea-importer.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-catalog-browser.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-engine-catalog.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-eea-sync.php';
require_once AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-maintenance-evidence.php';

register_activation_hook(__FILE__, array('Autolex_Platform', 'activate'));

/**
 * Returns the shared Autolex Platform instance.
 *
 * @return Autolex_Platform
 */
function autolex_platform()
{
    return Autolex_Platform::instance();
}

add_action('plugins_loaded', 'autolex_platform');
