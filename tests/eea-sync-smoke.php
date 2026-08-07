<?php
/**
 * Smoke checks for the allowlisted EEA Discodata query builder and response contract.
 */

define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-eea-sync.php';
require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-eea-failure-telemetry.php';
require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-eea-api-rejection-recovery.php';

$query = Autolex_EEA_Sync::build_query('BMW', '116d', 2021);
$required = array(
    '[CO2Emission].[latest].[co2cars_2021Fv24]',
    "[Year] = 2021",
    "[Status] = 'F'",
    "[Mk] = 'BMW'",
    "[Cn] = '116d'",
    '[Ec (cm3)] AS [ec]',
    '[Ep (KW)] AS [ep]',
    'COUNT(*) AS [r]',
    'GROUP BY',
);

foreach ($required as $fragment) {
    if (false === strpos($query, $fragment)) {
        fwrite(STDERR, "Missing EEA query fragment: {$fragment}\n");
        exit(1);
    }
}

$escaped = Autolex_EEA_Sync::build_query("O'NEILL", "Driver's", 2010);
if (false === strpos($escaped, "[Mk] = 'O''NEILL'") || false === strpos($escaped, "[Cn] = 'Driver''s'")) {
    fwrite(STDERR, "EEA query values were not escaped.\n");
    exit(1);
}

$final_2023 = Autolex_EEA_Sync::build_query('BMW', '530I', 2023);
if (false === strpos($final_2023, '[co2cars_2023Fv28]') || false === strpos($final_2023, "[Status] = 'F'")) {
    fwrite(STDERR, "The latest final EEA table was not selected.\n");
    exit(1);
}

$provisional_2025 = Autolex_EEA_Sync::build_query('BMW', '', 2025, 'make_discovery');
if (false === strpos($provisional_2025, '[co2cars_2025Pv31]') ||
    false === strpos($provisional_2025, "[Status] = 'P'") ||
    false !== strpos($provisional_2025, '[Cn] =')) {
    fwrite(STDERR, "The provisional new-model discovery query is invalid.\n");
    exit(1);
}

$make_index_2025 = Autolex_EEA_Sync::build_query('', '', 2025, 'make_index');
if (false === strpos($make_index_2025, 'SELECT [Mk] AS [mk]') ||
    false === strpos($make_index_2025, 'GROUP BY [Mk]') ||
    false !== strpos($make_index_2025, 'ORDER BY') ||
    false !== strpos($make_index_2025, "[Mk] =")) {
    fwrite(STDERR, "The all-make discovery query is invalid.\n");
    exit(1);
}

$sources = Autolex_EEA_Sync::source_configurations();
if ('F' !== $sources[2023]['status'] || 'P' !== $sources[2024]['status'] || 'P' !== $sources[2025]['status']) {
    fwrite(STDERR, "EEA source quality states are invalid.\n");
    exit(1);
}

try {
    Autolex_EEA_Sync::build_query('BMW', '116d', 2026);
    fwrite(STDERR, "An unconfigured reporting year was accepted.\n");
    exit(1);
} catch (InvalidArgumentException $exception) {
    // Expected: no allowlisted EEA passenger-car table exists for 2026 yet.
}

$current_response = json_encode(array(
    'results' => array(
        array('mk' => 'ABARTH', 'r' => 1553),
        array('mk' => 'BMW', 'r' => 49123),
    ),
));
$current_rows = Autolex_EEA_Sync::decode_response_rows($current_response);
if (count($current_rows) !== 2 || ($current_rows[0]['mk'] ?? '') !== 'ABARTH' || ($current_rows[1]['mk'] ?? '') !== 'BMW') {
    fwrite(STDERR, "Current EEA results envelope was not flattened into rows.\n");
    exit(1);
}

$legacy_response = json_encode(array(
    array('mk' => 'Audi', 'cn' => 'A4', 'r' => 25),
));
$legacy_rows = Autolex_EEA_Sync::decode_response_rows($legacy_response);
if (count($legacy_rows) !== 1 || ($legacy_rows[0]['cn'] ?? '') !== 'A4') {
    fwrite(STDERR, "Legacy direct EEA row lists are no longer supported.\n");
    exit(1);
}

