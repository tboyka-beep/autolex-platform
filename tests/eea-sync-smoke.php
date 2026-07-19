<?php
/**
 * Smoke checks for the allowlisted EEA Discodata query builder.
 */

define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-eea-sync.php';

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
    false === strpos($make_index_2025, 'GROUP BY [Mk] ORDER BY [Mk]') ||
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

echo "EEA live-sync query smoke test passed.\n";
