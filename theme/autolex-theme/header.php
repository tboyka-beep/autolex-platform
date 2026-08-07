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
            <span class="alx-brand-mark" aria-hidden="true">
                <svg viewBox="0 0 38 38" role="presentation" focusable="false">
                    <defs>
                        <linearGradient id="alx-logo-gradient" x1="4" y1="34" x2="34" y2="4" gradientUnits="userSpaceOnUse">
                            <stop offset="0" stop-color="#1769e8"/>
                            <stop offset="1" stop-color="#4f8df7"/>
                        </linearGradient>
                    </defs>
                    <path d="M6.1 28.9 16.5 7.3c.7-1.5 2.2-2.4 3.8-2.4h7.5l-3.4 7.2h-3.5l-2.8 6h5.3l4.8-10.1 5.7 11.7c.8 1.7-.4 3.7-2.3 3.7h-7.1l3.2 6.6H15.4l-3.2-6.6-2.4 5.5H6.1Z" fill="url(#alx-logo-gradient)"/>
                    <path d="m27.7 4.9 4.6 4.4-4.4 4.7-4.7-4.5 4.5-4.6Z" fill="#9fc1ff"/>
                </svg>
            </span>
            <span class="alx-brand-word">AUTOLEX</span>
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
            <details class="alx-header-search-menu">
                <summary class="alx-header-search-link">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    <span><?php esc_html_e('Keresés', 'autolex-theme'); ?></span>
                </summary>
                <form class="alx-header-search-panel" role="search" method="get" action="<?php echo esc_url(home_url('/autok/')); ?>">
                    <label class="screen-reader-text" for="alx-header-search-field"><?php esc_html_e('Jármű keresése', 'autolex-theme'); ?></label>
                    <span class="screen-reader-text" id="alx-header-search-hint"><?php esc_html_e('Keressen márka, modell, generáció vagy motorkód alapján.', 'autolex-theme'); ?></span>
                    <input
                        class="alx-header-search-field"
                        id="alx-header-search-field"
                        type="search"
                        name="q"
                        value="<?php echo esc_attr($autolex_header_query); ?>"
                        placeholder="<?php esc_attr_e('Márka, modell, generáció vagy motorkód…', 'autolex-theme'); ?>"
                        aria-describedby="alx-header-search-hint"
                        enterkeyhint="search"
                        autocomplete="off"
                    >
                    <button type="submit"><?php esc_html_e('Keresés', 'autolex-theme'); ?></button>
                </form>
            </details>
            <div class="alx-header-utilities" aria-label="<?php esc_attr_e('Oldalbeállítások', 'autolex-theme'); ?>">
                <span class="alx-utility-icon alx-utility-globe" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M4 12h16M12 4c2 2.2 3 4.9 3 8s-1 5.8-3 8c-2-2.2-3-4.9-3-8s1-5.8 3-8Z" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
                </span>
                <span class="alx-utility-pill" lang="hu" aria-label="<?php esc_attr_e('Nyelv: magyar', 'autolex-theme'); ?>">HU</span>
                <span class="alx-utility-icon" aria-label="<?php esc_attr_e('Világos megjelenés aktív', 'autolex-theme'); ?>" title="<?php esc_attr_e('Világos megjelenés', 'autolex-theme'); ?>">☼</span>
                <a class="alx-login-link" href="<?php echo esc_url(wp_login_url(home_url('/'))); ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="3" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M6.5 19c.5-3.1 2.5-5 5.5-5s5 1.9 5.5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    <span><?php esc_html_e('Bejelentkezés', 'autolex-theme'); ?></span>
                </a>
            </div>
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