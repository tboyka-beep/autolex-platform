<?php
/**
 * Front page template for the Autolex light theme.
 *
 * Values shown here are labels and navigation only. Numeric coverage data is
 * intentionally delegated to the plugin through named hooks.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main id="main-content" class="alx-main alx-home">
    <div class="alx-container alx-home-grid">
        <aside class="alx-quick-panel" aria-labelledby="alx-quick-title">
            <p class="alx-eyebrow"><?php esc_html_e('Autós adatok, egyszerűen.', 'autolex-theme'); ?></p>
            <h2 id="alx-quick-title"><?php esc_html_e('Gyors elérés', 'autolex-theme'); ?></h2>
            <nav aria-label="<?php esc_attr_e('Gyors funkciók', 'autolex-theme'); ?>">
                <a href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Katalógus', 'autolex-theme'); ?></a>
                <a href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Műszaki adatok', 'autolex-theme'); ?></a>
                <a href="<?php echo esc_url(home_url('/visszahivasok/')); ?>"><?php esc_html_e('Biztonság', 'autolex-theme'); ?></a>
                <a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><?php esc_html_e('Értékek és költségek', 'autolex-theme'); ?></a>
                <a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><?php esc_html_e('Tudástár', 'autolex-theme'); ?></a>
            </nav>
        </aside>

        <section class="alx-hero" aria-labelledby="alx-home-title">
            <div class="alx-hero-copy">
                <p class="alx-eyebrow"><?php esc_html_e('Megbízható információk. Minden járműhöz.', 'autolex-theme'); ?></p>
                <h1 id="alx-home-title"><?php esc_html_e('Minden jármű. Minden adat.', 'autolex-theme'); ?> <span><?php esc_html_e('Egy helyen.', 'autolex-theme'); ?></span></h1>
                <p><?php esc_html_e('Műszaki adatok, motorváltozatok, biztonsági információk és források átlátható rendszerben.', 'autolex-theme'); ?></p>

                <form class="alx-vehicle-search" role="search" action="<?php echo esc_url(home_url('/autok/')); ?>" method="get" data-alx-search-form>
                    <input type="hidden" name="search_type" value="vehicle" data-alx-search-type>
                    <div class="alx-search-tabs" role="tablist" aria-label="<?php esc_attr_e('Keresési mód', 'autolex-theme'); ?>">
                        <span id="alx-tab-vehicle" role="tab" tabindex="0" aria-selected="true" aria-controls="alx-panel-vehicle" data-search-mode="vehicle"><?php esc_html_e('Jármű keresése', 'autolex-theme'); ?></span>
                        <span id="alx-tab-vin" role="tab" tabindex="-1" aria-selected="false" aria-controls="alx-panel-vin" data-search-mode="vin"><?php esc_html_e('Alvázszám (VIN)', 'autolex-theme'); ?></span>
                        <span id="alx-tab-engine" role="tab" tabindex="-1" aria-selected="false" aria-controls="alx-panel-engine" data-search-mode="engine"><?php esc_html_e('Motorkód', 'autolex-theme'); ?></span>
                    </div>

                    <div id="alx-panel-vehicle" class="alx-search-fields" role="tabpanel" aria-labelledby="alx-tab-vehicle" data-search-panel="vehicle">
                        <label><span><?php esc_html_e('Márka', 'autolex-theme'); ?></span><input name="brand" type="search" autocomplete="off" placeholder="<?php esc_attr_e('Márka kiválasztása', 'autolex-theme'); ?>"></label>
                        <label><span><?php esc_html_e('Modell', 'autolex-theme'); ?></span><input name="model" type="search" autocomplete="off" placeholder="<?php esc_attr_e('Modell kiválasztása', 'autolex-theme'); ?>"></label>
                        <label><span><?php esc_html_e('Évjárat', 'autolex-theme'); ?></span><input name="year" inputmode="numeric" pattern="[0-9]{4}" placeholder="<?php esc_attr_e('Évjárat', 'autolex-theme'); ?>"></label>
                        <button type="submit"><?php esc_html_e('Keresés', 'autolex-theme'); ?></button>
                    </div>

                    <div id="alx-panel-vin" class="alx-search-fields" role="tabpanel" aria-labelledby="alx-tab-vin" data-search-panel="vin" hidden>
                        <label><span><?php esc_html_e('Alvázszám', 'autolex-theme'); ?></span><input name="vin" type="search" inputmode="text" minlength="11" maxlength="17" autocomplete="off" disabled placeholder="<?php esc_attr_e('Írd be a 11–17 karakteres alvázszámot', 'autolex-theme'); ?>"></label>
                        <button type="submit"><?php esc_html_e('VIN keresés', 'autolex-theme'); ?></button>
                    </div>

                    <div id="alx-panel-engine" class="alx-search-fields" role="tabpanel" aria-labelledby="alx-tab-engine" data-search-panel="engine" hidden>
                        <label><span><?php esc_html_e('Motorkód', 'autolex-theme'); ?></span><input name="engine_code" type="search" autocomplete="off" disabled placeholder="<?php esc_attr_e('Például: BKC, N47D20', 'autolex-theme'); ?>"></label>
                        <button type="submit"><?php esc_html_e('Motorkód keresés', 'autolex-theme'); ?></button>
                    </div>
                    <noscript><p class="alx-search-note"><?php esc_html_e('JavaScript nélkül a járműkeresés használható; VIN- vagy motorkód-kereséshez nyisd meg a katalógust.', 'autolex-theme'); ?></p></noscript>
                </form>
            </div>
            <div class="alx-hero-visual" aria-hidden="true">
                <div class="alx-car-silhouette"><span></span></div>
            </div>
        </section>

        <aside class="alx-coverage-panel" aria-labelledby="alx-coverage-title">
            <h2 id="alx-coverage-title"><?php esc_html_e('Adatok és lefedettség', 'autolex-theme'); ?></h2>
            <div class="alx-dynamic-slot" data-autolex-slot="coverage" aria-live="polite">
                <?php do_action('autolex_theme_coverage_panel'); ?>
                <p><?php esc_html_e('A tényleges lefedettségi adatok betöltése folyamatban.', 'autolex-theme'); ?></p>
            </div>
            <a class="alx-text-link" href="<?php echo esc_url(home_url('/autok/?view=brands')); ?>"><?php esc_html_e('Népszerű márkák megtekintése', 'autolex-theme'); ?></a>
        </aside>
    </div>

    <div class="alx-container">
        <section class="alx-metrics" aria-label="<?php esc_attr_e('Autolex lefedettségi mutatók', 'autolex-theme'); ?>">
            <?php do_action('autolex_theme_metric_strip'); ?>
            <div class="alx-empty-state"><?php esc_html_e('A valós rendszerstatisztikák itt jelennek meg, amint a plugin adatkapcsolata aktív.', 'autolex-theme'); ?></div>
        </section>

        <section class="alx-home-cards" aria-label="<?php esc_attr_e('Kiemelt Autolex funkciók', 'autolex-theme'); ?>">
            <article><p class="alx-eyebrow"><?php esc_html_e('Kiemelt jármű', 'autolex-theme'); ?></p><h2><?php esc_html_e('Részletes járműadatlapok', 'autolex-theme'); ?></h2><p><?php esc_html_e('Motor, teljesítmény, méretek, folyadékok, emisszió és biztonsági adatok egy helyen.', 'autolex-theme'); ?></p><a href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Katalógus megnyitása', 'autolex-theme'); ?></a></article>
            <article><p class="alx-eyebrow"><?php esc_html_e('Márkák felfedezése', 'autolex-theme'); ?></p><h2><?php esc_html_e('Átlátható modellhierarchia', 'autolex-theme'); ?></h2><p><?php esc_html_e('Márkák, modellek, generációk és motorváltozatok logikus útvonalakon.', 'autolex-theme'); ?></p><a href="<?php echo esc_url(home_url('/autok/?view=brands')); ?>"><?php esc_html_e('Összes márka', 'autolex-theme'); ?></a></article>
            <article><p class="alx-eyebrow"><?php esc_html_e('Összehasonlítás', 'autolex-theme'); ?></p><h2><?php esc_html_e('Legfeljebb három jármű', 'autolex-theme'); ?></h2><p><?php esc_html_e('A fontos műszaki és forrásminőségi különbségek egymás mellett.', 'autolex-theme'); ?></p><a href="<?php echo esc_url(home_url('/osszehasonlitas/')); ?>"><?php esc_html_e('Összehasonlítás indítása', 'autolex-theme'); ?></a></article>
            <article><p class="alx-eyebrow"><?php esc_html_e('Tudástár', 'autolex-theme'); ?></p><h2><?php esc_html_e('Érthető útmutatók', 'autolex-theme'); ?></h2><p><?php esc_html_e('VIN, motorolaj, gumiabroncs, műszaki jelölések és karbantartási alapok.', 'autolex-theme'); ?></p><a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><?php esc_html_e('Cikkek megnyitása', 'autolex-theme'); ?></a></article>
        </section>

        <section class="alx-safety-strip" aria-labelledby="alx-safety-title">
            <div><p class="alx-eyebrow"><?php esc_html_e('Safety Gate', 'autolex-theme'); ?></p><h2 id="alx-safety-title"><?php esc_html_e('Biztonsági és visszahívási információk', 'autolex-theme'); ?></h2><p><?php esc_html_e('A találatok hivatalos forrásból, egyértelmű állapotjelzéssel jelennek meg.', 'autolex-theme'); ?></p></div>
            <a href="<?php echo esc_url(home_url('/visszahivasok/')); ?>"><?php esc_html_e('Visszahívások megtekintése', 'autolex-theme'); ?></a>
        </section>
    </div>
</main>
<?php
get_footer();
