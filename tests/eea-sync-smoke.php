<?php
/**
 * Smoke checks for the allowlisted EEA Discodata query builder.
 */

define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-eea-sync.php';

$query = Autolex_EEA_Sync::build_query('BMW', '116d', 2021);
$required = array(
    '[CO2Emission].[latest].[co2cars]',
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

try {
    Autolex_EEA_Sync::build_query('BMW', '116d', 2022);
    fwrite(STDERR, "A provisional reporting year was accepted as final.\n");
    exit(1);
} catch (InvalidArgumentException $exception) {
    // Expected: 2022 is provisional in the currently exposed latest table.
}

echo "EEA live-sync query smoke test passed.\n";
