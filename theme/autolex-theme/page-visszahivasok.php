<?php
/**
 * Safety Gate and recall route shell.
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
    <div class="alx-safety-page">
        <div class="alx-container alx-safety-shell">
            <header class="alx-safety-hero" aria-labelledby="alx-safety-title">
                <div>
                    <p class="alx-eyebrow"><?php esc_html_e('Autolex Safety Gate', 'autolex-theme'); ?></p>
                    <h1 id="alx-safety-title"><?php the_title(); ?></h1>
                    <p><?php esc_html_e('Ellenőrizd a visszahívási és biztonsági információkat, mielőtt döntést hozol egy járműről.', 'autolex-theme'); ?></p>
                </div>
                <div class="alx-safety-mark" aria-hidden="true">
                    <span>!</span>
                </div>
            </header>

            <section class="alx-safety-notice" aria-labelledby="alx-safety-notice-title">
                <div class="alx-safety-notice-icon" aria-hidden="true">!</div>
                <div>
                    <h2 id="alx-safety-notice-title"><?php esc_html_e('A biztonsági adat nem helyettesíti a gyártói ellenőrzést', 'autolex-theme'); ?></h2>
                    <p><?php esc_html_e('A végső megerősítéshez mindig használd a gyártó, a márkaszerviz vagy az illetékes hatóság hivatalos alvázszám-alapú rendszerét.', 'autolex-theme'); ?></p>
                </div>
            </section>

            <section class="alx-safety-workspace" aria-live="polite">
                <?php if ($content !== '') : ?>
                    <div class="alx-safety-plugin-output">
                        <?php echo apply_filters('the_content', $content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php else : ?>
                    <div class="alx-safety-empty" role="status">
                        <p class="alx-eyebrow"><?php esc_html_e('Nincs lekérhető adat', 'autolex-theme'); ?></p>
                        <h2><?php esc_html_e('A Safety Gate tartalma még nem érhető el ezen az útvonalon.', 'autolex-theme'); ?></h2>
                        <p><?php esc_html_e('Keress rá egy járműre a katalógusban, vagy próbáld újra később.', 'autolex-theme'); ?></p>
                        <a class="alx-button" href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Járműkatalógus megnyitása', 'autolex-theme'); ?></a>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
    <?php
endwhile;

get_footer();