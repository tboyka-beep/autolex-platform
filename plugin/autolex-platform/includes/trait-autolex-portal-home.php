<?php
/** Autolex Portal rendering component. */
if (!defined('ABSPATH')) { exit; }

trait Autolex_Portal_Home_Trait
{
    private function render_homepage()
    {
        $facets   = $this->get_facets('');
        $coverage = $this->safe_coverage();
        $sources  = self::get_source_registry();
        $makes    = array_slice((array) ($facets['makes'] ?? array()), 0, 14);

        ob_start();
        ?>
        <main class="alx3-portal" id="autolex-main">
            <section class="alx3-hero" aria-labelledby="alx3-hero-title">
                <div class="alx3-grid-noise" aria-hidden="true"></div>
                <div class="alx3-hero__copy">
                    <span class="alx3-kicker"><b>EU / EEA</b> AUTÓADAT-RENDSZER</span>
                    <h1 id="alx3-hero-title">Autóadat.<br><em>Forrásból,</em><br>nem találomra.</h1>
                    <p>Keress márkára, modellre, generációra, motorra vagy motorkódra. Az Autolex minden műszaki állítást adatminőségi szinttel és forrásállapottal kezel.</p>
                    <form class="alx3-hero-search" action="<?php echo esc_url(home_url('/autok/')); ?>" method="get" role="search">
                        <label class="screen-reader-text" for="alx3-home-query"><?php echo esc_html__('Autó keresése', 'autolex-platform'); ?></label>
                        <span class="alx3-search-icon" aria-hidden="true"><?php echo $this->icon('search'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <input id="alx3-home-query" name="q" type="search" placeholder="BMW E87 118d, Golf VII, N47D20…" autocomplete="off">
                        <button type="submit">Keresés az adatbázisban</button>
                    </form>
                    <div class="alx3-proof-row">
                        <span><?php echo $this->icon('database'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> Valós adatbázis</span>
                        <span><?php echo $this->icon('shield'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> Forrásállapot</span>
                        <span><?php echo $this->icon('free'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> Csak ingyenes források</span>
                    </div>
                </div>
                <aside class="alx3-pipeline" aria-label="Autolex adatfolyam">
                    <header><span></span><span></span><span></span><b>DATA PIPELINE / LIVE</b></header>
                    <div class="alx3-pipeline__status">
                        <small>RENDSZERÁLLAPOT</small>
                        <strong><?php echo esc_html($coverage['health_label']); ?></strong>
                        <i class="is-<?php echo esc_attr($coverage['health']); ?>"></i>
                    </div>
                    <dl>
                        <div><dt>Járműváltozat</dt><dd><?php echo esc_html(number_format_i18n($coverage['vehicles'])); ?></dd></div>
                        <div><dt>Márka</dt><dd><?php echo esc_html(number_format_i18n($coverage['makes'])); ?></dd></div>
                        <div><dt>Motorjavaslat</dt><dd><?php echo esc_html(number_format_i18n($coverage['engine_variants'])); ?></dd></div>
                        <div><dt>EEA sor</dt><dd><?php echo esc_html($coverage['sync_progress']); ?>%</dd></div>
                    </dl>
                    <div class="alx3-pipeline__flow">
                        <span>EEA</span><b></b><span>NORMALIZE</span><b></b><span>VERIFY</span><b></b><span>PUBLISH</span>
                    </div>
                    <p><span></span> Utolsó állapotellenőrzés: <?php echo esc_html(wp_date('Y.m.d. H:i')); ?></p>
                </aside>
            </section>

            <section class="alx3-trust-strip" aria-label="Autolex alapelvek">
                <div><b>01</b><span>EU/EGT piaci jelenlét</span></div>
                <div><b>02</b><span>Motorváltozat külön rekord</span></div>
                <div><b>03</b><span>Többforrásos megerősítés</span></div>
                <div><b>04</b><span>VIN-köteles eltérések jelzése</span></div>
            </section>

            <section class="alx3-section alx3-section--makes" aria-labelledby="alx3-popular-title">
                <div class="alx3-section-head">
                    <div><span>NÉPSZERŰ INDULÓPONTOK</span><h2 id="alx3-popular-title">Márkák, egy kattintásra</h2></div>
                    <a href="<?php echo esc_url(home_url('/autok/')); ?>">Teljes autókatalógus <?php echo $this->icon('arrow'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
                </div>
                <div class="alx3-make-grid">
                    <?php foreach ($makes as $make) : ?>
                        <a href="<?php echo esc_url(add_query_arg('make', $make['value'], home_url('/autok/'))); ?>">
                            <span><?php echo esc_html($this->make_initials($make['value'])); ?></span>
                            <strong><?php echo esc_html($make['value']); ?></strong>
                            <small><?php echo esc_html(number_format_i18n((int) $make['total'])); ?> változat</small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="alx3-metrics" aria-label="Adatlefedettség">
                <article><span>EU járműváltozat</span><strong><?php echo esc_html(number_format_i18n($coverage['vehicles'])); ?></strong><small>normalizált műszaki rekord</small></article>
                <article><span>Motorrekord</span><strong><?php echo esc_html(number_format_i18n($coverage['engine_variants'])); ?></strong><small>külön kezelt motorváltozat</small></article>
                <article><span>Forrásbizonyíték</span><strong><?php echo esc_html(number_format_i18n($coverage['evidence_records'])); ?></strong><small>mezőhöz kapcsolt bizonyíték</small></article>
                <article><span>EEA feldolgozás</span><strong><?php echo esc_html($coverage['sync_progress']); ?>%</strong><small><?php echo esc_html(number_format_i18n($coverage['sync_completed'])); ?>/<?php echo esc_html(number_format_i18n($coverage['sync_targets'])); ?> cél feldolgozva</small></article>
            </section>

            <section class="alx3-section" aria-labelledby="alx3-capabilities-title">
                <div class="alx3-section-head">
                    <div><span>NEM CSAK EGY AUTÓLISTA</span><h2 id="alx3-capabilities-title">Sok adat, érthetően rendezve</h2></div>
                </div>
                <div class="alx3-capability-grid">
                    <?php
                    $capabilities = array(
                        array('engine', 'Motor és hajtás', 'Motorkód, motorjelölés, üzemanyag, hengerűrtartalom, kW és LE – ahol a forrás ezt ténylegesen alátámasztja.'),
                        array('filter', 'Mély szűrés', 'Márka, modell, üzemanyag, évjárat, teljesítmény, motorkód és adatminőség egy felületen.'),
                        array('source', 'Forrásbizonyíték', 'Az „ellenőrzött” jelölés nem marketingcímke: külön forrásrekord és ellenőrzési állapot tartozik hozzá.'),
                        array('warning', 'Biztonságos bizonytalanság', 'Ahol csak alvázszámmal dönthető el a kompatibilitás, a rendszer nem tippel, hanem VIN-ellenőrzést kér.'),
                        array('market', 'EU piaci kontextus', 'EEA, Eurostat és EAFO adatokkal a modell nemcsak műszaki, hanem európai piaci környezetben is értelmezhető.'),
                        array('recall', 'Visszahívási réteg', 'A Safety Gate nyilvános riasztásai külön, forrásolt biztonsági rétegként kerülnek be a következő adatfázisban.'),
                    );
                    foreach ($capabilities as $item) :
                        ?>
                        <article>
                            <i><?php echo $this->icon($item[0]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></i>
                            <h3><?php echo esc_html($item[1]); ?></h3>
                            <p><?php echo esc_html($item[2]); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="alx3-section alx3-section--sources" aria-labelledby="alx3-sources-title">
                <div class="alx3-section-head">
                    <div><span>INGYENES, VALÓS FORRÁSOK</span><h2 id="alx3-sources-title">Honnan érkeznek az adatok?</h2></div>
                    <a href="<?php echo esc_url(rest_url('autolex/v1/sources')); ?>">Gépi forrásjegyzék <?php echo $this->icon('arrow'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
                </div>
                <div class="alx3-source-grid">
                    <?php foreach ($sources as $source) : ?>
                        <article>
                            <header><span class="is-<?php echo esc_attr($source['automation']); ?>"><?php echo esc_html($this->source_status_label($source['automation'])); ?></span><b><?php echo esc_html(strtoupper($source['confidence'])); ?></b></header>
                            <h3><?php echo esc_html($source['name']); ?></h3>
                            <small><?php echo esc_html($source['publisher']); ?></small>
                            <p><?php echo esc_html($source['scope']); ?></p>
                            <footer><span><?php echo esc_html($source['access']); ?></span><a href="<?php echo esc_url($source['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($source['name']); ?> megnyitása"><?php echo $this->icon('external'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="alx3-method" aria-labelledby="alx3-method-title">
                <div><span>ADATMINŐSÉGI FOLYAMAT</span><h2 id="alx3-method-title">Importálttól az ellenőrzöttig</h2><p>Az Autolex nem mossa össze a nyers adatot a bizonyított műszaki állítással. Minden rekord lépcsőzetesen halad.</p></div>
                <ol>
                    <li><b>01</b><span><strong>Imported</strong><small>hivatalos rekord, még generációhoz nem kapcsolva</small></span></li>
                    <li><b>02</b><span><strong>Matched</strong><small>márka, modell és műszaki változat normalizálva</small></span></li>
                    <li><b>03</b><span><strong>Reviewed</strong><small>második forrás vagy emberi felülvizsgálat</small></span></li>
                    <li><b>04</b><span><strong>Verified</strong><small>legalább két független, elsődleges bizonyíték</small></span></li>
                </ol>
            </section>

            <section class="alx3-final-cta">
                <span>INDULHAT A KERESÉS?</span>
                <h2>Találd meg a pontos változatot.</h2>
                <p>A szűrő mutatja azt is, mennyire teljes és mennyire megerősített az adott rekord.</p>
                <a href="<?php echo esc_url(home_url('/autok/')); ?>">Autók böngészése <?php echo $this->icon('arrow'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
            </section>
        </main>
        <?php
        return (string) ob_get_clean();
    }
}
