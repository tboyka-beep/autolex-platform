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
        $makes    = array_slice((array) ($facets['makes'] ?? array()), 0, 12);
        $models   = array_slice((array) ($facets['models'] ?? array()), 0, 8);
        $recent   = $this->query_vehicles(array('sort' => 'year_desc', 'limit' => 6, 'page' => 1));
        $recent_items = (array) ($recent['items'] ?? array());

        ob_start();
        ?>
        <main class="alx3-portal alx3-home-light" id="autolex-main">
            <style>
                .alx3-home-light{--alx-home-bg:#f6f8fb;--alx-home-card:#fff;--alx-home-border:#e2e8f0;--alx-home-text:#14213d;--alx-home-muted:#64748b;--alx-home-blue:#1d4ed8;--alx-home-blue-soft:#eff6ff;--alx-home-red:#b91c1c;background:var(--alx-home-bg);color:var(--alx-home-text)}
                .alx3-home-light .alx3-hero{background:linear-gradient(135deg,#fff 0%,#eff6ff 55%,#f8fafc 100%);border:1px solid var(--alx-home-border);box-shadow:0 22px 60px rgba(15,23,42,.08)}
                .alx3-home-light .alx3-home-shortcuts,.alx3-home-light .alx3-home-model-grid,.alx3-home-light .alx3-home-recent-grid,.alx3-home-light .alx3-home-service-grid{display:grid;gap:16px}
                .alx3-home-light .alx3-home-shortcuts{grid-template-columns:repeat(4,minmax(0,1fr));margin:24px 0 34px}
                .alx3-home-light .alx3-home-shortcuts a,.alx3-home-light .alx3-home-model-grid a,.alx3-home-light .alx3-home-recent-grid a,.alx3-home-light .alx3-home-service-grid a{display:block;background:var(--alx-home-card);border:1px solid var(--alx-home-border);border-radius:18px;padding:20px;color:var(--alx-home-text);text-decoration:none;box-shadow:0 10px 28px rgba(15,23,42,.05);transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}
                .alx3-home-light .alx3-home-shortcuts a:hover,.alx3-home-light .alx3-home-model-grid a:hover,.alx3-home-light .alx3-home-recent-grid a:hover,.alx3-home-light .alx3-home-service-grid a:hover{transform:translateY(-3px);border-color:#93c5fd;box-shadow:0 16px 34px rgba(29,78,216,.10)}
                .alx3-home-light .alx3-home-shortcuts strong,.alx3-home-light .alx3-home-model-grid strong,.alx3-home-light .alx3-home-recent-grid strong,.alx3-home-light .alx3-home-service-grid strong{display:block;font-size:1.05rem;margin-bottom:6px}
                .alx3-home-light .alx3-home-shortcuts small,.alx3-home-light .alx3-home-model-grid small,.alx3-home-light .alx3-home-recent-grid small,.alx3-home-light .alx3-home-service-grid small{color:var(--alx-home-muted);line-height:1.5}
                .alx3-home-light .alx3-home-model-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
                .alx3-home-light .alx3-home-recent-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
                .alx3-home-light .alx3-home-service-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
                .alx3-home-light .alx3-home-badge{display:inline-flex;align-items:center;gap:6px;margin-top:12px;padding:6px 10px;border-radius:999px;background:var(--alx-home-blue-soft);color:var(--alx-home-blue);font-size:.78rem;font-weight:700}
                .alx3-home-light .alx3-home-badge.is-safety{background:#fef2f2;color:var(--alx-home-red)}
                .alx3-home-light .alx3-empty-state{background:#fff;border:1px dashed #cbd5e1;border-radius:18px;padding:28px;color:var(--alx-home-muted);text-align:center}
                .alx3-home-light .alx3-section-head p{max-width:720px;color:var(--alx-home-muted);margin:8px 0 0}
                @media(max-width:960px){.alx3-home-light .alx3-home-shortcuts,.alx3-home-light .alx3-home-model-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.alx3-home-light .alx3-home-recent-grid,.alx3-home-light .alx3-home-service-grid{grid-template-columns:1fr 1fr}}
                @media(max-width:640px){.alx3-home-light .alx3-home-shortcuts,.alx3-home-light .alx3-home-model-grid,.alx3-home-light .alx3-home-recent-grid,.alx3-home-light .alx3-home-service-grid{grid-template-columns:1fr}.alx3-home-light .alx3-home-shortcuts a,.alx3-home-light .alx3-home-model-grid a,.alx3-home-light .alx3-home-recent-grid a,.alx3-home-light .alx3-home-service-grid a{padding:18px}}
                @media(prefers-reduced-motion:reduce){.alx3-home-light *{scroll-behavior:auto!important;transition:none!important;animation:none!important}}
            </style>

            <section class="alx3-hero" aria-labelledby="alx3-hero-title">
                <div class="alx3-grid-noise" aria-hidden="true"></div>
                <div class="alx3-hero__copy">
                    <span class="alx3-kicker"><b>EU / EEA</b> AUTÓADAT-RENDSZER</span>
                    <h1 id="alx3-hero-title">Találd meg a pontos autót.<br><em>Forrásból,</em> nem találomra.</h1>
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
                        <span><?php echo $this->icon('free'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> Ingyenes források</span>
                    </div>
                </div>
                <aside class="alx3-pipeline" aria-label="Autolex adatfolyam">
                    <header><span></span><span></span><span></span><b>DATA PIPELINE / LIVE</b></header>
                    <div class="alx3-pipeline__status"><small>RENDSZERÁLLAPOT</small><strong><?php echo esc_html($coverage['health_label']); ?></strong><i class="is-<?php echo esc_attr($coverage['health']); ?>"></i></div>
                    <dl>
                        <div><dt>Járműváltozat</dt><dd><?php echo esc_html(number_format_i18n($coverage['vehicles'])); ?></dd></div>
                        <div><dt>Márka</dt><dd><?php echo esc_html(number_format_i18n($coverage['makes'])); ?></dd></div>
                        <div><dt>Motorjavaslat</dt><dd><?php echo esc_html(number_format_i18n($coverage['engine_variants'])); ?></dd></div>
                        <div><dt>EEA feldolgozás</dt><dd><?php echo esc_html($coverage['sync_progress']); ?>%</dd></div>
                    </dl>
                    <p><span></span> Utolsó állapotellenőrzés: <?php echo esc_html(wp_date('Y.m.d. H:i')); ?></p>
                </aside>
            </section>

            <nav class="alx3-home-shortcuts" aria-label="Gyors belépési pontok">
                <a href="<?php echo esc_url(home_url('/autok/')); ?>"><strong>Autó keresése</strong><small>Márka, modell, generáció és motor alapján.</small></a>
                <a href="<?php echo esc_url(add_query_arg('focus', 'engine', home_url('/autok/'))); ?>"><strong>Motor és motorkód</strong><small>Keress külön motorváltozatra vagy motorkódra.</small></a>
                <a href="<?php echo esc_url(home_url('/auto-osszehasonlitas/')); ?>"><strong>Összehasonlítás</strong><small>Legfeljebb három jármű egymás mellett.</small></a>
                <a href="<?php echo esc_url(add_query_arg('verification', 'vin_required', home_url('/autok/'))); ?>"><strong>Biztonság és VIN</strong><small>VIN-köteles és biztonsági jelzések áttekintése.</small></a>
            </nav>

            <section class="alx3-section alx3-section--makes" aria-labelledby="alx3-popular-title">
                <div class="alx3-section-head"><div><span>NÉPSZERŰ INDULÓPONTOK</span><h2 id="alx3-popular-title">Márkák, egy kattintásra</h2><p>A sorrend a katalógusban ténylegesen elérhető rekordok száma alapján készül.</p></div><a href="<?php echo esc_url(home_url('/autok/')); ?>">Teljes autókatalógus <?php echo $this->icon('arrow'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></div>
                <?php if ($makes) : ?>
                    <div class="alx3-make-grid">
                        <?php foreach ($makes as $make) : ?>
                            <a href="<?php echo esc_url(add_query_arg('make', $make['value'], home_url('/autok/'))); ?>"><span><?php echo esc_html($this->make_initials($make['value'])); ?></span><strong><?php echo esc_html($make['value']); ?></strong><small><?php echo esc_html(number_format_i18n((int) $make['total'])); ?> változat</small></a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="alx3-empty-state">A márkalista az első sikeres katalógus-import után jelenik meg.</div>
                <?php endif; ?>
            </section>

            <section class="alx3-section" aria-labelledby="alx3-models-title">
                <div class="alx3-section-head"><div><span>VALÓS KATALÓGUSADAT</span><h2 id="alx3-models-title">Népszerű modellek</h2><p>A lista a jelenlegi adatbázis legtöbb változattal rendelkező modelljeit mutatja.</p></div></div>
                <?php if ($models) : ?>
                    <div class="alx3-home-model-grid">
                        <?php foreach ($models as $model) : ?>
                            <a href="<?php echo esc_url(add_query_arg('model', $model['value'], home_url('/autok/'))); ?>"><strong><?php echo esc_html($model['value']); ?></strong><small><?php echo esc_html(number_format_i18n((int) $model['total'])); ?> katalogizált változat</small><span class="alx3-home-badge">Adatbázisból</span></a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="alx3-empty-state">Nincs még elegendő modelladat a népszerűségi lista összeállításához.</div>
                <?php endif; ?>
            </section>

            <section class="alx3-metrics" aria-label="Adatlefedettség">
                <article><span>EU járműváltozat</span><strong><?php echo esc_html(number_format_i18n($coverage['vehicles'])); ?></strong><small>normalizált műszaki rekord</small></article>
                <article><span>Motorrekord</span><strong><?php echo esc_html(number_format_i18n($coverage['engine_variants'])); ?></strong><small>külön kezelt motorváltozat</small></article>
                <article><span>Forrásbizonyíték</span><strong><?php echo esc_html(number_format_i18n($coverage['evidence_records'])); ?></strong><small>mezőhöz kapcsolt bizonyíték</small></article>
                <article><span>EEA feldolgozás</span><strong><?php echo esc_html($coverage['sync_progress']); ?>%</strong><small><?php echo esc_html(number_format_i18n($coverage['sync_completed'])); ?>/<?php echo esc_html(number_format_i18n($coverage['sync_targets'])); ?> cél feldolgozva</small></article>
            </section>

            <section class="alx3-section" aria-labelledby="alx3-recent-title">
                <div class="alx3-section-head"><div><span>LEGUTÓBBI ÉVJÁRATOK</span><h2 id="alx3-recent-title">Friss belépési pontok az adatbázisba</h2><p>Valós katalógusrekordok, a legújabb ismert gyártási kezdőév szerint rendezve.</p></div></div>
                <?php if ($recent_items) : ?>
                    <div class="alx3-home-recent-grid">
                        <?php foreach ($recent_items as $vehicle) : ?>
                            <a href="<?php echo esc_url($vehicle['url']); ?>"><strong><?php echo esc_html(trim($vehicle['make'] . ' ' . $vehicle['model'])); ?></strong><small><?php echo esc_html(trim($vehicle['generation'] . ' · ' . $vehicle['engine'])); ?><br><?php echo esc_html($vehicle['years']); ?></small><span class="alx3-home-badge">Adatminőség: <?php echo esc_html($vehicle['data_grade']); ?></span></a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="alx3-empty-state">A friss adatlapok az első sikeres katalógus-szinkron után jelennek meg.</div>
                <?php endif; ?>
            </section>

            <section class="alx3-section" aria-labelledby="alx3-services-title">
                <div class="alx3-section-head"><div><span>GYORS FUNKCIÓK</span><h2 id="alx3-services-title">Tovább az Autolex fő eszközeihez</h2></div></div>
                <div class="alx3-home-service-grid">
                    <a href="<?php echo esc_url(home_url('/auto-osszehasonlitas/')); ?>"><strong>Gyors összehasonlítás</strong><small>Válassz ki legfeljebb három autót, és vesd össze a motor-, teljesítmény-, évjárat- és adatminőségi mezőket.</small><span class="alx3-home-badge">Összehasonlító</span></a>
                    <a href="<?php echo esc_url(add_query_arg('verification', 'vin_required', home_url('/autok/'))); ?>"><strong>Safety Gate és VIN-jelzések</strong><small>A rendszer nem állít automatikus visszahívási érintettséget; a VIN-köteles eseteket külön jelöli.</small><span class="alx3-home-badge is-safety">Biztonsági kapu</span></a>
                    <a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><strong>Autós tudástár</strong><small>Érthető háttéranyagok motorokról, folyadékokról, karbantartásról és adatforrásokról.</small><span class="alx3-home-badge">Útmutatók</span></a>
                </div>
            </section>

            <section class="alx3-section alx3-section--sources" aria-labelledby="alx3-sources-title">
                <div class="alx3-section-head"><div><span>INGYENES, VALÓS FORRÁSOK</span><h2 id="alx3-sources-title">Honnan érkeznek az adatok?</h2></div><a href="<?php echo esc_url(rest_url('autolex/v1/sources')); ?>">Gépi forrásjegyzék <?php echo $this->icon('arrow'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></div>
                <div class="alx3-source-grid">
                    <?php foreach ($sources as $source) : ?>
                        <article><header><span class="is-<?php echo esc_attr($source['automation']); ?>"><?php echo esc_html($this->source_status_label($source['automation'])); ?></span><b><?php echo esc_html(strtoupper($source['confidence'])); ?></b></header><h3><?php echo esc_html($source['name']); ?></h3><small><?php echo esc_html($source['publisher']); ?></small><p><?php echo esc_html($source['scope']); ?></p><footer><span><?php echo esc_html($source['access']); ?></span><a href="<?php echo esc_url($source['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($source['name']); ?> megnyitása"><?php echo $this->icon('external'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></footer></article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="alx3-final-cta"><span>INDULHAT A KERESÉS?</span><h2>Találd meg a pontos változatot.</h2><p>A szűrő mutatja azt is, mennyire teljes és mennyire megerősített az adott rekord.</p><a href="<?php echo esc_url(home_url('/autok/')); ?>">Autók böngészése <?php echo $this->icon('arrow'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></section>
        </main>
        <?php
        return (string) ob_get_clean();
    }
}
