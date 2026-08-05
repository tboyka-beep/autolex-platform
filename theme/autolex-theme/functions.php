<?php
/**
 * Autolex theme bootstrap.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

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
 * Return a deterministic cache-busting version for one theme asset.
 *
 * The theme version remains a safe fallback when the file cannot be read.
 */
function autolex_theme_asset_version($relative_path)
{
    $relative_path = ltrim((string) $relative_path, '/');
    $absolute_path = get_template_directory() . '/' . $relative_path;

    if (is_file($absolute_path)) {
        $modified = filemtime($absolute_path);
        if ($modified !== false) {
            return (string) $modified;
        }
    }

    return (string) wp_get_theme()->get('Version');
}

function autolex_theme_assets()
{
    wp_enqueue_style('autolex-theme', get_stylesheet_uri(), array(), autolex_theme_asset_version('style.css'));
    wp_enqueue_style('autolex-theme-states', get_template_directory_uri() . '/assets/css/states.css', array('autolex-theme'), autolex_theme_asset_version('assets/css/states.css'));
    wp_enqueue_style('autolex-theme-content', get_template_directory_uri() . '/assets/css/content.css', array('autolex-theme'), autolex_theme_asset_version('assets/css/content.css'));

    if (is_page('autok')) {
        wp_enqueue_style('autolex-theme-catalog', get_template_directory_uri() . '/assets/css/catalog.css', array('autolex-theme', 'autolex-theme-states'), autolex_theme_asset_version('assets/css/catalog.css'));
    }

    if (is_page(array('markak', 'modellek', 'generaciok', 'motorok'))) {
        wp_enqueue_style('autolex-theme-hierarchy', get_template_directory_uri() . '/assets/css/hierarchy.css', array('autolex-theme', 'autolex-theme-states'), autolex_theme_asset_version('assets/css/hierarchy.css'));
    }

    if (is_page('jarmu')) {
        wp_enqueue_style('autolex-theme-vehicle', get_template_directory_uri() . '/assets/css/vehicle.css', array('autolex-theme', 'autolex-theme-states'), autolex_theme_asset_version('assets/css/vehicle.css'));
    }

    if (is_page('osszehasonlitas')) {
        wp_enqueue_style('autolex-theme-comparison', get_template_directory_uri() . '/assets/css/comparison.css', array('autolex-theme', 'autolex-theme-states'), autolex_theme_asset_version('assets/css/comparison.css'));
    }

    if (is_page('visszahivasok')) {
        wp_enqueue_style('autolex-theme-safety', get_template_directory_uri() . '/assets/css/safety.css', array('autolex-theme', 'autolex-theme-states'), autolex_theme_asset_version('assets/css/safety.css'));
    }

    if (is_page('forrasok')) {
        wp_enqueue_style('autolex-theme-sources', get_template_directory_uri() . '/assets/css/sources.css', array('autolex-theme', 'autolex-theme-states'), autolex_theme_asset_version('assets/css/sources.css'));
    }

    wp_enqueue_script('autolex-theme-shell', get_template_directory_uri() . '/assets/js/theme-shell.js', array(), autolex_theme_asset_version('assets/js/theme-shell.js'), true);
}
add_action('wp_enqueue_scripts', 'autolex_theme_assets');

/**
 * Keep attachment images responsive and non-blocking without overriding an
 * explicitly selected loading or fetch-priority strategy from WordPress/plugin code.
 */
function autolex_theme_image_attributes($attributes, $attachment, $size)
{
    if (!is_array($attributes)) {
        return $attributes;
    }

    if (empty($attributes['decoding'])) {
        $attributes['decoding'] = 'async';
    }

    if (empty($attributes['loading']) && empty($attributes['fetchpriority'])) {
        $attributes['loading'] = 'lazy';
    }

    if (empty($attributes['sizes'])) {
        $attributes['sizes'] = '(max-width: 768px) 100vw, 50vw';
    }

    return $attributes;
}
add_filter('wp_get_attachment_image_attributes', 'autolex_theme_image_attributes', 10, 3);

