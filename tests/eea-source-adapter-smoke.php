<?php
/** Deterministic contract for the reviewed EEA source adapter. */

define('ABSPATH', __DIR__ . '/');

final class WP_Error
{
    private $code;
    private $message;
    public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function absint($value) { return abs((int) $value); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function esc_url_raw($url) { return filter_var($url, FILTER_SANITIZE_URL); }

final class Autolex_Source_Provenance
{
    const STATUS_SINGLE_SOURCE = 'single_source_confirmed';
    const STATUS_MULTI_SOURCE = 'multi_source_match';
}
final class Autolex_Provenance_Batch_Importer
{
    private static $instance;
    public $last_call = array();
    public static function instance() { return self::$instance ?: self::$instance = new self(); }
    public function import($adapter, array $source, array $records, array $options = array())
    {
        $this->last_call = compact('adapter', 'source', 'records', 'options');
        return array('status' => !empty($options['dry_run']) ? 'dry_run_completed' : 'completed');
    }
}

require __DIR__ . '/../plugin/autolex-platform/includes/class-autolex-official-source-adapter.php';
require __DIR__ . '/../plugin/autolex-platform/includes/class-autolex-eea-source-adapter.php';

function expect_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$adapter = new Autolex_EEA_Source_Adapter();
$source = array(
    'url' => 'https://www.eea.europa.eu/en/datahub/datahubitem-view/vehicle-co2-monitoring',
    'retrieved_at' => '2026-08-03T00:45:00Z',
    'usage_reviewed' => true,
    'usage_note' => 'Official public EEA dataset; factual fields only.',
    'document_id' => 'EEA-CO2-CARS-2025',
);
$row = array(
    'entity_id' => 42,
    'record_id' => 'DE-2025-000123',
    'co2_g_km' => '118.5',
    'mass_kg' => '1540',
    'engine_capacity_cm3' => '1498',
    'power_kw' => '110',
    'registration_year' => '2025',
    'fuel_type' => 'Petrol',
    'reporting_period' => '2025',
);

$result = $adapter->import($source, array($row), array('dry_run' => true));
expect_true(!is_wp_error($result), 'Reviewed official EEA record must be accepted.');
expect_true('dry_run_completed' === $result['status'], 'Dry-run must reach the shared importer.');
$call = Autolex_Provenance_Batch_Importer::instance()->last_call;
expect_true('official-source-v1' === $call['adapter'], 'EEA must delegate to the common official adapter.');
expect_true('official_registry' === $call['source']['source_type'], 'EEA vehicle monitoring is treated as an official registry source.');
expect_true('European Environment Agency' === $call['source']['publisher'], 'Publisher must be normalized deterministically.');
expect_true(6 === count($call['records']), 'Required and present optional fields must become field-level claims.');
expect_true('single_source_confirmed' === $call['records'][0]['verification_status'], 'One EEA record remains single-source confirmed.');
expect_true(false !== strpos($call['records'][0]['source_locator'], 'DE-2025-000123'), 'Every claim must retain the EEA record identifier.');

$untrusted = $source;
$untrusted['url'] = 'https://example.com/eea.csv';
expect_true('autolex_eea_untrusted_host' === $adapter->normalize_source($untrusted)->get_error_code(), 'Non-EEA hosts must fail closed.');

$missing = $row;
unset($missing['co2_g_km']);
expect_true('autolex_eea_missing_row_field' === $adapter->normalize_row($missing)->get_error_code(), 'Missing required values must not be guessed.');

$invalid = $row;
$invalid['mass_kg'] = '-1';
expect_true('autolex_eea_invalid_numeric_value' === $adapter->normalize_row($invalid)->get_error_code(), 'Invalid numeric values must fail closed.');

$invalid_optional = $row;
$invalid_optional['power_kw'] = 'unknown';
expect_true('autolex_eea_invalid_optional_numeric' === $adapter->normalize_row($invalid_optional)->get_error_code(), 'Invalid optional numerics must not be silently accepted.');

echo "EEA source adapter smoke test passed.\n";
