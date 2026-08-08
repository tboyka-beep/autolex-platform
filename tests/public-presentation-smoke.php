<?php
/** ALX-050 Hungarian public presentation and factual-content contract. */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/../plugin/autolex-platform/includes/class-autolex-public-presentation.php';

$fail = static function ($message) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$fuels = array(
    'Petrol'          => 'Benzin',
    'Gasoline'        => 'Benzin',
    'Diesel'          => 'Dízel',
    'Electric'        => 'Elektromos',
    'Petrol/Electric' => 'Benzin / elektromos',
    'Diesel/Electric' => 'Dízel / elektromos',
    'PHEV'            => 'Plug-in hibrid',
    'LPG'             => 'LPG (autógáz)',
    'CNG'             => 'CNG (sűrített földgáz)',
    'Hydrogen'        => 'Hidrogén',
    'E85'             => 'E85 (etanol)',
);
foreach ($fuels as $raw => $expected) {
    $actual = Autolex_Public_Presentation::fuel_label($raw);
    if ($actual !== $expected) {
        $fail("fuel label {$raw} resolved to {$actual}, expected {$expected}");
    }
}

if (Autolex_Public_Presentation::fuel_label('Manufacturer X-Fuel') !== 'Manufacturer X-Fuel') {
    $fail('Unknown source terminology must be preserved instead of guessed.');
}

$payload = Autolex_Public_Presentation::localize_payload(array(
    'items' => array(
        array('id' => 1, 'fuel_type' => 'Petrol'),
        array('id' => 2, 'fuel_type' => 'Diesel'),
    ),
    'fuels' => array(
        array('value' => 'Petrol', 'total' => 12),
        array('value' => 'Diesel', 'total' => 7),
    ),
));
if (($payload['items'][0]['fuel_type'] ?? '') !== 'Benzin' || ($payload['items'][0]['fuel_type_raw'] ?? '') !== 'Petrol') {
    $fail('REST presentation must localize fuel while retaining the raw source value.');
}
if (($payload['items'][1]['fuel_type'] ?? '') !== 'Dízel' || ($payload['items'][1]['fuel_type_raw'] ?? '') !== 'Diesel') {
    $fail('Diesel REST presentation must be Hungarian and retain raw provenance.');
}
if (($payload['fuels'][0]['value'] ?? '') !== 'Petrol' || ($payload['fuels'][0]['label'] ?? '') !== 'Benzin') {
    $fail('Facet value must stay raw for filtering while its public label is Hungarian.');
}

$html = '<div><span>Petrol</span><option value="Diesel">Diesel (17)</option><b>PRIMARY</b></div>' .
    '<script>window.rawFuel = "Petrol"; window.badge = "PRIMARY";</script>';
$localized = Autolex_Public_Presentation::localize_html($html);
foreach (array('>Benzin<', '>Dízel (17)<', '>ELSŐDLEGES<') as $needle) {
    if (false === strpos($localized, $needle)) {
        $fail('HTML public localization missing marker: ' . $needle);
    }
}
if (false === strpos($localized, 'window.rawFuel = "Petrol"') || false === strpos($localized, 'window.badge = "PRIMARY"')) {
    $fail('Script/source payload must not be rewritten by the HTML presentation layer.');
}

$facts = Autolex_Public_Presentation::build_vehicle_facts(array(
    'make'        => 'Nissan',
    'model'       => 'Qashqai',
    'generation'  => 'J12',
    'engine'      => '1.3 DIG-T',
    'engine_code' => '',
    'fuel_type'   => 'Petrol',
    'capacity_cc' => 1332,
    'power_kw'    => 116,
    'power_ps'    => 158,
    'year_from'   => 2021,
    'year_to'     => 0,
));
$fact_map = array_column($facts, 'value', 'label');
if (($fact_map['Üzemanyag'] ?? '') !== 'Benzin') {
    $fail('Generated vehicle content must use Hungarian fuel terminology.');
}
if (isset($fact_map['Motorkód'])) {
    $fail('Missing fields must be omitted from generated factual content.');
}
foreach (array('Generáció', 'Motorváltozat', 'Hengerűrtartalom', 'Teljesítmény', 'Gyártási időszak') as $required) {
    if (!isset($fact_map[$required])) {
        $fail('Record-backed vehicle summary missing factual field: ' . $required);
    }
}

$presentation = file_get_contents(__DIR__ . '/../plugin/autolex-platform/includes/class-autolex-public-presentation.php');
if (!is_string($presentation) || false === strpos($presentation, 'RÖGZÍTETT KATALÓGUSADATOK')) {
    $fail('Vehicle summary must identify fields as recorded catalogue data.');
}
if (is_string($presentation) && false !== strpos($presentation, 'ELLENŐRZÖTT KATALÓGUSADATOK')) {
    $fail('Vehicle summary must not overstate every catalogue record as independently verified.');
}

$loader = file_get_contents(__DIR__ . '/../plugin/autolex-platform/autolex-platform.php');
foreach (array('class-autolex-public-presentation.php', 'Autolex_Public_Presentation::instance()') as $needle) {
    if (!is_string($loader) || false === strpos($loader, $needle)) {
        $fail('Plugin bootstrap missing public presentation integration: ' . $needle);
    }
}
if (!is_string($loader) || false === strpos($loader, "remove_action('template_redirect', array(\$public_presentation, 'start_html_localizer'), 1);")) {
    $fail('ALX-050G must disable the global public HTML output-buffer hook after presentation bootstrap.');
}
if (is_string($loader) && false !== strpos($loader, "add_action('template_redirect', array(\$public_presentation, 'start_html_localizer')")) {
    $fail('Plugin bootstrap must not re-register the global public HTML output buffer.');
}

$script = file_get_contents(__DIR__ . '/../plugin/autolex-platform/assets/js/autolex-public-presentation.js');
foreach (array("['PRIMARY', 'ELSŐDLEGES']", "['SUPPORT', 'MEGERŐSÍTŐ']", "['LIVE QUERY', 'ÉLŐ LEKÉRDEZÉS']", "['petrol', 'Benzin']", "['diesel', 'Dízel']", 'MutationObserver') as $needle) {
    if (!is_string($script) || false === strpos($script, $needle)) {
        $fail('Dynamic public localization contract missing: ' . $needle);
    }
}

echo "public-presentation-smoke: OK\n";
