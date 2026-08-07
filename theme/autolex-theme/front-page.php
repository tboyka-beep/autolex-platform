<?php
/**
 * Reference-dashboard front page for Autolex.
 *
 * Numeric data is plugin-owned and database-backed. Stock photography is used
 * only as clearly documented visual illustration and never as a data source.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

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
    array('label' => __('Biztonság', 'autolex-theme'), 'description' => __('Visszahívások és biztonsági adatok', 'autolex-theme'), 'url' => home_url('/visszahivasok/'), 'icon' => 'shield'),
    array('label' => __('Értékek és költségek', 'autolex-theme'), 'description' => __('Fogyasztás és fenntartási tudnivalók', 'autolex-theme'), 'url' => home_url('/tudastar/'), 'icon' => 'cost'),
    array('label' => __('Tudástár', 'autolex-theme'), 'description' => __('Útmutatók, cikkek, magyarázatok', 'autolex-theme'), 'url' => home_url('/tudastar/'), 'icon' => 'book'),
);

$hero_image = 'https://images.unsplash.com/photo-1773793097960-5dbdcbc081c0?auto=format&fit=crop&w=1600&q=82';
$featured_image = 'https://images.unsplash.com/photo-1523983302122-73e869e1f850?auto=format&fit=crop&w=900&q=82';
$compare_image = 'https://images.unsplash.com/photo-1537994725085-277ef72d1cb6?auto=format&fit=crop&w=900&q=82';
$dashboard_image = 'https://images.unsplash.com/photo-1633113215883-a43e36af27e4?auto=format&fit=crop&w=420&q=78';

get_header();
?>
<div class="alx-home" data-reference-dashboard="true" data-real-media="true">
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
                    <p><?php esc_html_e('Keress járművet telefonról is, ugyanazzal a teljes katalógussal.', 'autolex-theme'); ?></p>
                    <a href="#alx-home-search"><?php esc_html_e('Keresés megnyitása', 'autolex-theme'); ?></a>
                </div>
            </section>

            <section class="alx-rail-card alx-safety-card" aria-labelledby="alx-rail-safety-title">
                <p class="alx-safety-label"><span class="alx-safety-symbol" aria-hidden="true">!</span><?php esc_html_e('Biztonsági központ', 'autolex-theme'); ?></p>
                <h2 id="alx-rail-safety-title"><?php esc_html_e('Visszahívások ellenőrzése', 'autolex-theme'); ?></h2>
                <p><?php esc_html_e('Hivatalos, forrással megerősített biztonsági rekordok egy helyen.', 'autolex-theme'); ?></p>
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

                <div class="alx-hero-visual">
                    <img class="alx-hero-stock-photo" src="<?php echo esc_url($hero_image); ?>" alt="<?php esc_attr_e('Ezüst szedán hegyi úton – illusztráció', 'autolex-theme'); ?>" width="1600" height="900" loading="eager" decoding="async" fetchpriority="high">
                    <a class="alx-stock-credit" href="https://unsplash.com/photos/2kO5bZFLj1E" target="_blank" rel="noopener noreferrer">Chandler Cruttenden / Unsplash</a>
                </div>
            </section>

            <section class="alx-metrics" aria-label="<?php esc_attr_e('Autolex lefedettségi mutatók', 'autolex-theme'); ?>">
                <?php
                $autolex_render_home_slot(
                    'autolex_theme_metric_strip',
                    static function () {
                        ?><span class="alx-data-pending"><?php esc_html_e('A katalógus lefedettségi adatai frissítés alatt állnak.', 'autolex-theme'); ?></span><?php
                    }
                );
                ?>
            </section>

            <section class="alx-home-cards alx-home-cards--primary" aria-label="<?php esc_attr_e('Kiemelt Autolex funkciók', 'autolex-theme'); ?>">
                <article class="alx-dashboard-card alx-featured-vehicle-card">
                    <p class="alx-card-kicker"><?php esc_html_e('Kiemelt jármű', 'autolex-theme'); ?></p>
                    <div class="alx-featured-vehicle-head">
                        <div class="alx-featured-copy">
                            <?php
                            $autolex_render_home_slot(
                                'autolex_theme_featured_vehicle',
                                static function () {
                                    ?><h2><?php esc_html_e('Járműadatok egy nézetben', 'autolex-theme'); ?></h2><p><?php esc_html_e('Válassz járművet a katalógusból a részletes, forrásolt adatokhoz.', 'autolex-theme'); ?></p><a class="alx-card-action" href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Katalógus megnyitása', 'autolex-theme'); ?> →</a><?php
                                }
                            );
                            ?>
                        </div>
                        <div class="alx-featured-media">
                            <img src="<?php echo esc_url($featured_image); ?>" alt="<?php esc_attr_e('BMW szedán – stock illusztráció', 'autolex-theme'); ?>" width="900" height="560" loading="lazy" decoding="async">
                            <a class="alx-stock-credit" href="https://unsplash.com/photos/_8WDl2zgB_0" target="_blank" rel="noopener noreferrer">Arteum.ro / Unsplash</a>
                        </div>
                    </div>
                </article>

                <article class="alx-dashboard-card alx-brand-explore-card">
                    <div class="alx-card-heading"><p class="alx-card-kicker"><?php esc_html_e('Márkák felfedezése', 'autolex-theme'); ?></p><a href="<?php echo esc_url(home_url('/markak/')); ?>"><?php esc_html_e('A–Z', 'autolex-theme'); ?></a></div>
                    <ul class="alx-brand-explore-list">
                        <li><a href="<?php echo esc_url(add_query_arg('brand', 'BMW', home_url('/autok/'))); ?>"><span class="alx-brand-logo"><img src="https://cdn.simpleicons.org/bmw/0066B1" alt="" width="17" height="17" loading="lazy"></span><span>BMW</span><small>→</small></a></li>
                        <li><a href="<?php echo esc_url(add_query_arg('brand', 'Mercedes-Benz', home_url('/autok/'))); ?>"><span class="alx-brand-logo"><img src="https://cdn.simpleicons.org/mercedes/111827" alt="" width="17" height="17" loading="lazy"></span><span>Mercedes-Benz</span><small>→</small></a></li>
                        <li><a href="<?php echo esc_url(add_query_arg('brand', 'Audi', home_url('/autok/'))); ?>"><span class="alx-brand-logo"><img src="https://cdn.simpleicons.org/audi/BB0A30" alt="" width="17" height="17" loading="lazy"></span><span>Audi</span><small>→</small></a></li>
                        <li><a href="<?php echo esc_url(add_query_arg('brand', 'Volkswagen', home_url('/autok/'))); ?>"><span class="alx-brand-logo"><img src="https://cdn.simpleicons.org/volkswagen/001E50" alt="" width="17" height="17" loading="lazy"></span><span>Volkswagen</span><small>→</small></a></li>
                    </ul>
                    <a class="alx-card-action" href="<?php echo esc_url(home_url('/markak/')); ?>"><?php esc_html_e('Összes márka megtekintése', 'autolex-theme'); ?> →</a>
                </article>

                <article class="alx-dashboard-card alx-compare-card">
                    <div class="alx-card-heading"><p class="alx-card-kicker"><?php esc_html_e('Összehasonlítás', 'autolex-theme'); ?></p><a href="<?php echo esc_url(home_url('/osszehasonlitas/')); ?>"><?php esc_html_e('Megnyitás', 'autolex-theme'); ?> →</a></div>
                    <div class="alx-compare-vehicles alx-compare-vehicles--photos" aria-hidden="true">
                        <div class="alx-compare-media"><img src="<?php echo esc_url($compare_image); ?>" alt="" width="900" height="560" loading="lazy" decoding="async"></div>
                        <span class="alx-versus">VS.</span>
                        <div class="alx-compare-media"><img src="<?php echo esc_url($featured_image); ?>" alt="" width="900" height="560" loading="lazy" decoding="async"></div>
                    </div>
                    <?php
                    $autolex_render_home_slot(
                        'autolex_theme_comparison_preview',
                        static function () {
                            ?><p class="alx-data-pending"><?php esc_html_e('Válassz két járművet a részletes összehasonlításhoz.', 'autolex-theme'); ?></p><?php
                        }
                    );
                    ?>
                    <a class="alx-card-action" href="<?php echo esc_url(home_url('/osszehasonlitas/')); ?>"><?php esc_html_e('Összehasonlítás megnyitása', 'autolex-theme'); ?> →</a>
                </article>
            </section>
        </div>

        <aside class="alx-home-rail alx-home-rail--right" aria-label="<?php esc_attr_e('Adatok, márkák és tudástár', 'autolex-theme'); ?>">
            <section class="alx-coverage-panel" aria-labelledby="alx-coverage-title">
                <h2 id="alx-coverage-title"><?php esc_html_e('Adatok és lefedettség', 'autolex-theme'); ?></h2>
                <div class="alx-dynamic-slot" data-autolex-slot="coverage" aria-live="polite">
                    <?php
                    $autolex_render_home_slot(
                        'autolex_theme_coverage_panel',
                        static function () {
                            ?><p class="alx-data-pending"><?php esc_html_e('A lefedettségi adatok frissítés alatt állnak.', 'autolex-theme'); ?></p><?php
                        }
                    );
                    ?>
                </div>
            </section>

            <section class="alx-rail-card alx-brand-panel" aria-labelledby="alx-brand-panel-title">
                <div class="alx-panel-heading"><h2 id="alx-brand-panel-title"><?php esc_html_e('Népszerű márkák', 'autolex-theme'); ?></h2><a href="<?php echo esc_url(home_url('/markak/')); ?>"><?php esc_html_e('Összes márka', 'autolex-theme'); ?> →</a></div>
                <div class="alx-brand-slot" data-autolex-slot="popular-brands" aria-live="polite">
                    <?php
                    $autolex_render_home_slot(
                        'autolex_theme_popular_brands',
                        static function () {
                            $brands = array(
                                array('name' => 'BMW', 'slug' => 'bmw', 'logo' => 'https://cdn.simpleicons.org/bmw/0066B1'),
                                array('name' => 'Mercedes-Benz', 'slug' => 'mercedes-benz', 'logo' => 'https://cdn.simpleicons.org/mercedes/111827'),
                                array('name' => 'Audi', 'slug' => 'audi', 'logo' => 'https://cdn.simpleicons.org/audi/BB0A30'),
                                array('name' => 'Volkswagen', 'slug' => 'volkswagen', 'logo' => 'https://cdn.simpleicons.org/volkswagen/001E50'),
                                array('name' => 'Toyota', 'slug' => 'toyota', 'logo' => 'https://cdn.simpleicons.org/toyota/EB0A1E'),
                                array('name' => 'Ford', 'slug' => 'ford', 'logo' => 'https://cdn.simpleicons.org/ford/003478'),
                            );
                            ?><ul class="alx-brand-fallback-grid" aria-label="<?php esc_attr_e('Gyors márkaelérés', 'autolex-theme'); ?>"><?php foreach ($brands as $brand) : ?><li><a href="<?php echo esc_url(add_query_arg('brand', $brand['name'], home_url('/autok/'))); ?>"><span class="alx-brand-logo"><img src="<?php echo esc_url($brand['logo']); ?>" alt="" width="17" height="17" loading="lazy"></span><small><?php echo esc_html($brand['name']); ?></small></a></li><?php endforeach; ?></ul><?php
                        }
                    );
                    ?>
                </div>
            </section>

            <section class="alx-rail-card alx-knowledge-card" aria-labelledby="alx-knowledge-card-title">
                <div class="alx-panel-heading"><h2 id="alx-knowledge-card-title"><?php esc_html_e('Tudástár', 'autolex-theme'); ?></h2><a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><?php esc_html_e('Összes cikk', 'autolex-theme'); ?> →</a></div>
                <ul class="alx-knowledge-list">
                    <li><a href="<?php echo esc_url(home_url('/tudastar/#muszaki-alapok')); ?>"><span class="alx-knowledge-thumb"><img src="<?php echo esc_url($dashboard_image); ?>" alt="" width="180" height="100" loading="lazy" decoding="async"></span><span><strong><?php esc_html_e('Hogyan olvassuk le a VIN számot?', 'autolex-theme'); ?></strong><small><?php esc_html_e('Útmutató', 'autolex-theme'); ?></small></span></a></li>
                    <li><a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><span class="alx-knowledge-thumb"><img src="<?php echo esc_url($compare_image); ?>" alt="" width="180" height="100" loading="lazy" decoding="async"></span><span><strong><?php esc_html_e('Téli gumi: mikor és miért fontos?', 'autolex-theme'); ?></strong><small><?php esc_html_e('Biztonság', 'autolex-theme'); ?></small></span></a></li>
                    <li><a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><span class="alx-knowledge-thumb"><img src="<?php echo esc_url($featured_image); ?>" alt="" width="180" height="100" loading="lazy" decoding="async"></span><span><strong><?php esc_html_e('Motorolaj: mit, mikor, miért?', 'autolex-theme'); ?></strong><small><?php esc_html_e('Karbantartás', 'autolex-theme'); ?></small></span></a></li>
                    <li><a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><span class="alx-knowledge-thumb"><img src="<?php echo esc_url($hero_image); ?>" alt="" width="180" height="100" loading="lazy" decoding="async"></span><span><strong><?php esc_html_e('Fékek és alapvető biztonsági ellenőrzések', 'autolex-theme'); ?></strong><small><?php esc_html_e('Biztonság', 'autolex-theme'); ?></small></span></a></li>
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
