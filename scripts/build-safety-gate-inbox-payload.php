<?php
/**
 * Acquire and validate the official EU Safety Gate XML on a trusted CI runner.
 *
 * The metadata XML published by Safety Gate is a weekly-report index, not the
 * alert payload itself. This builder therefore follows the official chain:
 * data.europa metadata -> weekly-report index -> newest weekly-report detail.
 * The detail XML is the only payload written to the production inbox.
 */

define('ABSPATH', __DIR__ . '/');
define('AUTOLEX_PLATFORM_VERSION', '4.2.0');

function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_strip_all_tags($value) { return strip_tags($value); }

require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-safety-gate.php';

const AUTOLEX_SAFETY_CI_CONTRACT = 'verified_inbox_v1';
const AUTOLEX_SAFETY_CI_ALLOWED_HOSTS = array('data.europa.eu', 'ec.europa.eu', 'webgate.ec.europa.eu');

function autolex_safety_ci_fail($message)
{
    fwrite(STDERR, 'SAFETY_GATE_CI_FAIL: ' . $message . "\n");
    exit(1);
}

function autolex_safety_ci_allowed_url($url)
{
    return 'https' === strtolower((string) parse_url($url, PHP_URL_SCHEME))
        && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), AUTOLEX_SAFETY_CI_ALLOWED_HOSTS, true);
}

