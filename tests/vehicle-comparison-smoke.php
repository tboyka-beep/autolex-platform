<?php
/** Deterministic contract for the shareable three-vehicle comparison. */
$root = dirname(__DIR__);
$class = file_get_contents($root . '/plugin/autolex-platform/includes/class-autolex-vehicle-comparison.php');
$bridge = file_get_contents($root . '/plugin/autolex-platform/includes/class-autolex-comparison-page.php');
$css = file_get_contents($root . '/plugin/autolex-platform/assets/css/autolex-vehicle-comparison.css');
$bootstrap = file_get_contents($root . '/plugin/autolex-platform/autolex-platform.php');

$required = array(
    'class Autolex_Vehicle_Comparison',
    'normalize_ids',
    "if (3 === count(\$ids))",
    "name=\"vehicles\"",
    "name=\"compare\" value=\"1\"",
    'remove_url',
    'data_grade',
    'verification_status',
    'safety_gate_count',
    'vin_required',
    'Nincs adat',
    'VIN szükséges',
    'szöveges találat',
    'nem jelent automatikus alkatrész-kompatibilitást',
);
foreach ($required as $needle) {
    if (false === strpos($class, $needle)) {
        fwrite(STDERR, "Missing comparison contract: {$needle}\n");
        exit(1);
    }
}

foreach (array('is_page(\'autok\')', 'is_main_query()', 'Autolex_Vehicle_Comparison::instance()', '$comparison->render()') as $needle) {
    if (false === strpos($bridge, $needle)) {
        fwrite(STDERR, "Missing progressive page bridge: {$needle}\n");
        exit(1);
    }
}

foreach (array('class-autolex-vehicle-comparison.php', 'class-autolex-comparison-page.php', 'Autolex_Comparison_Page::instance()') as $needle) {
    if (false === strpos($bootstrap, $needle)) {
        fwrite(STDERR, "Comparison module is not bootstrapped: {$needle}\n");
        exit(1);
    }
}

foreach (array('.alx3-compare__table-wrap', 'overflow:auto', 'position:sticky', '@media(max-width:720px)', 'prefers-reduced-motion') as $needle) {
    if (false === strpos($css, $needle)) {
        fwrite(STDERR, "Missing responsive comparison design: {$needle}\n");
        exit(1);
    }
}

if (preg_match('/\bverified\b[^\n]{0,80}(?:true|automatikus|igazolt)/iu', $class)) {
    fwrite(STDERR, "Comparison must not manufacture verified status.\n");
    exit(1);
}

echo "Vehicle comparison contract passed.\n";
