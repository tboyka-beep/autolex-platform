<?php
/**
 * Standard page template.
 *
 * Keeps plugin-rendered page content intact while the theme owns the public shell.
 *
 * @package Autolex_Theme
 */

get_header();
?>
<div class="alx-container alx-document-layout">
    <?php while (have_posts()) : the_post(); ?>
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
    <?php endwhile; ?>
</div>
<?php
get_footer();
