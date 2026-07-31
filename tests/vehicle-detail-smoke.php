<?php

$root = dirname(__DIR__);
$files = array(
    'maintenance' => $root . '/plugin/autolex-platform/includes/class-autolex-maintenance-evidence.php',
    'portal'      => $root . '/plugin/autolex-platform/includes/class-autolex-portal.php',
    'script'      => $root . '/plugin/autolex-platform/assets/js/autolex-maintenance-evidence.js',
    'style'       => $root . '/plugin/autolex-platform/assets/css/autolex-vehicle-detail.css',
);

foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing vehicle detail {$name} file: {$path}\n");
        exit(1);
    }
}

$contracts = array(
    'maintenance' => array(
        "'recallsEndpoint'",
        "'fitment'",
        "'specification_search'",
        "'matched_product'",
        'get_vehicle_summary',
        "'primary_sources'",
        "'vin_claims'",
    ),
    'portal' => array(
        '/auto-adatlap/',
        "'autolex-vehicle-detail'",
        'autolex-vehicle-detail.css',
        "'autolex-vehicle-detail'",
    ),
    'script' => array(
        'renderOverview',
        'renderMaintenance',
        'renderRecommendations',
        'renderRecalls',
        "rule.rule_type !== 'fallback'",
        'specification_search',
        'alx3-product-card',
        'alx3-detail-nav',
        'aria-live',
    ),
    'style' => array(
        '.alx3-detail-overview',
        '.alx3-detail-nav',
        '.alx3-claim-card',
        '.alx3-product-card',
        '.alx3-recall-grid',
        '.alx3-recall-card',
        '@media(max-width:760px)',
        'prefers-reduced-motion',
    ),
);

foreach ($contracts as $name => $needles) {
    $content = file_get_contents($files[$name]);
    foreach ($needles as $needle) {
        if (false === strpos($content, $needle)) {
            fwrite(STDERR, "Vehicle detail contract missing from {$name}: {$needle}\n");
            exit(1);
        }
    }
}

$script = file_get_contents($files['script']);
if (false !== strpos($script, "rule.rule_type !== 'fallback' && rule.product_url")) {
    fwrite(STDERR, "Specification searches are still hidden when no concrete product URL exists.\n");
    exit(1);
}

$style = file_get_contents($files['style']);
if (strlen($style) < 10000) {
    fwrite(STDERR, "Vehicle detail design layer is unexpectedly incomplete.\n");
    exit(1);
}

echo "Autolex vehicle detail smoke tests passed.\n";
