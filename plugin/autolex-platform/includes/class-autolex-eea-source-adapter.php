<?php
/**
 * Deterministic provenance adapter for reviewed EEA vehicle CO2 records.
 *
 * The adapter performs no remote fetching. It accepts rows obtained through an
 * approved, licence-reviewed workflow and preserves the EEA record identifier
 * on every field-level claim.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_EEA_Source_Adapter
{
    const ADAPTER_CODE = 'eea-vehicle-co2-v1';
    const PUBLISHER = 'European Environment Agency';

    /**
     * @param array<string,mixed> $source Reviewed source metadata.
     * @param array<int,array<string,mixed>> $rows Reviewed EEA rows.
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
        foreach (array('url', 'retrieved_at', 'usage_reviewed', 'usage_note', 'document_id') as $field) {
            if (!isset($source[$field]) || '' === trim((string) $source[$field])) {
                return new WP_Error('autolex_eea_missing_source_field', sprintf('Hiányzó EEA forrásmező: %s.', $field));
            }
        }

        $host = strtolower((string) wp_parse_url((string) $source['url'], PHP_URL_HOST));
        $allowed_hosts = array('eea.europa.eu', 'www.eea.europa.eu', 'sdi.eea.europa.eu', 'discomap.eea.europa.eu');
        if (!in_array($host, $allowed_hosts, true)) {
            return new WP_Error('autolex_eea_untrusted_host', 'Az EEA adapter csak hivatalos EEA hosztot fogad el.');
        }

        $source['source_type'] = 'official_registry';
        $source['title'] = isset($source['title']) && '' !== trim((string) $source['title'])
            ? sanitize_text_field($source['title'])
            : 'EEA monitoring of CO2 emissions from passenger cars';
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
        foreach (array('entity_id', 'record_id', 'co2_g_km', 'mass_kg') as $field) {
            if (!array_key_exists($field, $row) || '' === trim((string) $row[$field])) {
                return new WP_Error('autolex_eea_missing_row_field', sprintf('A(z) %d. EEA sorból hiányzik: %s.', $index, $field));
            }
        }

        if (0 === absint($row['entity_id'])) {
            return new WP_Error('autolex_eea_invalid_entity', sprintf('A(z) %d. EEA sor entitásazonosítója érvénytelen.', $index));
        }

        if (!is_numeric($row['co2_g_km']) || (float) $row['co2_g_km'] < 0 || !is_numeric($row['mass_kg']) || (float) $row['mass_kg'] <= 0) {
            return new WP_Error('autolex_eea_invalid_numeric_value', sprintf('A(z) %d. EEA sor számszerű értéke érvénytelen.', $index));
        }

        $fields = array(
            'emissions.co2_g_km' => (string) (float) $row['co2_g_km'],
            'dimensions.mass_kg' => (string) (float) $row['mass_kg'],
        );

        $optional_numeric = array(
            'engine_capacity_cm3' => 'engine.displacement_cm3',
            'power_kw' => 'engine.power_kw',
            'registration_year' => 'identity.registration_year',
        );
        foreach ($optional_numeric as $source_field => $field_path) {
            if (isset($row[$source_field]) && '' !== trim((string) $row[$source_field])) {
                if (!is_numeric($row[$source_field]) || (float) $row[$source_field] < 0) {
                    return new WP_Error('autolex_eea_invalid_optional_numeric', sprintf('A(z) %d. EEA sor opcionális számszerű értéke érvénytelen: %s.', $index, $source_field));
                }
                $fields[$field_path] = (string) (float) $row[$source_field];
            }
        }

        if (isset($row['fuel_type']) && '' !== trim((string) $row['fuel_type'])) {
            $fields['engine.fuel_type'] = sanitize_text_field($row['fuel_type']);
        }

        $claims = array();
        foreach ($fields as $field_path => $value) {
            $claims[] = array(
                'entity_type' => 'vehicle',
                'entity_id' => absint($row['entity_id']),
                'field_path' => $field_path,
                'observed_value' => $value,
                'canonical_value' => $value,
                'verification_status' => Autolex_Source_Provenance::STATUS_SINGLE_SOURCE,
                'normalization_rule' => 'eea_vehicle_co2_v1',
                'field_scope' => strstr($field_path, '.', true) ?: $field_path,
                'source_locator' => 'record:' . sanitize_text_field($row['record_id']) . '#' . $field_path,
                'observed_at' => isset($row['reporting_period']) ? sanitize_text_field($row['reporting_period']) : '',
            );
        }

        return $claims;
    }
}
