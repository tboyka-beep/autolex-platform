<?php
$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/autolex-provenance-' . bin2hex(random_bytes(6));
$upgrade_dir = $tmp . '/wp-admin/includes';
if (!mkdir($upgrade_dir, 0777, true) && !is_dir($upgrade_dir)) {
    fwrite(STDERR, "FAIL: unable to create temporary WordPress stub directory\n");
    exit(1);
}

file_put_contents(
    $upgrade_dir . '/upgrade.php',
    "<?php\nfunction dbDelta(\$sql) { \$GLOBALS['autolex_dbdelta'][] = \$sql; }\n"
);

define('ABSPATH', $tmp . '/');
$GLOBALS['autolex_dbdelta'] = array();
$GLOBALS['autolex_options'] = array();

final class Autolex_Provenance_Test_WPDB
{
    public $prefix = 'wp_';

    public function get_charset_collate()
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
}

$wpdb = new Autolex_Provenance_Test_WPDB();

function sanitize_key($value)
{
    return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value));
}

function update_option($key, $value, $autoload = null)
{
    $GLOBALS['autolex_options'][$key] = array($value, $autoload);
    return true;
}

require $root . '/plugin/autolex-platform/includes/class-autolex-source-provenance.php';

Autolex_Source_Provenance::install_schema();

$statuses = Autolex_Source_Provenance::verification_statuses();
$sql = implode("\n", $GLOBALS['autolex_dbdelta']);

$checks = array(
    'seven verification states are allow-listed' => count($statuses) === 7,
    'manufacturer status is stable' => in_array('manufacturer_source', $statuses, true),
    'official registry status is stable' => in_array('official_registry', $statuses, true),
    'multi-source status is stable' => in_array('multi_source_match', $statuses, true),
    'single-source status matches public contract' => in_array('single_source_confirmed', $statuses, true),
    'conflict status is stable' => in_array('source_conflict', $statuses, true),
    'incomplete status is stable' => in_array('incomplete', $statuses, true),
    'VIN-required status is stable' => in_array('vin_required', $statuses, true),
    'unknown evidence fails closed' => Autolex_Source_Provenance::normalize_status('untrusted promoted state') === 'incomplete',
    'known evidence remains unchanged' => Autolex_Source_Provenance::normalize_status('multi_source_match') === 'multi_source_match',
    'exactly five additive tables are installed' => count($GLOBALS['autolex_dbdelta']) === 5,
    'source table is additive and keyed' => strpos($sql, 'CREATE TABLE wp_autolex_sources') !== false && strpos($sql, 'UNIQUE KEY source_key (source_key)') !== false,
    'claim table protects one entity field contract' => strpos($sql, 'CREATE TABLE wp_autolex_source_claims') !== false && strpos($sql, 'UNIQUE KEY entity_field (entity_type, entity_id, field_path)') !== false,
    'evidence table is idempotent per claim source value' => strpos($sql, 'CREATE TABLE wp_autolex_source_evidence') !== false && strpos($sql, 'UNIQUE KEY claim_source_value (claim_id, source_id, observed_value_hash)') !== false,
    'conflict variants are preserved as evidence pairs' => strpos($sql, 'CREATE TABLE wp_autolex_source_conflicts') !== false && strpos($sql, 'UNIQUE KEY evidence_pair (claim_id, evidence_id, conflicting_evidence_id)') !== false,
    'import batches are idempotent and default to dry-run' => strpos($sql, 'CREATE TABLE wp_autolex_source_imports') !== false && strpos($sql, 'UNIQUE KEY batch_key (batch_key)') !== false && strpos($sql, "mode varchar(20) NOT NULL DEFAULT 'dry_run'") !== false,
    'source metadata includes URL document retrieval and hash fields' => strpos($sql, 'source_url text NOT NULL') !== false && strpos($sql, 'document_identifier varchar(191)') !== false && strpos($sql, 'retrieved_at datetime NOT NULL') !== false && strpos($sql, 'content_sha256 char(64)') !== false,
    'schema version is recorded without autoload' => isset($GLOBALS['autolex_options']['autolex_source_provenance_schema_version']) && $GLOBALS['autolex_options']['autolex_source_provenance_schema_version'] === array('1.0.0', false),
    'legacy catalogue tables are not modified' => strpos($sql, 'ALTER TABLE') === false && strpos($sql, 'DROP TABLE') === false && strpos($sql, 'DELETE FROM') === false,
);

$failed = false;
foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        $failed = true;
        continue;
    }
    echo "PASS: {$label}\n";
}

@unlink($upgrade_dir . '/upgrade.php');
@rmdir($upgrade_dir);
@rmdir(dirname($upgrade_dir));
@rmdir(dirname(dirname($upgrade_dir)));
@rmdir($tmp);

exit($failed ? 1 : 0);
