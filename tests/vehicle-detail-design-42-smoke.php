<?php
/**
 * Deterministic contract for the Autolex 4.2 vehicle detail design layer.
 */

$root = dirname(__DIR__);
$entry = $root . '/plugin/autolex-platform/assets/css/autolex-home-41.css';
$detail = $root . '/plugin/autolex-platform/assets/css/autolex-vehicle-detail-42.css';

if (!is_file($entry) || !is_file($detail)) {
    fwrite(STDERR, "Missing Autolex 4.2 vehicle detail assets.\n");
    exit(1);
}

$entry_css = file_get_contents($entry);
$detail_css = file_get_contents($detail);

$required_entry_markers = array(
    '@import url("autolex-vehicle-detail-42.css")',
);

$required_detail_markers = array(
    '.alxp-vehicle-hero',
    '.alxp-detail-layout',
    '.alxp-detail-nav',
    '.alxp-maintenance-grid',
    '.alxp-source-list',
    '.alxp-product-rules',
    '[data-source-status="multi_source_match"]',
    '[data-source-status="manufacturer_source"]',
    '[data-source-status="official_registry"]',
    '[data-source-status="source_conflict"]',
    '[data-source-status="incomplete"]',
    '[data-source-status="vin_required"]',
    '.alxp-recall-warning',
    '@media (max-width: 1024px)',
    '@media (max-width: 768px)',
    '@media (max-width: 375px)',
    '@media (prefers-reduced-motion: reduce)',
    ':focus-visible',
    'var(--alx42-safety)',
);

foreach ($required_entry_markers as $marker) {
    if (false === strpos($entry_css, $marker)) {
        fwrite(STDERR, "Vehicle detail entrypoint marker missing: {$marker}\n");
        exit(1);
    }
}

foreach ($required_detail_markers as $marker) {
    if (false === strpos($detail_css, $marker)) {
        fwrite(STDERR, "Vehicle detail design marker missing: {$marker}\n");
        exit(1);
    }
}

$forbidden_patterns = array(
    '/url\(["\']?https?:\/\//i',
    '/#0{3,6}\b/i',
    '/background\s*:\s*(?:#0{3,6}|black)\b/i',
);

foreach ($forbidden_patterns as $pattern) {
    if (1 === preg_match($pattern, $detail_css)) {
        fwrite(STDERR, "Vehicle detail design contains a forbidden remote or dark legacy asset.\n");
        exit(1);
    }
}

if (substr_count($detail_css, 'data-source-status') < 6) {
    fwrite(STDERR, "Vehicle detail source status coverage is incomplete.\n");
    exit(1);
}

echo "Autolex 4.2 vehicle detail design contract passed.\n";
