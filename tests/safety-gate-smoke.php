<?php

define('ABSPATH', __DIR__ . '/');

function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_strip_all_tags($value) { return strip_tags($value); }

require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-safety-gate.php';

$metadata = array('result' => array('distributions' => array(
    array('title' => 'Weekly Reports - Excel Format', 'download_url' => 'https://ec.europa.eu/file.xls', 'format' => 'XLS'),
    array('title' => 'Weekly Reports - XML format', 'download_url' => 'https://ec.europa.eu/safety-gate/weekly.xml', 'format' => 'XML'),
)));
$url = Autolex_Safety_Gate::discover_xml_url($metadata);
if ('https://ec.europa.eu/safety-gate/weekly.xml' !== $url) {
    fwrite(STDERR, "Safety Gate XML discovery failed.\n");
    exit(1);
}

$vehicle = Autolex_Safety_Gate::normalize_alert(array(
    'Reference' => 'A12/00001/26',
    'Product category' => 'Motor vehicles',
    'Product' => 'Passenger car',
    'Brand' => 'Example Motors',
    'Model' => 'Road One',
    'Risk type' => 'Injuries',
    'Risk description' => 'A component may fail.',
    'Notification date' => '2026-07-24',
));
if (!$vehicle || 'Example Motors' !== $vehicle['brand'] || '2026-07-24' !== $vehicle['notified_at']) {
    fwrite(STDERR, "Vehicle alert normalization failed.\n");
    exit(1);
}

$non_vehicle = Autolex_Safety_Gate::normalize_alert(array(
    'Reference' => 'A12/00002/26',
    'Product category' => 'Toys',
    'Product' => 'Toy car',
    'Brand' => 'Example Toys',
));
if (false !== $non_vehicle) {
    fwrite(STDERR, "Non-vehicle alert was not rejected.\n");
    exit(1);
}

$again = Autolex_Safety_Gate::normalize_alert(array(
    'Reference' => 'A12/00001/26', 'Category' => 'Motor vehicles', 'Product' => 'Passenger car',
    'Brand' => 'Example Motors', 'Model' => 'Road One', 'Risk' => 'Injuries', 'Date' => '2026-07-24',
));
if (!$again || $vehicle['fingerprint'] !== $again['fingerprint']) {
    fwrite(STDERR, "Vehicle alert fingerprint is not stable.\n");
    exit(1);
}

echo "Safety Gate smoke tests passed.\n";
