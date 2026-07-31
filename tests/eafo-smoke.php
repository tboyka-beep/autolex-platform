<?php

define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-eafo.php';

$manifest = Autolex_EAFO::validate_manifest(array(
    'dataset' => 'af_fleet',
    'source_url' => 'https://alternative-fuels-observatory.ec.europa.eu/transport-mode/road/hungary/vehicles-and-fleet',
    'format' => 'csv',
    'sha256' => str_repeat('a', 64),
    'reference_period' => 'Q2 2026',
    'retrieved_at' => '2026-07-31T10:00:00Z',
    'file_size' => 2048,
));
if (!$manifest['verified_source_host'] || false !== $manifest['individual_vehicle_verification']) {
    fwrite(STDERR, "EAFO manifest scope validation failed.\n");
    exit(1);
}

try {
    Autolex_EAFO::validate_manifest(array_merge($manifest, array(
        'source_url' => 'https://example.com/eafo.csv',
    )));
    fwrite(STDERR, "Untrusted EAFO host was accepted.\n");
    exit(1);
} catch (InvalidArgumentException $expected) {
}

$row = Autolex_EAFO::normalize_row(array(
    'Country code' => 'HU',
    'Reference period' => 'Q2 2026',
    'Vehicle category' => 'M1',
    'Fuel type' => 'BEV',
    'Indicator' => 'AF fleet',
    'Value' => '94009',
    'Unit' => 'NR',
));
if (!$row || 94009.0 !== $row['value'] || 'source_download_validated' !== $row['verification_status']) {
    fwrite(STDERR, "EAFO row normalization failed.\n");
    exit(1);
}

if (false !== Autolex_EAFO::normalize_row(array(
    'Country code' => 'HU', 'Period' => 'Q2 2026', 'Fuel' => 'UNKNOWN',
    'Indicator' => 'AF fleet', 'Value' => '12', 'Unit' => 'NR',
))) {
    fwrite(STDERR, "Unsupported EAFO fuel was accepted.\n");
    exit(1);
}

echo "EAFO adapter smoke tests passed.\n";
