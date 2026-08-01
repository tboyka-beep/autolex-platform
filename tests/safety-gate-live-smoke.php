<?php
/**
 * Live, read-only validation for the official EU Safety Gate XML distribution.
 *
 * This test never writes WordPress or database state. It validates that the
 * official metadata can still resolve an allowlisted HTTPS XML resource and
 * that the response is structurally valid XML within the importer size limit.
 */

define('ABSPATH', __DIR__ . '/');

function wp_parse_url($url, $component = -1)
{
    return parse_url($url, $component);
}

function wp_strip_all_tags($value)
{
    return strip_tags($value);
}

require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-safety-gate.php';

const AUTOLEX_LIVE_ALLOWED_HOSTS = array(
    'data.europa.eu',
    'ec.europa.eu',
    'webgate.ec.europa.eu',
);

/**
 * @param string              $url     Official HTTPS URL.
 * @param int                 $max     Maximum accepted response size.
 * @param array<int,string>   $headers HTTP headers.
 * @return array{body:string,effective_url:string,content_type:string}
 */
function autolex_live_fetch($url, $max, $headers = array())
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required.');
    }

    $handle = curl_init($url);
    if (false === $handle) {
        throw new RuntimeException('Could not initialize cURL.');
    }

    curl_setopt_array($handle, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Autolex-Safety-Gate-Live-Smoke/1.0 (+https://autolex.hu/)',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    ));

    $body = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $effective_url = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
    $content_type = strtolower((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
    curl_close($handle);

    if (false === $body) {
        throw new RuntimeException('Network request failed: ' . $error);
    }
    if (200 !== $status) {
        throw new RuntimeException('Official source returned HTTP ' . $status . '.');
    }
    if ('' === trim($body)) {
        throw new RuntimeException('Official source returned an empty response.');
    }
    if (strlen($body) > $max) {
        throw new RuntimeException('Official source exceeded the response size limit.');
    }

    $host = strtolower((string) parse_url($effective_url, PHP_URL_HOST));
    if ('https' !== strtolower((string) parse_url($effective_url, PHP_URL_SCHEME))
        || !in_array($host, AUTOLEX_LIVE_ALLOWED_HOSTS, true)) {
        throw new RuntimeException('Request redirected outside the official EU allowlist.');
    }

    return array(
        'body' => $body,
        'effective_url' => $effective_url,
        'content_type' => $content_type,
    );
}

$metadata_errors = array();
$metadata_source = '';
$xml_url = '';

foreach (Autolex_Safety_Gate::DATASET_APIS as $metadata_url) {
    try {
        $response = autolex_live_fetch(
            $metadata_url,
            4 * 1024 * 1024,
            array('Accept: application/json')
        );
        $metadata = json_decode($response['body'], true);
        if (!is_array($metadata) || JSON_ERROR_NONE !== json_last_error()) {
            throw new RuntimeException('Metadata response is not valid JSON.');
        }

        $candidate = Autolex_Safety_Gate::discover_xml_url($metadata);
        $host = strtolower((string) parse_url($candidate, PHP_URL_HOST));
        if ('https' !== strtolower((string) parse_url($candidate, PHP_URL_SCHEME))
            || !in_array($host, AUTOLEX_LIVE_ALLOWED_HOSTS, true)) {
            throw new RuntimeException('No allowlisted HTTPS XML distribution was discovered.');
        }

        $metadata_source = $response['effective_url'];
        $xml_url = $candidate;
        break;
    } catch (Throwable $exception) {
        $metadata_errors[] = $metadata_url . ': ' . $exception->getMessage();
    }
}

if ('' === $xml_url) {
    fwrite(STDERR, "Safety Gate live metadata discovery failed.\n");
    foreach ($metadata_errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

try {
    $xml_response = autolex_live_fetch(
        $xml_url,
        Autolex_Safety_Gate::MAX_XML_BYTES,
        array('Accept: application/xml, text/xml;q=0.9, */*;q=0.1')
    );

    if (!function_exists('simplexml_load_string')) {
        throw new RuntimeException('The PHP SimpleXML extension is required.');
    }

    $previous = libxml_use_internal_errors(true);
    $root = simplexml_load_string(
        $xml_response['body'],
        'SimpleXMLElement',
        LIBXML_NONET | LIBXML_NOCDATA
    );
    $xml_errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (false === $root) {
        $message = $xml_errors ? trim((string) $xml_errors[0]->message) : 'unknown XML parser error';
        throw new RuntimeException('Official XML is invalid: ' . $message);
    }

    $nodes = $root->xpath('//*');
    if (!is_array($nodes) || count($nodes) < 2) {
        throw new RuntimeException('Official XML does not contain an alert-like document structure.');
    }

    echo json_encode(array(
        'metadata_source' => $metadata_source,
        'xml_source' => $xml_response['effective_url'],
        'xml_bytes' => strlen($xml_response['body']),
        'content_type' => $xml_response['content_type'],
        'root_element' => $root->getName(),
        'element_count' => count($nodes),
        'sha256' => hash('sha256', $xml_response['body']),
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Safety Gate live XML validation failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
