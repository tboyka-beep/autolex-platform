<?php
/**
 * Sources and confirmation route for the Autolex light theme.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();
    $source_content = trim((string) get_the_content());
    ?>
    <div class="alx-sources-route">
        <section class="alx-container alx-sources-page" aria-labelledby="alx-sources-title">
            <header class="alx-sources-header">
                <p class="alx-eyebrow"><?php esc_html_e('Adatforrás és megerősítés', 'autolex-theme'); ?></p>
                <h1 id="alx-sources-title"><?php the_title(); ?></h1>
                <p class="alx-sources-lead">
                    <?php esc_html_e('Az Autolex minden műszaki, biztonsági és folyadékadatnál elkülöníti a forrást, a megerősítés állapotát és az esetleges eltéréseket.', 'autolex-theme'); ?>
                </p>
            </header>

            <aside class="alx-sources-principles" aria-labelledby="alx-sources-principles-title">
                <h2 id="alx-sources-principles-title"><?php esc_html_e('Megerősítési alapelvek', 'autolex-theme'); ?></h2>
                <ul>
                    <li><?php esc_html_e('Elsődleges a gyártói, hatósági vagy homologizációs dokumentum.', 'autolex-theme'); ?></li>
                    <li><?php esc_html_e('Eltérő adatoknál a konfliktust láthatóan jelezzük, nem választunk önkényesen értéket.', 'autolex-theme'); ?></li>
                    <li><?php esc_html_e('Biztonsági és visszahívási információt mindig ellenőrizni kell a gyártó vagy az illetékes hatóság felületén.', 'autolex-theme'); ?></li>
                </ul>
            </aside>

            <div class="alx-sources-workspace" aria-live="polite" aria-busy="false">
                <?php if ($source_content !== '') : ?>
                    <div class="alx-sources-plugin-output">
                        <?php echo apply_filters('the_content', $source_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php else : ?>
                    <section class="alx-sources-empty" aria-labelledby="alx-sources-empty-title">
                        <h2 id="alx-sources-empty-title"><?php esc_html_e('A forrásjegyzék előkészítés alatt áll', 'autolex-theme'); ?></h2>
                        <p><?php esc_html_e('Ezen az oldalon kizárólag ellenőrizhető, hivatkozható források jelenhetnek meg. Kitalált vagy megerősítetlen forráslistát nem mutatunk.', 'autolex-theme'); ?></p>
                        <a class="alx-button" href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Járműkatalógus megnyitása', 'autolex-theme'); ?></a>
                    </section>
                <?php endif; ?>
            </div>

            <section class="alx-sources-status" aria-labelledby="alx-sources-status-title">
                <h2 id="alx-sources-status-title"><?php esc_html_e('Megerősítési állapotok', 'autolex-theme'); ?></h2>
                <div class="alx-sources-status-grid">
                    <article>
                        <strong><?php esc_html_e('Megerősített', 'autolex-theme'); ?></strong>
                        <p><?php esc_html_e('Elsődleges vagy több, egymástól független hiteles forrás egyezik.', 'autolex-theme'); ?></p>
                    </article>
                    <article>
                        <strong><?php esc_html_e('Részleges', 'autolex-theme'); ?></strong>
                        <p><?php esc_html_e('Az adat használható, de egyes változatok vagy piacok között eltérés lehet.', 'autolex-theme'); ?></p>
                    </article>
                    <article>
                        <strong><?php esc_html_e('Konfliktusos', 'autolex-theme'); ?></strong>
                        <p><?php esc_html_e('A hiteles források eltérnek; az érték döntés előtt külön ellenőrzést igényel.', 'autolex-theme'); ?></p>
                    </article>
                </div>
            </section>
        </section>
    </div>
    <?php
endwhile;

get_footer();