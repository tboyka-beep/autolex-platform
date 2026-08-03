<?php
/**
 * Deterministic contract for the Autolex 4.2 brand/model/generation hierarchy.
 */

$root = dirname(__DIR__);
$css_path = $root . '/plugin/autolex-platform/assets/css/autolex-taxonomy-42.css';
$entry_path = $root . '/plugin/autolex-platform/assets/css/autolex-home-41.css';

if (!is_file($css_path) || !is_file($entry_path)) {
    fwrite(STDERR, "Missing Autolex 4.2 taxonomy design assets.\n");
    exit(1);
}

$css = file_get_contents($css_path);
$entry = file_get_contents($entry_path);

$contracts = array(
    'taxonomy entrypoint' => '@import url("autolex-taxonomy-42.css")',
    'brand page scope' => '.alxbc-brand-page',
    'model page scope' => '.alxbc-model-page',
    'generation page scope' => '.alxbc-generation-page',
    'engine page scope' => '.alxbc-engine-page',
    'light surface token' => '--alx-taxonomy-surface: #ffffff',
    'graphite text token' => '--alx-taxonomy-text: #17202b',
    'blue accent token' => '--alx-taxonomy-accent: #1769e0',
    'hierarchy hero' => '.alxbc-taxonomy-hero',
    'model grid' => '.alxbc-model-grid',
    'generation grid' => '.alxbc-generation-grid',
    'engine grid' => '.alxbc-engine-grid',
    'source conflict state' => 'data-source-status="source_conflict"',
    'VIN state' => 'data-source-status="needs_vin"',
    'keyboard focus' => ':focus-visible',
    'tablet breakpoint' => '@media (max-width: 768px)',
    'small mobile breakpoint' => '@media (max-width: 480px)',
    'reduced motion' => '@media (prefers-reduced-motion: reduce)',
);

foreach ($contracts as $label => $needle) {
    $haystack = 'taxonomy entrypoint' === $label ? $entry : $css;
    if (false === strpos($haystack, $needle)) {
        fwrite(STDERR, sprintf("Missing taxonomy design contract: %s (%s)\n", $label, $needle));
        exit(1);
    }
}

if (preg_match('/url\s*\(\s*["\']?https?:\/\//i', $css)) {
    fwrite(STDERR, "Remote visual assets are forbidden in the taxonomy design layer.\n");
    exit(1);
}

if (false !== stripos($css, '#000000') || false !== stripos($css, 'background: #000')) {
    fwrite(STDERR, "A black legacy page surface leaked into the taxonomy design layer.\n");
    exit(1);
}

echo "Autolex 4.2 taxonomy design smoke test passed.\n";
