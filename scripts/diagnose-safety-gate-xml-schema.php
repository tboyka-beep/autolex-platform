<?php
/** Read-only XML schema diagnostics for the current official Safety Gate feed. */

$url = 'https://ec.europa.eu/safety-gate-alerts/api/download/weeklyReport/list/xml/en';
$allowed = array('ec.europa.eu', 'data.europa.eu', 'webgate.ec.europa.eu');

$handle = curl_init($url);
if (false === $handle) {
    fwrite(STDERR, "DIAG_FAIL: curl init\n");
    exit(1);
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
    fwrite(STDERR, 'DIAG_FAIL: ' . ($error ?: 'HTTP ' . $status) . "\n");
    exit(1);
}
$host = strtolower((string) parse_url($effective, PHP_URL_HOST));
if (!in_array($host, $allowed, true)) {
    fwrite(STDERR, "DIAG_FAIL: redirect outside allowlist\n");
    exit(1);
}
if (false !== stripos($xml, '<!DOCTYPE')) {
    fwrite(STDERR, "DIAG_FAIL: doctype present\n");
    exit(1);
}

$previous = libxml_use_internal_errors(true);
$root = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
libxml_clear_errors();
libxml_use_internal_errors($previous);
if (false === $root) {
    fwrite(STDERR, "DIAG_FAIL: invalid XML\n");
    exit(1);
}

$tag_counts = array();
$path_counts = array();
$interesting = array();
$walk = static function ($node, $path, $depth) use (&$walk, &$tag_counts, &$path_counts, &$interesting) {
    if ($depth > 7) {
        return;
    }
    foreach ($node->children() as $name => $child) {
        $name = (string) $name;
        $child_path = $path . '/' . $name;
        $tag_counts[$name] = ($tag_counts[$name] ?? 0) + 1;
        $path_counts[$child_path] = ($path_counts[$child_path] ?? 0) + 1;
        if (preg_match('/brand|model|category|product|risk|reference|notification|vehicle|measure|country|date|type/i', $child_path)) {
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

$top_tags = array_slice($tag_counts, 0, 60, true);
$top_paths = array_slice($path_counts, 0, 80, true);
$interesting_paths = array_slice($interesting, 0, 120, true);

// Print only schema names/counts. No alert values are emitted.
echo json_encode(array(
    'root' => $root->getName(),
    'bytes' => strlen($xml),
    'sha256' => hash('sha256', $xml),
    'top_tags' => $top_tags,
    'top_paths' => $top_paths,
    'interesting_paths' => $interesting_paths,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