function autolex_theme_primary_fallback()
{
    $links = array(
        __('Katalógus', 'autolex-theme')       => home_url('/autok/'),
        __('Márkák', 'autolex-theme')          => home_url('/markak/'),
        __('Jármű adatok', 'autolex-theme')    => home_url('/autok/'),
        __('Biztonság', 'autolex-theme')       => home_url('/visszahivasok/'),
        __('Összehasonlítás', 'autolex-theme') => home_url('/osszehasonlitas/'),
        __('Források', 'autolex-theme')        => home_url('/forrasok/'),
        __('Tudástár', 'autolex-theme')        => home_url('/tudastar/'),
    );

    echo '<ul class="alx-nav-list">';
    foreach ($links as $label => $url) {
        printf('<li><a href="%1$s">%2$s</a></li>', esc_url($url), esc_html($label));
    }
    echo '</ul>';
}

/**
 * Avoid duplicate metadata when a dedicated SEO plugin owns the document head.
 */
function autolex_theme_has_seo_plugin()
{
    return defined('WPSEO_VERSION')
        || defined('RANK_MATH_VERSION')
        || defined('SEOPRESS_VERSION')
        || defined('AIOSEO_VERSION');
}

/**
 * Return the canonical URL for the current public document.
 */
function autolex_theme_canonical_url()
{
    if (is_singular()) {
        return get_permalink();
    }

    if (is_front_page()) {
        return home_url('/');
    }

    if (is_search()) {
        return get_search_link(get_search_query());
    }

    return false;
}

/**
 * Build a conservative description from real WordPress content only.
 */
function autolex_theme_meta_description()
{
    if (!is_singular()) {
        return '';
    }

    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return '';
    }

    $description = has_excerpt($post) ? $post->post_excerpt : wp_strip_all_tags(strip_shortcodes($post->post_content));
    return wp_trim_words(preg_replace('/\s+/', ' ', trim($description)), 28, '…');
}

/**
 * Build BreadcrumbList from the current WordPress hierarchy.
 */
function autolex_theme_breadcrumb_schema()
{
    $items = array(
        array(
            '@type'    => 'ListItem',
            'position' => 1,
            'name'     => get_bloginfo('name'),
            'item'     => home_url('/'),
        ),
    );

    if (is_singular()) {
        $post = get_queried_object();
        if ($post instanceof WP_Post) {
            $position = 2;
            foreach (array_reverse(get_post_ancestors($post)) as $ancestor_id) {
                $items[] = array(
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'name'     => get_the_title($ancestor_id),
                    'item'     => get_permalink($ancestor_id),
                );
            }

            $items[] = array(
                '@type'    => 'ListItem',
                'position' => $position,
                'name'     => get_the_title($post),
                'item'     => get_permalink($post),
            );
        }
    }

    if (count($items) < 2) {
        return array();
    }

    return array(
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    );
}

/**
 * Output canonical, description and validated JSON-LD without inventing vehicle data.
 * The plugin may provide a complete Vehicle node through autolex_theme_vehicle_schema.
 */
function autolex_theme_document_metadata()
{
    if (autolex_theme_has_seo_plugin()) {
        return;
    }

    $canonical = autolex_theme_canonical_url();
    if ($canonical) {
        printf("\n<link rel=\"canonical\" href=\"%s\">\n", esc_url($canonical));
    }

    $description = autolex_theme_meta_description();
    if ($description !== '') {
        printf("<meta name=\"description\" content=\"%s\">\n", esc_attr($description));
    }

    $schemas = array();
    $breadcrumb_schema = autolex_theme_breadcrumb_schema();
    if ($breadcrumb_schema) {
        $schemas[] = $breadcrumb_schema;
    }

    if (is_page('jarmu')) {
        $vehicle_schema = apply_filters('autolex_theme_vehicle_schema', array());
        if (is_array($vehicle_schema)
            && isset($vehicle_schema['@type'], $vehicle_schema['name'])
            && $vehicle_schema['@type'] === 'Vehicle'
            && is_string($vehicle_schema['name'])
            && trim($vehicle_schema['name']) !== ''
        ) {
            $vehicle_schema['@context'] = 'https://schema.org';
            $schemas[] = $vehicle_schema;
        }
    }

    foreach ($schemas as $schema) {
        printf(
            "<script type=\"application/ld+json\">%s</script>\n",
            wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
add_action('wp_head', 'autolex_theme_document_metadata', 2);
