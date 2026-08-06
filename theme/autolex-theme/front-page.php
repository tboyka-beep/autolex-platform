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
            <div class="alx-hero-visual" aria-hidden="true">
                <svg class="alx-car-silhouette" viewBox="0 0 760 300" role="presentation" focusable="false">
                    <defs>
                        <linearGradient id="alx-car-body" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#ffffff"/>
                            <stop offset="0.58" stop-color="#eef4fb"/>
                            <stop offset="1" stop-color="#cbd8e8"/>
                        </linearGradient>
                        <linearGradient id="alx-car-glass" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#49627d"/>
                            <stop offset="1" stop-color="#15293d"/>
                        </linearGradient>
                        <filter id="alx-car-shadow" x="-20%" y="-30%" width="140%" height="180%">
                            <feDropShadow dx="0" dy="22" stdDeviation="18" flood-color="#274564" flood-opacity="0.2"/>
                        </filter>
                    </defs>
                    <ellipse cx="390" cy="253" rx="292" ry="24" fill="#7890aa" opacity="0.16"/>
                    <g filter="url(#alx-car-shadow)">
                        <path d="M82 196c15-36 47-62 92-72l93-19 63-58c14-13 34-21 54-21h143c25 0 49 9 68 25l82 70c34 8 59 24 74 49l13 22-13 33H96l-26-16z" fill="url(#alx-car-body)" stroke="#9fb1c5" stroke-width="4" stroke-linejoin="round"/>
                        <path d="M285 103l60-51c10-9 23-14 36-14h82v72z" fill="url(#alx-car-glass)" stroke="#dce7f2" stroke-width="4"/>
                        <path d="M477 38h45c18 0 35 7 49 19l68 59-153-6z" fill="url(#alx-car-glass)" stroke="#dce7f2" stroke-width="4"/>
                        <path d="M96 199h114l22 24H91z" fill="#d7e1ec" opacity="0.92"/>
                        <path d="M618 126l64 12c24 4 41 17 54 37l-84 2z" fill="#e3edf7"/>
                        <path d="M657 145l56 12 17 18-75 2z" fill="#d92d3f" opacity="0.9"/>
                        <path d="M106 176h72l24 19H92z" fill="#1769e8" opacity="0.25"/>
                        <path d="M248 122h217" stroke="#aec0d3" stroke-width="4" stroke-linecap="round"/>
                        <path d="M478 118l-2 80" stroke="#a9bacd" stroke-width="3"/>
                        <path d="M333 119l-8 80" stroke="#a9bacd" stroke-width="3"/>
                        <path d="M246 204h350" stroke="#7f94aa" stroke-width="5" stroke-linecap="round" opacity="0.46"/>
                        <path d="M598 207h112l-17 22h-97z" fill="#34495e"/>
                        <circle cx="236" cy="218" r="53" fill="#21364a" stroke="#f7fafc" stroke-width="10"/>
                        <circle cx="236" cy="218" r="25" fill="#8fa3b8" stroke="#dfe8f1" stroke-width="7"/>
                        <circle cx="610" cy="218" r="53" fill="#21364a" stroke="#f7fafc" stroke-width="10"/>
                        <circle cx="610" cy="218" r="25" fill="#8fa3b8" stroke="#dfe8f1" stroke-width="7"/>
                        <circle cx="236" cy="218" r="8" fill="#1769e8"/>
                        <circle cx="610" cy="218" r="8" fill="#1769e8"/>
                    </g>
                </svg>
            </div>
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
            <div class="alx-empty-state" style="grid-template-columns:repeat(auto-fit,minmax(120px,1fr));place-items:stretch;padding:0 20px 0 48px;" role="status">
                <span style="display:grid;place-items:center;padding:12px 8px;text-align:center;"><strong style="display:block;color:var(--alx-text);font-size:18px;line-height:1;">—</strong><small style="display:block;margin-top:7px;color:var(--alx-text-muted);font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;"><?php esc_html_e('Márkák', 'autolex-theme'); ?></small></span>
                <span style="display:grid;place-items:center;border-left:1px solid var(--alx-line);padding:12px 8px;text-align:center;"><strong style="display:block;color:var(--alx-text);font-size:18px;line-height:1;">—</strong><small style="display:block;margin-top:7px;color:var(--alx-text-muted);font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;"><?php esc_html_e('Modellek', 'autolex-theme'); ?></small></span>
                <span style="display:grid;place-items:center;border-left:1px solid var(--alx-line);padding:12px 8px;text-align:center;"><strong style="display:block;color:var(--alx-text);font-size:18px;line-height:1;">—</strong><small style="display:block;margin-top:7px;color:var(--alx-text-muted);font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;"><?php esc_html_e('Generációk', 'autolex-theme'); ?></small></span>
                <span style="display:grid;place-items:center;border-left:1px solid var(--alx-line);padding:12px 8px;text-align:center;"><strong style="display:block;color:var(--alx-text);font-size:18px;line-height:1;">—</strong><small style="display:block;margin-top:7px;color:var(--alx-text-muted);font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;"><?php esc_html_e('Motorváltozatok', 'autolex-theme'); ?></small></span>
                <span style="display:grid;place-items:center;border-left:1px solid var(--alx-line);padding:12px 8px;text-align:center;"><strong style="display:block;color:var(--alx-text);font-size:18px;line-height:1;">—</strong><small style="display:block;margin-top:7px;color:var(--alx-text-muted);font-size:10px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;"><?php esc_html_e('Forrásrekordok', 'autolex-theme'); ?></small></span>
            </div>
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