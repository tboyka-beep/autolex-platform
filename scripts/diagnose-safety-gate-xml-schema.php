<?php
/** Read-only XML schema diagnostics for the current official Safety Gate feed. */

$index_url = 'https://ec.europa.eu/safety-gate-alerts/api/download/weeklyReport/list/xml/en';
$allowed = array('ec.europa.eu', 'data.europa.eu', 'webgate.ec.europa.eu');

function diag_fetch($url, $allowed)
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ('https' !== strtolower((string) parse_url($url, PHP_URL_SCHEME)) || !in_array($host, $allowed, true)) {
        throw new RuntimeException('URL outside allowlist: ' . $url);
    }
    $handle = curl_init($url);
    if (false === $handle) {
        throw new RuntimeException('curl init failed');
    }
    curl_setopt_array($handle, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_USERAGENT => 'Autolex-Safety-Gate-Schema-Diagnostic/1.0',
    ));
    $xml = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $effective = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($handle);
    curl_close($handle);
    if (false === $xml || 200 !== $status) {
        throw new RuntimeException($error ?: 'HTTP ' . $status);
    }
    $effective_host = strtolower((string) parse_url($effective, PHP_URL_HOST));
    if (!in_array($effective_host, $allowed, true)) {
        throw new RuntimeException('redirect outside allowlist');
    }
    if (false !== stripos($xml, '<!DOCTYPE')) {
        throw new RuntimeException('doctype present');
    }
    return array('body' => $xml, 'effective_url' => $effective);
}

function diag_xml($xml)
{
    $previous = libxml_use_internal_errors(true);
    $root = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (false === $root) {
        throw new RuntimeException('invalid XML');
    }
    return $root;
}

function diag_schema($root)
{
    $tag_counts = array();
    $path_counts = array();
    $interesting = array();
    $walk = static function ($node, $path, $depth) use (&$walk, &$tag_counts, &$path_counts, &$interesting) {
        if ($depth > 9) {
            return;
        }
        foreach ($node->children() as $name => $child) {
            $name = (string) $name;
            $child_path = $path . '/' . $name;
            $tag_counts[$name] = ($tag_counts[$name] ?? 0) + 1;
            $path_counts[$child_path] = ($path_counts[$child_path] ?? 0) + 1;
            if (preg_match('/brand|model|category|product|risk|reference|notification|vehicle|measure|country|date|type|description|recall/i', $child_path)) {
                $interesting[$child_path] = ($interesting[$child_path] ?? 0) + 1;
            }
            if (count($child->children())) {
                $walk($child, $child_path, $depth + 1);
            }
        }
    };
    $walk($root, '/' . $root->getName(), 0);
    arsort($tag_counts);
    arsort($path_counts);
    arsort($interesting);
    return array(
        'root' => $root->getName(),
        'top_tags' => array_slice($tag_counts, 0, 80, true),
        'top_paths' => array_slice($path_counts, 0, 100, true),
        'interesting_paths' => array_slice($interesting, 0, 160, true),
    );
}

try {
    $index_response = diag_fetch($index_url, $allowed);
    $index_root = diag_xml($index_response['body']);
    $index_schema = diag_schema($index_root);

    $report_samples = array();
    $first_report_url = '';
    foreach ($index_root->weeklyReport as $report) {
        $target = trim((string) $report->URL);
        $target_host = strtolower((string) parse_url($target, PHP_URL_HOST));
        if ('' === $first_report_url && in_array($target_host, $allowed, true)) {
            $first_report_url = $target;
        }
        $report_samples[] = array(
            'reference' => trim((string) $report->reference),
            'publicationDate' => trim((string) $report->publicationDate),
            'year' => trim((string) $report->year),
            'week' => trim((string) $report->week),
            'url' => $target,
            'url_host_allowed' => in_array($target_host, $allowed, true),
        );
        if (count($report_samples) >= 3) {
            break;
        }
    }
    if ('' === $first_report_url) {
        throw new RuntimeException('no allowlisted weekly report detail URL');
    }

    $detail_response = diag_fetch($first_report_url, $allowed);
    $detail_root = diag_xml($detail_response['body']);
    $detail_schema = diag_schema($detail_root);

    // Public schema diagnostics only. A handful of enum-like values are useful
    // for mapping and contain no user/private information.
    $enum_samples = array();
    foreach ($detail_root->xpath('//*') ?: array() as $node) {
        $name = (string) $node->getName();
        if (!preg_match('/category|risk|country|type/i', $name) || count($node->children())) {
            continue;
        }
        $value = trim((string) $node);
        if ('' !== $value && strlen($value) <= 120) {
            $enum_samples[$name . '=' . $value] = true;
        }
        if (count($enum_samples) >= 20) {
            break;
        }
    }

    echo json_encode(array(
        'index' => array_merge($index_schema, array(
            'bytes' => strlen($index_response['body']),
            'sha256' => hash('sha256', $index_response['body']),
            'report_samples' => $report_samples,
        )),
        'latest_detail' => array_merge($detail_schema, array(
            'url' => $detail_response['effective_url'],
            'bytes' => strlen($detail_response['body']),
            'sha256' => hash('sha256', $detail_response['body']),
            'enum_samples' => array_keys($enum_samples),
        )),
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'DIAG_FAIL: ' . $exception->getMessage() . "\n");
    exit(1);
}
