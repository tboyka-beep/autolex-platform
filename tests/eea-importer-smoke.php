<?php
/**
 * Minimal normalization test that runs without a WordPress installation.
 */

define('ABSPATH', __DIR__ . '/');

function wp_strip_all_tags($value)
{
    return strip_tags($value);
}

require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-eea-importer.php';

$vehicle = Autolex_EEA_Importer::normalize_vehicle(
    array(
        'ms'     => 'HU',
        'man'    => 'BMW AG',
        'mk'     => 'BMW',
        'cn'     => '320d',
        't'      => '3K',
        'va'     => '31AY',
        've'     => '6A5000',
        'ct'     => 'M1',
        'ft'     => 'diesel',
        'ec_cm3' => '1 995',
        'ep_kw'  => '140,0',
        'm_kg'   => '1580',
        'ewltp_g_km' => '132,5',
        'r'      => '3',
        'status' => 'final',
    ),
    2024
);

$expectations = array(
    'make'                 => 'BMW',
    'model'                => '320d',
    'vehicle_category'     => 'M1',
    'country_code'         => 'HU',
    'engine_capacity_cc'   => 1995,
    'engine_power_kw'      => 140.0,
    'co2_wltp'             => 132.5,
    'registration_count'   => 3,
    'source_year'          => 2024,
);

foreach ($expectations as $field => $expected) {
    if (!array_key_exists($field, $vehicle) || $vehicle[$field] !== $expected) {
        fwrite(STDERR, sprintf("Unexpected %s value.\n", $field));
        exit(1);
    }
}

if (64 !== strlen($vehicle['fingerprint'])) {
    fwrite(STDERR, "Invalid vehicle fingerprint.\n");
    exit(1);
}

$unsupported = Autolex_EEA_Importer::normalize_vehicle(
    array('mk' => 'Example', 'cn' => 'Motorcycle', 'ct' => 'L3'),
    2024
);

if (null !== $unsupported) {
    fwrite(STDERR, "Unsupported vehicle category was accepted.\n");
    exit(1);
}

echo "EEA normalization smoke test passed.\n";
