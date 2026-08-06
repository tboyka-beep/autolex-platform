<?php
/**
 * Fallback template.
 *
 * @package Autolex_Theme
 */

get_header();
?>
<div class="alx-container">
    <div class="alx-content-shell">
        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <?php if (!is_front_page()) : ?>
                        <h1><?php the_title(); ?></h1>
                    <?php endif; ?>
                    <div class="entry-content"><?php the_content(); ?></div>
                </article>
            <?php endwhile; ?>
        <?php else : ?>
            <section aria-labelledby="alx-empty-title">
                <h1 id="alx-empty-title"><?php esc_html_e('Nincs megjeleníthető tartalom', 'autolex-theme'); ?></h1>
                <p><?php esc_html_e('Próbálj másik keresést, vagy nyisd meg a járműkatalógust.', 'autolex-theme'); ?></p>
                <a href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Katalógus megnyitása', 'autolex-theme'); ?></a>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php
get_footer();
