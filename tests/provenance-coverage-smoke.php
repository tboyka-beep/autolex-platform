<?php
/**
 * Deterministic contract for the read-only provenance coverage endpoint.
 */

$root = dirname(__DIR__);
$bootstrap = $root . '/plugin/autolex-platform/includes/class-autolex-platform.php';
$coverage = $root . '/plugin/autolex-platform/includes/class-autolex-provenance-coverage.php';

if (!is_file($bootstrap) || !is_file($coverage)) {
    fwrite(STDERR, "Missing provenance coverage implementation.\n");
    exit(1);
}

$bootstrap_php = file_get_contents($bootstrap);
$coverage_php = file_get_contents($coverage);

$required_bootstrap = array(
    "require_once __DIR__ . '/class-autolex-provenance-coverage.php'",
    'Autolex_Provenance_Coverage::instance()->register()',
);
foreach ($required_bootstrap as $marker) {
    if (false === strpos($bootstrap_php, $marker)) {
        fwrite(STDERR, "Coverage bootstrap marker missing: {$marker}\n");
        exit(1);
    }
}

$required_markers = array(
    "'/provenance-coverage'",
    "'methods'             => 'GET'",
    "'permission_callback' => '__return_true'",
    'get_coverage_response',
    'get_coverage',
    'Autolex_Source_Provenance::claims_table()',
    'Autolex_Source_Provenance::sources_table()',
    'Autolex_Source_Provenance::evidence_table()',
    'COUNT(DISTINCT CONCAT(entity_type',
    'conflicting_claims',
    'verification_status',
    'source_types',
    'entity_types',
    "'Cache-Control', 'public, max-age=300, stale-while-revalidate=60'",
    "'generated_at'       => gmdate('c')",
    "'schema_version'     => '1.1.0'",
    'array_fill_keys(Autolex_Source_Provenance::verification_statuses(), 0)',
    "array('vehicle', 'engine', 'generation', 'model', 'market_stat')",
    'Autolex_Source_Cards::validate_entity_type',
);
foreach ($required_markers as $marker) {
    if (false === strpos($coverage_php, $marker)) {
        fwrite(STDERR, "Coverage contract marker missing: {$marker}\n");
        exit(1);
    }
}

$forbidden_patterns = array(
    '/DELETE\s+FROM/i',
    '/TRUNCATE\s+/i',
    '/DROP\s+TABLE/i',
    '/INSERT\s+INTO/i',
    '/UPDATE\s+[^\n]+SET/i',
    '/methods[^\n]+POST/i',
    '/methods[^\n]+PUT/i',
    '/methods[^\n]+DELETE/i',
);
foreach ($forbidden_patterns as $pattern) {
    if (1 === preg_match($pattern, $coverage_php)) {
        fwrite(STDERR, "Coverage implementation contains forbidden behavior.\n");
        exit(1);
    }
}

echo "Autolex 4.2 provenance coverage contract passed.\n";
