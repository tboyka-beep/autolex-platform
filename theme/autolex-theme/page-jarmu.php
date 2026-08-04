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
    ?>
    <main id="main-content" class="alx-main alx-vehicle-page">
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

            <nav class="alx-vehicle-section-nav" aria-label="<?php esc_attr_e('Adatlap szakaszai', 'autolex-theme'); ?>">
                <a href="#attekintes"><?php esc_html_e('Áttekintés', 'autolex-theme'); ?></a>
                <a href="#muszaki-adatok"><?php esc_html_e('Műszaki adatok', 'autolex-theme'); ?></a>
                <a href="#biztonsag"><?php esc_html_e('Biztonság', 'autolex-theme'); ?></a>
                <a href="#forrasok"><?php esc_html_e('Források', 'autolex-theme'); ?></a>
            </nav>

            <div id="attekintes" class="alx-vehicle-workspace" aria-live="polite">
                <?php if ($content !== '') : ?>
                    <div class="alx-vehicle-plugin-output">
                        <?php the_content(); ?>
                    </div>
                <?php else : ?>
                    <section class="alx-vehicle-empty" aria-labelledby="alx-vehicle-empty-title">
                        <h2 id="alx-vehicle-empty-title"><?php esc_html_e('A jármű adatai még nem érhetők el', 'autolex-theme'); ?></h2>
                        <p><?php esc_html_e('Ehhez az adatlaphoz még nincs ellenőrzött műszaki vagy forrásadat. A rendszer nem jelenít meg becsült vagy kitalált értékeket.', 'autolex-theme'); ?></p>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="alx-vehicle-safety-note" id="biztonsag" aria-labelledby="alx-vehicle-safety-title">
                <h2 id="alx-vehicle-safety-title"><?php esc_html_e('Biztonsági adatok ellenőrzése', 'autolex-theme'); ?></h2>
                <p><?php esc_html_e('Visszahívás, folyadék, kerékméret vagy más biztonságkritikus adat esetén mindig ellenőrizd a gyártói dokumentációt, az alvázszámot és a hivatalos szervizinformációt.', 'autolex-theme'); ?></p>
                <a href="<?php echo esc_url(home_url('/visszahivasok/')); ?>"><?php esc_html_e('Visszahívások megnyitása', 'autolex-theme'); ?></a>
            </aside>

            <section class="alx-vehicle-source-note" id="forrasok" aria-labelledby="alx-vehicle-source-title">
                <h2 id="alx-vehicle-source-title"><?php esc_html_e('Források és megerősítés', 'autolex-theme'); ?></h2>
                <p><?php esc_html_e('Az Autolex csak a plugin által átadott, forráshoz kapcsolható adatokat jeleníti meg. Hiányos vagy konfliktusos adat esetén ezt külön állapotként kell jelezni.', 'autolex-theme'); ?></p>
            </section>
        </article>
    </main>
    <?php
endwhile;

get_footer();
