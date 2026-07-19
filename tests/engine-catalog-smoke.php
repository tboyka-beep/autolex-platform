<?php
/**
 * Smoke checks for legacy engine-field discovery and normalized classes.
 */

define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-catalog-browser.php';
require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-engine-catalog.php';

$reflection = new ReflectionClass('Autolex_Catalog_Browser');
$browser    = $reflection->newInstanceWithoutConstructor();
$mapper     = $reflection->getMethod('map_columns');
$mapper->setAccessible(true);

$map = $mapper->invoke(
    $browser,
    array(
        'vehicle_id',
        'make',
        'model',
        'generation_name',
        'engine_name',
        'engine_code',
        'fuel_type',
        'engine_capacity_cc',
        'engine_power_kw',
        'year_from',
        'year_to',
    )
);

$expected = array(
    'id'          => 'vehicle_id',
    'make'        => 'make',
    'model'       => 'model',
    'generation'  => 'generation_name',
    'engine'      => 'engine_name',
    'engine_code' => 'engine_code',
    'fuel_type'   => 'fuel_type',
    'capacity_cc' => 'engine_capacity_cc',
    'power_kw'    => 'engine_power_kw',
);

foreach ($expected as $key => $value) {
    if (!is_array($map) || ($map[$key] ?? '') !== $value) {
        fwrite(STDERR, "Engine mapping failed for {$key}.\n");
        exit(1);
    }
}

if (Autolex_Engine_Catalog::SCHEMA_VERSION !== '1.0.0') {
    fwrite(STDERR, "Unexpected engine schema version.\n");
    exit(1);
}

echo "Engine catalogue smoke checks passed.\n";
