<?php
/**
 * Deterministic adapter for reviewed EU Safety Gate recall records.
 *
 * This adapter never fetches remote content. It accepts records already
 * retrieved through an approved workflow, validates the official source
 * contract, preserves record identifiers and delegates idempotent persistence
 * to the common official-source adapter.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Safety_Gate_Source_Adapter
{
    const ADAPTER_CODE = 'safety-gate-v1';
    const PUBLISHER = 'European Commission Safety Gate';

    /**
     * Imports reviewed Safety Gate records.
     *
     * @param array<string,mixed> $source Reviewed source metadata.
     * @param array<int,array<string,mixed>> $rows Safety Gate rows.
     * @param array<string,mixed> $options Import options.
     * @return array<string,mixed>|WP_Error
     */
    public function import(array $source, array $rows, array $options = array())
    {
        $source = $this->normalize_source($source);
        if (is_wp_error($source)) {
            return $source;
        }

        $normalized_rows = array();
        foreach ($rows as $index => $row) {
            $normalized = $this->normalize_row($row, $index);
            if (is_wp_error($normalized)) {
                return $normalized;
            }
            foreach ($normalized as $claim) {
                $normalized_rows[] = $claim;
            }
        }

        $official = new Autolex_Official_Source_Adapter();
        return $official->import($source, $normalized_rows, $options);
    }

    /**
     * Validates and normalizes source metadata.
     *
     * @param array<string,mixed> $source Source metadata.
     * @return array<string,mixed>|WP_Error
     */
    public function normalize_source(array $source)
    {
        foreach (array('url', 'retrieved_at', 'usage_reviewed', 'usage_note') as $field) {
            if (!isset($source[$field]) || '' === trim((string) $source[$field])) {
                return new WP_Error('autolex_safety_gate_missing_source_field', sprintf('Hiányzó Safety Gate forrásmező: %s.', $field));
            }
        }

        $host = strtolower((string) wp_parse_url((string) $source['url'], PHP_URL_HOST));
        if (!in_array($host, array('ec.europa.eu', 'webgate.ec.europa.eu'), true)) {
            return new WP_Error('autolex_safety_gate_untrusted_host', 'A Safety Gate adapter csak hivatalos Európai Bizottság hosztot fogad el.');
        }

        $source['source_type'] = 'official_registry';
        $source['title'] = isset($source['title']) && '' !== trim((string) $source['title'])
            ? sanitize_text_field($source['title'])
            : 'EU Safety Gate vehicle recall record';
        $source['publisher'] = self::PUBLISHER;
        $source['document_id'] = isset($source['document_id']) ? sanitize_text_field($source['document_id']) : '';
        $source['license_note'] = isset($source['license_note'])
            ? sanitize_text_field($source['license_note'])
            : sanitize_text_field($source['usage_note']);

        return $source;
    }

    /**
     * Expands one Safety Gate record to field-level provenance claims.
     *
     * @param array<string,mixed> $row Source row.
     * @param int $index Row index.
     * @return array<int,array<string,mixed>>|WP_Error
     */
    public function normalize_row(array $row, $index = 0)
    {
        foreach (array('entity_id', 'alert_number', 'risk_type', 'product_description') as $field) {
            if (!isset($row[$field]) || '' === trim((string) $row[$field])) {
                return new WP_Error('autolex_safety_gate_missing_row_field', sprintf('A(z) %d. Safety Gate sorból hiányzik: %s.', $index, $field));
            }
        }

        if (0 === absint($row['entity_id'])) {
            return new WP_Error('autolex_safety_gate_invalid_entity', sprintf('A(z) %d. Safety Gate sor entitásazonosítója érvénytelen.', $index));
        }

        $fields = array(
            'safety.recall.alert_number' => $row['alert_number'],
            'safety.recall.risk_type' => $row['risk_type'],
            'safety.recall.product_description' => $row['product_description'],
        );

        foreach (array('notifying_country', 'production_dates', 'remedy', 'company_recall_code') as $optional) {
            if (isset($row[$optional]) && '' !== trim((string) $row[$optional])) {
                $fields['safety.recall.' . $optional] = $row[$optional];
            }
        }

        $claims = array();
        foreach ($fields as $field_path => $value) {
            $claims[] = array(
                'entity_type' => 'vehicle',
                'entity_id' => absint($row['entity_id']),
                'field_path' => $field_path,
                'observed_value' => sanitize_text_field($value),
                'canonical_value' => sanitize_text_field($value),
                'verification_status' => Autolex_Source_Provenance::STATUS_SINGLE_SOURCE,
                'normalization_rule' => 'safety_gate_text_v1',
                'field_scope' => 'safety.recall',
                'source_locator' => 'alert:' . sanitize_text_field($row['alert_number']) . '#' . $field_path,
                'observed_at' => isset($row['published_at']) ? sanitize_text_field($row['published_at']) : '',
            );
        }

        return $claims;
    }
}
