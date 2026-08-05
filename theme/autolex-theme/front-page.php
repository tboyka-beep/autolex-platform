<?php
/**
 * Front page template for the Autolex light theme.
 *
 * Numeric coverage data is delegated to the plugin through named hooks.
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
        <aside class="alx-home-rail alx-home-rail--left" aria-label="<?php esc_attr_e('Gyors funkciók és biztonság', 'autolex-theme'); ?>">
            <section class="alx-quick-panel" aria-labelledby="alx-quick-title">
                <h2 id="alx-quick-title"><?php esc_html_e('Autós adatok, egyszerűen.', 'autolex-theme'); ?></h2>
                <p class="alx-panel-intro"><?php esc_html_e('Megbízható információk. Minden járműhöz.', 'autolex-theme'); ?></p>
                <nav class="alx-quick-links" aria-label="<?php esc_attr_e('Gyors funkciók', 'autolex-theme'); ?>">
                    <a href="<?php echo esc_url(home_url('/autok/')); ?>"><span class="alx-quick-icon" aria-hidden="true">⌘</span><span><strong><?php esc_html_e('Katalógus', 'autolex-theme'); ?></strong><small><?php esc_html_e('Márkák, modellek, verziók', 'autolex-theme'); ?></small></span></a>
                    <a href="<?php echo esc_url(home_url('/autok/')); ?>"><span class="alx-quick-icon" aria-hidden="true">⚙</span><span><strong><?php esc_html_e('Műszaki adatok', 'autolex-theme'); ?></strong><small><?php esc_html_e('Részletes járműspecifikációk', 'autolex-theme'); ?></small></span></a>
                    <a href="<?php echo esc_url(home_url('/visszahivasok/')); ?>"><span class="alx-quick-icon" aria-hidden="true">◇</span><span><strong><?php esc_html_e('Biztonság', 'autolex-theme'); ?></strong><small><?php esc_html_e('Visszahívások, biztonsági adatok', 'autolex-theme'); ?></small></span></a>
                    <a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><span class="alx-quick-icon" aria-hidden="true">%</span><span><strong><?php esc_html_e('Értékek és költségek', 'autolex-theme'); ?></strong><small><?php esc_html_e('Fogyasztás, adatok, fenntartás', 'autolex-theme'); ?></small></span></a>
                    <a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><span class="alx-quick-icon" aria-hidden="true">▤</span><span><strong><?php esc_html_e('Tudástár', 'autolex-theme'); ?></strong><small><?php esc_html_e('Útmutatók, cikkek, magyarázatok', 'autolex-theme'); ?></small></span></a>
                </nav>
            </section>

            <section class="alx-rail-card alx-mobile-card" aria-labelledby="alx-mobile-card-title">
                <span class="alx-phone-icon" aria-hidden="true"></span>
                <div><h2 id="alx-mobile-card-title"><?php esc_html_e('Mobil nézet', 'autolex-theme'); ?></h2><p><?php esc_html_e('Férjen hozzá bárhonnan, bármikor.', 'autolex-theme'); ?></p><a href="#main-content"><?php esc_html_e('Megnyitás', 'autolex-theme'); ?></a></div>
            </section>

            <section class="alx-rail-card alx-safety-card" aria-labelledby="alx-rail-safety-title">
                <p class="alx-safety-label">◈ <?php esc_html_e('Biztonsági riasztás', 'autolex-theme'); ?></p>
                <h2 id="alx-rail-safety-title"><?php esc_html_e('Aktív visszahívások', 'autolex-theme'); ?></h2>
                <p><?php esc_html_e('Ellenőrizd a hivatalos, forrással megerősített biztonsági rekordokat.', 'autolex-theme'); ?></p>
                <a href="<?php echo esc_url(home_url('/visszahivasok/')); ?>"><?php esc_html_e('Megtekintés', 'autolex-theme'); ?> →</a>
            </section>
        </aside>

        <section class="alx-hero" aria-labelledby="alx-home-title">
            <div class="alx-hero-copy">
                <h1 id="alx-home-title"><?php esc_html_e('Minden jármű.', 'autolex-theme'); ?><br><?php esc_html_e('Minden adat.', 'autolex-theme'); ?> <span><?php esc_html_e('Egy helyen.', 'autolex-theme'); ?></span></h1>
                <p><?php esc_html_e('Műszaki adatok, felszereltség, fogyasztás, visszahívások és még sok más – megbízható forrásokból, naprakészen.', 'autolex-theme'); ?></p>

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
            <div class="alx-hero-visual" aria-hidden="true"><div class="alx-car-silhouette"><span></span></div></div>
        </section>

        <aside class="alx-home-rail alx-home-rail--right" aria-label="<?php esc_attr_e('Adatok és népszerű márkák', 'autolex-theme'); ?>">
            <section class="alx-coverage-panel" aria-labelledby="alx-coverage-title">
                <h2 id="alx-coverage-title"><?php esc_html_e('Adatok és lefedettség', 'autolex-theme'); ?></h2>
                <div class="alx-dynamic-slot" data-autolex-slot="coverage" aria-live="polite">
                    <?php do_action('autolex_theme_coverage_panel'); ?>
                    <p><?php esc_html_e('A tényleges lefedettségi adatok betöltése folyamatban.', 'autolex-theme'); ?></p>
                </div>
            </section>
            <section class="alx-rail-card alx-brand-panel" aria-labelledby="alx-brand-panel-title">
                <div class="alx-panel-heading"><h2 id="alx-brand-panel-title"><?php esc_html_e('Népszerű márkák', 'autolex-theme'); ?></h2><a href="<?php echo esc_url(home_url('/autok/?view=brands')); ?>"><?php esc_html_e('Összes márka', 'autolex-theme'); ?> →</a></div>
                <div class="alx-brand-slot" data-autolex-slot="popular-brands" aria-live="polite"><?php do_action('autolex_theme_popular_brands'); ?><p><?php esc_html_e('A népszerű márkák a valós használati adatok alapján jelennek meg.', 'autolex-theme'); ?></p></div>
            </section>
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
