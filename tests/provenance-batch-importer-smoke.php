<?php
/**
 * Deterministic contract for the Autolex 4.2 provenance batch importer.
 */

$root = dirname(__DIR__);
$importer = $root . '/plugin/autolex-platform/includes/class-autolex-provenance-batch-importer.php';

if (!is_readable($importer)) {
    fwrite(STDERR, "Missing provenance batch importer.\n");
    exit(1);
}

$source = file_get_contents($importer);
$required = array(
    "const IMPORT_VERSION = '1.0.0'",
    'public static function batch_key',
    'public function import',
    'public function validate_records',
    "'dry_run'",
    "'force'",
    "'skipped_duplicate_batch'",
    "'claims_created'",
    "'claims_updated'",
    "'claims_skipped'",
    "'evidence_created'",
    "'conflicts_found'",
    "'errors_found'",
    'Autolex_Source_Provenance::source_key',
    'upsert_source',
    'upsert_claim',
    'record_evidence',
    'stable_records',
    'usort',
    'hash(\'sha256\'',
);

foreach ($required as $needle) {
    if (false === strpos($source, $needle)) {
        fwrite(STDERR, "Missing batch importer contract: {$needle}\n");
        exit(1);
    }
}

$forbidden = array(
    'rand(',
    'mt_rand(',
    'uniqid(',
    'DELETE FROM',
    'TRUNCATE TABLE',
    "verification_status' => Autolex_Source_Provenance::STATUS_MULTI_SOURCE",
);
foreach ($forbidden as $needle) {
    if (false !== stripos($source, $needle)) {
        fwrite(STDERR, "Unsafe or non-deterministic importer token: {$needle}\n");
        exit(1);
    }
}

echo "Autolex provenance batch importer smoke test passed.\n";
