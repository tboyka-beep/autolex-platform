<?php

define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-portal.php';

$complete = array(
    'engine_code' => 'N47D20',
    'fuel_type'   => 'Diesel',
    'capacity_cc' => 1995,
    'power_kw'    => 105,
    'year_from'   => 2007,
);
if ('A' !== Autolex_Portal::calculate_quality_grade($complete)) {
    fwrite(STDERR, "Complete vehicle must receive grade A.\n");
    exit(1);
}

$partial = array('engine' => '1.6 TDI', 'fuel_type' => 'Diesel');
if ('B' !== Autolex_Portal::calculate_quality_grade($partial)) {
    fwrite(STDERR, "Partially specified engine must receive grade B.\n");
    exit(1);
}

if ('C' !== Autolex_Portal::calculate_quality_grade(array('make' => 'Example'))) {
    fwrite(STDERR, "Basic vehicle must receive grade C.\n");
    exit(1);
}

$sources = Autolex_Portal::get_source_registry();
if (count($sources) < 6) {
    fwrite(STDERR, "The free source registry is incomplete.\n");
    exit(1);
}
foreach ($sources as $source) {
    foreach (array('code', 'name', 'publisher', 'url', 'access', 'scope', 'automation', 'confidence', 'cost') as $field) {
        if (empty($source[$field])) {
            fwrite(STDERR, "Source registry field {$field} is missing.\n");
            exit(1);
        }
    }
    if ('free' !== $source['cost']) {
        fwrite(STDERR, "Paid data source detected in the allowlist.\n");
        exit(1);
    }
    if (0 !== strpos($source['url'], 'https://')) {
        fwrite(STDERR, "Source registry URL must use HTTPS.\n");
        exit(1);
    }
}

echo "Autolex Portal smoke tests passed.\n";
