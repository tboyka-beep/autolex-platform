<?php
/** Autolex 4.0 homepage rendering component. */
if (!defined('ABSPATH')) {
    exit;
}

trait Autolex_Portal_Home_Trait
{
    /**
     * Render the Autolex 4.0 light-first homepage.
     *
     * @return string
     */
    private function render_homepage()
    {
        $facets       = $this->get_facets('');
        $coverage     = $this->safe_coverage();
        $makes        = array_slice((array) ($facets['makes'] ?? array()), 0, 12);
        $models       = array_slice((array) ($facets['models'] ?? array()), 0, 8);
        $recent       = $this->query_vehicles(array('sort' => 'year_desc', 'limit' => 6, 'page' => 1));
        $recent_items = (array) ($recent['items'] ?? array());

        ob_start();
        ?>
        <style id="autolex-4-home-style">
            body.autolex-portal-3{background:#f4f7fb!important;color:#132238!important}
            body.autolex-portal-3 .site-main,body.autolex-portal-3 .content-area,body.autolex-portal-3 .entry-content{background:#f4f7fb!important}
            body.autolex-portal-3 .ct-header [data-row="middle"],body.autolex-portal-3 #header [data-row="middle"]{background:rgba(255,255,255,.97)!important;border-bottom:1px solid #e5eaf2!important;box-shadow:0 8px 28px rgba(15,23,42,.06)!important}
            body.autolex-portal-3 .ct-header nav>ul>li>a{color:#1d2c43!important;font-weight:800!important;letter-spacing:.02em!important}
            body.autolex-portal-3 .ct-header nav>ul>li>a:hover,body.autolex-portal-3 .ct-header nav>ul>li.current-menu-item>a{color:#2563eb!important}
            .alx4-home{--alx4-bg:#f4f7fb;--alx4-surface:#fff;--alx4-surface-soft:#f8fafc;--alx4-border:#e5eaf2;--alx4-text:#132238;--alx4-muted:#607089;--alx4-blue:#2563eb;--alx4-blue-dark:#1749b7;--alx4-red:#dc2626;--alx4-green:#15803d;--alx4-shadow:0 22px 65px rgba(15,23,42,.10);width:min(1320px,calc(100% - 40px));margin:0 auto;padding:34px 0 72px;color:var(--alx4-text)}
            .alx4-home *{box-sizing:border-box}
            .alx4-home a{text-decoration:none}
            .alx4-home :focus-visible{outline:3px solid rgba(37,99,235,.32);outline-offset:4px}
            .alx4-hero{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1.25fr) minmax(340px,.75fr);gap:40px;align-items:center;min-height:560px;padding:64px;border:1px solid var(--alx4-border);border-radius:34px;background:linear-gradient(135deg,#fff 0%,#f7fbff 54%,#edf4ff 100%);box-shadow:var(--alx4-shadow)}
            .alx4-hero:before,.alx4-hero:after{content:"";position:absolute;border-radius:999px;pointer-events:none}
            .alx4-hero:before{width:520px;height:520px;right:-180px;top:-210px;background:radial-gradient(circle,rgba(37,99,235,.18),rgba(37,99,235,0) 68%)}
            .alx4-hero:after{width:420px;height:420px;left:-180px;bottom:-260px;background:radial-gradient(circle,rgba(14,165,233,.12),rgba(14,165,233,0) 68%)}
            .alx4-hero__copy,.alx4-hero__panel{position:relative;z-index:2}
            .alx4-eyebrow{display:inline-flex;align-items:center;gap:10px;margin-bottom:20px;padding:9px 13px;border:1px solid #dbe7fb;border-radius:999px;background:#fff;color:#2452a6;font-size:.76rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase;box-shadow:0 10px 30px rgba(37,99,235,.08)}
            .alx4-eyebrow:before{content:"";width:8px;height:8px;border-radius:50%;background:#2563eb;box-shadow:0 0 0 5px rgba(37,99,235,.11)}
            .alx4-hero h1{max-width:820px;margin:0;font-size:clamp(3rem,6vw,6rem);line-height:.96;letter-spacing:-.065em;color:#101c30}
            .alx4-hero h1 span{color:#2563eb}
            .alx4-hero__lead{max-width:720px;margin:28px 0 0;color:var(--alx4-muted);font-size:clamp(1rem,1.7vw,1.24rem);line-height:1.7}
            .alx4-search{display:grid;grid-template-columns:1fr auto;gap:10px;margin-top:34px;padding:10px;border:1px solid #d8e2f0;border-radius:20px;background:#fff;box-shadow:0 16px 40px rgba(15,23,42,.09)}
            .alx4-search__field{display:flex;align-items:center;gap:12px;min-width:0;padding:0 14px}
            .alx4-search__field svg{width:22px;height:22px;color:#64748b;flex:0 0 auto}
            .alx4-search input{width:100%;min-width:0;border:0!important;box-shadow:none!important;background:transparent!important;color:#0f172a!important;font-size:1rem;padding:14px 0!important}
            .alx4-search input::placeholder{color:#8996aa}
            .alx4-search button{border:0;border-radius:14px;padding:0 25px;background:linear-gradient(135deg,#2563eb,#1749b7);color:#fff;font-weight:900;cursor:pointer;box-shadow:0 12px 26px rgba(37,99,235,.24);transition:transform .2s ease,box-shadow .2s ease}
            .alx4-search button:hover{transform:translateY(-1px);box-shadow:0 16px 30px rgba(37,99,235,.30)}
            .alx4-search-hints{display:flex;flex-wrap:wrap;gap:9px;margin-top:16px}
            .alx4-search-hints a{padding:8px 11px;border:1px solid #dde6f3;border-radius:999px;background:#fff;color:#48617f;font-size:.8rem;font-weight:800}
            .alx4-search-hints a:hover{border-color:#9fc0f7;color:#1d4ed8}
            .alx4-hero__panel{padding:26px;border:1px solid rgba(255,255,255,.8);border-radius:28px;background:rgba(255,255,255,.82);backdrop-filter:blur(18px);box-shadow:0 24px 60px rgba(15,23,42,.12)}
            .alx4-panel-top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px}
            .alx4-panel-top span{font-size:.72rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
            .alx4-live{display:inline-flex;align-items:center;gap:8px;color:var(--alx4-green)!important}
            .alx4-live:before{content:"";width:8px;height:8px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 5px rgba(34,197,94,.12)}
            .alx4-primary-stat{padding:22px;border-radius:22px;background:linear-gradient(135deg,#10213b,#1d3b68);color:#fff}
            .alx4-primary-stat small{display:block;color:#b7c7dd;font-weight:800;text-transform:uppercase;letter-spacing:.08em}
            .alx4-primary-stat strong{display:block;margin-top:8px;font-size:clamp(2.8rem,5vw,4.4rem);line-height:1;letter-spacing:-.05em}
            .alx4-primary-stat em{display:block;margin-top:8px;color:#cdd8e7;font-style:normal}
            .alx4-panel-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}
            .alx4-panel-grid article{padding:18px;border:1px solid var(--alx4-border);border-radius:18px;background:#fff}
            .alx4-panel-grid small{display:block;color:#77859a;font-weight:800}
            .alx4-panel-grid strong{display:block;margin-top:6px;font-size:1.45rem;color:#132238}
            .alx4-quick-nav{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:22px 0 56px}
            .alx4-quick-nav a{position:relative;overflow:hidden;display:block;padding:22px;border:1px solid var(--alx4-border);border-radius:22px;background:#fff;color:var(--alx4-text);box-shadow:0 12px 36px rgba(15,23,42,.06);transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}
            .alx4-quick-nav a:hover{transform:translateY(-4px);border-color:#aac6f6;box-shadow:0 18px 46px rgba(37,99,235,.12)}
            .alx4-quick-nav b{display:grid;place-items:center;width:42px;height:42px;margin-bottom:16px;border-radius:14px;background:#eff6ff;color:#2563eb;font-size:1.15rem}
            .alx4-quick-nav strong{display:block;font-size:1.02rem}
            .alx4-quick-nav small{display:block;margin-top:7px;color:var(--alx4-muted);line-height:1.5}
            .alx4-section{margin-top:56px}
            .alx4-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:22px}
            .alx4-section-head span{display:block;color:#2563eb;font-size:.72rem;font-weight:900;letter-spacing:.13em;text-transform:uppercase}
            .alx4-section-head h2{margin:7px 0 0;font-size:clamp(1.8rem,3vw,2.7rem);letter-spacing:-.035em;color:#132238}
            .alx4-section-head p{max-width:680px;margin:9px 0 0;color:var(--alx4-muted);line-height:1.65}
            .alx4-section-head>a{color:#1d4ed8;font-weight:900;white-space:nowrap}
            .alx4-brand-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}
            .alx4-brand-grid a{display:flex;align-items:center;gap:13px;padding:18px;border:1px solid var(--alx4-border);border-radius:18px;background:#fff;color:#132238;box-shadow:0 10px 28px rgba(15,23,42,.045);transition:transform .2s ease,border-color .2s ease}
            .alx4-brand-grid a:hover{transform:translateY(-3px);border-color:#a9c5f5}
            .alx4-brand-mark{display:grid;place-items:center;width:42px;height:42px;flex:0 0 auto;border-radius:14px;background:linear-gradient(145deg,#edf4ff,#fff);color:#2563eb;font-weight:950;box-shadow:inset 0 0 0 1px #dbe8fb}
            .alx4-brand-grid strong{display:block;font-size:.95rem}
            .alx4-brand-grid small{display:block;margin-top:3px;color:#7b8799;font-size:.75rem}
            .alx4-metric-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1px;overflow:hidden;margin-top:56px;border:1px solid var(--alx4-border);border-radius:24px;background:var(--alx4-border);box-shadow:0 16px 46px rgba(15,23,42,.06)}
            .alx4-metric-strip article{padding:28px;background:#fff}
            .alx4-metric-strip small{display:block;color:#738197;font-weight:800}
            .alx4-metric-strip strong{display:block;margin-top:8px;font-size:2.15rem;letter-spacing:-.04em;color:#132238}
            .alx4-metric-strip em{display:block;margin-top:5px;color:#8491a4;font-style:normal;font-size:.82rem}
            .alx4-model-grid,.alx4-recent-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
            .alx4-model-card,.alx4-recent-card{position:relative;display:flex;flex-direction:column;min-height:178px;padding:22px;border:1px solid var(--alx4-border);border-radius:22px;background:#fff;color:#132238;box-shadow:0 12px 36px rgba(15,23,42,.05);transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}
            .alx4-model-card:hover,.alx4-recent-card:hover{transform:translateY(-4px);border-color:#aac6f6;box-shadow:0 18px 48px rgba(37,99,235,.11)}
            .alx4-card-kicker{display:block;color:#2563eb;font-size:.7rem;font-weight:900;letter-spacing:.1em;text-transform:uppercase}
            .alx4-model-card strong,.alx4-recent-card strong{display:block;margin-top:12px;font-size:1.12rem;line-height:1.3}
            .alx4-model-card small,.alx4-recent-card small{display:block;margin-top:8px;color:var(--alx4-muted);line-height:1.55}
            .alx4-card-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:auto;padding-top:18px;color:#2563eb;font-size:.78rem;font-weight:900}
            .alx4-feature-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}
            .alx4-feature{position:relative;overflow:hidden;padding:32px;border:1px solid var(--alx4-border);border-radius:26px;background:#fff;box-shadow:0 16px 46px rgba(15,23,42,.06)}
            .alx4-feature.is-safety{background:linear-gradient(145deg,#fff,#fff5f5);border-color:#f2d4d4}
            .alx4-feature.is-compare{background:linear-gradient(145deg,#fff,#f3f7ff)}
            .alx4-feature span{display:inline-flex;padding:7px 10px;border-radius:999px;background:#eff6ff;color:#2563eb;font-size:.72rem;font-weight:900;text-transform:uppercase;letter-spacing:.08em}
            .alx4-feature.is-safety span{background:#fef2f2;color:#dc2626}
            .alx4-feature h3{margin:18px 0 0;font-size:1.65rem;letter-spacing:-.03em;color:#132238}
            .alx4-feature p{margin:10px 0 0;color:var(--alx4-muted);line-height:1.7}
            .alx4-feature a{display:inline-flex;margin-top:22px;color:#1d4ed8;font-weight:900}
            .alx4-empty{padding:30px;border:1px dashed #cbd5e1;border-radius:20px;background:#fff;color:#6b7a90;text-align:center}
            @media(max-width:1100px){.alx4-hero{grid-template-columns:1fr;padding:48px}.alx4-brand-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.alx4-model-grid,.alx4-recent-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
            @media(max-width:780px){.alx4-home{width:min(100% - 24px,1320px);padding-top:18px}.alx4-hero{min-height:auto;padding:32px 24px;border-radius:26px}.alx4-hero h1{font-size:clamp(2.55rem,14vw,4.4rem)}.alx4-search{grid-template-columns:1fr}.alx4-search button{min-height:52px}.alx4-quick-nav{grid-template-columns:repeat(2,minmax(0,1fr))}.alx4-brand-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.alx4-metric-strip{grid-template-columns:repeat(2,minmax(0,1fr))}.alx4-feature-grid{grid-template-columns:1fr}.alx4-section-head{align-items:flex-start;flex-direction:column}.alx4-section-head>a{white-space:normal}}
            @media(max-width:520px){.alx4-hero{padding:26px 18px}.alx4-hero__panel{padding:18px}.alx4-panel-grid,.alx4-quick-nav,.alx4-brand-grid,.alx4-metric-strip,.alx4-model-grid,.alx4-recent-grid{grid-template-columns:1fr}.alx4-metric-strip{gap:1px}.alx4-section{margin-top:42px}}
            @media(prefers-reduced-motion:reduce){.alx4-home *{scroll-behavior:auto!important;animation:none!important;transition:none!important}}
        </style>

        <main class="alx4-home" id="autolex-main">
            <section class="alx4-hero" aria-labelledby="alx4-hero-title">
                <div class="alx4-hero__copy">
                    <span class="alx4-eyebrow">Magyar autós tudásbázis</span>
                    <h1 id="alx4-hero-title">Minden fontos adat az autódról, <span>egy helyen.</span></h1>
                    <p class="alx4-hero__lead">Keress márkára, modellre, generációra, motorra vagy motorkódra. Az Autolex célja, hogy gyorsan megtaláld a valóban hasznos műszaki, biztonsági és karbantartási információkat.</p>
                    <form class="alx4-search" action="<?php echo esc_url(home_url('/autok/')); ?>" method="get" role="search">
                        <label class="screen-reader-text" for="alx4-home-query"><?php echo esc_html__('Autó keresése', 'autolex-platform'); ?></label>
                        <div class="alx4-search__field">
                            <?php echo $this->icon('search'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <input id="alx4-home-query" name="q" type="search" placeholder="BMW 320d, Golf VII, N47D20, Ford Focus Mk3…" autocomplete="off">
                        </div>
                        <button type="submit">Keresés</button>
                    </form>
                    <div class="alx4-search-hints" aria-label="Gyors keresési példák">
                        <a href="<?php echo esc_url(add_query_arg('q', 'BMW 320d', home_url('/autok/'))); ?>">BMW 320d</a>
                        <a href="<?php echo esc_url(add_query_arg('q', 'Golf VII', home_url('/autok/'))); ?>">Golf VII</a>
                        <a href="<?php echo esc_url(add_query_arg('q', 'N47D20', home_url('/autok/'))); ?>">N47D20</a>
                        <a href="<?php echo esc_url(add_query_arg('q', 'Suzuki Swift', home_url('/autok/'))); ?>">Suzuki Swift</a>
                    </div>
                </div>

                <aside class="alx4-hero__panel" aria-label="Autolex adatbázis állapota">
                    <div class="alx4-panel-top"><span>Adatbázis állapot</span><span class="alx4-live">Aktív</span></div>
                    <div class="alx4-primary-stat">
                        <small>Elérhető járműváltozat</small>
                        <strong><?php echo esc_html(number_format_i18n((int) $coverage['vehicles'])); ?></strong>
                        <em>Rendszerezett katalógusrekord</em>
                    </div>
                    <div class="alx4-panel-grid">
                        <article><small>Márka</small><strong><?php echo esc_html(number_format_i18n((int) $coverage['makes'])); ?></strong></article>
                        <article><small>Motorrekord</small><strong><?php echo esc_html(number_format_i18n((int) $coverage['engine_variants'])); ?></strong></article>
                        <article><small>Forrásbizonyíték</small><strong><?php echo esc_html(number_format_i18n((int) $coverage['evidence_records'])); ?></strong></article>
                        <article><small>EEA feldolgozás</small><strong><?php echo esc_html((int) $coverage['sync_progress']); ?>%</strong></article>
                    </div>
                </aside>
            </section>

            <nav class="alx4-quick-nav" aria-label="Autolex gyorsmenü">
                <a href="<?php echo esc_url(home_url('/autok/')); ?>"><b>01</b><strong>Autókatalógus</strong><small>Böngéssz márka, modell, generáció és motor szerint.</small></a>
                <a href="<?php echo esc_url(add_query_arg('focus', 'engine', home_url('/autok/'))); ?>"><b>02</b><strong>Motorok és motorkódok</strong><small>Találd meg a pontos motorváltozatot és kapcsolódó adatokat.</small></a>
                <a href="<?php echo esc_url(home_url('/auto-osszehasonlitas/')); ?>"><b>03</b><strong>Összehasonlítás</strong><small>Hasonlíts össze legfeljebb három járművet egy nézetben.</small></a>
                <a href="<?php echo esc_url(add_query_arg('verification', 'vin_required', home_url('/autok/'))); ?>"><b>04</b><strong>Visszahívások</strong><small>Biztonsági jelzések, Safety Gate és VIN-köteles ellenőrzés.</small></a>
            </nav>

            <section class="alx4-section" aria-labelledby="alx4-brands-title">
                <div class="alx4-section-head">
                    <div><span>Népszerű márkák</span><h2 id="alx4-brands-title">Indulj a márkából</h2><p>A sorrend a jelenlegi katalógusban elérhető járműváltozatok alapján készül.</p></div>
                    <a href="<?php echo esc_url(home_url('/autok/')); ?>">Összes márka →</a>
                </div>
                <?php if ($makes) : ?>
                    <div class="alx4-brand-grid">
                        <?php foreach ($makes as $make) : ?>
                            <a href="<?php echo esc_url(add_query_arg('make', $make['value'], home_url('/autok/'))); ?>">
                                <span class="alx4-brand-mark"><?php echo esc_html($this->make_initials($make['value'])); ?></span>
                                <span><strong><?php echo esc_html($make['value']); ?></strong><small><?php echo esc_html(number_format_i18n((int) $make['total'])); ?> változat</small></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="alx4-empty">A márkalista az első sikeres katalógus-import után jelenik meg.</div>
                <?php endif; ?>
            </section>

            <section class="alx4-metric-strip" aria-label="Autolex adatlefedettség">
                <article><small>Járműváltozat</small><strong><?php echo esc_html(number_format_i18n((int) $coverage['vehicles'])); ?></strong><em>normalizált rekord</em></article>
                <article><small>Motorrekord</small><strong><?php echo esc_html(number_format_i18n((int) $coverage['engine_variants'])); ?></strong><em>külön kezelt változat</em></article>
                <article><small>Forrásbizonyíték</small><strong><?php echo esc_html(number_format_i18n((int) $coverage['evidence_records'])); ?></strong><em>mezőhöz kapcsolva</em></article>
                <article><small>Feldolgozottság</small><strong><?php echo esc_html((int) $coverage['sync_progress']); ?>%</strong><em>EEA célállomány</em></article>
            </section>

            <section class="alx4-section" aria-labelledby="alx4-models-title">
                <div class="alx4-section-head"><div><span>Gyakran keresett modellek</span><h2 id="alx4-models-title">Gyors belépés a katalógusba</h2><p>A kártyák valós adatbázis-lefedettségből készülnek, nem kézzel beégetett listából.</p></div></div>
                <?php if ($models) : ?>
                    <div class="alx4-model-grid">
                        <?php foreach ($models as $model) : ?>
                            <a class="alx4-model-card" href="<?php echo esc_url(add_query_arg('model', $model['value'], home_url('/autok/'))); ?>">
                                <span class="alx4-card-kicker">Modell</span>
                                <strong><?php echo esc_html($model['value']); ?></strong>
                                <small><?php echo esc_html(number_format_i18n((int) $model['total'])); ?> katalogizált járműváltozat</small>
                                <span class="alx4-card-footer"><span>Részletek</span><span>→</span></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="alx4-empty">Nincs még elegendő modelladat a lista összeállításához.</div>
                <?php endif; ?>
            </section>

            <section class="alx4-section" aria-labelledby="alx4-recent-title">
                <div class="alx4-section-head"><div><span>Legutóbb bővített adatlapok</span><h2 id="alx4-recent-title">Friss adatok, gyorsan elérhetően</h2><p>A legújabb ismert gyártási kezdőév szerint rendezett járműadatlapok.</p></div></div>
                <?php if ($recent_items) : ?>
                    <div class="alx4-recent-grid">
                        <?php foreach ($recent_items as $vehicle) : ?>
                            <a class="alx4-recent-card" href="<?php echo esc_url($vehicle['url']); ?>">
                                <span class="alx4-card-kicker">Adatminőség: <?php echo esc_html($vehicle['data_grade']); ?></span>
                                <strong><?php echo esc_html(trim($vehicle['make'] . ' ' . $vehicle['model'])); ?></strong>
                                <small><?php echo esc_html(trim($vehicle['generation'] . ' · ' . $vehicle['engine'])); ?><br><?php echo esc_html($vehicle['years']); ?></small>
                                <span class="alx4-card-footer"><span>Teljes adatlap</span><span>→</span></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="alx4-empty">A friss adatlapok az első sikeres katalógus-szinkron után jelennek meg.</div>
                <?php endif; ?>
            </section>

            <section class="alx4-section" aria-label="Kiemelt Autolex funkciók">
                <div class="alx4-feature-grid">
                    <article class="alx4-feature is-safety">
                        <span>Safety Gate</span>
                        <h3>Visszahívások és biztonsági jelzések</h3>
                        <p>A márka–modell találatok tájékoztató jellegűek. Az érintettség végleges ellenőrzéséhez VIN-alapú gyártói vagy hatósági megerősítés szükséges.</p>
                        <a href="<?php echo esc_url(add_query_arg('verification', 'vin_required', home_url('/autok/'))); ?>">Biztonsági adatok megnyitása →</a>
                    </article>
                    <article class="alx4-feature is-compare">
                        <span>Összehasonlítás</span>
                        <h3>Nézd meg egymás mellett</h3>
                        <p>Motor, teljesítmény, évjárat, hengerűrtartalom, adatminőség és forrásállapot legfeljebb három járműhöz.</p>
                        <a href="<?php echo esc_url(home_url('/auto-osszehasonlitas/')); ?>">Összehasonlítás indítása →</a>
                    </article>
                </div>
            </section>
        </main>
        <?php
        return (string) ob_get_clean();
    }
}
