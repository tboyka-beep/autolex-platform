<?php
/**
 * Deterministic provenance adapter for reviewed Eurostat transport records.
 *
 * No remote request is performed here. The adapter accepts only records
 * obtained through an approved, licence-reviewed workflow and stores each
 * statistical observation as a field-level provenance claim.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Eurostat_Source_Adapter
{
    const ADAPTER_CODE = 'eurostat-transport-v1';
    const PUBLISHER = 'Eurostat';

    /**
     * @param array<string,mixed> $source Reviewed source metadata.
     * @param array<int,array<string,mixed>> $rows Reviewed Eurostat rows.
     * @param array<string,mixed> $options Import options.
     * @return array<string,mixed>|WP_Error
     */
    public function import(array $source, array $rows, array $options = array())
    {
        $source = $this->normalize_source($source);
        if (is_wp_error($source)) {
            return $source;
        }

        $claims = array();
        foreach ($rows as $index => $row) {
            $normalized = $this->normalize_row($row, $index);
            if (is_wp_error($normalized)) {
                return $normalized;
            }
            foreach ($normalized as $claim) {
                $claims[] = $claim;
            }
        }

        $official = new Autolex_Official_Source_Adapter();
        return $official->import($source, $claims, $options);
    }

    /**
     * @param array<string,mixed> $source Source metadata.
     * @return array<string,mixed>|WP_Error
     */
    public function normalize_source(array $source)
    {
        foreach (array('url', 'retrieved_at', 'usage_note', 'document_id') as $field) {
            if (!isset($source[$field]) || '' === trim((string) $source[$field])) {
                return new WP_Error('autolex_eurostat_missing_source_field', sprintf('Hiányzó Eurostat forrásmező: %s.', $field));
            }
        }

        if (!array_key_exists('usage_reviewed', $source) || true !== $source['usage_reviewed']) {
            return new WP_Error('autolex_eurostat_usage_not_reviewed', 'A Eurostat forrás felhasználhatóságát dokumentáltan ellenőrizni kell.');
        }

        $host = strtolower((string) wp_parse_url((string) $source['url'], PHP_URL_HOST));
        $allowed_hosts = array('ec.europa.eu', 'eurostat.ec.europa.eu');
        if (!in_array($host, $allowed_hosts, true)) {
            return new WP_Error('autolex_eurostat_untrusted_host', 'A Eurostat adapter csak hivatalos Eurostat hosztot fogad el.');
        }

        $source['source_type'] = 'official_statistics';
        $source['title'] = isset($source['title']) && '' !== trim((string) $source['title'])
            ? sanitize_text_field($source['title'])
            : 'Eurostat transport statistics';
        $source['publisher'] = self::PUBLISHER;
        $source['document_id'] = sanitize_text_field($source['document_id']);
        $source['license_note'] = isset($source['license_note'])
            ? sanitize_text_field($source['license_note'])
            : sanitize_text_field($source['usage_note']);

        return $source;
    }

    /**
     * @param array<string,mixed> $row Source row.
     * @param int $index Row index.
     * @return array<int,array<string,mixed>>|WP_Error
     */
    public function normalize_row(array $row, $index = 0)
    {
        foreach (array('entity_id', 'dataset_code', 'geo', 'time', 'indicator', 'value', 'unit') as $field) {
            if (!array_key_exists($field, $row) || '' === trim((string) $row[$field])) {
                return new WP_Error('autolex_eurostat_missing_row_field', sprintf('A(z) %d. Eurostat sorból hiányzik: %s.', $index, $field));
            }
        }

        if (0 === absint($row['entity_id'])) {
            return new WP_Error('autolex_eurostat_invalid_entity', sprintf('A(z) %d. Eurostat sor entitásazonosítója érvénytelen.', $index));
        }

        if (!is_numeric($row['value']) || !is_finite((float) $row['value']) || (float) $row['value'] < 0) {
            return new WP_Error('autolex_eurostat_invalid_value', sprintf('A(z) %d. Eurostat sor értéke érvénytelen.', $index));
        }

        $indicator = sanitize_key($row['indicator']);
        $allowed = array(
            'passenger_cars_total',
            'new_passenger_car_registrations',
            'battery_electric_cars',
            'plug_in_hybrid_cars',
            'road_fatalities',
        );
        if (!in_array($indicator, $allowed, true)) {
            return new WP_Error('autolex_eurostat_unknown_indicator', sprintf('A(z) %d. Eurostat sor indikátora nem engedélyezett.', $index));
        }

        $geo = strtoupper(sanitize_text_field($row['geo']));
        $time = sanitize_text_field($row['time']);
        if (!preg_match('/^[A-Z]{2,3}$/', $geo) || !preg_match('/^\d{4}$/', $time)) {
            return new WP_Error('autolex_eurostat_invalid_dimension', sprintf('A(z) %d. Eurostat sor dimenziója érvénytelen.', $index));
        }

        $value = (string) (float) $row['value'];
        $dataset = sanitize_key($row['dataset_code']);
        $unit = sanitize_text_field($row['unit']);
        $field_path = 'market.' . strtolower($geo) . '.' . $time . '.' . $indicator;

        return array(
            array(
                'entity_type' => 'market_stat',
                'entity_id' => absint($row['entity_id']),
                'field_path' => $field_path,
                'observed_value' => $value,
                'canonical_value' => $value,
                'verification_status' => Autolex_Source_Provenance::STATUS_SINGLE_SOURCE,
                'normalization_rule' => 'eurostat_transport_v1',
                'field_scope' => 'market',
                'source_locator' => 'dataset:' . $dataset . '#geo=' . $geo . '&time=' . $time . '&indicator=' . $indicator . '&unit=' . rawurlencode($unit),
                'observed_at' => $time,
            ),
        );
    }
}
