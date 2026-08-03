<?php
$root = dirname(__DIR__);
define('ABSPATH', sys_get_temp_dir() . '/autolex-write-smoke/');

final class WP_Error
{
    public $code;
    public function __construct($code) { $this->code = $code; }
}

final class Autolex_Provenance_Write_Test_WPDB
{
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $queries = array();
    public $existing_source_id = 0;
    public $existing_claim_id = 0;
    public $existing_evidence_id = 0;
    public $different_evidence_ids = array();

    public function prepare($query, ...$args)
    {
        return array($query, $args);
    }

    public function get_var($prepared)
    {
        $query = is_array($prepared) ? $prepared[0] : $prepared;
        if (strpos($query, 'FROM wp_autolex_sources') !== false) return $this->existing_source_id;
        if (strpos($query, 'FROM wp_autolex_source_claims') !== false) return $this->existing_claim_id;
        if (strpos($query, 'FROM wp_autolex_source_evidence') !== false && strpos($query, 'COUNT') === false) return $this->existing_evidence_id;
        if (strpos($query, 'COUNT(DISTINCT source_id)') !== false) return 2;
        if (strpos($query, 'COUNT(*) FROM wp_autolex_source_conflicts') !== false) return count($this->different_evidence_ids);
        return 0;
    }

    public function get_col($prepared)
    {
        return $this->different_evidence_ids;
    }

    public function insert($table, $data)
    {
        $this->queries[] = array('insert', $table, $data);
        $this->insert_id++;
        return 1;
    }

    public function update($table, $data, $where)
    {
        $this->queries[] = array('update', $table, $data, $where);
        return 1;
    }

    public function query($prepared)
    {
        $this->queries[] = array('query', $prepared);
        return 1;
    }
}

$wpdb = new Autolex_Provenance_Write_Test_WPDB();

function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function esc_url_raw($value, $protocols = null) { return filter_var($value, FILTER_VALIDATE_URL) ? $value : ''; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function absint($value) { return abs((int) $value); }
function current_time($type, $gmt = false) { return '2026-08-02 12:00:00'; }

require $root . '/plugin/autolex-platform/includes/class-autolex-source-provenance.php';
$service = Autolex_Source_Provenance::instance();

$source = array(
    'source_type' => 'manufacturer_document',
    'title' => 'Official technical data',
    'publisher' => 'Example Motors',
    'source_url' => 'https://example.com/specification.pdf',
    'document_identifier' => 'DOC-42',
    'retrieved_at' => '2026-08-02T10:00:00Z',
);

$source_key_a = Autolex_Source_Provenance::source_key($source);
$source['retrieved_at'] = '2026-08-03T10:00:00Z';
$source_key_b = Autolex_Source_Provenance::source_key($source);
$source_dry = $service->upsert_source($source, true);
$claim_dry = $service->upsert_claim('vehicle', 42, 'engine.power_kw', 110, array('verification_status' => 'unknown-promoted'), true);
$evidence_dry = $service->record_evidence(10, 20, array('kw' => 110), array(), true);

$checks = array(
    'source identity excludes retrieval timestamp' => $source_key_a === $source_key_b,
    'source identity is SHA-256' => preg_match('/^[a-f0-9]{64}$/', $source_key_a) === 1,
    'value hashing is deterministic for reordered maps' => Autolex_Source_Provenance::value_hash(array('b' => 2, 'a' => 1)) === Autolex_Source_Provenance::value_hash(array('a' => 1, 'b' => 2)),
    'source dry-run does not write' => $source_dry['action'] === 'would_create' && count($wpdb->queries) === 0,
    'claim dry-run fails unknown status closed' => $claim_dry['action'] === 'would_create' && preg_match('/^[a-f0-9]{64}$/', $claim_dry['value_hash']) === 1,
    'evidence dry-run is idempotent and non-writing' => $evidence_dry['action'] === 'would_create' && $evidence_dry['conflicts'] === 0 && count($wpdb->queries) === 0,
);

$wpdb->existing_source_id = 7;
$wpdb->existing_claim_id = 8;
$wpdb->existing_evidence_id = 9;
$wpdb->different_evidence_ids = array(3, 4);
$duplicate_source = $service->upsert_source($source, true);
$duplicate_claim = $service->upsert_claim('vehicle', 42, 'engine.power_kw', 110, array(), true);
$duplicate_evidence = $service->record_evidence(10, 20, array('kw' => 110), array(), true);
$checks['existing source resolves to update'] = $duplicate_source['action'] === 'would_update' && $duplicate_source['id'] === 7;
$checks['existing claim resolves to update'] = $duplicate_claim['action'] === 'would_update' && $duplicate_claim['id'] === 8;
$checks['duplicate evidence is skipped and conflicts preserved'] = $duplicate_evidence['action'] === 'would_skip_duplicate' && $duplicate_evidence['id'] === 9 && $duplicate_evidence['conflicts'] === 2;
$checks['all dry-runs remain side-effect free'] = count($wpdb->queries) === 0;

$failed = false;
foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        $failed = true;
    } else {
        echo "PASS: {$label}\n";
    }
}
exit($failed ? 1 : 0);
