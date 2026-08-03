<?php
/**
 * Deterministic contract for public Autolex 4.2 source cards.
 */

$root = dirname(__DIR__);
$bootstrap = $root . '/plugin/autolex-platform/includes/class-autolex-platform.php';
$cards = $root . '/plugin/autolex-platform/includes/class-autolex-source-cards.php';

if (!is_file($bootstrap) || !is_file($cards)) {
    fwrite(STDERR, "Missing source card implementation.\n");
    exit(1);
}

$bootstrap_php = file_get_contents($bootstrap);
$cards_php = file_get_contents($cards);

$required_bootstrap = array(
    "require_once __DIR__ . '/class-autolex-source-cards.php'",
    'Autolex_Source_Cards::instance()->register()',
);
foreach ($required_bootstrap as $marker) {
    if (false === strpos($bootstrap_php, $marker)) {
        fwrite(STDERR, "Source card bootstrap marker missing: {$marker}\n");
        exit(1);
    }
}

$required_markers = array(
    "add_shortcode('autolex_sources'",
    'Források és megerősítés',
    'get_entity_sources',
    'Autolex_Source_Provenance::claims_table()',
    'Autolex_Source_Provenance::evidence_table()',
    'Autolex_Source_Provenance::sources_table()',
    'ORDER BY c.conflict_count DESC',
    'target="_blank" rel="noopener noreferrer nofollow"',
    'data-source-status',
    'STATUS_MANUFACTURER',
    'STATUS_OFFICIAL',
    'STATUS_MULTI_SOURCE',
    'STATUS_SINGLE_SOURCE',
    'STATUS_CONFLICT',
    'STATUS_INCOMPLETE',
    'STATUS_VIN_REQUIRED',
    'mysql2date',
    'source_type_label',
);

foreach ($required_markers as $marker) {
    if (false === strpos($cards_php, $marker)) {
        fwrite(STDERR, "Source card contract marker missing: {$marker}\n");
        exit(1);
    }
}

$forbidden_patterns = array(
    '/http:\/\//i',
    '/DELETE\s+FROM/i',
    '/TRUNCATE\s+/i',
    '/DROP\s+TABLE/i',
    '/verification_status\s*=\s*[\'\"]multi_source_match[\'\"]/i',
);
foreach ($forbidden_patterns as $pattern) {
    if (1 === preg_match($pattern, $cards_php)) {
        fwrite(STDERR, "Source card implementation contains forbidden behavior.\n");
        exit(1);
    }
}

if (substr_count($cards_php, 'esc_html') < 8 || substr_count($cards_php, 'esc_attr') < 5 || false === strpos($cards_php, 'esc_url(')) {
    fwrite(STDERR, "Source card output escaping contract is incomplete.\n");
    exit(1);
}

if (false === strpos($cards_php, "0 === strpos(\$url, 'https://')")) {
    fwrite(STDERR, "Source card HTTPS fail-closed contract is missing.\n");
    exit(1);
}

echo "Autolex 4.2 source cards contract passed.\n";
