<?php
/**
 * Fail-closed adapter for official Eurostat road-transport datasets.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Eurostat
{
    const API_BASE = 'https://ec.europa.eu/eurostat/api/dissemination/statistics/1.0/data/';
    const MAX_RESPONSE_BYTES = 4194304;
    const CACHE_TTL = 21600;

    const ALLOWED_DATASETS = array(
        'road_eqs_carpda',
        'road_eqr_carpda',
        'road_eqs_zev',
        'road_eqr_zev',
        'road_eqs_carhab',
    );

    /**
     * Returns machine-readable provenance and scope for one supported dataset.
     *
     * @param string $dataset Eurostat dataset code.
     * @return array<string,mixed>
     */
    public static function source_meta($dataset)
    {
        $dataset = self::normalize_dataset($dataset);

        return array(
            'source_code' => 'EUROSTAT_' . strtoupper($dataset),
            'provider' => 'Eurostat',
            'dataset' => $dataset,
            'api_base' => self::API_BASE,
            'official_host' => 'ec.europa.eu',
            'format' => 'JSON-stat 2.0',
            'scope' => 'country-, year- and powertrain-level road-vehicle statistics',
            'individual_vehicle_verification' => false,
            'license_review_required' => true,
        );
    }

    /**
     * Builds a bounded official API URL for one allowlisted dataset.
     *
     * @param string              $dataset Dataset code.
     * @param array<string,mixed> $filters Dimension filters.
     * @return string
     */
    public static function build_url($dataset, $filters = array())
    {
        $dataset = self::normalize_dataset($dataset);
        $params = array('format' => 'JSON', 'lang' => 'EN');

        foreach ($filters as $dimension => $value) {
            $dimension = strtolower(trim((string) $dimension));
            if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $dimension)) {
                throw new InvalidArgumentException('Invalid Eurostat dimension code.');
            }
            if (is_array($value)) {
                $value = array_values(array_filter(array_map(array(__CLASS__, 'normalize_filter_value'), $value), 'strlen'));
                if (!$value || count($value) > 25) {
                    throw new InvalidArgumentException('Eurostat filter lists must contain 1 to 25 values.');
                }
                $params[$dimension] = implode(',', $value);
                continue;
            }
            $value = self::normalize_filter_value($value);
            if ('' === $value) {
                throw new InvalidArgumentException('Eurostat filter values cannot be empty.');
            }
            $params[$dimension] = $value;
        }

        return self::API_BASE . rawurlencode($dataset) . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Validates a decoded JSON-stat 2.0 dataset without mutating storage.
     *
     * @param mixed  $payload Decoded JSON response.
     * @param string $dataset Expected dataset code.
     * @return array<string,mixed>
     */
    public static function validate_payload($payload, $dataset)
    {
        $dataset = self::normalize_dataset($dataset);
        if (!is_array($payload)) {
            throw new RuntimeException('Eurostat returned a non-object JSON response.');
        }
        if ('dataset' !== ($payload['class'] ?? null)) {
            throw new RuntimeException('Eurostat response is not a JSON-stat dataset.');
        }
        if (!isset($payload['version']) || 0 !== strpos((string) $payload['version'], '2')) {
            throw new RuntimeException('Eurostat response is not JSON-stat 2.x.');
        }

        $ids = $payload['id'] ?? null;
        $sizes = $payload['size'] ?? null;
        $dimensions = $payload['dimension'] ?? null;
        $values = $payload['value'] ?? null;
        if (!is_array($ids) || !is_array($sizes) || !is_array($dimensions) || !is_array($values)) {
            throw new RuntimeException('Eurostat response is missing JSON-stat dimensions or values.');
        }
        if (!$ids || count($ids) !== count($sizes)) {
            throw new RuntimeException('Eurostat dimension identifiers and sizes do not match.');
        }

        $cell_count = 1;
        foreach ($sizes as $size) {
            if (!is_int($size) && !ctype_digit((string) $size)) {
                throw new RuntimeException('Eurostat contains a non-numeric dimension size.');
            }
            $size = (int) $size;
            if ($size < 1 || $size > 100000) {
                throw new RuntimeException('Eurostat contains an unsafe dimension size.');
            }
            $cell_count *= $size;
            if ($cell_count > 1000000) {
                throw new RuntimeException('Eurostat response exceeds the supported cell limit.');
            }
        }

        foreach ($ids as $id) {
            if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/i', $id) || !array_key_exists($id, $dimensions)) {
                throw new RuntimeException('Eurostat response contains an invalid or missing dimension.');
            }
        }
        foreach (array_keys($values) as $position) {
            if (!is_int($position) && !ctype_digit((string) $position)) {
                throw new RuntimeException('Eurostat value positions must be numeric.');
            }
            if ((int) $position < 0 || (int) $position >= $cell_count) {
                throw new RuntimeException('Eurostat value position exceeds the declared dimensions.');
            }
        }

        return array(
            'dataset' => $dataset,
            'label' => isset($payload['label']) ? trim((string) $payload['label']) : '',
            'updated' => isset($payload['updated']) ? trim((string) $payload['updated']) : '',
            'id' => array_values($ids),
            'size' => array_map('intval', array_values($sizes)),
            'dimension' => $dimensions,
            'value' => $values,
            'status' => isset($payload['status']) && is_array($payload['status']) ? $payload['status'] : array(),
            'cell_count' => $cell_count,
            'source_meta' => self::source_meta($dataset),
        );
    }

    /**
     * Fetches and validates a cached Eurostat response.
     *
     * @param string              $dataset Dataset code.
     * @param array<string,mixed> $filters Dimension filters.
     * @return array<string,mixed>
     */
    public static function fetch($dataset, $filters = array())
    {
        $url = self::build_url($dataset, $filters);
        $cache_key = 'autolex_eurostat_' . substr(hash('sha256', $url), 0, 32);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_safe_remote_get($url, array(
            'timeout' => 25,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'limit_response_size' => self::MAX_RESPONSE_BYTES,
            'user-agent' => 'Autolex-Platform/' . AUTOLEX_PLATFORM_VERSION . ' (+https://autolex.hu/)',
            'headers' => array('Accept' => 'application/json'),
        ));
        if (is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $status) {
            throw new RuntimeException('Official Eurostat API returned HTTP ' . $status . '.');
        }
        $body = (string) wp_remote_retrieve_body($response);
        if ('' === trim($body) || strlen($body) > self::MAX_RESPONSE_BYTES) {
            throw new RuntimeException('Official Eurostat API returned an empty or oversized response.');
        }
        $payload = json_decode($body, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new RuntimeException('Official Eurostat API returned invalid JSON.');
        }

        $validated = self::validate_payload($payload, $dataset);
        $validated['source_url'] = $url;
        $validated['fetched_at'] = gmdate('c');
        set_transient($cache_key, $validated, self::CACHE_TTL);
        return $validated;
    }

    /** @param string $dataset Dataset. @return string */
    private static function normalize_dataset($dataset)
    {
        $dataset = strtolower(trim((string) $dataset));
        if (!in_array($dataset, self::ALLOWED_DATASETS, true)) {
            throw new InvalidArgumentException('Unsupported Eurostat road-transport dataset.');
        }
        return $dataset;
    }

    /** @param mixed $value Filter value. @return string */
    private static function normalize_filter_value($value)
    {
        if (!is_scalar($value)) {
            return '';
        }
        $value = trim((string) $value);
        if (strlen($value) > 80 || !preg_match('/^[A-Za-z0-9_.:\-]+$/', $value)) {
            throw new InvalidArgumentException('Invalid Eurostat filter value.');
        }
        return $value;
    }
}
