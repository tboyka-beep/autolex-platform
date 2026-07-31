<?php

define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-eurostat.php';

$url = Autolex_Eurostat::build_url('road_eqs_carpda', array(
    'geo' => 'HU',
    'time' => '2024',
    'mot_nrg' => array('ELC', 'PETROL'),
));
if (0 !== strpos($url, 'https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/road_eqs_carpda?')) {
    fwrite(STDERR, "Eurostat endpoint is not allowlisted.\n");
    exit(1);
}
foreach (array('format=JSON', 'lang=EN', 'geo=HU', 'time=2024', 'mot_nrg=ELC%2CPETROL') as $expected) {
    if (false === strpos($url, $expected)) {
        fwrite(STDERR, "Eurostat URL is missing {$expected}.\n");
        exit(1);
    }
}

try {
    Autolex_Eurostat::build_url('nama_10_gdp');
    fwrite(STDERR, "Unsupported Eurostat dataset was accepted.\n");
    exit(1);
} catch (InvalidArgumentException $expected) {
}

$fixture = array(
    'version' => '2.0',
    'class' => 'dataset',
    'label' => 'Passenger cars by type of motor energy',
    'updated' => '2026-07-30T23:00:00+0200',
    'id' => array('freq', 'unit', 'mot_nrg', 'geo', 'time'),
    'size' => array(1, 1, 2, 1, 1),
    'dimension' => array(
        'freq' => array('category' => array('index' => array('A' => 0))),
        'unit' => array('category' => array('index' => array('NR' => 0))),
        'mot_nrg' => array('category' => array('index' => array('ELC' => 0, 'PETROL' => 1))),
        'geo' => array('category' => array('index' => array('HU' => 0))),
        'time' => array('category' => array('index' => array('2024' => 0))),
    ),
    'value' => array(0 => 1000, 1 => 2500000),
);
$validated = Autolex_Eurostat::validate_payload($fixture, 'road_eqs_carpda');
if (2 !== $validated['cell_count'] || false !== $validated['source_meta']['individual_vehicle_verification']) {
    fwrite(STDERR, "Eurostat JSON-stat validation or scope failed.\n");
    exit(1);
}

$fixture['value'][2] = 1;
try {
    Autolex_Eurostat::validate_payload($fixture, 'road_eqs_carpda');
    fwrite(STDERR, "Out-of-range Eurostat value was accepted.\n");
    exit(1);
} catch (RuntimeException $expected) {
}

echo "Eurostat adapter smoke tests passed.\n";
