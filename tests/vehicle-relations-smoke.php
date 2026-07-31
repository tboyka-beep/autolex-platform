<?php
/**
 * Deterministic contract for related variants and detail-page policies.
 */

$root = dirname(__DIR__);
$php  = file_get_contents($root . '/plugin/autolex-platform/includes/class-autolex-vehicle-relations.php');
$js   = file_get_contents($root . '/plugin/autolex-platform/assets/js/autolex-vehicle-relations.js');
$css  = file_get_contents($root . '/plugin/autolex-platform/assets/css/autolex-vehicle-relations.css');
$boot = file_get_contents($root . '/plugin/autolex-platform/autolex-platform.php');

$checks = array(
    'REST route' => strpos($php, '/vehicle-relations/(?P<vehicle_id>\\d+)') !== false,
    'cache policy' => strpos($php, 'stale-while-revalidate=3600') !== false,
    'mapping allowlist' => strpos($php, "preg_match('/^[A-Za-z0-9_]+$/") !== false,
    'same make relation' => strpos($php, "$map['make']") !== false,
    'same model relation' => strpos($php, "$map['model']") !== false,
    'same generation engine relation' => strpos($php, '$same_generation') !== false && strpos($php, "$map['generation']") !== false,
    'maximum relation limits' => strpos($php, 'false, 8') !== false && strpos($php, 'true, 10') !== false,
    'recall summary' => strpos($php, 'recall_summary') !== false && strpos($php, 'MAX(notified_at)') !== false,
    'no compatibility assertion' => strpos($php, "'compatibility' => 'not_asserted'") !== false,
    'module registration' => strpos($boot, 'class-autolex-vehicle-relations.php') !== false && strpos($boot, 'Autolex_Vehicle_Relations::instance()') !== false,
    'safe catalog back link' => strpos($js, 'Vissza az autókatalógushoz') !== false,
    'related generations UI' => strpos($js, 'Más generációk') !== false,
    'engine variants UI' => strpos($js, 'Motorváltozatok') !== false,
    'text match disclaimer' => strpos($js, 'nem VIN-alapú visszahívási igazolás') !== false,
    'offer level explanation' => strpos($js, 'Az ajánlási szintek jelentése') !== false,
    'explicit compatibility warning' => strpos($js, 'Nem jelentenek automatikus alkatrész-kompatibilitást') !== false,
    'empty state' => strpos($js, 'Nincs további megbízhatóan azonosított rekord') !== false,
    'error fallback' => strpos($js, 'meglévő adatlap változatlanul használható') !== false,
    'responsive grid' => strpos($css, '@media(max-width:700px)') !== false,
    'reduced motion' => strpos($css, 'prefers-reduced-motion') !== false,
);

$failed = array_keys(array_filter($checks, static function ($passed) { return !$passed; }));
if ($failed) {
    fwrite(STDERR, 'Vehicle relations contract failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Vehicle relations contract passed (' . count($checks) . " checks).\n";
