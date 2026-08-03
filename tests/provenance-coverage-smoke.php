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

// Public reporting must stay bounded: one totals query plus three grouped queries.
$query_calls = substr_count($coverage_php, '$wpdb->get_row(') + substr_count($coverage_php, '$wpdb->get_results(');
if (4 !== $query_calls) {
    fwrite(STDERR, "Coverage query budget changed unexpectedly: {$query_calls} queries.\n");
    exit(1);
}

$required_statuses = array(
    'manufacturer_source',
    'official_registry',
    'multi_source_match',
    'single_source_confirmed',
    'source_conflict',
    'incomplete',
    'vin_required',
);
$provenance_file = $root . '/plugin/autolex-platform/includes/class-autolex-source-provenance.php';
$provenance_php = is_file($provenance_file) ? file_get_contents($provenance_file) : '';
foreach ($required_statuses as $status) {
    if (false === strpos($provenance_php, "'{$status}'")) {
        fwrite(STDERR, "Required verification status missing from provenance schema: {$status}\n");
        exit(1);
    }
}

// Every SQL statement in the endpoint must remain an aggregate SELECT.
preg_match_all('/"(SELECT[^\"]+)"/s', $coverage_php, $sql_matches);
if (4 !== count($sql_matches[1])) {
    fwrite(STDERR, "Coverage SQL contract must expose exactly four SELECT statements.\n");
    exit(1);
}
foreach ($sql_matches[1] as $sql) {
    if (0 !== stripos(ltrim($sql), 'SELECT ')) {
        fwrite(STDERR, "Coverage contains a non-SELECT SQL statement.\n");
        exit(1);
    }
    if (false !== stripos($sql, 'SELECT *')) {
        fwrite(STDERR, "Coverage queries must not use SELECT *.\n");
        exit(1);
    }
}

$forbidden_patterns = array(
    '/DELETE\s+FROM/i',
    '/TRUNCATE\s+/i',
    '/DROP\s+TABLE/i',
    '/ALTER\s+TABLE/i',
    '/CREATE\s+TABLE/i',
    '/INSERT\s+INTO/i',
    '/REPLACE\s+INTO/i',
    '/UPDATE\s+[^\n]+SET/i',
    '/methods[^\n]+POST/i',
    '/methods[^\n]+PUT/i',
    '/methods[^\n]+PATCH/i',
    '/methods[^\n]+DELETE/i',
    '/wp_remote_(get|post|request)/i',
);
foreach ($forbidden_patterns as $pattern) {
    if (1 === preg_match($pattern, $coverage_php)) {
        fwrite(STDERR, "Coverage implementation contains forbidden behavior.\n");
        exit(1);
    }
}

echo "Autolex 4.2 provenance coverage QA contract passed.\n";
