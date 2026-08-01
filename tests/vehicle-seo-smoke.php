<?php
/** Deterministic vehicle SEO, performance and accessibility contract. */

define('ABSPATH', __DIR__ . '/');
function absint($value) { return abs((int) $value); }
function home_url($path = '/') { return 'https://autolex.hu' . '/' . ltrim((string) $path, '/'); }

require_once __DIR__ . '/../plugin/autolex-platform/includes/class-autolex-vehicle-seo.php';

$vehicle = array(
    'id' => 42,
    'make' => 'BMW',
    'model' => '1 Series',
    'generation' => 'E87',
    'engine' => '118d',
    'engine_code' => 'N47D20',
    'fuel_type' => 'Dízel',
    'capacity_cc' => 1995,
    'power_kw' => 105,
    'power_ps' => 143,
    'year_from' => 2007,
    'year_to' => 2011,
);

$failures = array();
$assert = static function ($condition, $message) use (&$failures) {
    if (!$condition) { $failures[] = $message; }
};

$assert(42 === Autolex_Vehicle_SEO::vehicle_id_from_uri('/auto-adatlap/42/?utm_source=test'), 'Dynamic vehicle ID parsing failed.');
$assert(0 === Autolex_Vehicle_SEO::vehicle_id_from_uri('/autok/?vehicles=42'), 'Catalogue URL must not be treated as a detail page.');
$title = Autolex_Vehicle_SEO::seo_title($vehicle);
$assert(false !== strpos($title, 'BMW 1 Series E87'), 'SEO title must contain real vehicle identity.');
$assert(false !== strpos($title, '118d'), 'SEO title must contain the stored engine label.');
$description = Autolex_Vehicle_SEO::meta_description($vehicle);
$length = function_exists('mb_strlen') ? mb_strlen($description) : strlen($description);
$assert($length <= 158, 'Meta description must stay within the deterministic limit.');
$assert(false !== strpos($description, 'N47D20'), 'Meta description must use the stored engine code.');

$canonical = 'https://autolex.hu/auto-adatlap/42/';
$graph = Autolex_Vehicle_SEO::schema_graph($vehicle, $canonical, 'https://autolex.hu/autok/');
$assert(2 === count($graph), 'Schema graph must contain Vehicle and BreadcrumbList only.');
$assert('Vehicle' === ($graph[0]['@type'] ?? ''), 'Vehicle schema is missing.');
$assert('BreadcrumbList' === ($graph[1]['@type'] ?? ''), 'Breadcrumb schema is missing.');
$assert(1995 === ($graph[0]['vehicleEngine']['engineDisplacement']['value'] ?? 0), 'Engine displacement must use the stored catalogue value.');
$assert(!isset($graph[0]['offers']), 'SEO must not invent commercial offers or compatibility.');
$assert(!isset($graph[0]['vehicleIdentificationNumber']), 'SEO must not invent a VIN.');

$minimal = array('id' => 7, 'make' => 'Ford', 'model' => 'Focus');
$minimal_graph = Autolex_Vehicle_SEO::schema_graph($minimal, 'https://autolex.hu/auto-adatlap/7/', 'https://autolex.hu/autok/');
$assert(!isset($minimal_graph[0]['fuelType']), 'Empty optional fields must be omitted, not serialized.');
$assert(!isset($minimal_graph[0]['vehicleEngine']), 'Missing engine data must not create an EngineSpecification.');

$source = file_get_contents(__DIR__ . '/../plugin/autolex-platform/includes/class-autolex-vehicle-seo.php');
foreach (array(
    "set_transient(\$cache_key, \$snapshot, 15 * MINUTE_IN_SECONDS)",
    "remove_action('wp_head', 'rel_canonical')",
    "add_filter('wp_robots'",
    "'max-image-preview'",
    "JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE",
) as $contract) {
    $assert(false !== strpos($source, $contract), 'Missing SEO/performance contract: ' . $contract);
}

$detail_css = file_get_contents(__DIR__ . '/../plugin/autolex-platform/assets/css/autolex-vehicle-detail.css');
$portal_css = file_get_contents(__DIR__ . '/../plugin/autolex-platform/assets/css/autolex-portal-3.css');
$assert(false !== strpos($detail_css . $portal_css, ':focus-visible'), 'Visible keyboard focus contract is missing.');
$assert(false !== strpos($detail_css . $portal_css, 'prefers-reduced-motion'), 'Reduced-motion contract is missing.');

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Vehicle SEO, performance and accessibility contract passed.\n";
