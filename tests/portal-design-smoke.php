<?php

$root = dirname(__DIR__);
$css_path = $root . '/plugin/autolex-platform/assets/css/autolex-portal-3.css';
$home_path = $root . '/plugin/autolex-platform/includes/trait-autolex-portal-home.php';
$catalog_path = $root . '/plugin/autolex-platform/includes/trait-autolex-portal-catalog.php';

foreach (array($css_path, $home_path, $catalog_path) as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Required portal design file is missing: {$path}\n");
        exit(1);
    }
}

$css = (string) file_get_contents($css_path);
$home = (string) file_get_contents($home_path);
$catalog = (string) file_get_contents($catalog_path);

$required_css = array(
    '.alx3-catalog-hero',
    '.alx3-catalog-layout',
    '.alx3-results-toolbar',
    '.alx3-vehicle-grid',
    '.alx3-vehicle-card',
    '.alx3-filters.is-open',
    '@media(max-width:900px)',
    '@media(prefers-reduced-motion:reduce)',
);
foreach ($required_css as $selector) {
    if (false === strpos($css, $selector)) {
        fwrite(STDERR, "Portal design selector is missing: {$selector}\n");
        exit(1);
    }
}

$required_home_sections = array(
    'alx4-home',
    'alx4-hero',
    'alx4-search',
    'alx4-hero__panel',
    'alx4-quick-nav',
    'alx4-brand-grid',
    'alx4-metric-strip',
    'alx4-model-grid',
    'alx4-recent-grid',
    'alx4-feature-grid',
    'body.autolex-portal-3{background:#f4f7fb!important',
    '@media(prefers-reduced-motion:reduce)',
);
foreach ($required_home_sections as $section) {
    if (false === strpos($home, $section)) {
        fwrite(STDERR, "Autolex 4.0 homepage contract is missing: {$section}\n");
        exit(1);
    }
}

if (false === strpos($home, 'Minden fontos adat az autódról')) {
    fwrite(STDERR, "Autolex 4.0 value proposition is missing.\n");
    exit(1);
}
if (false === strpos($home, 'name="q" type="search"')) {
    fwrite(STDERR, "Server-side search fallback is missing.\n");
    exit(1);
}
if (false === strpos($home, "number_format_i18n((int) \$coverage['vehicles'])")) {
    fwrite(STDERR, "Homepage database metrics must remain data-backed.\n");
    exit(1);
}
if (false === strpos($catalog, 'alx3-vehicle-grid') || false === strpos($catalog, 'alx3-results-toolbar')) {
    fwrite(STDERR, "Catalogue layout is not rendered.\n");
    exit(1);
}
if (false === strpos($catalog, 'aria-expanded="false"') || false === strpos($catalog, 'aria-controls="alx3-filter-panel"')) {
    fwrite(STDERR, "Mobile filter accessibility contract is incomplete.\n");
    exit(1);
}

echo "Autolex 4.0 portal design smoke tests passed.\n";
