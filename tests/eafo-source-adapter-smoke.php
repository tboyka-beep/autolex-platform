<?php
/** Deterministic contract for the reviewed EAFO source adapter. */

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
require __DIR__ . '/../plugin/autolex-platform/includes/class-autolex-eafo-source-adapter.php';

function expect_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$adapter = new Autolex_EAFO_Source_Adapter();
$source = array(
    'url' => 'https://alternative-fuels-observatory.ec.europa.eu/transport-mode/road/european-union-eu27/country-comparison',
    'retrieved_at' => '2026-08-03T03:40:00Z',
    'usage_reviewed' => true,
    'usage_note' => 'Official European Commission EAFO observation; factual aggregate values only.',
    'document_id' => 'eafo-road-country-comparison',
);
$row = array(
    'entity_id' => 348,
    'record_id' => 'HU-2025-public-recharging-points',
    'geo' => 'HU',
    'time' => '2025',
    'indicator' => 'public_recharging_points',
    'value' => '4821',
    'unit' => 'number',
);

$result = $adapter->import($source, array($row), array('dry_run' => true));
expect_true(!is_wp_error($result), 'Reviewed official EAFO record must be accepted.');
expect_true('dry_run_completed' === $result['status'], 'Dry-run must reach the shared importer.');
$call = Autolex_Provenance_Batch_Importer::instance()->last_call;
expect_true('official-source-v1' === $call['adapter'], 'EAFO must delegate to the common official adapter.');
expect_true('official_statistics' === $call['source']['source_type'], 'EAFO data must be classified as official statistics.');
expect_true('European Alternative Fuels Observatory' === $call['source']['publisher'], 'Publisher must be normalized deterministically.');
expect_true(1 === count($call['records']), 'One EAFO observation must become one field-level claim.');
expect_true('market_stat' === $call['records'][0]['entity_type'], 'EAFO observations must remain separate from vehicle technical claims.');
expect_true('alternative_fuels.hu.2025.public_recharging_points' === $call['records'][0]['field_path'], 'Country, year and indicator must form the stable field path.');
expect_true('single_source_confirmed' === $call['records'][0]['verification_status'], 'One EAFO record remains single-source confirmed.');
expect_true(false !== strpos($call['records'][0]['source_locator'], 'HU-2025-public-recharging-points'), 'Every claim must retain its EAFO record identifier.');

$untrusted = $source;
$untrusted['url'] = 'https://example.com/eafo.json';
expect_true('autolex_eafo_untrusted_host' === $adapter->normalize_source($untrusted)->get_error_code(), 'Non-EAFO hosts must fail closed.');

$unreviewed = $source;
$unreviewed['usage_reviewed'] = false;
expect_true('autolex_eafo_usage_not_reviewed' === $adapter->normalize_source($unreviewed)->get_error_code(), 'Unreviewed usage must fail closed.');

$missing = $row;
unset($missing['value']);
expect_true('autolex_eafo_missing_row_field' === $adapter->normalize_row($missing)->get_error_code(), 'Missing values must not be guessed.');

$invalid = $row;
$invalid['value'] = '-1';
expect_true('autolex_eafo_invalid_value' === $adapter->normalize_row($invalid)->get_error_code(), 'Negative observations must fail closed.');

$unknown = $row;
$unknown['indicator'] = 'estimated_vehicle_range';
expect_true('autolex_eafo_unknown_indicator' === $adapter->normalize_row($unknown)->get_error_code(), 'Unknown indicators must not become arbitrary claims.');

$bad_dimension = $row;
$bad_dimension['time'] = 'latest';
expect_true('autolex_eafo_invalid_dimension' === $adapter->normalize_row($bad_dimension)->get_error_code(), 'Non-deterministic dimensions must fail closed.');

echo "EAFO source adapter smoke test passed.\n";
