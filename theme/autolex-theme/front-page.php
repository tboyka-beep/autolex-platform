<?php
/**
 * Reference-dashboard front page for Autolex.
 *
 * Plugin-owned numeric data is rendered through named hooks. Theme fallbacks
 * preserve the approved layout without inventing coverage or popularity data.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render a plugin-owned homepage slot and use the fallback only when the hook
 * produced no output.
 *
 * @param string   $hook_name         WordPress action name.
 * @param callable $fallback_renderer Escaped fallback renderer.
 */
$autolex_render_home_slot = static function ($hook_name, $fallback_renderer) {
    if (!is_string($hook_name) || $hook_name === '' || !is_callable($fallback_renderer)) {
        return;
    }

    ob_start();
    do_action($hook_name);
    $output = trim((string) ob_get_clean());

    if ($output !== '') {
        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted theme/plugin markup.
        return;
    }

    $fallback_renderer();
};

$autolex_quick_links = array(
    array('label' => __('Katalógus', 'autolex-theme'), 'description' => __('Márkák, modellek, verziók', 'autolex-theme'), 'url' => home_url('/autok/'), 'icon' => 'catalog'),
    array('label' => __('Műszaki adatok', 'autolex-theme'), 'description' => __('Részletes járműspecifikációk', 'autolex-theme'), 'url' => home_url('/autok/'), 'icon' => 'spec'),
    array('label' => __('Biztonság', 'autolex-theme'), 'description' => __('Visszahívások, biztonsági adatok', 'autolex-theme'), 'url' => home_url('/visszahivasok/'), 'icon' => 'shield'),
    array('label' => __('Értékek és költségek', 'autolex-theme'), 'description' => __('Fogyasztás, adatok, fenntartás', 'autolex-theme'), 'url' => home_url('/tudastar/'), 'icon' => 'cost'),
    array('label' => __('Tudástár', 'autolex-theme'), 'description' => __('Útmutatók, cikkek, magyarázatok', 'autolex-theme'), 'url' => home_url('/tudastar/'), 'icon' => 'book'),
);

