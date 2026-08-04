<?php
/**
 * Search results template for the Autolex light theme.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
$query = get_search_query();
?>
<main id="main-content" class="alx-main">
    <section class="alx-container alx-search-page" aria-labelledby="alx-search-title">
        <header class="alx-page-heading">
            <p class="alx-eyebrow"><?php esc_html_e('Autolex keresés', 'autolex-theme'); ?></p>
            <h1 id="alx-search-title">
                <?php
                printf(
                    esc_html__('Találatok erre: „%s”', 'autolex-theme'),
                    esc_html($query)
                );
                ?>
            </h1>
            <form class="alx-state-search" role="search" action="<?php echo esc_url(home_url('/')); ?>" method="get">
                <label for="alx-search-query"><?php esc_html_e('Új keresés', 'autolex-theme'); ?></label>
                <div>
                    <input id="alx-search-query" type="search" name="s" value="<?php echo esc_attr($query); ?>" placeholder="<?php esc_attr_e('Márka, modell vagy motorkód', 'autolex-theme'); ?>">
                    <button type="submit"><?php esc_html_e('Keresés', 'autolex-theme'); ?></button>
                </div>
            </form>
        </header>

        <?php if (have_posts()) : ?>
            <div class="alx-result-grid" role="list">
                <?php while (have_posts()) : the_post(); ?>
                    <article <?php post_class('alx-result-card'); ?> role="listitem">
                        <p class="alx-result-type"><?php echo esc_html(get_post_type_object(get_post_type())->labels->singular_name ?? __('Tartalom', 'autolex-theme')); ?></p>
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 24)); ?></p>
                        <a class="alx-text-link" href="<?php the_permalink(); ?>"><?php esc_html_e('Megnyitás', 'autolex-theme'); ?> →</a>
                    </article>
                <?php endwhile; ?>
            </div>
            <nav class="alx-pagination" aria-label="<?php esc_attr_e('Találati oldalak', 'autolex-theme'); ?>">
                <?php the_posts_pagination(array('mid_size' => 1, 'prev_text' => __('Előző', 'autolex-theme'), 'next_text' => __('Következő', 'autolex-theme'))); ?>
            </nav>
        <?php else : ?>
            <div class="alx-state-card alx-search-empty">
                <p class="alx-eyebrow"><?php esc_html_e('Nincs találat', 'autolex-theme'); ?></p>
                <h2><?php esc_html_e('Próbálj rövidebb vagy másik keresőkifejezést.', 'autolex-theme'); ?></h2>
                <p><?php esc_html_e('Kereshetsz márkára, modellre, generációra vagy motorkódra is.', 'autolex-theme'); ?></p>
                <a class="alx-button" href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Autókatalógus megnyitása', 'autolex-theme'); ?></a>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php
get_footer();
