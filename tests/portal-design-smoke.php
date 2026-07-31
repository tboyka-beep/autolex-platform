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
    '.alx3-hero',
    '.alx3-pipeline',
    '.alx3-capability-grid',
    '.alx3-source-grid',
    '.alx3-method',
    '.alx3-final-cta',
    '.alx3-catalog-hero',
    '.alx3-catalog-layout',
    '.alx3-results-toolbar',
    '.alx3-vehicle-grid',
    '.alx3-vehicle-card',
    '.alx3-filters.is-open',
    '.is-adapter_ready',
    '.is-live_validated',
    '@media(max-width:900px)',
    '@media(prefers-reduced-motion:reduce)',
);
foreach ($required_css as $selector) {
    if (false === strpos($css, $selector)) {
        fwrite(STDERR, "Portal design selector is missing: {$selector}\n");
        exit(1);
    }
}

if (false === strpos($css, '--alx3-accent:#c5482d')) {
    fwrite(STDERR, "The approved Autolex accent token is missing.\n");
    exit(1);
}
if (false === strpos($css, 'grid-template-columns:repeat(3,minmax(0,1fr))')) {
    fwrite(STDERR, "Desktop information-card layout is missing.\n");
    exit(1);
}
if (false === strpos($home, 'alx3-source-grid') || false === strpos($home, 'alx3-method') || false === strpos($home, 'alx3-final-cta')) {
    fwrite(STDERR, "Homepage premium sections are not rendered.\n");
    exit(1);
}
if (false === strpos($catalog, 'alx3-vehicle-grid') || false === strpos($catalog, 'alx3-results-toolbar')) {
    fwrite(STDERR, "Catalogue premium layout is not rendered.\n");
    exit(1);
}
if (false === strpos($catalog, 'aria-expanded="false"') || false === strpos($catalog, 'aria-controls="alx3-filter-panel"')) {
    fwrite(STDERR, "Mobile filter accessibility contract is incomplete.\n");
    exit(1);
}

echo "Autolex Portal design smoke tests passed.\n";