get_header();
?>
<div class="alx-home" data-reference-dashboard="true">
    <div class="alx-container alx-home-grid">
        <aside class="alx-home-rail alx-home-rail--left" aria-label="<?php esc_attr_e('Gyors funkciók és biztonság', 'autolex-theme'); ?>">
            <section class="alx-quick-panel" aria-labelledby="alx-quick-title">
                <h2 id="alx-quick-title"><?php esc_html_e('Autós adatok, egyszerűen.', 'autolex-theme'); ?></h2>
                <p class="alx-panel-intro"><?php esc_html_e('Megbízható információk. Minden járműhöz.', 'autolex-theme'); ?></p>
                <nav class="alx-quick-links" aria-label="<?php esc_attr_e('Gyors funkciók', 'autolex-theme'); ?>">
                    <?php foreach ($autolex_quick_links as $item) : ?>
                        <a href="<?php echo esc_url($item['url']); ?>">
                            <span class="alx-quick-icon alx-quick-icon--<?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></span>
                            <span><strong><?php echo esc_html($item['label']); ?></strong><small><?php echo esc_html($item['description']); ?></small></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </section>

            <section class="alx-rail-card alx-mobile-card" aria-labelledby="alx-mobile-card-title">
                <span class="alx-phone-icon" aria-hidden="true"></span>
                <div>
                    <h2 id="alx-mobile-card-title"><?php esc_html_e('Mobil nézet', 'autolex-theme'); ?></h2>
                    <p><?php esc_html_e('Férjen hozzá bárhonnan, bármikor.', 'autolex-theme'); ?></p>
                    <a href="#alx-home-search"><?php esc_html_e('Megnyitás', 'autolex-theme'); ?></a>
                </div>
            </section>

            <section class="alx-rail-card alx-safety-card" aria-labelledby="alx-rail-safety-title">
                <p class="alx-safety-label"><span class="alx-safety-symbol" aria-hidden="true">!</span><?php esc_html_e('Biztonsági riasztás', 'autolex-theme'); ?></p>
                <strong class="alx-safety-count" aria-hidden="true">—</strong>
                <h2 id="alx-rail-safety-title"><?php esc_html_e('Aktív visszahívások', 'autolex-theme'); ?></h2>
                <p><?php esc_html_e('Ellenőrizd a hivatalos, forrással megerősített biztonsági rekordokat.', 'autolex-theme'); ?></p>
                <a href="<?php echo esc_url(home_url('/visszahivasok/')); ?>"><?php esc_html_e('Megtekintés', 'autolex-theme'); ?> →</a>
            </section>
        </aside>

        <div class="alx-home-center">
            <section class="alx-hero" aria-labelledby="alx-home-title">
                <div class="alx-hero-copy">
                    <h1 id="alx-home-title"><?php esc_html_e('Minden jármű.', 'autolex-theme'); ?><br><?php esc_html_e('Minden adat.', 'autolex-theme'); ?><br><span><?php esc_html_e('Egy helyen.', 'autolex-theme'); ?></span></h1>
                    <p><?php esc_html_e('Műszaki adatok, felszereltség, fogyasztás, visszahívások és még sok más – megbízható forrásokból, naprakészen.', 'autolex-theme'); ?></p>

                    <form id="alx-home-search" class="alx-vehicle-search" role="search" action="<?php echo esc_url(home_url('/autok/')); ?>" method="get" data-alx-search-form>
                        <input type="hidden" name="search_type" value="vehicle" data-alx-search-type>
                        <div class="alx-search-tabs" role="tablist" aria-label="<?php esc_attr_e('Keresési mód', 'autolex-theme'); ?>">
                            <span id="alx-tab-vehicle" role="tab" tabindex="0" aria-selected="true" aria-controls="alx-panel-vehicle" data-search-mode="vehicle"><span class="alx-tab-icon" aria-hidden="true">▣</span><?php esc_html_e('Jármű keresése', 'autolex-theme'); ?></span>
                            <span id="alx-tab-vin" role="tab" tabindex="-1" aria-selected="false" aria-controls="alx-panel-vin" data-search-mode="vin"><span class="alx-tab-icon" aria-hidden="true">◇</span><?php esc_html_e('Alvázszám (VIN)', 'autolex-theme'); ?></span>
                            <span id="alx-tab-engine" role="tab" tabindex="-1" aria-selected="false" aria-controls="alx-panel-engine" data-search-mode="engine"><span class="alx-tab-icon" aria-hidden="true">◉</span><?php esc_html_e('Motorkód', 'autolex-theme'); ?></span>
                        </div>

                        <div id="alx-panel-vehicle" class="alx-search-fields" role="tabpanel" aria-labelledby="alx-tab-vehicle" data-search-panel="vehicle">
                            <label><span><?php esc_html_e('Márka', 'autolex-theme'); ?></span><input name="brand" type="search" autocomplete="off" placeholder="<?php esc_attr_e('Márka kiválasztása', 'autolex-theme'); ?>"></label>
                            <label><span><?php esc_html_e('Modell', 'autolex-theme'); ?></span><input name="model" type="search" autocomplete="off" placeholder="<?php esc_attr_e('Modell kiválasztása', 'autolex-theme'); ?>"></label>
                            <label><span><?php esc_html_e('Évjárat', 'autolex-theme'); ?></span><input name="year" inputmode="numeric" pattern="[0-9]{4}" placeholder="<?php esc_attr_e('Évjárat', 'autolex-theme'); ?>"></label>
                            <button type="submit"><?php esc_html_e('Keresés', 'autolex-theme'); ?></button>
                        </div>

                        <div id="alx-panel-vin" class="alx-search-fields alx-search-fields--single" role="tabpanel" aria-labelledby="alx-tab-vin" data-search-panel="vin" hidden>
                            <label><span><?php esc_html_e('Alvázszám', 'autolex-theme'); ?></span><input name="vin" type="search" inputmode="text" minlength="11" maxlength="17" autocomplete="off" disabled placeholder="<?php esc_attr_e('Írd be a 11–17 karakteres alvázszámot', 'autolex-theme'); ?>"></label>
                            <button type="submit"><?php esc_html_e('VIN keresés', 'autolex-theme'); ?></button>
                        </div>

                        <div id="alx-panel-engine" class="alx-search-fields alx-search-fields--single" role="tabpanel" aria-labelledby="alx-tab-engine" data-search-panel="engine" hidden>
                            <label><span><?php esc_html_e('Motorkód', 'autolex-theme'); ?></span><input name="engine_code" type="search" autocomplete="off" disabled placeholder="<?php esc_attr_e('Például: BKC, N47D20', 'autolex-theme'); ?>"></label>
                            <button type="submit"><?php esc_html_e('Motorkód keresés', 'autolex-theme'); ?></button>
                        </div>
                        <noscript><p class="alx-search-note"><?php esc_html_e('JavaScript nélkül a járműkeresés használható; VIN- vagy motorkód-kereséshez nyisd meg a katalógust.', 'autolex-theme'); ?></p></noscript>
                    </form>
                </div>

                <div class="alx-hero-visual" aria-hidden="true">
                    <svg class="alx-car-silhouette" viewBox="0 0 820 420" role="presentation" focusable="false">
                        <defs>
                            <linearGradient id="alx-car-body" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="0.58" stop-color="#edf3f9"/><stop offset="1" stop-color="#cbd7e4"/></linearGradient>
                            <linearGradient id="alx-car-glass" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#47627e"/><stop offset="1" stop-color="#152b42"/></linearGradient>
                            <linearGradient id="alx-car-tail" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#ff7a86"/><stop offset="1" stop-color="#d21f36"/></linearGradient>
                            <filter id="alx-car-shadow" x="-20%" y="-20%" width="150%" height="170%"><feDropShadow dx="0" dy="20" stdDeviation="18" flood-color="#274564" flood-opacity="0.22"/></filter>
                        </defs>
                        <ellipse cx="446" cy="354" rx="316" ry="34" fill="#6f88a4" opacity="0.14"/>
                        <g filter="url(#alx-car-shadow)" transform="translate(16 4)">
                            <path d="M121 275c12-54 55-103 116-126l108-40 93-60c22-14 48-21 74-19l128 10c34 3 64 18 88 42l55 57c24 26 35 58 32 94l-5 56c-2 26-24 46-50 46H171c-30 0-55-22-58-52z" fill="url(#alx-car-body)" stroke="#9fb1c5" stroke-width="4" stroke-linejoin="round"/>
                            <path d="m375 112 76-49c18-12 40-17 61-15l92 7c21 2 40 10 56 24l62 56-151 8-109-3z" fill="url(#alx-car-glass)" stroke="#dbe6f0" stroke-width="5"/>
                            <path d="m608 58 39 12 74 65-92 4z" fill="#29435f" opacity="0.92"/>
                            <path d="m360 118 5 108M514 99l15 127M641 137l33 116" stroke="#a9bacb" stroke-width="3" opacity="0.9"/>
                            <path d="M151 251c95-26 187-39 276-39 113 0 218 20 335 67" fill="none" stroke="#b8c7d5" stroke-width="4"/>
                            <path d="m660 164 101-8 27 31-112 17z" fill="url(#alx-car-tail)" stroke="#b51c31" stroke-width="3"/>
                            <path d="m672 169 79-4 13 10-84 12z" fill="#fff2f4" opacity="0.82"/>
                            <path d="m713 275 68 4-6 31-80 2z" fill="#24384c"/>
                            <ellipse cx="723" cy="299" rx="18" ry="8" fill="#101b26"/><ellipse cx="759" cy="300" rx="18" ry="8" fill="#101b26"/>
                            <circle cx="294" cy="302" r="62" fill="#20364c" stroke="#f9fbfd" stroke-width="12"/><circle cx="294" cy="302" r="31" fill="#879bae" stroke="#dfe7ef" stroke-width="8"/>
                            <circle cx="660" cy="302" r="62" fill="#20364c" stroke="#f9fbfd" stroke-width="12"/><circle cx="660" cy="302" r="31" fill="#879bae" stroke="#dfe7ef" stroke-width="8"/>
                            <circle cx="294" cy="302" r="8" fill="#1769e8"/><circle cx="660" cy="302" r="8" fill="#1769e8"/>
                            <path d="M175 327h533" stroke="#8196ab" stroke-width="4" opacity="0.5"/>
                        </g>
                    </svg>
                </div>
            </section>

            <section class="alx-metrics" aria-label="<?php esc_attr_e('Autolex lefedettségi mutatók', 'autolex-theme'); ?>">
                <?php
                $autolex_render_home_slot(
                    'autolex_theme_metric_strip',
                    static function () {
                        $fallback_metrics = array(__('Márka', 'autolex-theme'), __('Modell', 'autolex-theme'), __('Változat', 'autolex-theme'), __('Motorkód', 'autolex-theme'), __('Lefedettség', 'autolex-theme'));
                        foreach ($fallback_metrics as $label) {
                            ?><span class="alx-live-metric alx-live-metric--fallback"><strong>—</strong><small><?php echo esc_html($label); ?></small></span><?php
                        }
                    }
                );
                ?>
            </section>

            <section class="alx-home-cards alx-home-cards--primary" aria-label="<?php esc_attr_e('Kiemelt Autolex funkciók', 'autolex-theme'); ?>">
                <article class="alx-dashboard-card alx-featured-vehicle-card">
                    <p class="alx-card-kicker"><?php esc_html_e('Kiemelt jármű', 'autolex-theme'); ?></p>
                    <div class="alx-featured-vehicle-head">
                        <div><h2><?php esc_html_e('Járműadatok egy nézetben', 'autolex-theme'); ?></h2><p><?php esc_html_e('Motor, teljesítmény, méretek és fogyasztási adatok.', 'autolex-theme'); ?></p></div>
                        <svg class="alx-card-car" viewBox="0 0 220 92" aria-hidden="true"><path d="M22 60c8-17 20-27 39-31l38-9 27-15h36c12 0 23 4 32 12l20 18c8 7 12 16 12 27v7H19z" fill="#eef3f8" stroke="#9eb0c2" stroke-width="2"/><path d="m101 22 27-14h31c10 0 18 3 25 10l18 16-50 1z" fill="#314a63"/><circle cx="66" cy="67" r="16" fill="#26394c"/><circle cx="66" cy="67" r="7" fill="#a8b7c6"/><circle cx="178" cy="67" r="16" fill="#26394c"/><circle cx="178" cy="67" r="7" fill="#a8b7c6"/></svg>
                    </div>
                    <div class="alx-featured-stats"><span><strong>—</strong><small><?php esc_html_e('Motor', 'autolex-theme'); ?></small></span><span><strong>—</strong><small><?php esc_html_e('Teljesítmény', 'autolex-theme'); ?></small></span><span><strong>—</strong><small><?php esc_html_e('0–100 km/h', 'autolex-theme'); ?></small></span><span><strong>—</strong><small><?php esc_html_e('Fogyasztás', 'autolex-theme'); ?></small></span></div>
                    <a class="alx-card-action" href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Részletek megtekintése', 'autolex-theme'); ?> →</a>
                </article>

                <article class="alx-dashboard-card alx-brand-explore-card">
                    <div class="alx-card-heading"><p class="alx-card-kicker"><?php esc_html_e('Márkák felfedezése', 'autolex-theme'); ?></p><a href="<?php echo esc_url(home_url('/markak/')); ?>"><?php esc_html_e('A–Z', 'autolex-theme'); ?></a></div>
                    <ul class="alx-brand-explore-list">
                        <li><a href="<?php echo esc_url(add_query_arg('brand', 'BMW', home_url('/autok/'))); ?>"><span class="alx-brand-dot">B</span><span>BMW</span><small>→</small></a></li>
                        <li><a href="<?php echo esc_url(add_query_arg('brand', 'Mercedes-Benz', home_url('/autok/'))); ?>"><span class="alx-brand-dot">M</span><span>Mercedes-Benz</span><small>→</small></a></li>
                        <li><a href="<?php echo esc_url(add_query_arg('brand', 'Audi', home_url('/autok/'))); ?>"><span class="alx-brand-dot">A</span><span>Audi</span><small>→</small></a></li>
                        <li><a href="<?php echo esc_url(add_query_arg('brand', 'Volkswagen', home_url('/autok/'))); ?>"><span class="alx-brand-dot">V</span><span>Volkswagen</span><small>→</small></a></li>
                    </ul>
                    <a class="alx-card-action" href="<?php echo esc_url(home_url('/markak/')); ?>"><?php esc_html_e('Összes márka megtekintése', 'autolex-theme'); ?> →</a>
                </article>

                <article class="alx-dashboard-card alx-compare-card">
                    <div class="alx-card-heading"><p class="alx-card-kicker"><?php esc_html_e('Összehasonlítás', 'autolex-theme'); ?></p><a href="<?php echo esc_url(home_url('/osszehasonlitas/')); ?>"><?php esc_html_e('Megnyitás', 'autolex-theme'); ?> →</a></div>
                    <div class="alx-compare-vehicles">
                        <div><strong><?php esc_html_e('Jármű A', 'autolex-theme'); ?></strong><svg viewBox="0 0 150 58" aria-hidden="true"><path d="M13 39c8-15 22-22 42-24l28-6 24 5 28 21 2 11H11z" fill="#eef3f8" stroke="#aab9c8" stroke-width="2"/><circle cx="45" cy="45" r="10" fill="#2a3f52"/><circle cx="111" cy="45" r="10" fill="#2a3f52"/></svg></div>
                        <span class="alx-versus" aria-hidden="true">VS.</span>
                        <div><strong><?php esc_html_e('Jármű B', 'autolex-theme'); ?></strong><svg viewBox="0 0 150 58" aria-hidden="true"><path d="M13 39c8-15 22-22 42-24l28-6 24 5 28 21 2 11H11z" fill="#f3f5f8" stroke="#9aaaba" stroke-width="2"/><circle cx="45" cy="45" r="10" fill="#2a3f52"/><circle cx="111" cy="45" r="10" fill="#2a3f52"/></svg></div>
                    </div>
                    <dl class="alx-compare-lines"><div><dt><?php esc_html_e('Teljesítmény', 'autolex-theme'); ?></dt><dd>— / —</dd></div><div><dt><?php esc_html_e('0–100 km/h', 'autolex-theme'); ?></dt><dd>— / —</dd></div><div><dt><?php esc_html_e('Fogyasztás', 'autolex-theme'); ?></dt><dd>— / —</dd></div></dl>
                    <a class="alx-card-action" href="<?php echo esc_url(home_url('/osszehasonlitas/')); ?>"><?php esc_html_e('Összehasonlítás megnyitása', 'autolex-theme'); ?> →</a>
                </article>
            </section>
        </div>

        <aside class="alx-home-rail alx-home-rail--right" aria-label="<?php esc_attr_e('Adatok, márkák és tudástár', 'autolex-theme'); ?>">
            <section class="alx-coverage-panel" aria-labelledby="alx-coverage-title">
                <h2 id="alx-coverage-title"><?php esc_html_e('Adatok és lefedettség', 'autolex-theme'); ?></h2>
                <div class="alx-dynamic-slot" data-autolex-slot="coverage" aria-live="polite">
                    <?php $autolex_render_home_slot('autolex_theme_coverage_panel', static function () { $rows = array(__('Járművek a rendszerben', 'autolex-theme'), __('Műszaki adatok', 'autolex-theme'), __('Visszahívási rekordok', 'autolex-theme'), __('Frissítve', 'autolex-theme')); ?><dl class="alx-coverage-fallback"><?php foreach ($rows as $label) : ?><div><dt><?php echo esc_html($label); ?></dt><dd>—</dd></div><?php endforeach; ?></dl><?php }); ?>
                </div>
            </section>

            <section class="alx-rail-card alx-brand-panel" aria-labelledby="alx-brand-panel-title">
                <div class="alx-panel-heading"><h2 id="alx-brand-panel-title"><?php esc_html_e('Népszerű márkák', 'autolex-theme'); ?></h2><a href="<?php echo esc_url(home_url('/markak/')); ?>"><?php esc_html_e('Összes márka', 'autolex-theme'); ?> →</a></div>
                <div class="alx-brand-slot" data-autolex-slot="popular-brands" aria-live="polite">
                    <?php $autolex_render_home_slot('autolex_theme_popular_brands', static function () { $brands = array('BMW', 'Mercedes-Benz', 'Audi', 'Volkswagen', 'Toyota', 'Honda', 'Ford', 'Škoda'); ?><ul class="alx-brand-fallback-grid" aria-label="<?php esc_attr_e('Gyors márkaelérés', 'autolex-theme'); ?>"><?php foreach ($brands as $brand) : ?><li><a href="<?php echo esc_url(add_query_arg('brand', $brand, home_url('/autok/'))); ?>"><span><?php echo esc_html(mb_substr($brand, 0, 1)); ?></span><small><?php echo esc_html($brand); ?></small></a></li><?php endforeach; ?></ul><?php }); ?>
                </div>
            </section>

            <section class="alx-rail-card alx-knowledge-card" aria-labelledby="alx-knowledge-card-title">
                <div class="alx-panel-heading"><h2 id="alx-knowledge-card-title"><?php esc_html_e('Tudástár', 'autolex-theme'); ?></h2><a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><?php esc_html_e('Összes cikk', 'autolex-theme'); ?> →</a></div>
                <ul class="alx-knowledge-list">
                    <li><a href="<?php echo esc_url(home_url('/tudastar/#muszaki-alapok')); ?>"><span class="alx-knowledge-thumb">VIN</span><span><strong><?php esc_html_e('Hogyan olvassuk le a VIN számot?', 'autolex-theme'); ?></strong><small><?php esc_html_e('Útmutató', 'autolex-theme'); ?></small></span></a></li>
                    <li><a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><span class="alx-knowledge-thumb">WLTP</span><span><strong><?php esc_html_e('A WLTP szabvány magyarázata', 'autolex-theme'); ?></strong><small><?php esc_html_e('Útmutató', 'autolex-theme'); ?></small></span></a></li>
                    <li><a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><span class="alx-knowledge-thumb">OLAJ</span><span><strong><?php esc_html_e('Motorolaj: mit, mikor, miért?', 'autolex-theme'); ?></strong><small><?php esc_html_e('Karbantartás', 'autolex-theme'); ?></small></span></a></li>
                    <li><a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><span class="alx-knowledge-thumb">GUMI</span><span><strong><?php esc_html_e('Téli gumi: mikor és miért fontos?', 'autolex-theme'); ?></strong><small><?php esc_html_e('Biztonság', 'autolex-theme'); ?></small></span></a></li>
                </ul>
            </section>
        </aside>
    </div>

    <div class="alx-container">
        <section class="alx-safety-strip" aria-labelledby="alx-safety-title">
            <span class="alx-safety-strip-icon" aria-hidden="true">!</span>
            <div class="alx-safety-strip-copy"><p class="alx-card-kicker"><?php esc_html_e('Aktív visszahívások', 'autolex-theme'); ?></p><h2 id="alx-safety-title"><?php esc_html_e('Biztonsági és visszahívási információk', 'autolex-theme'); ?></h2></div>
            <p class="alx-safety-strip-meta"><?php esc_html_e('Hivatalos forrásokból, egyértelmű állapotjelzéssel.', 'autolex-theme'); ?></p>
            <a href="<?php echo esc_url(home_url('/visszahivasok/')); ?>"><?php esc_html_e('Megtekintés', 'autolex-theme'); ?> →</a>
        </section>
    </div>
</div>
<?php
get_footer();
