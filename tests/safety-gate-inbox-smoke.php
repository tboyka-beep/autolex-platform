<?php
/**
 * Deterministic security and normalization contract for verified Safety Gate inbox.
 */

define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', __DIR__ . '/wp-content');
define('AUTOLEX_PLATFORM_VERSION', '4.2.0');

final class WP_Error
{
    private $code;
    private $message;
    public function __construct($code, $message) { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function esc_url_raw($url, $protocols = null) { unset($protocols); return filter_var($url, FILTER_SANITIZE_URL); }
function get_option($name, $default = false)
{
    if ('autolex_safety_gate_transport_mode' === $name) {
        return Autolex_Safety_Gate_Inbox::CONTRACT;
    }
    return $default;
}

require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-safety-gate.php';
require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-safety-gate-inbox.php';

function expect_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Alerts>
  <Alert>
    <Reference>A12/01234/26</Reference>
    <NotificationDate>2026-08-01</NotificationDate>
    <ProductCategory>Motor vehicles</ProductCategory>
    <ProductName>Passenger car</ProductName>
    <Brand>Example Motors</Brand>
    <Model>Road One</Model>
    <RiskType>Injuries</RiskType>
    <RiskDescription>A component may fail.</RiskDescription>
    <Measures>Recall and repair affected vehicles.</Measures>
    <NotifyingCountry>Germany</NotifyingCountry>
  </Alert>
</Alerts>
XML;

$now = time();
$sha = hash('sha256', $xml);
$manifest = array(
    'contract' => Autolex_Safety_Gate_Inbox::CONTRACT,
    'payload_file' => 'safety-gate-' . $sha . '.xml',
    'source_url' => 'https://ec.europa.eu/safety-gate/weekly.xml',
    'metadata_source' => 'https://data.europa.eu/api/hub/search/datasets/rapex-rapid-alert-system-non-food',
    'sha256' => $sha,
    'bytes' => strlen($xml),
    'retrieved_at' => gmdate('c', $now - 60),
    'commit_sha' => str_repeat('a', 40),
    'workflow_run_id' => 123456789,
);

$valid = Autolex_Safety_Gate_Inbox::validate_manifest($manifest, $now);
expect_true(!is_wp_error($valid), 'Valid official inbox manifest must be accepted.');
expect_true($sha === $valid['sha256'], 'Manifest SHA must survive normalization.');
expect_true(true === Autolex_Safety_Gate_Inbox::verify_payload($valid, $xml), 'Matching XML bytes/hash must verify.');

$untrusted = $manifest;
$untrusted['source_url'] = 'https://example.com/weekly.xml';
expect_true('autolex_safety_inbox_untrusted_source' === Autolex_Safety_Gate_Inbox::validate_manifest($untrusted, $now)->get_error_code(), 'Non-EU source host must fail closed.');

$traversal = $manifest;
$traversal['payload_file'] = '../' . $manifest['payload_file'];
expect_true('autolex_safety_inbox_invalid_payload_name' === Autolex_Safety_Gate_Inbox::validate_manifest($traversal, $now)->get_error_code(), 'Payload traversal must fail closed.');

$wrong_name = $manifest;
$wrong_name['payload_file'] = 'weekly.xml';
expect_true('autolex_safety_inbox_invalid_payload_name' === Autolex_Safety_Gate_Inbox::validate_manifest($wrong_name, $now)->get_error_code(), 'Payload filename must be bound to its SHA.');

$stale = $manifest;
$stale['retrieved_at'] = gmdate('c', $now - Autolex_Safety_Gate_Inbox::MAX_MANIFEST_AGE - 1);
expect_true('autolex_safety_inbox_stale_manifest' === Autolex_Safety_Gate_Inbox::validate_manifest($stale, $now)->get_error_code(), 'Stale manifest must fail closed.');

$future = $manifest;
$future['retrieved_at'] = gmdate('c', $now + Autolex_Safety_Gate_Inbox::MAX_FUTURE_SKEW + 1);
expect_true('autolex_safety_inbox_stale_manifest' === Autolex_Safety_Gate_Inbox::validate_manifest($future, $now)->get_error_code(), 'Future-dated manifest must fail closed.');

$bad_commit = $manifest;
$bad_commit['commit_sha'] = 'main';
expect_true('autolex_safety_inbox_invalid_commit' === Autolex_Safety_Gate_Inbox::validate_manifest($bad_commit, $now)->get_error_code(), 'Manifest must be commit-SHA bound.');

$bad_hash = $valid;
$bad_hash['sha256'] = str_repeat('0', 64);
expect_true('autolex_safety_inbox_hash_mismatch' === Autolex_Safety_Gate_Inbox::verify_payload($bad_hash, $xml)->get_error_code(), 'Payload hash mismatch must fail closed.');

$bad_size = $valid;
$bad_size['bytes']++;
expect_true('autolex_safety_inbox_size_mismatch' === Autolex_Safety_Gate_Inbox::verify_payload($bad_size, $xml)->get_error_code(), 'Payload byte mismatch must fail closed.');

$doctype = "<?xml version=\"1.0\"?><!DOCTYPE x [<!ENTITY e SYSTEM \"file:///etc/passwd\">]><x>&e;</x>";
$doctype_manifest = $valid;
$doctype_manifest['bytes'] = strlen($doctype);
$doctype_manifest['sha256'] = hash('sha256', $doctype);
expect_true('autolex_safety_inbox_doctype_denied' === Autolex_Safety_Gate_Inbox::verify_payload($doctype_manifest, $doctype)->get_error_code(), 'DOCTYPE/XXE payload must fail closed before parsing.');

$reflection = new ReflectionClass('Autolex_Safety_Gate_Inbox');
$instance = $reflection->newInstanceWithoutConstructor();
$parse = $reflection->getMethod('parse_xml');
$parse->setAccessible(true);
$alerts = $parse->invoke($instance, $xml, $manifest['source_url']);
expect_true(1 === count($alerts), 'Verified XML must normalize one vehicle alert.');
expect_true('Example Motors' === $alerts[0]['brand'], 'Inbox parser must reuse Safety Gate brand normalization.');
expect_true('Road One' === $alerts[0]['model'], 'Inbox parser must reuse Safety Gate model normalization.');
expect_true($manifest['source_url'] === $alerts[0]['source_url'], 'Normalized alert must retain the verified official source URL.');

$legacy_event = (object) array('hook' => 'autolex_safety_gate_sync');
expect_true(false === $instance->block_legacy_transport_schedule(null, $legacy_event, false), 'Verified inbox mode must block legacy outbound weekly scheduling.');
$retry_event = (object) array('hook' => Autolex_Safety_Gate::RETRY_HOOK);
expect_true(false === $instance->block_legacy_transport_schedule(null, $retry_event, false), 'Verified inbox mode must block legacy outbound recovery scheduling.');
$other_event = (object) array('hook' => 'unrelated_hook');
expect_true(null === $instance->block_legacy_transport_schedule(null, $other_event, false), 'Unrelated cron events must remain untouched.');

$source = file_get_contents(dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-safety-gate-inbox.php');
foreach (array(
    "'/safety-gate-ingest-status'",
    "'methods' => 'GET'",
    "'permission_callback' => '__return_true'",
    "LIBXML_NONET | LIBXML_NOCDATA",
    "hash_equals",
    "is_link",
    "realpath",
    "autolex_safety_gate_transport_mode",
    "wp_clear_scheduled_hook('autolex_safety_gate_sync')",
) as $marker) {
    expect_true(false !== strpos($source, $marker), 'Inbox security/status marker missing: ' . $marker);
}
expect_true(false === strpos($source, "'methods' => 'POST'"), 'Inbox must not expose a public write REST endpoint.');

echo "Verified Safety Gate inbox smoke test passed.\n";