foreach (array(
    '{"unexpected":[{"mk":"BMW"}]}',
    '{"results":"not-a-row-list"}',
    '{"results":["not-a-row"]}',
    '{"error":"query rejected"}',
) as $invalid_response) {
    try {
        Autolex_EEA_Sync::decode_response_rows($invalid_response);
        fwrite(STDERR, "An invalid EEA response envelope was accepted.\n");
        exit(1);
    } catch (RuntimeException $exception) {
        // Expected: format changes and API errors must fail closed.
    }
}

$sync_source = file_get_contents(dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-eea-sync.php');
foreach (array(
    'RESULTS_WRAPPER_RECOVERY_OPTION',
    "AND rows_read = 1",
    "AND vehicles_imported = 0",
    "AND engines_proposed = 0",
    "AND links_proposed = 0",
    "status = 'pending'",
    'page_number = 1',
) as $recovery_fragment) {
    if (false === strpos($sync_source, $recovery_fragment)) {
        fwrite(STDERR, "Missing wrapped-response recovery guard: {$recovery_fragment}\n");
        exit(1);
    }
}

$failure_cases = array(
    'EEA Discodata request failed: cURL error 28' => 'transport',
    'EEA Discodata returned HTTP 503.' => 'http',
    'EEA Discodata rejected the read-only query.' => 'api_rejected',
    'EEA Discodata returned invalid JSON.' => 'response_format',
    'EEA Discodata returned an unexpected JSON envelope.' => 'response_format',
    'EEA result exceeded 500 pages and requires a narrower source target.' => 'pagination_overflow',
    'Unsupported EEA reporting year.' => 'source_configuration',
    'EEA discovered-make target insert failed: write error' => 'database_write',
    'Unclassified importer exception' => 'other',
    '' => 'missing_error',
);
foreach ($failure_cases as $message => $expected_category) {
    $actual_category = Autolex_EEA_Failure_Telemetry::classify_failure_message($message);
    if ($actual_category !== $expected_category) {
        fwrite(STDERR, "EEA failure telemetry misclassified a smoke case: {$actual_category} != {$expected_category}\n");
        exit(1);
    }
}

$telemetry_source = file_get_contents(dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-eea-failure-telemetry.php');
foreach (array(
    "'privacy'      => 'aggregate_only'",
    "'/eea-failure-summary'",
    "WHERE status = 'failed'",
    'MAX_ROWS = 1000',
) as $telemetry_fragment) {
    if (false === strpos($telemetry_source, $telemetry_fragment)) {
        fwrite(STDERR, "Missing sanitized EEA failure telemetry guard: {$telemetry_fragment}\n");
        exit(1);
    }
}

if (Autolex_EEA_API_Rejection_Recovery::PROBATION_ATTEMPTS !== 7 ||
    Autolex_EEA_API_Rejection_Recovery::REJECTED_ERROR !== 'EEA Discodata rejected the read-only query.') {
    fwrite(STDERR, "EEA API rejection recovery no longer guarantees one fresh attempt.\n");
    exit(1);
}

$api_recovery_source = file_get_contents(dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-eea-api-rejection-recovery.php');
foreach (array(
    "status = 'failed'",
    "target_type = 'make_discovery'",
    'attempts >= 8',
    'page_number = 1',
    'rows_read = 0',
    'vehicles_imported = 0',
    'engines_proposed = 0',
    'links_proposed = 0',
    "last_error = %s",
    "status = 'retry'",
    'attempts = %d',
    "'attempt_policy' => 'one_fresh_attempt_only'",
) as $api_recovery_fragment) {
    if (false === strpos($api_recovery_source, $api_recovery_fragment)) {
        fwrite(STDERR, "Missing bounded API-rejection recovery guard: {$api_recovery_fragment}\n");
        exit(1);
    }
}

echo "EEA live-sync query, response, telemetry and bounded recovery smoke test passed.\n";
