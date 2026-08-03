<?php
/**
 * Deterministic contract for the Autolex 4.2 comparison design layer.
 */

$root = dirname(__DIR__);
$entry = $root . '/plugin/autolex-platform/assets/css/autolex-home-41.css';
$compare = $root . '/plugin/autolex-platform/assets/css/autolex-compare-42.css';

if (!is_file($entry) || !is_file($compare)) {
    fwrite(STDERR, "Missing Autolex 4.2 comparison assets.\n");
    exit(1);
}

$entry_css = file_get_contents($entry);
$compare_css = file_get_contents($compare);

$required_entry_markers = array(
    '@import url("autolex-compare-42.css")',
);

$required_compare_markers = array(
    '.alxp-compare-hero',
    '.alxp-compare-toolbar',
    '.alxp-compare-grid',
    '.alxp-compare-cell',
    '.alxp-compare-vehicle',
    '.alxp-compare-difference',
    '.alxp-compare-empty',
    '.alxp-compare-error',
    '[data-source-status="multi_source_match"]',
    '[data-source-status="manufacturer_source"]',
    '[data-source-status="official_registry"]',
    '[data-source-status="source_conflict"]',
    '[data-source-status="vin_required"]',
    '@media (max-width: 1024px)',
    '@media (max-width: 768px)',
    '@media (max-width: 375px)',
    '@media (prefers-reduced-motion: reduce)',
    ':focus-visible',
    'role="rowheader"',
);

foreach ($required_entry_markers as $marker) {
    if (false === strpos($entry_css, $marker)) {
        fwrite(STDERR, "Comparison entrypoint marker missing: {$marker}\n");
        exit(1);
    }
}

foreach ($required_compare_markers as $marker) {
    if (false === strpos($compare_css, $marker)) {
        fwrite(STDERR, "Comparison design marker missing: {$marker}\n");
        exit(1);
    }
}

$forbidden_patterns = array(
    '/url\(["\']?https?:\/\//i',
    '/#0{3,6}\b/i',
    '/background\s*:\s*(?:#0{3,6}|black)\b/i',
);

foreach ($forbidden_patterns as $pattern) {
    if (1 === preg_match($pattern, $compare_css)) {
        fwrite(STDERR, "Comparison design contains a forbidden remote or dark legacy asset.\n");
        exit(1);
    }
}

if (substr_count($compare_css, 'data-source-status') < 5) {
    fwrite(STDERR, "Comparison source status coverage is incomplete.\n");
    exit(1);
}

echo "Autolex 4.2 comparison design contract passed.\n";
