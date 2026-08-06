<?php
/**
 * Single article template.
 *
 * @package Autolex_Theme
 */

get_header();
?>
<div class="alx-container alx-document-layout">
    <?php while (have_posts()) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('alx-document alx-article'); ?>>
            <header class="alx-document-header">
                <p class="alx-eyebrow"><?php esc_html_e('Tudástár', 'autolex-theme'); ?></p>
                <h1><?php the_title(); ?></h1>
                <div class="alx-article-meta">
                    <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date()); ?></time>
                    <?php if (get_the_author()) : ?>
                        <span aria-hidden="true">·</span>
                        <span><?php echo esc_html(get_the_author()); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (has_excerpt()) : ?>
                    <div class="alx-document-lead"><?php the_excerpt(); ?></div>
                <?php endif; ?>
            </header>
            <?php if (has_post_thumbnail()) : ?>
                <figure class="alx-article-cover">
                    <?php the_post_thumbnail('large', array('loading' => 'eager', 'fetchpriority' => 'high')); ?>
                </figure>
            <?php endif; ?>
            <div class="alx-document-content entry-content">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; ?>
</div>
<?php
get_footer();
