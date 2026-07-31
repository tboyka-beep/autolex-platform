<?php
/**
 * Conservative adapter foundation for official EAFO downloadable datasets.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_EAFO
{
    const OFFICIAL_HOST = 'alternative-fuels-observatory.ec.europa.eu';
    const MAX_FILE_BYTES = 10485760;

    const DATASETS = array(
        'af_fleet',
        'af_registrations',
        'af_market_share',
        'recharging_infrastructure',
        'refuelling_infrastructure',
    );

    const FUELS = array('BEV', 'PHEV', 'H2', 'LPG', 'CNG', 'LNG');

    /**
     * Returns the conservative machine-readable source contract.
     *
     * @param string $dataset Dataset key.
     * @return array<string,mixed>
     */
    public static function source_meta($dataset)
    {
        $dataset = self::normalize_dataset($dataset);
        return array(
            'source_code' => 'EAFO_' . strtoupper($dataset),
            'provider' => 'European Alternative Fuels Observatory',
            'dataset' => $dataset,
            'official_host' => self::OFFICIAL_HOST,
            'formats' => array('csv', 'xlsx'),
            'automated_endpoint_verified' => false,
            'human_download_manifest_required' => true,
            'individual_vehicle_verification' => false,
            'scope' => 'country- and period-level alternative-fuel vehicle and infrastructure statistics',
        );
    }

    /**
     * Validates provenance for a manually downloaded official EAFO file.
     *
     * @param array<string,mixed> $manifest Manifest.
     * @return array<string,string|int|bool>
     */
    public static function validate_manifest($manifest)
    {
        if (!is_array($manifest)) {
            throw new InvalidArgumentException('EAFO manifest must be an array.');
        }
        $dataset = self::normalize_dataset($manifest['dataset'] ?? '');
        $source_url = trim((string) ($manifest['source_url'] ?? ''));
        $format = strtolower(trim((string) ($manifest['format'] ?? '')));
        $sha256 = strtolower(trim((string) ($manifest['sha256'] ?? '')));
        $reference_period = trim((string) ($manifest['reference_period'] ?? ''));
        $retrieved_at = trim((string) ($manifest['retrieved_at'] ?? ''));
        $file_size = (int) ($manifest['file_size'] ?? 0);

        if (!self::is_official_url($source_url)) {
            throw new InvalidArgumentException('EAFO source URL is not on the official allowlisted host.');
        }
        if (!in_array($format, array('csv', 'xlsx'), true)) {
            throw new InvalidArgumentException('EAFO manifest format must be CSV or XLSX.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new InvalidArgumentException('EAFO manifest requires a SHA-256 file fingerprint.');
        }
        if ('' === $reference_period || strlen($reference_period) > 80) {
            throw new InvalidArgumentException('EAFO manifest requires a bounded reference period.');
        }
        $timestamp = strtotime($retrieved_at);
        if (!$timestamp || $timestamp > (time() + 300)) {
            throw new InvalidArgumentException('EAFO manifest retrieved_at is invalid.');
        }
        if ($file_size < 1 || $file_size > self::MAX_FILE_BYTES) {
            throw new InvalidArgumentException('EAFO file size is outside the supported limit.');
        }

        return array(
            'dataset' => $dataset,
            'source_url' => $source_url,
            'format' => $format,
            'sha256' => $sha256,
            'reference_period' => $reference_period,
            'retrieved_at' => gmdate('c', $timestamp),
            'file_size' => $file_size,
            'verified_source_host' => true,
            'individual_vehicle_verification' => false,
        );
    }

    /**
     * Normalizes one EAFO CSV row into the Autolex market-statistics contract.
     *
     * @param array<string,mixed> $row Source row.
     * @return array<string,mixed>|false
     */
    public static function normalize_row($row)
    {
        if (!is_array($row)) {
            return false;
        }
        $normalized = array();
        foreach ($row as $key => $value) {
            $key = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', (string) $key));
            if ('' !== $key && is_scalar($value)) {
                $normalized[$key] = trim((string) $value);
            }
        }
        $pick = static function ($aliases) use ($normalized) {
            foreach ($aliases as $alias) {
                $alias = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $alias));
                if (isset($normalized[$alias]) && '' !== $normalized[$alias]) {
                    return $normalized[$alias];
                }
            }
            return '';
        };

        $country = strtoupper($pick(array('countrycode', 'iso2', 'country')));
        $period = $pick(array('referenceperiod', 'period', 'year', 'quarter', 'month'));
        $vehicle_category = strtoupper($pick(array('vehiclecategory', 'vehicleclass', 'category')));
        $fuel = strtoupper($pick(array('fuel', 'fueltype', 'powertrain', 'technology')));
        $metric = strtolower($pick(array('metric', 'indicator', 'series')));
        $unit = strtoupper($pick(array('unit', 'unitofmeasure')));
        $raw_value = str_replace(array(' ', ','), array('', '.'), $pick(array('value', 'amount', 'count')));

        if (!preg_match('/^[A-Z]{2}$/', $country) || '' === $period || '' === $metric || '' === $unit) {
            return false;
        }
        if ('' !== $fuel && !in_array($fuel, self::FUELS, true)) {
            return false;
        }
        if (!is_numeric($raw_value)) {
            return false;
        }
        $value = (float) $raw_value;
        if (!is_finite($value) || $value < 0) {
            return false;
        }

        return array(
            'country' => $country,
            'reference_period' => substr($period, 0, 40),
            'vehicle_category' => substr($vehicle_category, 0, 20),
            'fuel' => $fuel,
            'metric' => substr($metric, 0, 80),
            'value' => $value,
            'unit' => substr($unit, 0, 30),
            'verification_status' => 'source_download_validated',
            'individual_vehicle_verification' => false,
        );
    }

    /** @param string $url URL. @return bool */
    public static function is_official_url($url)
    {
        if (!is_string($url) || 0 !== strpos($url, 'https://')) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts)
            && self::OFFICIAL_HOST === strtolower((string) ($parts['host'] ?? ''))
            && empty($parts['user'])
            && empty($parts['pass']);
    }

    /** @param string $dataset Dataset. @return string */
    private static function normalize_dataset($dataset)
    {
        $dataset = strtolower(trim((string) $dataset));
        if (!in_array($dataset, self::DATASETS, true)) {
            throw new InvalidArgumentException('Unsupported EAFO dataset contract.');
        }
        return $dataset;
    }
}
