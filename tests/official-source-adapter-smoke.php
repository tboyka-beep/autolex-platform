<?php
/**
 * Deterministic contract for the official source adapter.
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

function expect_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$adapter = new Autolex_Official_Source_Adapter();
$source = array(
    'source_type' => 'official_registry',
    'title' => 'EU official vehicle record',
    'publisher' => 'European Union',
    'url' => 'https://example.eu/record/123',
    'document_id' => 'EU-123',
    'retrieved_at' => '2026-08-03T00:45:00Z',
    'usage_reviewed' => true,
    'usage_note' => 'Public official record; metadata and factual fields only.',
);
$rows = array(array(
    'entity_type' => 'vehicle',
    'entity_id' => 42,
    'field_path' => 'engine.power_kw',
    'observed_value' => '110',
    'normalization_rule' => 'integer_kw_v1',
    'source_locator' => 'record.power_kw',
));

$result = $adapter->import($source, $rows, array('dry_run' => true));
expect_true(!is_wp_error($result), 'A reviewed HTTPS official source must be accepted.');
expect_true('dry_run_completed' === $result['status'], 'Dry-run must reach the common importer.');
$call = Autolex_Provenance_Batch_Importer::instance()->last_call;
expect_true('official-source-v1' === $call['adapter'], 'Stable adapter code is required.');
expect_true('single_source_confirmed' === $call['records'][0]['verification_status'], 'One source must default to single-source confirmation.');
expect_true(!isset($call['source']['usage_reviewed']), 'Internal review flag must not be persisted as source metadata.');

$insecure = $source;
$insecure['url'] = 'http://example.eu/record/123';
expect_true('autolex_official_source_insecure_url' === $adapter->validate_source($insecure)->get_error_code(), 'Non-HTTPS source URLs must fail closed.');

$unreviewed = $source;
$unreviewed['usage_reviewed'] = false;
expect_true('autolex_official_source_usage_unreviewed' === $adapter->validate_source($unreviewed)->get_error_code(), 'Usage review is mandatory.');

$secondary = $source;
$secondary['source_type'] = 'trusted_secondary';
expect_true('autolex_official_source_type_denied' === $adapter->validate_source($secondary)->get_error_code(), 'Official adapter must reject secondary sources.');

$false_multi = $rows[0];
$false_multi['verification_status'] = 'multi_source_match';
expect_true('autolex_official_row_false_multi_source' === $adapter->normalize_row($false_multi)->get_error_code(), 'A single official source cannot claim multi-source confirmation.');

$missing = $rows[0];
unset($missing['observed_value']);
expect_true('autolex_official_row_missing_field' === $adapter->normalize_row($missing)->get_error_code(), 'Missing technical values must not be guessed.');

echo "Official source adapter smoke test passed.\n";
