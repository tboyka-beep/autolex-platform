<?php
/**
 * Shared brand, model, generation and engine hierarchy shell.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$alx_hierarchy_context = isset($args['context']) ? sanitize_key($args['context']) : 'brand';
$alx_hierarchy_labels  = array(
    'brand' => array(
        'eyebrow' => __('Járműkatalógus', 'autolex-theme'),
        'lead'    => __('Válassz márkát a valós katalógusadatokból. Az Autolex nem jelenít meg kézzel beírt darabszámot vagy nem igazolt lefedettséget.', 'autolex-theme'),
        'empty'   => __('Jelenleg nincs megjeleníthető márkaadat.', 'autolex-theme'),
    ),
    'model' => array(
        'eyebrow' => __('Márka és modellek', 'autolex-theme'),
        'lead'    => __('A kiválasztott márkához tartozó modellek, kizárólag a plugin által átadott katalógusadatokból.', 'autolex-theme'),
        'empty'   => __('Ehhez a márkához jelenleg nincs megjeleníthető modelladat.', 'autolex-theme'),
    ),
    'generation' => array(
        'eyebrow' => __('Modell és generációk', 'autolex-theme'),
        'lead'    => __('Generációk és gyártási időszakok ellenőrizhető katalógusforrásból, becsült értékek nélkül.', 'autolex-theme'),
        'empty'   => __('Ehhez a modellhez jelenleg nincs megjeleníthető generációadat.', 'autolex-theme'),
    ),
    'engine' => array(
        'eyebrow' => __('Generáció és motorok', 'autolex-theme'),
        'lead'    => __('Motorváltozatok és motorkódok a plugin adat- és proveniencia-logikájának változtatása nélkül.', 'autolex-theme'),
        'empty'   => __('Ehhez a generációhoz jelenleg nincs megjeleníthető motoradat.', 'autolex-theme'),
    ),
);
$alx_hierarchy_label = isset($alx_hierarchy_labels[$alx_hierarchy_context])
    ? $alx_hierarchy_labels[$alx_hierarchy_context]
    : $alx_hierarchy_labels['brand'];
$content = trim((string) get_the_content());
?>
<main id="main-content" class="alx-main alx-hierarchy-page alx-hierarchy-<?php echo esc_attr($alx_hierarchy_context); ?>">
    <article <?php post_class('alx-container alx-hierarchy-shell'); ?> aria-labelledby="alx-hierarchy-title">
        <nav class="alx-breadcrumbs" aria-label="<?php esc_attr_e('Morzsamenü', 'autolex-theme'); ?>">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Főoldal', 'autolex-theme'); ?></a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Katalógus', 'autolex-theme'); ?></a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?php the_title(); ?></span>
        </nav>

        <header class="alx-hierarchy-hero">
            <div>
                <p class="alx-eyebrow"><?php echo esc_html($alx_hierarchy_label['eyebrow']); ?></p>
                <h1 id="alx-hierarchy-title"><?php the_title(); ?></h1>
                <p class="alx-hierarchy-lead"><?php echo esc_html($alx_hierarchy_label['lead']); ?></p>
            </div>
            <a class="alx-button alx-button-secondary" href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Teljes katalógus', 'autolex-theme'); ?></a>
        </header>

        <div class="alx-hierarchy-workspace" aria-live="polite" data-hierarchy-level="<?php echo esc_attr($alx_hierarchy_context); ?>">
            <?php if ($content !== '') : ?>
                <div class="alx-hierarchy-plugin-output">
                    <?php the_content(); ?>
                </div>
            <?php else : ?>
                <section class="alx-hierarchy-empty" aria-labelledby="alx-hierarchy-empty-title">
                    <h2 id="alx-hierarchy-empty-title"><?php echo esc_html($alx_hierarchy_label['empty']); ?></h2>
                    <p><?php esc_html_e('Az oldal nem helyettesíti hiányzó adatokkal a plugin válaszát. Próbáld meg a katalóguskeresőt, vagy térj vissza később.', 'autolex-theme'); ?></p>
                    <a class="alx-button" href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Katalógus megnyitása', 'autolex-theme'); ?></a>
                </section>
            <?php endif; ?>
        </div>
    </article>
</main>
