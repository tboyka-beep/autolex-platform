<?php
/** ALX-050W normal the_content factual-summary contract. */

define('ABSPATH', __DIR__ . '/');
if (!function_exists('esc_html__')) {
    function esc_html__($value, $domain = null) { return (string) $value; }
}
if (!function_exists('esc_html')) {
    function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('number_format_i18n')) {
    function number_format_i18n($value, $decimals = 0) { return number_format((float) $value, $decimals, ',', ' '); }
}

require_once __DIR__ . '/../plugin/autolex-platform/includes/class-autolex-vehicle-fact-content.php';

$fail = static function ($message) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

if (169 !== Autolex_Vehicle_Fact_Content::vehicle_id_from_uri('/auto-adatlap/169/bmw-f30-3-series/')) {
    $fail('Dynamic vehicle-detail URI must resolve its numeric catalogue id.');
}
if (0 !== Autolex_Vehicle_Fact_Content::vehicle_id_from_uri('/autok/')) {
    $fail('Non-detail URI must fail closed with no vehicle id.');
}

$html = Autolex_Vehicle_Fact_Content::render_summary(
    array('make' => 'BMW', 'model' => '3 Series', 'generation' => 'F30'),
    array(
        array('label' => 'Üzemanyag', 'value' => 'Benzin'),
        array('label' => 'Teljesítmény', 'value' => '184 LE / 135 kW'),
    )
);
foreach (array(
    'data-autolex-public-facts="true"',
    'RÖGZÍTETT KATALÓGUSADATOK',
    'Röviden erről a változatról',
    'Hiányzó adatot az Autolex nem becsül és nem talál ki.',
    'Benzin',
) as $needle) {
    if (false === strpos($html, $needle)) {
        $fail('Rendered normal-content summary marker missing: ' . $needle);
    }
}
if (false !== strpos($html, 'ELLENŐRZÖTT KATALÓGUSADATOK')) {
    $fail('Summary must not overstate catalogue values as independently verified.');
}

$source = file_get_contents(__DIR__ . '/../plugin/autolex-platform/includes/class-autolex-vehicle-fact-content.php');
foreach (array(
    "add_filter('the_content', array(\$this, 'ensure_vehicle_fact_summary'), 76)",
    'vehicle_id_from_uri',
    'get_queried_object_id',
    'Autolex_Public_Presentation::build_vehicle_facts($vehicle)',
    'data-autolex-public-facts="true"',
) as $needle) {
    if (!is_string($source) || false === strpos($source, $needle)) {
        $fail('Normal-content renderer contract marker missing: ' . $needle);
    }
}
if (is_string($source) && false !== strpos($source, "add_action('template_redirect'")) {
    $fail('ALX-050W must not introduce a response-wide template_redirect renderer.');
}

$loader = file_get_contents(__DIR__ . '/../plugin/autolex-platform/autolex-platform.php');
foreach (array(
    "includes/class-autolex-vehicle-fact-content.php",
    'Autolex_Vehicle_Fact_Content::instance();',
    "remove_action('template_redirect', array(\$public_presentation, 'start_html_localizer'), 1);",
    "remove_action('template_redirect', array(\$vehicle_fact_fallback, 'start_buffer'), 2);",
) as $needle) {
    if (!is_string($loader) || false === strpos($loader, $needle)) {
        $fail('Bootstrap safety marker missing: ' . $needle);
    }
}

echo "vehicle-fact-content-smoke: OK\n";