/** @return array{body:string,effective_url:string,content_type:string} */
function autolex_safety_ci_fetch($url, $max, $headers = array())
{
    if (!autolex_safety_ci_allowed_url($url)) {
        throw new RuntimeException('Requested URL is outside the official EU HTTPS allowlist.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required.');
    }

    $last = 'Unknown network error.';
    for ($attempt = 1; $attempt <= 4; ++$attempt) {
        $handle = curl_init($url);
        if (false === $handle) {
            throw new RuntimeException('Could not initialize cURL.');
        }
        curl_setopt_array($handle, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Autolex-Safety-Gate-CI/1.0 (+https://autolex.hu/)',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FORBID_REUSE => true,
        ));
        $body = curl_exec($handle);
        $error = curl_error($handle);
        $errno = curl_errno($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $effective = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
        $content_type = strtolower((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
        curl_close($handle);

        $transient_http = 408 === $status || 425 === $status || 429 === $status || $status >= 500;
        $transient_curl = in_array($errno, array(
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_CONNECT,
            CURLE_OPERATION_TIMEDOUT,
            CURLE_RECV_ERROR,
            CURLE_SEND_ERROR,
            CURLE_GOT_NOTHING,
            CURLE_PARTIAL_FILE,
            CURLE_SSL_CONNECT_ERROR,
        ), true);

        if (false !== $body && 200 === $status) {
            if ('' === trim($body) || strlen($body) > $max) {
                throw new RuntimeException('Official source body is empty or exceeds the accepted size.');
            }
            if (!autolex_safety_ci_allowed_url($effective)) {
                throw new RuntimeException('Official request redirected outside the EU allowlist.');
            }
            return array('body' => $body, 'effective_url' => $effective, 'content_type' => $content_type);
        }

        $last = false === $body
            ? 'Network request failed: ' . ($error ?: 'cURL error ' . $errno)
            : 'Official source returned HTTP ' . $status . '.';
        if ($attempt >= 4 || (!$transient_http && !$transient_curl)) {
            throw new RuntimeException($last);
        }
        fwrite(STDERR, sprintf("Transient official Safety Gate failure %d/4: %s\n", $attempt, $last));
        sleep(2 ** ($attempt - 1));
    }
    throw new RuntimeException($last);
}

/** @return SimpleXMLElement */
function autolex_safety_ci_parse_xml($xml, $label)
{
    if (false !== stripos($xml, '<!DOCTYPE')) {
        throw new RuntimeException($label . ' unexpectedly contains a DOCTYPE declaration.');
    }
    if (!function_exists('simplexml_load_string')) {
        throw new RuntimeException('PHP SimpleXML is required.');
    }
    $previous = libxml_use_internal_errors(true);
    $root = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (false === $root) {
        $detail = $errors ? trim((string) $errors[0]->message) : 'unknown XML parser error';
        throw new RuntimeException($label . ' is invalid XML: ' . $detail);
    }
    if ('Safety-Gate' !== $root->getName()) {
        throw new RuntimeException($label . ' has an unexpected root element: ' . $root->getName());
    }
    return $root;
}

function autolex_safety_ci_flatten($node, $depth = 0)
{
    $fields = array();
    if ($depth > 3) {
        return $fields;
    }
    foreach ($node->children() as $name => $child) {
        $key = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', (string) $name));
        $text = trim((string) $child);
        if ('' !== $key && '' !== $text && !isset($fields[$key])) {
            $fields[$key] = $text;
        }
        if (count($child->children())) {
            $fields = array_merge($fields, autolex_safety_ci_flatten($child, $depth + 1));
        }
    }
    return $fields;
}

/** @return string ISO date or empty string. */
function autolex_safety_ci_normalize_date($value)
{
    $value = trim((string) $value);
    if ('' === $value) {
        return '';
    }
    foreach (array('d/m/Y', 'Y-m-d', 'd-m-Y') as $format) {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && (false === $errors || (0 === $errors['warning_count'] && 0 === $errors['error_count']))) {
            return $date->format('Y-m-d');
        }
    }
    return '';
}

/**
 * Selects the newest official weekly-report detail URL from the report index.
 *
 * @return array{url:string,reference:string,publication_date:string,year:int,week:int}
 */
function autolex_safety_ci_latest_report(SimpleXMLElement $index_root)
{
    $reports = array();
    foreach ($index_root->weeklyReport as $report) {
        $url = trim((string) $report->URL);
        if (!autolex_safety_ci_allowed_url($url)) {
            continue;
        }
        $publication = autolex_safety_ci_normalize_date((string) $report->publicationDate);
        $year = (int) $report->year;
        $week = (int) $report->week;
        $sort = $publication ? strtotime($publication . ' 00:00:00 UTC') : 0;
        if ($sort <= 0 && $year > 0 && $week > 0) {
            $fallback = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $fallback = $fallback->setISODate($year, $week, 5)->setTime(0, 0);
            $sort = $fallback->getTimestamp();
        }
        $reports[] = array(
            'url' => $url,
            'reference' => trim((string) $report->reference),
            'publication_date' => $publication,
            'year' => $year,
            'week' => $week,
            'sort' => $sort,
        );
    }
    if (!$reports) {
        throw new RuntimeException('Weekly-report index contains no allowlisted detail URL.');
    }
    usort($reports, static function ($a, $b) {
        if ($a['sort'] === $b['sort']) {
            return $b['week'] <=> $a['week'];
        }
        return $b['sort'] <=> $a['sort'];
    });
    unset($reports[0]['sort']);
    return $reports[0];
}

$output_dir = isset($argv[1]) ? rtrim((string) $argv[1], '/\\') : '';
$commit_sha = strtolower(trim((string) getenv('GITHUB_SHA')));
$run_id = (int) getenv('GITHUB_RUN_ID');
if ('' === $output_dir || !preg_match('/^[a-f0-9]{40}$/', $commit_sha) || $run_id <= 0) {
    autolex_safety_ci_fail('Output directory, 40-char GITHUB_SHA and positive GITHUB_RUN_ID are required.');
}
if (!is_dir($output_dir) && !mkdir($output_dir, 0700, true) && !is_dir($output_dir)) {
    autolex_safety_ci_fail('Could not create output directory.');
}

try {
    $discovery_metadata_source = '';
    $report_index_url = '';
    $metadata_errors = array();
    foreach (Autolex_Safety_Gate::DATASET_APIS as $metadata_url) {
        try {
            $metadata_response = autolex_safety_ci_fetch($metadata_url, 4 * 1024 * 1024, array('Accept: application/json'));
            $metadata = json_decode($metadata_response['body'], true);
            if (!is_array($metadata) || JSON_ERROR_NONE !== json_last_error()) {
                throw new RuntimeException('Metadata response is not valid JSON.');
            }
            $candidate = Autolex_Safety_Gate::discover_xml_url($metadata);
            if (!autolex_safety_ci_allowed_url($candidate)) {
                throw new RuntimeException('No allowlisted HTTPS XML distribution was discovered.');
            }
            $discovery_metadata_source = $metadata_response['effective_url'];
            $report_index_url = $candidate;
            break;
        } catch (Throwable $exception) {
            $metadata_errors[] = $metadata_url . ': ' . $exception->getMessage();
        }
    }
    if ('' === $report_index_url) {
        throw new RuntimeException('Metadata discovery failed: ' . implode(' | ', $metadata_errors));
    }

    $index_response = autolex_safety_ci_fetch(
        $report_index_url,
        Autolex_Safety_Gate::MAX_XML_BYTES,
        array('Accept: application/xml, text/xml;q=0.9, */*;q=0.1')
    );
    $index_root = autolex_safety_ci_parse_xml($index_response['body'], 'Official Safety Gate weekly-report index');
    if (0 === count($index_root->weeklyReport)) {
        throw new RuntimeException('Official Safety Gate weekly-report index contains no reports.');
    }
    $latest_report = autolex_safety_ci_latest_report($index_root);

    $detail_response = autolex_safety_ci_fetch(
        $latest_report['url'],
        Autolex_Safety_Gate::MAX_XML_BYTES,
        array('Accept: application/xml, text/xml;q=0.9, */*;q=0.1')
    );
    $xml = $detail_response['body'];
    $detail_root = autolex_safety_ci_parse_xml($xml, 'Official Safety Gate weekly-report detail');
    $notifications = $detail_root->xpath('//notifications') ?: array();
    if (!$notifications) {
        throw new RuntimeException('Official Safety Gate weekly-report detail contains no notifications.');
    }

    $report_date = autolex_safety_ci_normalize_date((string) $detail_root->report_date);
    if ('' === $report_date) {
        $report_date = $latest_report['publication_date'];
    }

    $vehicle_alerts = array();
    foreach ($notifications as $notification) {
        $fields = autolex_safety_ci_flatten($notification);
        if ($report_date && empty($fields['notificationdate'])) {
            $fields['notificationdate'] = $report_date;
        }
        $alert = Autolex_Safety_Gate::normalize_alert($fields, $detail_response['effective_url']);
        if ($alert) {
            $vehicle_alerts[$alert['fingerprint']] = true;
        }
    }
    if (!$vehicle_alerts) {
        throw new RuntimeException('Latest official weekly report contains no vehicle alerts recognized by the production normalization contract.');
    }

    $sha = hash('sha256', $xml);
    $bytes = strlen($xml);
    $payload_name = 'safety-gate-' . $sha . '.xml';
    $retrieved_at = gmdate('c');
    $manifest = array(
        'contract' => AUTOLEX_SAFETY_CI_CONTRACT,
        'payload_file' => $payload_name,
        'source_url' => $detail_response['effective_url'],
        'metadata_source' => $index_response['effective_url'],
        'sha256' => $sha,
        'bytes' => $bytes,
        'retrieved_at' => $retrieved_at,
        'commit_sha' => $commit_sha,
        'workflow_run_id' => $run_id,
    );
    $evidence = array_merge($manifest, array(
        'discovery_metadata_source' => $discovery_metadata_source,
        'report_index_url' => $index_response['effective_url'],
        'report_index_sha256' => hash('sha256', $index_response['body']),
        'latest_report_reference' => $latest_report['reference'],
        'latest_report_publication_date' => $latest_report['publication_date'],
        'latest_report_year' => $latest_report['year'],
        'latest_report_week' => $latest_report['week'],
        'content_type' => $detail_response['content_type'],
        'root_element' => $detail_root->getName(),
        'notification_count' => count($notifications),
        'recognized_vehicle_alerts' => count($vehicle_alerts),
    ));

    if (false === file_put_contents($output_dir . '/' . $payload_name, $xml, LOCK_EX)
        || false === file_put_contents($output_dir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX)
        || false === file_put_contents($output_dir . '/evidence.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX)) {
        throw new RuntimeException('Could not write Safety Gate CI payload/evidence files.');
    }

    echo 'SAFETY_GATE_CI_OK: ' . json_encode($evidence, JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $exception) {
    autolex_safety_ci_fail($exception->getMessage());
}
