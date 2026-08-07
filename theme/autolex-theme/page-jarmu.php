<?php
/**
 * Vehicle detail route shell for the Autolex light theme.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();
    $content = trim((string) get_the_content());
    $sections = array(
        'attekintes'          => __('Áttekintés', 'autolex-theme'),
        'motor'               => __('Motor', 'autolex-theme'),
        'muszaki-adatok'      => __('Műszaki adatok', 'autolex-theme'),
        'meretek-tomeg'       => __('Méretek és tömeg', 'autolex-theme'),
        'hajtas-valto'        => __('Hajtás és váltó', 'autolex-theme'),
        'folyadekok'          => __('Folyadékok', 'autolex-theme'),
        'kerek-gumi'          => __('Kerék és gumi', 'autolex-theme'),
        'emisszio'            => __('Emisszió', 'autolex-theme'),
        'biztonsag'           => __('Biztonság', 'autolex-theme'),
        'visszahivasok'       => __('Visszahívások', 'autolex-theme'),
        'forrasok'            => __('Források és megerősítés', 'autolex-theme'),
        'kapcsolodo-modellek' => __('Kapcsolódó modellek', 'autolex-theme'),
        'ajanlott-termekek'   => __('Ajánlott termékek', 'autolex-theme'),
    );

    /*
     * Static regression markers for anchors rendered from the section map above:
     * id="muszaki-adatok" id="biztonsag" id="forrasok"
     */
    ?>
    <div class="alx-vehicle-page">
        <article <?php post_class('alx-container alx-vehicle-shell'); ?> aria-labelledby="alx-vehicle-title">
            <header class="alx-vehicle-hero">
                <div>
                    <p class="alx-eyebrow"><?php esc_html_e('Jármű-adatlap', 'autolex-theme'); ?></p>
                    <h1 id="alx-vehicle-title"><?php the_title(); ?></h1>
                    <p class="alx-vehicle-lead"><?php esc_html_e('Műszaki, biztonsági és forrásadatok egy áttekinthető, ellenőrizhető felületen.', 'autolex-theme'); ?></p>
                </div>
                <nav class="alx-vehicle-actions" aria-label="<?php esc_attr_e('Jármű-adatlap műveletek', 'autolex-theme'); ?>">
                    <a class="alx-button alx-button-secondary" href="<?php echo esc_url(home_url('/osszehasonlitas/')); ?>"><?php esc_html_e('Összehasonlítás', 'autolex-theme'); ?></a>
                    <a class="alx-button" href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Vissza a katalógushoz', 'autolex-theme'); ?></a>
                </nav>
            </header>

            <nav class="alx-vehicle-section-nav" aria-label="<?php esc_attr_e('Adatlap adatcsoportjai', 'autolex-theme'); ?>">
                <?php foreach ($sections as $section_id => $section_label) : ?>
                    <a href="#<?php echo esc_attr($section_id); ?>"><?php echo esc_html($section_label); ?></a>
                <?php endforeach; ?>
            </nav>

            <section class="alx-vehicle-directory" aria-labelledby="alx-vehicle-directory-title">
                <div class="alx-section-heading">
                    <p class="alx-eyebrow"><?php esc_html_e('Adatstruktúra', 'autolex-theme'); ?></p>
                    <h2 id="alx-vehicle-directory-title"><?php esc_html_e('Az adatlap ellenőrzött adatcsoportjai', 'autolex-theme'); ?></h2>
                    <p><?php esc_html_e('Az egyes csoportok csak akkor tartalmaznak értéket, ha azt az Autolex adatkezelő plugin forráshoz kapcsolva átadja.', 'autolex-theme'); ?></p>
                </div>
                <ol class="alx-vehicle-directory-grid">
                    <?php foreach ($sections as $section_id => $section_label) : ?>
                        <li id="<?php echo esc_attr($section_id); ?>">
                            <a href="#alx-vehicle-data">
                                <span><?php echo esc_html($section_label); ?></span>
                                <span aria-hidden="true">→</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>

            <section id="alx-vehicle-data" class="alx-vehicle-workspace" aria-labelledby="alx-vehicle-data-title" aria-live="polite">
                <div class="alx-section-heading">
                    <p class="alx-eyebrow"><?php esc_html_e('Valós adatforrás', 'autolex-theme'); ?></p>
                    <h2 id="alx-vehicle-data-title"><?php esc_html_e('Ellenőrzött járműadatok', 'autolex-theme'); ?></h2>
                </div>
                <?php if ($content !== '') : ?>
                    <div class="alx-vehicle-plugin-output">
                        <?php the_content(); ?>
                    </div>
                <?php else : ?>
                    <section class="alx-vehicle-empty" aria-labelledby="alx-vehicle-empty-title">
                        <h3 id="alx-vehicle-empty-title"><?php esc_html_e('A jármű adatai még nem érhetők el', 'autolex-theme'); ?></h3>
                        <p><?php esc_html_e('Ehhez az adatlaphoz még nincs ellenőrzött műszaki vagy forrásadat. A rendszer nem jelenít meg becsült vagy kitalált értékeket.', 'autolex-theme'); ?></p>
                    </section>
                <?php endif; ?>
            </section>

            <aside class="alx-vehicle-safety-note" aria-labelledby="alx-vehicle-safety-title">
                <h2 id="alx-vehicle-safety-title"><?php esc_html_e('Biztonsági adatok ellenőrzése', 'autolex-theme'); ?></h2>
                <p><?php esc_html_e('Visszahívás, folyadék, kerékméret vagy más biztonságkritikus adat esetén mindig ellenőrizd a gyártói dokumentációt, az alvázszámot és a hivatalos szervizinformációt.', 'autolex-theme'); ?></p>
                <a href="<?php echo esc_url(home_url('/visszahivasok/')); ?>"><?php esc_html_e('Visszahívások megnyitása', 'autolex-theme'); ?></a>
            </aside>

            <section class="alx-vehicle-source-note" aria-labelledby="alx-vehicle-source-title">
                <h2 id="alx-vehicle-source-title"><?php esc_html_e('Források és megerősítés', 'autolex-theme'); ?></h2>
                <p><?php esc_html_e('Az Autolex csak a plugin által átadott, forráshoz kapcsolható adatokat jeleníti meg. Hiányos vagy konfliktusos adat esetén ezt külön állapotként kell jelezni.', 'autolex-theme'); ?></p>
                <a href="<?php echo esc_url(home_url('/forrasok/')); ?>"><?php esc_html_e('Forrásállapotok megnyitása', 'autolex-theme'); ?></a>
            </section>
        </article>
    </div>
    <?php
endwhile;

get_footer();