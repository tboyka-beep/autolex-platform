<?php
/** ALX-050E/T route-independent factual-summary fallback contract. */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/../plugin/autolex-platform/includes/class-autolex-public-presentation.php';
require_once __DIR__ . '/../plugin/autolex-platform/includes/class-autolex-vehicle-fact-fallback.php';

$fail = static function ($message) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$marker = 'data-autolex-public-facts="true"';
$summary = '<section ' . $marker . ' data-autolex-public-facts-fallback="true">RÖGZÍTETT KATALÓGUSADATOK</section>';

$html = '<!doctype html><html><body><main><article>vehicle</article></main></body></html>';
$injected = Autolex_Vehicle_Fact_Fallback::inject_summary_html($html, $summary);
if (substr_count($injected, $marker) !== 1) {
    $fail('Fallback helper must inject the factual summary exactly once when called explicitly.');
}
$summary_pos = strpos($injected, $marker);
$main_close_pos = stripos($injected, '</main>');
if ($summary_pos === false || $main_close_pos === false || $summary_pos > $main_close_pos) {
    $fail('Fallback helper factual summary must be inserted before </main>.');
}

$already = '<html><body><main><section ' . $marker . '>primary renderer</section></main></body></html>';
if (Autolex_Vehicle_Fact_Fallback::inject_summary_html($already, $summary) !== $already) {
    $fail('Existing factual summary marker must prevent duplicate injection.');
}

if (Autolex_Vehicle_Fact_Fallback::inject_summary_html($html, '') !== $html) {
    $fail('Empty summary must fail closed without modifying HTML.');
}
if (Autolex_Vehicle_Fact_Fallback::inject_summary_html($html, '<section>missing marker</section>') !== $html) {
    $fail('Unmarked summary must fail closed without modifying HTML.');
}

$body_only = '<!doctype html><html><body><article>vehicle</article></body></html>';
$body_injected = Autolex_Vehicle_Fact_Fallback::inject_summary_html($body_only, $summary);
if (substr_count($body_injected, $marker) !== 1 || strpos($body_injected, $marker) > stripos($body_injected, '</body>')) {
    $fail('Fallback helper must use </body> only when </main> is unavailable.');
}

$malformed = '<div>partial response without document close anchors</div>';
if (Autolex_Vehicle_Fact_Fallback::inject_summary_html($malformed, $summary) !== $malformed) {
    $fail('Malformed/non-document responses must remain unchanged.');
}

$source = file_get_contents(__DIR__ . '/../plugin/autolex-platform/includes/class-autolex-vehicle-fact-fallback.php');
if (!is_string($source)) {
    $fail('Fallback source cannot be read.');
}
foreach (array(
    "add_action('template_redirect', array(\$this, 'start_buffer'), 2)",
    'ob_start(array($this, \'inject_vehicle_summary\'))',
    'data-autolex-public-facts-fallback="true"',
    "~/auto-adatlap/(\\d+)(?:/|$)~",
    'Autolex_Catalog_Browser::instance()->get_legacy_mapping()',
    'Autolex_Public_Presentation::build_vehicle_facts($vehicle)',
    "array('</main>', '</body>')",
    'Hiányzó adatot az Autolex nem becsül és nem talál ki.',
) as $needle) {
    if (false === strpos($source, $needle)) {
        $fail('Fallback helper contract marker missing: ' . $needle);
    }
}

if (substr_count($source, 'data-autolex-public-facts="true"') < 2) {
    $fail('Fallback helper must both detect and emit the duplicate-prevention marker.');
}

$presentation = file_get_contents(__DIR__ . '/../plugin/autolex-platform/includes/class-autolex-public-presentation.php');
foreach (array(
    "add_filter('the_content', array(\$this, 'prepend_vehicle_fact_summary'), 75)",
    'data-autolex-public-facts="true"',
) as $needle) {
    if (!is_string($presentation) || false === strpos($presentation, $needle)) {
        $fail('Primary the_content factual renderer missing: ' . $needle);
    }
}

$loader = file_get_contents(__DIR__ . '/../plugin/autolex-platform/autolex-platform.php');
foreach (array(
    "includes/class-autolex-vehicle-fact-fallback.php",
    '$vehicle_fact_fallback = Autolex_Vehicle_Fact_Fallback::instance();',
    "remove_action('template_redirect', array(\$vehicle_fact_fallback, 'start_buffer'), 2);",
) as $needle) {
    if (!is_string($loader) || false === strpos($loader, $needle)) {
        $fail('Plugin bootstrap missing ALX-050T fallback-buffer safety contract: ' . $needle);
    }
}
if (is_string($loader) && false !== strpos($loader, "add_action('template_redirect', array(\$vehicle_fact_fallback, 'start_buffer')")) {
    $fail('Plugin bootstrap must not re-register the factual fallback whole-page output buffer.');
}

// Both known response-wide buffers must be explicitly disabled at plugin bootstrap.
foreach (array(
    "remove_action('template_redirect', array(\$public_presentation, 'start_html_localizer'), 1);",
    "remove_action('template_redirect', array(\$vehicle_fact_fallback, 'start_buffer'), 2);",
) as $needle) {
    if (!is_string($loader) || false === strpos($loader, $needle)) {
        $fail('Whole-page buffer disable marker missing: ' . $needle);
    }
}

echo "vehicle-fact-fallback-smoke: OK\n";
