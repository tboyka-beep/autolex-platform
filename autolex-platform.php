<?php
/**
 * Plugin Name: Autolex Platform
 * Description: Autolex core platform plugin placeholder.
 * Version: 2.0.0-dev
 * Author: BCS / Autolex
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function () {
    add_menu_page(
        'Autolex Platform',
        'Autolex Platform',
        'manage_options',
        'autolex-platform',
        function () {
            echo '<div class="wrap"><h1>Autolex Platform</h1><p>Dev pipeline active.</p></div>';
        },
        'dashicons-car',
        26
    );
});
