<?php
/**
 * Archive template for knowledge and editorial content.
 *
 * @package Autolex_Theme
 */

get_header();
?>
<div class="alx-container alx-archive-layout">
    <header class="alx-archive-header">
        <p class="alx-eyebrow"><?php esc_html_e('Autolex Tudástár', 'autolex-theme'); ?></p>
        <h1><?php the_archive_title(); ?></h1>
        <?php the_archive_description('<div class="alx-document-lead">', '</div>'); ?>
    </header>

    <?php if (have_posts()) : ?>
        <div class="alx-archive-grid">
            <?php while (have_posts()) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('alx-archive-card'); ?>>
                    <?php if (has_post_thumbnail()) : ?>
                        <a class="alx-archive-card-media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
                            <?php the_post_thumbnail('medium_large', array('loading' => 'lazy')); ?>
                        </a>
                    <?php endif; ?>
                    <div class="alx-archive-card-body">
                        <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date()); ?></time>
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <div class="alx-archive-card-excerpt"><?php the_excerpt(); ?></div>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
        <nav class="alx-pagination" aria-label="<?php esc_attr_e('Tudástár lapozás', 'autolex-theme'); ?>">
            <?php the_posts_pagination(array('mid_size' => 1, 'prev_text' => __('Előző', 'autolex-theme'), 'next_text' => __('Következő', 'autolex-theme'))); ?>
        </nav>
    <?php else : ?>
        <section class="alx-empty-state" aria-labelledby="alx-archive-empty-title">
            <h2 id="alx-archive-empty-title"><?php esc_html_e('Még nincs megjeleníthető bejegyzés', 'autolex-theme'); ?></h2>
            <p><?php esc_html_e('A járműkatalógus addig is elérhető.', 'autolex-theme'); ?></p>
            <a href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Katalógus megnyitása', 'autolex-theme'); ?></a>
        </section>
    <?php endif; ?>
</div>
<?php
get_footer();
