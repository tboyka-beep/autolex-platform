<?php
/**
 * Deterministic, idempotent batch importer for source-backed vehicle claims.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Provenance_Batch_Importer
{
    const IMPORT_VERSION = '1.0.0';

    /** @var Autolex_Provenance_Batch_Importer|null */
    private static $instance = null;

    /** @return Autolex_Provenance_Batch_Importer */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Builds a stable batch identity from adapter, source identity and normalized records.
     * Volatile retrieval timestamps are deliberately excluded.
     *
     * @param string                   $adapter_code Adapter identifier.
     * @param array<string,mixed>      $source Source metadata.
     * @param array<int,array<string,mixed>> $records Import records.
     * @return string
     */
    public static function batch_key($adapter_code, array $source, array $records)
    {
        $fingerprint = array(
            'version' => self::IMPORT_VERSION,
            'adapter_code' => sanitize_key($adapter_code),
            'source_key' => Autolex_Source_Provenance::source_key($source),
            'records' => self::stable_records($records),
        );
        return hash('sha256', wp_json_encode($fingerprint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Validates and imports one source-backed batch.
     *
     * Each record must contain entity_type, entity_id, field_path and observed_value.
     * canonical_value is optional and defaults to observed_value. No missing value is
     * guessed. Conflicting observations are preserved by the provenance service.
     *
     * @param string                   $adapter_code Adapter identifier.
     * @param array<string,mixed>      $source Source metadata.
     * @param array<int,array<string,mixed>> $records Import records.
     * @param array<string,mixed>      $options dry_run and force flags.
     * @return array<string,mixed>|WP_Error
     */
    public function import($adapter_code, array $source, array $records, array $options = array())
    {
        global $wpdb;

        $adapter_code = sanitize_key($adapter_code);
        $dry_run = !empty($options['dry_run']);
        $force = !empty($options['force']);
        if ('' === $adapter_code) {
            return new WP_Error('autolex_invalid_adapter', 'Az adapter azonosítója kötelező.');
        }

        $validation = $this->validate_records($records);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $batch_key = self::batch_key($adapter_code, $source, $records);
        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT id, status, report_json FROM ' . Autolex_Source_Provenance::imports_table() . ' WHERE batch_key = %s',
            $batch_key
        ), ARRAY_A);

        if (!$dry_run && !$force && is_array($existing) && 'completed' === $existing['status']) {
            return array(
                'batch_key' => $batch_key,
                'batch_id' => (int) $existing['id'],
                'status' => 'skipped_duplicate_batch',
                'dry_run' => false,
                'report' => self::decode_report($existing['report_json']),
            );
        }

        $report = array(
            'adapter_code' => $adapter_code,
            'import_version' => self::IMPORT_VERSION,
            'dry_run' => $dry_run,
            'source_count' => 0,
            'claims_read' => count($records),
            'claims_created' => 0,
            'claims_updated' => 0,
            'claims_skipped' => 0,
            'evidence_created' => 0,
            'conflicts_found' => 0,
            'errors_found' => 0,
            'errors' => array(),
        );

        $batch_id = 0;
        if (!$dry_run) {
            $batch_id = $this->start_batch($batch_key, $adapter_code, $force);
            if (is_wp_error($batch_id)) {
                return $batch_id;
            }
        }

        $provenance = Autolex_Source_Provenance::instance();
        $source_result = $provenance->upsert_source($source, $dry_run);
        if (is_wp_error($source_result)) {
            return $this->finish_with_error($batch_id, $batch_key, $report, $source_result, $dry_run);
        }
        $report['source_count'] = 1;
        $source_id = isset($source_result['id']) ? (int) $source_result['id'] : 0;

        foreach ($records as $index => $record) {
            $canonical_value = array_key_exists('canonical_value', $record)
                ? $record['canonical_value']
                : $record['observed_value'];
            $claim_args = array(
                'verification_status' => isset($record['verification_status']) ? $record['verification_status'] : Autolex_Source_Provenance::STATUS_SINGLE_SOURCE,
                'normalization_rule' => isset($record['normalization_rule']) ? $record['normalization_rule'] : '',
            );
            $claim = $provenance->upsert_claim(
                $record['entity_type'],
                $record['entity_id'],
                $record['field_path'],
                $canonical_value,
                $claim_args,
                $dry_run
            );
            if (is_wp_error($claim)) {
                $this->append_error($report, $index, $claim);
                continue;
            }

            $action = isset($claim['action']) ? $claim['action'] : '';
            if (in_array($action, array('created', 'would_create'), true)) {
                ++$report['claims_created'];
            } elseif (in_array($action, array('updated', 'would_update'), true)) {
                ++$report['claims_updated'];
            } else {
                ++$report['claims_skipped'];
            }

            $claim_id = isset($claim['id']) ? (int) $claim['id'] : 0;
            if ($dry_run && 0 === $claim_id) {
                continue;
            }
            $evidence = $provenance->record_evidence(
                $claim_id,
                $source_id,
                $record['observed_value'],
                array(
                    'field_scope' => isset($record['field_scope']) ? $record['field_scope'] : $record['field_path'],
                    'evidence_status' => isset($record['verification_status']) ? $record['verification_status'] : Autolex_Source_Provenance::STATUS_SINGLE_SOURCE,
                    'source_locator' => isset($record['source_locator']) ? $record['source_locator'] : '',
                    'observed_at' => isset($record['observed_at']) ? $record['observed_at'] : '',
                ),
                $dry_run
            );
            if (is_wp_error($evidence)) {
                $this->append_error($report, $index, $evidence);
                continue;
            }
            if (in_array(isset($evidence['action']) ? $evidence['action'] : '', array('created', 'would_create'), true)) {
                ++$report['evidence_created'];
            }
            $report['conflicts_found'] += isset($evidence['conflicts']) ? (int) $evidence['conflicts'] : 0;
        }

        $status = $report['errors_found'] > 0 ? 'completed_with_errors' : 'completed';
        if (!$dry_run) {
            $this->complete_batch($batch_id, $status, $report);
        }

        return array(
            'batch_key' => $batch_key,
            'batch_id' => $batch_id,
            'status' => $dry_run ? 'dry_run_completed' : $status,
            'dry_run' => $dry_run,
            'report' => $report,
        );
    }

    /** @param array<int,array<string,mixed>> $records Records. @return true|WP_Error */
    public function validate_records(array $records)
    {
        if (empty($records)) {
            return new WP_Error('autolex_empty_batch', 'Az import batch nem lehet üres.');
        }
        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                return new WP_Error('autolex_invalid_record', sprintf('A(z) %d. rekord nem tömb.', $index));
            }
            foreach (array('entity_type', 'entity_id', 'field_path', 'observed_value') as $required) {
                if (!array_key_exists($required, $record) || '' === (string) $record[$required]) {
                    return new WP_Error('autolex_missing_record_field', sprintf('A(z) %d. rekordból hiányzik: %s.', $index, $required));
                }
            }
            if (0 === absint($record['entity_id'])) {
                return new WP_Error('autolex_invalid_entity_id', sprintf('A(z) %d. rekord entitásazonosítója érvénytelen.', $index));
            }
        }
        return true;
    }

    /** @param array<int,array<string,mixed>> $records Records. @return array<int,array<string,mixed>> */
    private static function stable_records(array $records)
    {
        $stable = array();
        foreach ($records as $record) {
            $stable[] = array(
                'entity_type' => sanitize_key(isset($record['entity_type']) ? $record['entity_type'] : ''),
                'entity_id' => absint(isset($record['entity_id']) ? $record['entity_id'] : 0),
                'field_path' => isset($record['field_path']) ? (string) $record['field_path'] : '',
                'canonical_value' => array_key_exists('canonical_value', $record) ? $record['canonical_value'] : (isset($record['observed_value']) ? $record['observed_value'] : null),
                'observed_value' => isset($record['observed_value']) ? $record['observed_value'] : null,
                'verification_status' => isset($record['verification_status']) ? $record['verification_status'] : Autolex_Source_Provenance::STATUS_SINGLE_SOURCE,
                'normalization_rule' => isset($record['normalization_rule']) ? $record['normalization_rule'] : '',
                'source_locator' => isset($record['source_locator']) ? $record['source_locator'] : '',
            );
        }
        usort($stable, function ($left, $right) {
            return strcmp(wp_json_encode($left), wp_json_encode($right));
        });
        return $stable;
    }

    /** @param string $batch_key Batch key. @param string $adapter_code Adapter. @param bool $force Force. @return int|WP_Error */
    private function start_batch($batch_key, $adapter_code, $force)
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $table = Autolex_Source_Provenance::imports_table();
        $existing_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . $table . ' WHERE batch_key = %s', $batch_key));
        $data = array(
            'adapter_code' => $adapter_code,
            'mode' => 'write',
            'status' => 'running',
            'started_at' => $now,
            'completed_at' => null,
            'report_json' => null,
            'updated_at' => $now,
        );
        if ($existing_id && $force) {
            $wpdb->update($table, $data, array('id' => $existing_id));
            return $existing_id;
        }
        $data['batch_key'] = $batch_key;
        $data['created_at'] = $now;
        $wpdb->insert($table, $data);
        if (!$wpdb->insert_id) {
            return new WP_Error('autolex_import_batch_write_failed', 'Az import batch indítása sikertelen.');
        }
        return (int) $wpdb->insert_id;
    }

    /** @param int $batch_id Batch ID. @param string $status Status. @param array<string,mixed> $report Report. @return void */
    private function complete_batch($batch_id, $status, array $report)
    {
        global $wpdb;
        $wpdb->update(
            Autolex_Source_Provenance::imports_table(),
            array(
                'status' => $status,
                'source_count' => $report['source_count'],
                'claims_read' => $report['claims_read'],
                'claims_created' => $report['claims_created'],
                'claims_updated' => $report['claims_updated'],
                'claims_skipped' => $report['claims_skipped'],
                'conflicts_found' => $report['conflicts_found'],
                'errors_found' => $report['errors_found'],
                'report_json' => wp_json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'completed_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ),
            array('id' => absint($batch_id))
        );
    }

    /** @param int $batch_id Batch ID. @param string $batch_key Key. @param array<string,mixed> $report Report. @param WP_Error $error Error. @param bool $dry_run Dry run. @return array<string,mixed> */
    private function finish_with_error($batch_id, $batch_key, array $report, WP_Error $error, $dry_run)
    {
        $this->append_error($report, -1, $error);
        if (!$dry_run && $batch_id) {
            $this->complete_batch($batch_id, 'failed', $report);
        }
        return array(
            'batch_key' => $batch_key,
            'batch_id' => $batch_id,
            'status' => 'failed',
            'dry_run' => $dry_run,
            'report' => $report,
        );
    }

    /** @param array<string,mixed> $report Report. @param int $index Record index. @param WP_Error $error Error. @return void */
    private function append_error(array &$report, $index, WP_Error $error)
    {
        ++$report['errors_found'];
        $report['errors'][] = array(
            'record_index' => $index,
            'code' => $error->get_error_code(),
            'message' => $error->get_error_message(),
        );
    }

    /** @param mixed $encoded Encoded report. @return array<string,mixed> */
    private static function decode_report($encoded)
    {
        $decoded = json_decode((string) $encoded, true);
        return is_array($decoded) ? $decoded : array();
    }
}
