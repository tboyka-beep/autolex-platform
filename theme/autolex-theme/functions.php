<?php
/**
 * Autolex theme bootstrap.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the smallest theme feature set required by the public shell.
 *
 * @return void
 */
function autolex_theme_setup()
{
    load_theme_textdomain('autolex-theme', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');

    register_nav_menus(
        array(
            'primary' => __('Fő navigáció', 'autolex-theme'),
            'footer'  => __('Lábléc navigáció', 'autolex-theme'),
        )
    );
}
add_action('after_setup_theme', 'autolex_theme_setup');

/**
 * Loads only the theme-owned public assets.
 *
 * @return void
 */
function autolex_theme_assets()
{
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('autolex-theme', get_stylesheet_uri(), array(), $version);
    wp_enqueue_style(
        'autolex-theme-states',
        get_template_directory_uri() . '/assets/css/states.css',
        array('autolex-theme'),
        $version
    );
    wp_enqueue_style(
        'autolex-theme-content',
        get_template_directory_uri() . '/assets/css/content.css',
        array('autolex-theme'),
        $version
    );

    if (is_page('autok')) {
        wp_enqueue_style(
            'autolex-theme-catalog',
            get_template_directory_uri() . '/assets/css/catalog.css',
            array('autolex-theme', 'autolex-theme-states'),
            $version
        );
    }

    if (is_page('osszehasonlitas')) {
        wp_enqueue_style(
            'autolex-theme-comparison',
            get_template_directory_uri() . '/assets/css/comparison.css',
            array('autolex-theme', 'autolex-theme-states'),
            $version
        );
    }

    if (is_page('visszahivasok')) {
        wp_enqueue_style(
            'autolex-theme-safety',
            get_template_directory_uri() . '/assets/css/safety.css',
            array('autolex-theme', 'autolex-theme-states'),
            $version
        );
    }

    wp_enqueue_script(
        'autolex-theme-shell',
        get_template_directory_uri() . '/assets/js/theme-shell.js',
        array(),
        $version,
        true
    );
}
add_action('wp_enqueue_scripts', 'autolex_theme_assets');

/**
 * Provides deterministic navigation when the WordPress menu is not configured.
 *
 * @return void
 */
function autolex_theme_primary_fallback()
{
    $links = array(
        __('Katalógus', 'autolex-theme')       => home_url('/autok/'),
        __('Márkák', 'autolex-theme')          => home_url('/autok/?view=brands'),
        __('Jármű adatok', 'autolex-theme')    => home_url('/autok/'),
        __('Biztonság', 'autolex-theme')       => home_url('/visszahivasok/'),
        __('Összehasonlítás', 'autolex-theme') => home_url('/osszehasonlitas/'),
        __('Források', 'autolex-theme')        => home_url('/forrasok/'),
        __('Tudástár', 'autolex-theme')        => home_url('/tudastar/'),
    );

    echo '<ul class="alx-nav-list">';
    foreach ($links as $label => $url) {
        printf(
            '<li><a href="%1$s">%2$s</a></li>',
            esc_url($url),
            esc_html($label)
        );
    }
    echo '</ul>';
}
