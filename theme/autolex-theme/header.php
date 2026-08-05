<?php
/**
 * Site header.
 *
 * @package Autolex_Theme
 */
$autolex_header_query = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('autolex-theme'); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text" href="#main-content"><?php esc_html_e('Ugrás a tartalomra', 'autolex-theme'); ?></a>
<header class="alx-site-header" role="banner">
    <div class="alx-container alx-header-row">
        <a class="alx-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('Autolex főoldal', 'autolex-theme'); ?>">
            <span class="alx-brand-mark" aria-hidden="true">A</span>
            <span>AUTOLEX</span>
        </a>
        <nav class="alx-primary-nav" aria-label="<?php esc_attr_e('Fő navigáció', 'autolex-theme'); ?>">
            <?php
            wp_nav_menu(
                array(
                    'theme_location' => 'primary',
                    'container'      => false,
                    'fallback_cb'    => 'autolex_theme_primary_fallback',
                    'depth'          => 1,
                )
            );
            ?>
        </nav>
        <div class="alx-header-actions">
            <form role="search" method="get" action="<?php echo esc_url(home_url('/autok/')); ?>">
                <label class="screen-reader-text" for="alx-header-search"><?php esc_html_e('Jármű keresése', 'autolex-theme'); ?></label>
                <span class="screen-reader-text" id="alx-header-search-hint"><?php esc_html_e('Keressen márka, modell, generáció vagy motorkód alapján.', 'autolex-theme'); ?></span>
                <input
                    class="alx-header-search"
                    id="alx-header-search"
                    type="search"
                    name="q"
                    value="<?php echo esc_attr($autolex_header_query); ?>"
                    placeholder="<?php esc_attr_e('Keresés…', 'autolex-theme'); ?>"
                    aria-describedby="alx-header-search-hint"
                    enterkeyhint="search"
                    autocomplete="off"
                >
            </form>
            <button class="alx-menu-toggle" type="button" aria-expanded="false" aria-controls="alx-mobile-menu" data-autolex-menu-toggle>
                <span aria-hidden="true">☰</span>
                <span class="screen-reader-text"><?php esc_html_e('Menü megnyitása', 'autolex-theme'); ?></span>
            </button>
        </div>
    </div>
    <div class="alx-mobile-menu" id="alx-mobile-menu" hidden>
        <nav class="alx-container" aria-label="<?php esc_attr_e('Mobil navigáció', 'autolex-theme'); ?>">
            <?php autolex_theme_primary_fallback(); ?>
        </nav>
    </div>
</header>
<main class="alx-main" id="main-content">
