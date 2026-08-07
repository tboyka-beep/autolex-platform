<?php

define('ABSPATH', __DIR__ . '/');
define('AUTOLEX_PLATFORM_VERSION', '4.2.0');

function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_strip_all_tags($value) { return strip_tags($value); }

class WP_Error
{
    private $message;
    public function __construct($code = '', $message = '') { $this->message = (string) $message; }
    public function get_error_message() { return $this->message; }
}

$GLOBALS['autolex_safety_http_queue'] = array();
$GLOBALS['autolex_safety_http_calls'] = array();

function wp_safe_remote_get($url, $args = array())
{
    $GLOBALS['autolex_safety_http_calls'][] = array('url' => $url, 'args' => $args);
    if (!$GLOBALS['autolex_safety_http_queue']) {
        return new WP_Error('empty_mock_queue', 'Mock response queue is empty.');
    }
    return array_shift($GLOBALS['autolex_safety_http_queue']);
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($response) { return (int) ($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body($response) { return (string) ($response['body'] ?? ''); }

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

if (!Autolex_Safety_Gate::is_transient_http_status(503)
    || !Autolex_Safety_Gate::is_transient_http_status(429)
    || Autolex_Safety_Gate::is_transient_http_status(404)) {
    fwrite(STDERR, "Safety Gate transient HTTP classification failed.\n");
    exit(1);
}

$reflection = new ReflectionClass('Autolex_Safety_Gate');
$instance = $reflection->newInstanceWithoutConstructor();
$fetch = $reflection->getMethod('fetch_text');
$fetch->setAccessible(true);

$GLOBALS['autolex_safety_http_calls'] = array();
$GLOBALS['autolex_safety_http_queue'] = array(
    new WP_Error('http_request_failed', 'cURL error 56: Connection closed abruptly'),
    array('response' => array('code' => 200), 'body' => '<ok/>'),
);
$body = $fetch->invoke($instance, 'https://data.europa.eu/test.xml', 2048, array('Accept' => 'application/xml'));
if ('<ok/>' !== $body || 2 !== count($GLOBALS['autolex_safety_http_calls'])) {
    fwrite(STDERR, "Safety Gate WP_Error retry failed.\n");
    exit(1);
}
$first_args = $GLOBALS['autolex_safety_http_calls'][0]['args'];
if ('1.1' !== ($first_args['httpversion'] ?? '')
    || empty($first_args['reject_unsafe_urls'])
    || 2048 !== ($first_args['limit_response_size'] ?? 0)
    || Autolex_Safety_Gate::FETCH_TIMEOUT !== ($first_args['timeout'] ?? 0)) {
    fwrite(STDERR, "Safety Gate hardened HTTP request arguments failed.\n");
    exit(1);
}

$GLOBALS['autolex_safety_http_calls'] = array();
$GLOBALS['autolex_safety_http_queue'] = array(
    array('response' => array('code' => 503), 'body' => 'busy'),
    array('response' => array('code' => 200), 'body' => '<recovered/>'),
);
$body = $fetch->invoke($instance, 'https://ec.europa.eu/test.xml', 2048, array());
if ('<recovered/>' !== $body || 2 !== count($GLOBALS['autolex_safety_http_calls'])) {
    fwrite(STDERR, "Safety Gate transient HTTP retry failed.\n");
    exit(1);
}

$GLOBALS['autolex_safety_http_calls'] = array();
$GLOBALS['autolex_safety_http_queue'] = array(
    array('response' => array('code' => 404), 'body' => 'not found'),
    array('response' => array('code' => 200), 'body' => 'must not be used'),
);
$failed_closed = false;
try {
    $fetch->invoke($instance, 'https://ec.europa.eu/missing.xml', 2048, array());
} catch (RuntimeException $exception) {
    $failed_closed = false !== strpos($exception->getMessage(), 'HTTP 404');
}
if (!$failed_closed || 1 !== count($GLOBALS['autolex_safety_http_calls'])) {
    fwrite(STDERR, "Safety Gate non-transient HTTP did not fail closed.\n");
    exit(1);
}

$source = file_get_contents(dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-safety-gate.php');
foreach (array(
    "const RETRY_HOOK = 'autolex_safety_gate_retry'",
    "wp_schedule_single_event(time() + 5, self::RETRY_HOOK)",
    "wp_schedule_single_event(time() + (15 * MINUTE_IN_SECONDS), self::RETRY_HOOK)",
    "wp_clear_scheduled_hook(self::RETRY_HOOK)",
    "add_action(self::RETRY_HOOK, array(\$this, 'sync'))",
) as $marker) {
    if (false === strpos($source, $marker)) {
        fwrite(STDERR, "Safety Gate recovery scheduling marker missing: {$marker}\n");
        exit(1);
    }
}

echo "Safety Gate smoke tests passed.\n";
