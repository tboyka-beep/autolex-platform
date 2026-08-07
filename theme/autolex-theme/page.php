<?php
/**
 * Standard page template.
 *
 * Keeps plugin-rendered page content intact while the theme owns the public shell.
 * Adds a dedicated, data-safe knowledge base shell for the Tudástár route.
 *
 * @package Autolex_Theme
 */

get_header();
?>
<div class="alx-container alx-document-layout">
    <?php while (have_posts()) : the_post(); ?>
        <?php if (is_page('tudastar')) : ?>
            <?php $knowledge_content = trim((string) get_the_content()); ?>
            <div class="alx-knowledge-page" aria-labelledby="alx-knowledge-title">
                <header class="alx-document-header alx-knowledge-header">
                    <p class="alx-eyebrow"><?php esc_html_e('Autós tudástár', 'autolex-theme'); ?></p>
                    <h1 id="alx-knowledge-title"><?php the_title(); ?></h1>
                    <p class="alx-document-lead">
                        <?php esc_html_e('Közérthető útmutatók műszaki adatokhoz, karbantartáshoz, biztonsághoz és a járműadatok helyes értelmezéséhez.', 'autolex-theme'); ?>
                    </p>
                </header>

                <nav class="alx-knowledge-topics" aria-label="<?php esc_attr_e('Tudástár témakörei', 'autolex-theme'); ?>">
                    <a href="#muszaki-alapok"><?php esc_html_e('Műszaki alapok', 'autolex-theme'); ?></a>
                    <a href="#karbantartas"><?php esc_html_e('Karbantartás', 'autolex-theme'); ?></a>
                    <a href="#biztonsag"><?php esc_html_e('Biztonság', 'autolex-theme'); ?></a>
                    <a href="#adatertelmezes"><?php esc_html_e('Adatértelmezés', 'autolex-theme'); ?></a>
                </nav>

                <div class="alx-knowledge-workspace" aria-live="polite" aria-busy="false">
                    <?php if ($knowledge_content !== '') : ?>
                        <div class="alx-document-content alx-knowledge-plugin-output entry-content">
                            <?php echo apply_filters('the_content', $knowledge_content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    <?php else : ?>
                        <section class="alx-knowledge-empty" aria-labelledby="alx-knowledge-empty-title">
                            <h2 id="alx-knowledge-empty-title"><?php esc_html_e('A tudástár tartalma előkészítés alatt áll', 'autolex-theme'); ?></h2>
                            <p><?php esc_html_e('Csak ellenőrzött, forrással alátámasztott útmutatókat jelenítünk meg. Amíg egy témakör nincs kész, nem töltjük fel bizonytalan állításokkal.', 'autolex-theme'); ?></p>
                            <a class="alx-button" href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Járműkatalógus megnyitása', 'autolex-theme'); ?></a>
                        </section>
                    <?php endif; ?>
                </div>

                <section class="alx-knowledge-directory" aria-labelledby="alx-knowledge-directory-title">
                    <h2 id="alx-knowledge-directory-title"><?php esc_html_e('Témakörök', 'autolex-theme'); ?></h2>
                    <div class="alx-knowledge-grid">
                        <article id="muszaki-alapok">
                            <h3><?php esc_html_e('Műszaki alapok', 'autolex-theme'); ?></h3>
                            <p><?php esc_html_e('Motorok, hajtásláncok, méretek és teljesítményadatok érthetően.', 'autolex-theme'); ?></p>
                        </article>
                        <article id="karbantartas">
                            <h3><?php esc_html_e('Karbantartás', 'autolex-theme'); ?></h3>
                            <p><?php esc_html_e('Folyadékok, kerekek, gumik és időszakos ellenőrzések forrásalapon.', 'autolex-theme'); ?></p>
                        </article>
                        <article id="biztonsag">
                            <h3><?php esc_html_e('Biztonság', 'autolex-theme'); ?></h3>
                            <p><?php esc_html_e('Visszahívások, Safety Gate-jelzések és kötelező megerősítési lépések.', 'autolex-theme'); ?></p>
                        </article>
                        <article id="adatertelmezes">
                            <h3><?php esc_html_e('Adatértelmezés', 'autolex-theme'); ?></h3>
                            <p><?php esc_html_e('Megerősített, részleges és konfliktusos járműadatok helyes olvasása.', 'autolex-theme'); ?></p>
                        </article>
                    </div>
                </section>
            </div>
        <?php else : ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('alx-document'); ?>>
                <header class="alx-document-header">
                    <p class="alx-eyebrow"><?php esc_html_e('Autolex', 'autolex-theme'); ?></p>
                    <h1><?php the_title(); ?></h1>
                    <?php if (has_excerpt()) : ?>
                        <div class="alx-document-lead"><?php the_excerpt(); ?></div>
                    <?php endif; ?>
                </header>
                <div class="alx-document-content entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
        <?php endif; ?>
    <?php endwhile; ?>
</div>
<?php
get_footer();