<?php
/**
 * Comparison page shell.
 *
 * Keeps comparison data and interaction inside the Autolex plugin while the
 * theme owns the accessible light-first public layout.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<div class="alx-comparison-page" tabindex="-1">
    <div class="alx-shell alx-comparison-shell">
        <header class="alx-comparison-hero" aria-labelledby="alx-comparison-title">
            <p class="alx-eyebrow"><?php esc_html_e('Autolex összehasonlító', 'autolex-theme'); ?></p>
            <h1 id="alx-comparison-title"><?php esc_html_e('Járművek összehasonlítása', 'autolex-theme'); ?></h1>
            <p><?php esc_html_e('Válassz legfeljebb három járművet, és vesd össze az igazolt műszaki, méret-, hajtás-, fogyasztási és biztonsági adatokat.', 'autolex-theme'); ?></p>
        </header>

        <section class="alx-comparison-workspace" aria-labelledby="alx-comparison-workspace-title">
            <h2 id="alx-comparison-workspace-title" class="screen-reader-text">
                <?php esc_html_e('Összehasonlítási munkaterület', 'autolex-theme'); ?>
            </h2>

            <div class="alx-comparison-status" role="status" aria-live="polite" aria-atomic="true">
                <?php esc_html_e('Az összehasonlítás adatai a kiválasztott járművek alapján töltődnek be.', 'autolex-theme'); ?>
            </div>

            <div class="alx-comparison-plugin-output">
                <?php
                if (have_posts()) {
                    while (have_posts()) {
                        the_post();
                        $comparison_content = trim((string) get_the_content());

                        if ('' !== $comparison_content) {
                            the_content();
                        } else {
                            ?>
                            <div class="alx-state-card alx-comparison-empty" role="note">
                                <h2><?php esc_html_e('Még nincs kiválasztott jármű', 'autolex-theme'); ?></h2>
                                <p><?php esc_html_e('A katalógusból adj járműveket az összehasonlításhoz. Az Autolex csak rendelkezésre álló és forrással jelölt adatokat mutat.', 'autolex-theme'); ?></p>
                                <a class="alx-button alx-button-primary" href="<?php echo esc_url(home_url('/autok/')); ?>">
                                    <?php esc_html_e('Jármű választása', 'autolex-theme'); ?>
                                </a>
                            </div>
                            <?php
                        }
                    }
                } else {
                    ?>
                    <div class="alx-state-card alx-comparison-error" role="alert">
                        <h2><?php esc_html_e('Az összehasonlítás most nem érhető el', 'autolex-theme'); ?></h2>
                        <p><?php esc_html_e('Nem sikerült betölteni az oldal alapadatait. Próbáld újra, vagy térj vissza a katalógushoz.', 'autolex-theme'); ?></p>
                        <a class="alx-button" href="<?php echo esc_url(home_url('/autok/')); ?>">
                            <?php esc_html_e('Vissza a katalógushoz', 'autolex-theme'); ?>
                        </a>
                    </div>
                    <?php
                }
                ?>
            </div>
        </section>
    </div>
</div>
<?php
get_footer();