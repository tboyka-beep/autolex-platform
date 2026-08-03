<?php
/**
 * Deterministic contract for the Safety Gate source adapter.
 */

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
require __DIR__ . '/../plugin/autolex-platform/includes/class-autolex-safety-gate-source-adapter.php';

function expect_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$adapter = new Autolex_Safety_Gate_Source_Adapter();
$source = array(
    'url' => 'https://ec.europa.eu/safety-gate-alerts/screen/webReport/alertDetail/10001234',
    'retrieved_at' => '2026-08-03T01:44:00Z',
    'usage_reviewed' => true,
    'usage_note' => 'Official public Safety Gate record; factual metadata only.',
    'document_id' => 'A12/01234/26',
);
$row = array(
    'entity_id' => 42,
    'alert_number' => 'A12/01234/26',
    'risk_type' => 'Injuries',
    'product_description' => 'Passenger car',
    'notifying_country' => 'Germany',
    'production_dates' => '2025-01 to 2025-04',
    'remedy' => 'Recall and repair affected vehicles.',
    'published_at' => '2026-07-31',
);

$result = $adapter->import($source, array($row), array('dry_run' => true));
expect_true(!is_wp_error($result), 'Reviewed official Safety Gate record must be accepted.');
expect_true('dry_run_completed' === $result['status'], 'Dry-run must reach the shared importer.');
$call = Autolex_Provenance_Batch_Importer::instance()->last_call;
expect_true('official-source-v1' === $call['adapter'], 'Safety Gate must delegate to the common official adapter.');
expect_true('official_registry' === $call['source']['source_type'], 'Safety Gate is an official registry source.');
expect_true('European Commission Safety Gate' === $call['source']['publisher'], 'Publisher must be normalized deterministically.');
expect_true(6 === count($call['records']), 'Required and present optional recall fields must become field-level claims.');
expect_true('single_source_confirmed' === $call['records'][0]['verification_status'], 'One Safety Gate record remains single-source confirmed.');
expect_true(false !== strpos($call['records'][0]['source_locator'], 'A12/01234/26'), 'Every claim must retain the alert identifier.');

$untrusted = $source;
$untrusted['url'] = 'https://example.com/safety-alert/123';
expect_true('autolex_safety_gate_untrusted_host' === $adapter->normalize_source($untrusted)->get_error_code(), 'Non-Commission hosts must fail closed.');

$missing = $row;
unset($missing['risk_type']);
expect_true('autolex_safety_gate_missing_row_field' === $adapter->normalize_row($missing)->get_error_code(), 'Missing required recall fields must not be guessed.');

$invalid = $row;
$invalid['entity_id'] = 0;
expect_true('autolex_safety_gate_invalid_entity' === $adapter->normalize_row($invalid)->get_error_code(), 'Invalid entity mappings must fail closed.');

echo "Safety Gate source adapter smoke test passed.\n";
