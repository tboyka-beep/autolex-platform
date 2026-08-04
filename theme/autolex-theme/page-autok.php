<?php
/**
 * Vehicle catalogue page shell.
 *
 * The Autolex plugin remains responsible for catalogue data, filters and
 * interaction. The theme owns only the accessible light-first route layout.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main id="main-content" class="alx-catalog-page" tabindex="-1">
    <div class="alx-shell alx-catalog-shell">
        <header class="alx-catalog-hero" aria-labelledby="alx-catalog-title">
            <div>
                <p class="alx-eyebrow"><?php esc_html_e('Autolex járműadatbázis', 'autolex-theme'); ?></p>
                <h1 id="alx-catalog-title"><?php esc_html_e('Járműkatalógus', 'autolex-theme'); ?></h1>
                <p><?php esc_html_e('Böngéssz márka, modell, generáció és motor szerint. A megjelenő műszaki adatok forrás- és megerősítési állapottal együtt értelmezendők.', 'autolex-theme'); ?></p>
            </div>
            <a class="alx-button alx-button-secondary" href="<?php echo esc_url(home_url('/osszehasonlitas/')); ?>">
                <?php esc_html_e('Összehasonlítás megnyitása', 'autolex-theme'); ?>
            </a>
        </header>

        <section class="alx-catalog-workspace" aria-labelledby="alx-catalog-workspace-title">
            <h2 id="alx-catalog-workspace-title" class="screen-reader-text">
                <?php esc_html_e('Katalógus és szűrők', 'autolex-theme'); ?>
            </h2>

            <div class="alx-catalog-status" role="status" aria-live="polite" aria-atomic="true">
                <?php esc_html_e('A találatok és szűrők a rendelkezésre álló Autolex-adatok alapján töltődnek be.', 'autolex-theme'); ?>
            </div>

            <div class="alx-catalog-plugin-output">
                <?php
                if (have_posts()) {
                    while (have_posts()) {
                        the_post();
                        $catalogue_content = trim((string) get_the_content());

                        if ('' !== $catalogue_content) {
                            the_content();
                        } else {
                            ?>
                            <div class="alx-state-card alx-catalog-empty" role="note">
                                <h2><?php esc_html_e('A katalógus még nem tartalmaz megjeleníthető találatot', 'autolex-theme'); ?></h2>
                                <p><?php esc_html_e('Az adatkapcsolat vagy az import feldolgozása még folyamatban lehet. Kitalált járművek és kézzel beírt darabszámok helyett csak igazolt adatok jelennek meg.', 'autolex-theme'); ?></p>
                                <a class="alx-button alx-button-primary" href="<?php echo esc_url(home_url('/')); ?>">
                                    <?php esc_html_e('Vissza a főoldalra', 'autolex-theme'); ?>
                                </a>
                            </div>
                            <?php
                        }
                    }
                } else {
                    ?>
                    <div class="alx-state-card alx-catalog-error" role="alert">
                        <h2><?php esc_html_e('A katalógus most nem tölthető be', 'autolex-theme'); ?></h2>
                        <p><?php esc_html_e('Az oldal alapadatai nem érhetők el. Próbáld újra később, vagy használd a főoldali keresőt.', 'autolex-theme'); ?></p>
                        <a class="alx-button" href="<?php echo esc_url(home_url('/')); ?>">
                            <?php esc_html_e('Főoldali kereső', 'autolex-theme'); ?>
                        </a>
                    </div>
                    <?php
                }
                ?>
            </div>
        </section>
    </div>
</main>
<?php
get_footer();
