<?php
/**
 * Deterministic adapter for explicitly permitted official vehicle datasets.
 *
 * The adapter does not scrape or fetch remote content. It accepts already
 * retrieved, license-reviewed rows, normalizes them into provenance claims,
 * and delegates the idempotent write/dry-run behavior to the batch importer.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Official_Source_Adapter
{
    const ADAPTER_CODE = 'official-source-v1';

    /** @var array<int,string> */
    private static $allowed_source_types = array(
        'manufacturer',
        'official_registry',
        'official_statistics',
    );

    /**
     * Imports normalized official rows.
     *
     * @param array<string,mixed> $source Reviewed source metadata.
     * @param array<int,array<string,mixed>> $rows Dataset rows.
     * @param array<string,mixed> $options Importer options.
     * @return array<string,mixed>|WP_Error
     */
    public function import(array $source, array $rows, array $options = array())
    {
        $source_validation = $this->validate_source($source);
        if (is_wp_error($source_validation)) {
            return $source_validation;
        }

        $records = array();
        foreach ($rows as $index => $row) {
            $record = $this->normalize_row($row, $index);
            if (is_wp_error($record)) {
                return $record;
            }
            $records[] = $record;
        }

        return Autolex_Provenance_Batch_Importer::instance()->import(
            self::ADAPTER_CODE,
            $this->normalize_source($source),
            $records,
            $options
        );
    }

    /**
     * Validates reviewed source metadata without making network requests.
     *
     * @param array<string,mixed> $source Source metadata.
     * @return true|WP_Error
     */
    public function validate_source(array $source)
    {
        foreach (array('source_type', 'title', 'publisher', 'url', 'retrieved_at') as $field) {
            if (!isset($source[$field]) || '' === trim((string) $source[$field])) {
                return new WP_Error('autolex_official_source_missing_field', sprintf('Hiányzó forrásmező: %s.', $field));
            }
        }

        if (!in_array($source['source_type'], self::$allowed_source_types, true)) {
            return new WP_Error('autolex_official_source_type_denied', 'Az adapter csak gyártói vagy hivatalos forrást fogad el.');
        }

        $scheme = wp_parse_url((string) $source['url'], PHP_URL_SCHEME);
        if ('https' !== strtolower((string) $scheme)) {
            return new WP_Error('autolex_official_source_insecure_url', 'A hivatalos forrás URL-jének HTTPS-t kell használnia.');
        }

        if (empty($source['usage_reviewed']) || empty($source['usage_note'])) {
            return new WP_Error('autolex_official_source_usage_unreviewed', 'A forrás felhasználhatósági ellenőrzése és megjegyzése kötelező.');
        }

        return true;
    }

    /**
     * Converts one reviewed row to the common provenance contract.
     *
     * @param array<string,mixed> $row Source row.
     * @param int $index Row index.
     * @return array<string,mixed>|WP_Error
     */
    public function normalize_row(array $row, $index = 0)
    {
        foreach (array('entity_type', 'entity_id', 'field_path', 'observed_value') as $field) {
            if (!array_key_exists($field, $row) || '' === trim((string) $row[$field])) {
                return new WP_Error('autolex_official_row_missing_field', sprintf('A(z) %d. sorból hiányzik: %s.', $index, $field));
            }
        }

        if (0 === absint($row['entity_id'])) {
            return new WP_Error('autolex_official_row_invalid_entity', sprintf('A(z) %d. sor entitásazonosítója érvénytelen.', $index));
        }

        $verification_status = isset($row['verification_status'])
            ? sanitize_key($row['verification_status'])
            : Autolex_Source_Provenance::STATUS_SINGLE_SOURCE;

        if (Autolex_Source_Provenance::STATUS_MULTI_SOURCE === $verification_status) {
            return new WP_Error('autolex_official_row_false_multi_source', 'Egyetlen adapterforrás nem jelölhető több forrásból egyezőnek.');
        }

        return array(
            'entity_type' => sanitize_key($row['entity_type']),
            'entity_id' => absint($row['entity_id']),
            'field_path' => sanitize_text_field($row['field_path']),
            'canonical_value' => array_key_exists('canonical_value', $row) ? $row['canonical_value'] : $row['observed_value'],
            'observed_value' => $row['observed_value'],
            'verification_status' => $verification_status,
            'normalization_rule' => isset($row['normalization_rule']) ? sanitize_text_field($row['normalization_rule']) : '',
            'field_scope' => isset($row['field_scope']) ? sanitize_text_field($row['field_scope']) : sanitize_text_field($row['field_path']),
            'source_locator' => isset($row['source_locator']) ? sanitize_text_field($row['source_locator']) : '',
            'observed_at' => isset($row['observed_at']) ? sanitize_text_field($row['observed_at']) : '',
        );
    }

    /** @param array<string,mixed> $source Source metadata. @return array<string,mixed> */
    private function normalize_source(array $source)
    {
        $normalized = $source;
        $normalized['source_type'] = sanitize_key($source['source_type']);
        $normalized['title'] = sanitize_text_field($source['title']);
        $normalized['publisher'] = sanitize_text_field($source['publisher']);
        $normalized['url'] = esc_url_raw($source['url'], array('https'));
        $normalized['document_id'] = isset($source['document_id']) ? sanitize_text_field($source['document_id']) : '';
        $normalized['retrieved_at'] = sanitize_text_field($source['retrieved_at']);
        $normalized['usage_note'] = sanitize_text_field($source['usage_note']);
        $normalized['license_note'] = isset($source['license_note']) ? sanitize_text_field($source['license_note']) : $normalized['usage_note'];
        $normalized['content_hash'] = isset($source['content_hash']) ? strtolower(sanitize_text_field($source['content_hash'])) : '';
        unset($normalized['usage_reviewed']);
        return $normalized;
    }
}
