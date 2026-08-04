<?php
/**
 * Not-found template for the Autolex light theme.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main id="main-content" class="alx-main">
    <section class="alx-container alx-state-page" aria-labelledby="alx-not-found-title">
        <div class="alx-state-card">
            <p class="alx-state-code" aria-hidden="true">404</p>
            <p class="alx-eyebrow"><?php esc_html_e('Az oldal nem található', 'autolex-theme'); ?></p>
            <h1 id="alx-not-found-title"><?php esc_html_e('Ez az útvonal nincs az Autolex katalógusában.', 'autolex-theme'); ?></h1>
            <p><?php esc_html_e('Ellenőrizd a címet, keress járműre vagy térj vissza a katalógushoz.', 'autolex-theme'); ?></p>
            <form class="alx-state-search" role="search" action="<?php echo esc_url(home_url('/')); ?>" method="get">
                <label for="alx-404-search"><?php esc_html_e('Keresés az Autolexen', 'autolex-theme'); ?></label>
                <div>
                    <input id="alx-404-search" type="search" name="s" placeholder="<?php esc_attr_e('Márka, modell vagy motorkód', 'autolex-theme'); ?>">
                    <button type="submit"><?php esc_html_e('Keresés', 'autolex-theme'); ?></button>
                </div>
            </form>
            <nav class="alx-state-actions" aria-label="<?php esc_attr_e('Hasznos útvonalak', 'autolex-theme'); ?>">
                <a class="alx-button" href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Autókatalógus', 'autolex-theme'); ?></a>
                <a class="alx-button alx-button-secondary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Vissza a főoldalra', 'autolex-theme'); ?></a>
            </nav>
        </div>
    </section>
</main>
<?php
get_footer();
