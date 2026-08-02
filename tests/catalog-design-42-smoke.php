<?php

$root = dirname(__DIR__);
$css = file_get_contents($root . '/plugin/autolex-platform/assets/css/autolex-catalog-42.css');
$entry = file_get_contents($root . '/plugin/autolex-platform/assets/css/autolex-home-41.css');
$markup = file_get_contents($root . '/plugin/autolex-platform/includes/trait-autolex-portal-catalog.php');

foreach (array(
    '.alx3-catalog-hero',
    '.alx3-catalog-layout',
    '.alx3-filters',
    'position: sticky',
    '.alx3-results-toolbar',
    '.alx3-vehicle-grid',
    '.alx3-vehicle-card',
    '.alx3-card-badges',
    '.alx3-specs',
    '.alx3-empty',
    '[data-filter-close]',
    '.alx3-filter-toggle',
    '@media (max-width: 860px)',
    '@media (max-width: 560px)',
    '@media (prefers-reduced-motion: reduce)',
) as $needle) {
    if (false === strpos($css, $needle)) {
        fwrite(STDERR, "Autolex 4.2 catalogue design contract is missing: {$needle}.\n");
        exit(1);
    }
}

foreach (array(
    'var(--alx42-graphite)',
    'var(--alx42-blue)',
    'var(--alx42-safety)',
    'var(--alx42-focus)',
    ':focus-within',
    '.is-conflict',
    '.is-vin_required',
) as $needle) {
    if (false === strpos($css, $needle)) {
        fwrite(STDERR, "Catalogue accessibility or source-state style is missing: {$needle}.\n");
        exit(1);
    }
}

if (false === strpos($entry, '@import url("autolex-catalog-42.css");')) {
    fwrite(STDERR, "The 4.2 catalogue stylesheet is not loaded by the public entrypoint.\n");
    exit(1);
}

foreach (array(
    'class="alx3-catalog"',
    'class="alx3-catalog-hero"',
    'id="alx3-filter-panel"',
    'class="alx3-results-toolbar"',
    'class="alx3-vehicle-grid"',
    'class="alx3-vehicle-card"',
    'class="alx3-card-badges"',
    'class="alx3-specs"',
    'class="alx3-empty"',
) as $needle) {
    if (false === strpos($markup, $needle)) {
        fwrite(STDERR, "Existing server-rendered catalogue markup contract is missing: {$needle}.\n");
        exit(1);
    }
}

foreach (array('http://', 'https://', '@import url("https://', 'url("//') as $remote) {
    if (false !== strpos($css, $remote)) {
        fwrite(STDERR, "Remote or protocol-relative catalogue asset is forbidden: {$remote}.\n");
        exit(1);
    }
}

if (false !== stripos($css, 'verified')) {
    fwrite(STDERR, "The catalogue design layer must not introduce a new verified claim.\n");
    exit(1);
}

echo "Autolex 4.2 catalogue design smoke tests passed.\n";
