<?php
/**
 * Streaming importer for official EEA passenger-car and van CSV exports.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_EEA_Importer
{
    /** @var bool */
    private static $registered = false;

    /**
     * Registers the WP-CLI command when WP-CLI is available.
     *
     * @return void
     */
    public static function register()
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('autolex eu import-eea', array(__CLASS__, 'cli_import'));
        }
    }

    /**
     * Imports an EEA CSV from WP-CLI.
     *
     * ## OPTIONS
     *
     * <file>
     * : Absolute path to an official EEA CSV export.
     *
     * --year=<year>
     * : Reporting year represented by the source file.
     *
     * [--limit=<rows>]
     * : Optional row limit for a dry-sized production test.
     *
     * [--category=<category>]
     * : Default category when the source omits it. Accepts M1 or N1.
     *
     * ## EXAMPLES
     *
     *     wp autolex eu import-eea /secure/eea-2024.csv --year=2024
     *
     * @param array<int,string>    $args       Positional arguments.
     * @param array<string,string> $assoc_args Named arguments.
     * @return void
     */
    public static function cli_import($args, $assoc_args)
    {
        $file  = isset($args[0]) ? $args[0] : '';
        $year  = isset($assoc_args['year']) ? (int) $assoc_args['year'] : 0;
        $limit = isset($assoc_args['limit']) ? max(0, (int) $assoc_args['limit']) : 0;
        $category = isset($assoc_args['category']) ? strtoupper($assoc_args['category']) : 'M1';

        if (!$file || !is_readable($file)) {
            WP_CLI::error('The EEA CSV file is not readable.');
        }

        if ($year < 2000 || $year > ((int) gmdate('Y') + 1)) {
            WP_CLI::error('The source year is outside the supported range.');
        }

        if (!in_array($category, array('M1', 'N1'), true)) {
            WP_CLI::error('The default category must be M1 or N1.');
        }

        $result = self::import_file($file, $year, $limit, $category);

        if ('completed' !== $result['status']) {
            WP_CLI::error($result['error']);
        }

        WP_CLI::success(
            sprintf(
                'EEA import completed: %d read, %d accepted, %d skipped.',
                $result['rows_read'],
                $result['rows_accepted'],
                $result['rows_skipped']
            )
        );
    }

    /**
     * Streams an EEA CSV into the normalized EU catalogue.
     *
     * @param string $file  Absolute source file path.
     * @param int    $year  EEA reporting year.
     * @param int    $limit Optional maximum data rows.
     * @param string $default_category Category used when the source omits it.
     * @return array<string,int|string>
     */
    public static function import_file($file, $year, $limit = 0, $default_category = 'M1')
    {
        global $wpdb;

        Autolex_EU_Catalog::install_schema();

        $handle = fopen($file, 'rb');
        if (false === $handle) {
            return self::error_result('Unable to open the EEA CSV file.');
        }

        $first_line = fgets($handle);
        if (false === $first_line) {
            fclose($handle);
            return self::error_result('The EEA CSV file is empty.');
        }

        $delimiter = self::detect_delimiter($first_line);
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter);

        if (!$headers) {
            fclose($handle);
            return self::error_result('The EEA CSV header could not be read.');
        }

        $headers = array_map(array(__CLASS__, 'normalize_header'), $headers);
        if (!self::has_required_headers($headers)) {
            fclose($handle);
            return self::error_result('The CSV does not contain recognizable EEA make and model fields.');
        }

        $imports_table = Autolex_EU_Catalog::imports_table();
        $now           = current_time('mysql', true);
        $file_hash     = hash_file('sha256', $file);

        $wpdb->insert(
            $imports_table,
            array(
                'source_code' => 'EEA_CO2',
                'source_year' => $year,
                'file_name'   => wp_basename($file),
                'file_sha256' => $file_hash ? $file_hash : '',
                'status'      => 'running',
                'started_at'  => $now,
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s')
        );
        $import_id = (int) $wpdb->insert_id;

        $read = 0;
        $accepted = 0;
        $skipped = 0;
        $error = '';

        $wpdb->query('START TRANSACTION');

        try {
            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($limit && $read >= $limit) {
                    break;
                }

                ++$read;
                $row = self::combine_row($headers, $values);
                $vehicle = self::normalize_vehicle($row, $year, $default_category);

                if (!$vehicle) {
                    ++$skipped;
                    continue;
                }

                self::upsert_vehicle($vehicle);
                ++$accepted;

                if (0 === ($read % 500)) {
                    $wpdb->query('COMMIT');
                    $wpdb->query('START TRANSACTION');
                    self::update_import_progress($import_id, $read, $accepted, $skipped);
                }
            }

            $wpdb->query('COMMIT');
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');
            $error = $exception->getMessage();
        }

        fclose($handle);

        $status = $error ? 'failed' : 'completed';
        $wpdb->update(
            $imports_table,
            array(
                'status'        => $status,
                'rows_read'     => $read,
                'rows_accepted' => $accepted,
                'rows_skipped'  => $skipped,
                'completed_at'  => current_time('mysql', true),
                'last_error'    => $error ? substr($error, 0, 65000) : null,
            ),
            array('id' => $import_id),
            array('%s', '%d', '%d', '%d', '%s', '%s'),
            array('%d')
        );

        return array(
            'status'        => $status,
            'rows_read'     => $read,
            'rows_accepted' => $accepted,
            'rows_skipped'  => $skipped,
            'error'         => $error,
        );
    }

    /**
     * Normalizes one EEA row and applies the Autolex EU scope.
     *
     * @param array<string,string> $row  Source row.
     * @param int                  $year Source year.
     * @param string               $default_category Category used when absent.
     * @return array<string,int|float|string|null>|null
     */
    public static function normalize_vehicle($row, $year, $default_category = 'M1')
    {
        $manufacturer = self::text(self::pick($row, array('manufacturer_name_eu_standard', 'manufacturer_name', 'manufacturer', 'mh', 'man')));
        $make         = self::text(self::pick($row, array('make', 'mk')));
        $model        = self::text(self::pick($row, array('commercial_name', 'cn', 'model')));
        $category     = strtoupper(self::text(self::pick($row, array('vehicle_category', 'category', 'ct'))));
        $country      = strtoupper(self::text(self::pick($row, array('country', 'member_state', 'ms'))));

        if (!$make) {
            $make = $manufacturer;
        }

        if (!$manufacturer) {
            $manufacturer = $make;
        }

        if (!$make || !$model) {
            return null;
        }

        if (!$category) {
            $category = strtoupper($default_category);
        }

        if (!in_array($category, array('M1', 'N1'), true)) {
            return null;
        }

        $country = preg_replace('/[^A-Z]/', '', $country);
        if (strlen($country) > 2) {
            $country = substr($country, 0, 2);
        }

        $vehicle = array(
            'manufacturer'         => $manufacturer,
            'make'                 => $make,
            'model'                => $model,
            'type_approval'        => self::text(self::pick($row, array('type', 'type_approval', 't'))),
            'variant'              => self::text(self::pick($row, array('variant', 'va'))),
            'version'              => self::text(self::pick($row, array('version', 've'))),
            'vehicle_category'     => $category,
            'fuel_type'            => self::text(self::pick($row, array('fuel_type', 'ft'))),
            'fuel_mode'            => self::text(self::pick($row, array('fuel_mode', 'fm'))),
            'engine_capacity_cc'   => self::integer(self::pick($row, array('engine_capacity_cm3', 'ec_cm3', 'ec'))),
            'engine_power_kw'      => self::decimal(self::pick($row, array('engine_power_kw', 'ep_kw', 'ep'))),
            'mass_kg'              => self::decimal(self::pick($row, array('mass_in_running_order_kg', 'm_kg', 'm'))),
            'co2_wltp'             => self::decimal(self::pick($row, array('wltp_co2', 'ewltp', 'ewltp_g_km', 'specific_co2_emissions_g_km'))),
            'wheelbase_mm'         => self::decimal(self::pick($row, array('wheelbase_mm', 'w_mm', 'w'))),
            'track_width_front_mm' => self::decimal(self::pick($row, array('track_width_front_mm', 'at1_mm', 'at1'))),
            'track_width_rear_mm'  => self::decimal(self::pick($row, array('track_width_rear_mm', 'at2_mm', 'at2'))),
            'registration_count'   => max(1, self::integer(self::pick($row, array('registration_count', 'count', 'registrations', 'r')))),
            'source_status'        => self::text(self::pick($row, array('status', 'sc_status'))),
            'source_year'          => (int) $year,
            'country_code'         => $country,
        );

        $identity = array(
            $vehicle['manufacturer'],
            $vehicle['make'],
            $vehicle['model'],
            $vehicle['type_approval'],
            $vehicle['variant'],
            $vehicle['version'],
            $vehicle['vehicle_category'],
            $vehicle['fuel_type'],
            $vehicle['fuel_mode'],
            $vehicle['engine_capacity_cc'],
            $vehicle['engine_power_kw'],
        );

        $vehicle['fingerprint'] = hash('sha256', strtolower(implode('|', $identity)));

        return $vehicle;
    }

    /**
     * Upserts one normalized vehicle and its EU/EEA market presence.
     *
     * @param array<string,int|float|string|null> $vehicle Normalized row.
     * @return void
     */
    private static function upsert_vehicle($vehicle)
    {
        global $wpdb;

        $vehicles_table = Autolex_EU_Catalog::vehicles_table();
        $markets_table  = Autolex_EU_Catalog::markets_table();
        $now            = current_time('mysql', true);

        $sql = $wpdb->prepare(
            "INSERT INTO {$vehicles_table} (
                fingerprint, manufacturer, make, model, type_approval, variant, version,
                vehicle_category, fuel_type, fuel_mode, engine_capacity_cc, engine_power_kw,
                mass_kg, co2_wltp, wheelbase_mm, track_width_front_mm, track_width_rear_mm,
                registration_count, first_seen_year, last_seen_year, source_code,
                source_status, created_at, updated_at
            ) VALUES (
                %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %f, %f, %f, %f, %f, %f,
                %d, %d, %d, 'EEA_CO2', %s, %s, %s
            ) ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                registration_count = registration_count + VALUES(registration_count),
                first_seen_year = LEAST(first_seen_year, VALUES(first_seen_year)),
                last_seen_year = GREATEST(last_seen_year, VALUES(last_seen_year)),
                mass_kg = COALESCE(VALUES(mass_kg), mass_kg),
                co2_wltp = COALESCE(VALUES(co2_wltp), co2_wltp),
                source_status = VALUES(source_status),
                updated_at = VALUES(updated_at)",
            $vehicle['fingerprint'],
            $vehicle['manufacturer'],
            $vehicle['make'],
            $vehicle['model'],
            $vehicle['type_approval'],
            $vehicle['variant'],
            $vehicle['version'],
            $vehicle['vehicle_category'],
            $vehicle['fuel_type'],
            $vehicle['fuel_mode'],
            $vehicle['engine_capacity_cc'],
            $vehicle['engine_power_kw'],
            $vehicle['mass_kg'],
            $vehicle['co2_wltp'],
            $vehicle['wheelbase_mm'],
            $vehicle['track_width_front_mm'],
            $vehicle['track_width_rear_mm'],
            $vehicle['registration_count'],
            $vehicle['source_year'],
            $vehicle['source_year'],
            $vehicle['source_status'],
            $now,
            $now
        );

        if (false === $wpdb->query($sql)) {
            throw new RuntimeException('Vehicle upsert failed: ' . $wpdb->last_error);
        }

        $vehicle_id = (int) $wpdb->insert_id;
        if (!$vehicle_id || !$vehicle['country_code']) {
            return;
        }

        $market_sql = $wpdb->prepare(
            "INSERT INTO {$markets_table} (
                vehicle_id, country_code, registration_count, first_seen_year, last_seen_year, updated_at
            ) VALUES (%d, %s, %d, %d, %d, %s)
            ON DUPLICATE KEY UPDATE
                registration_count = registration_count + VALUES(registration_count),
                first_seen_year = LEAST(first_seen_year, VALUES(first_seen_year)),
                last_seen_year = GREATEST(last_seen_year, VALUES(last_seen_year)),
                updated_at = VALUES(updated_at)",
            $vehicle_id,
            $vehicle['country_code'],
            $vehicle['registration_count'],
            $vehicle['source_year'],
            $vehicle['source_year'],
            $now
        );

        if (false === $wpdb->query($market_sql)) {
            throw new RuntimeException('EU market upsert failed: ' . $wpdb->last_error);
        }
    }

    /**
     * Idempotently stores one aggregated official-source observation.
     *
     * Unlike the streaming CSV path, this method never increments the same
     * source snapshot twice. It is therefore safe for retried WP-Cron batches.
     *
     * @param array<string,int|float|string|null> $vehicle Normalized EEA row.
     * @return int Normalized EU vehicle ID.
     */
    public static function upsert_vehicle_snapshot($vehicle)
    {
        global $wpdb;

        $vehicles_table     = Autolex_EU_Catalog::vehicles_table();
        $markets_table      = Autolex_EU_Catalog::markets_table();
        $observations_table = Autolex_EU_Catalog::observations_table();
        $now                = current_time('mysql', true);

        $sql = $wpdb->prepare(
            "INSERT INTO {$vehicles_table} (
                fingerprint, manufacturer, make, model, type_approval, variant, version,
                vehicle_category, fuel_type, fuel_mode, engine_capacity_cc, engine_power_kw,
                mass_kg, co2_wltp, wheelbase_mm, track_width_front_mm, track_width_rear_mm,
                registration_count, first_seen_year, last_seen_year, source_code,
                source_status, created_at, updated_at
            ) VALUES (
                %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %f, %f, %f, %f, %f, %f,
                0, %d, %d, 'EEA_CO2', %s, %s, %s
            ) ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                first_seen_year = LEAST(first_seen_year, VALUES(first_seen_year)),
                last_seen_year = GREATEST(last_seen_year, VALUES(last_seen_year)),
                source_status = VALUES(source_status),
                updated_at = VALUES(updated_at)",
            $vehicle['fingerprint'],
            $vehicle['manufacturer'],
            $vehicle['make'],
            $vehicle['model'],
            $vehicle['type_approval'],
            $vehicle['variant'],
            $vehicle['version'],
            $vehicle['vehicle_category'],
            $vehicle['fuel_type'],
            $vehicle['fuel_mode'],
            $vehicle['engine_capacity_cc'],
            $vehicle['engine_power_kw'],
            $vehicle['mass_kg'],
            $vehicle['co2_wltp'],
            $vehicle['wheelbase_mm'],
            $vehicle['track_width_front_mm'],
            $vehicle['track_width_rear_mm'],
            $vehicle['source_year'],
            $vehicle['source_year'],
            $vehicle['source_status'],
            $now,
            $now
        );

        if (false === $wpdb->query($sql)) {
            throw new RuntimeException('EEA snapshot vehicle upsert failed: ' . $wpdb->last_error);
        }

        $vehicle_id = (int) $wpdb->insert_id;
        if (!$vehicle_id) {
            throw new RuntimeException('EEA snapshot vehicle ID was not resolved.');
        }

        $observation_identity = array(
            $vehicle['fingerprint'],
            'EEA_CO2',
            (int) $vehicle['source_year'],
            (string) $vehicle['source_status'],
            (string) $vehicle['country_code'],
        );
        $source_fingerprint = hash('sha256', strtolower(implode('|', $observation_identity)));
        $content_hash       = hash('sha256', wp_json_encode($vehicle));

        $observation_sql = $wpdb->prepare(
            "INSERT INTO {$observations_table} (
                vehicle_id, source_fingerprint, source_code, source_year, source_status,
                country_code, registration_count, content_hash, imported_at, updated_at
            ) VALUES (%d, %s, 'EEA_CO2', %d, %s, %s, %d, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                vehicle_id = VALUES(vehicle_id),
                registration_count = VALUES(registration_count),
                content_hash = VALUES(content_hash),
                updated_at = VALUES(updated_at)",
            $vehicle_id,
            $source_fingerprint,
            $vehicle['source_year'],
            $vehicle['source_status'],
            $vehicle['country_code'],
            $vehicle['registration_count'],
            $content_hash,
            $now,
            $now
        );

        if (false === $wpdb->query($observation_sql)) {
            throw new RuntimeException('EEA source observation upsert failed: ' . $wpdb->last_error);
        }

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(registration_count), 0)
                FROM {$observations_table}
                WHERE vehicle_id = %d",
                $vehicle_id
            )
        );
        $wpdb->update(
            $vehicles_table,
            array('registration_count' => $total, 'updated_at' => $now),
            array('id' => $vehicle_id),
            array('%d', '%s'),
            array('%d')
        );

        if ($vehicle['country_code']) {
            $market = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT COALESCE(SUM(registration_count), 0) AS registrations,
                        MIN(source_year) AS first_year, MAX(source_year) AS last_year
                    FROM {$observations_table}
                    WHERE vehicle_id = %d AND country_code = %s",
                    $vehicle_id,
                    $vehicle['country_code']
                ),
                ARRAY_A
            );
            $market_sql = $wpdb->prepare(
                "INSERT INTO {$markets_table} (
                    vehicle_id, country_code, registration_count, first_seen_year, last_seen_year, updated_at
                ) VALUES (%d, %s, %d, %d, %d, %s)
                ON DUPLICATE KEY UPDATE
                    registration_count = VALUES(registration_count),
                    first_seen_year = VALUES(first_seen_year),
                    last_seen_year = VALUES(last_seen_year),
                    updated_at = VALUES(updated_at)",
                $vehicle_id,
                $vehicle['country_code'],
                (int) ($market['registrations'] ?? 0),
                (int) ($market['first_year'] ?? $vehicle['source_year']),
                (int) ($market['last_year'] ?? $vehicle['source_year']),
                $now
            );
            if (false === $wpdb->query($market_sql)) {
                throw new RuntimeException('EEA snapshot market upsert failed: ' . $wpdb->last_error);
            }
        }

        return $vehicle_id;
    }

    /** @return array<string,int|string> */
    private static function error_result($message)
    {
        return array(
            'status'        => 'failed',
            'rows_read'     => 0,
            'rows_accepted' => 0,
            'rows_skipped'  => 0,
            'error'         => $message,
        );
    }

    /** @return void */
    private static function update_import_progress($import_id, $read, $accepted, $skipped)
    {
        global $wpdb;
        $wpdb->update(
            Autolex_EU_Catalog::imports_table(),
            array(
                'rows_read'     => $read,
                'rows_accepted' => $accepted,
                'rows_skipped'  => $skipped,
            ),
            array('id' => $import_id),
            array('%d', '%d', '%d'),
            array('%d')
        );
    }

    /** @return string */
    private static function detect_delimiter($line)
    {
        $delimiters = array(',' => 0, ';' => 0, "\t" => 0);
        foreach ($delimiters as $delimiter => $count) {
            $delimiters[$delimiter] = substr_count($line, $delimiter);
        }
        arsort($delimiters);
        return (string) key($delimiters);
    }

    /** @return string */
    private static function normalize_header($header)
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        return trim($header, '_');
    }

    /** @return bool */
    private static function has_required_headers($headers)
    {
        $has_make  = (bool) array_intersect($headers, array('make', 'mk', 'manufacturer', 'manufacturer_name', 'manufacturer_name_eu_standard', 'mh', 'man'));
        $has_model = (bool) array_intersect($headers, array('commercial_name', 'cn', 'model'));
        return $has_make && $has_model;
    }

    /** @return array<string,string> */
    private static function combine_row($headers, $values)
    {
        $values = array_pad(array_slice($values, 0, count($headers)), count($headers), '');
        return array_combine($headers, $values);
    }

    /** @return string */
    private static function pick($row, $keys)
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && '' !== trim((string) $row[$key])) {
                return (string) $row[$key];
            }
        }
        return '';
    }

    /** @return string */
    private static function text($value)
    {
        return trim(wp_strip_all_tags((string) $value));
    }

    /** @return int */
    private static function integer($value)
    {
        $number = self::decimal($value);
        return null === $number ? 0 : max(0, (int) round($number));
    }

    /** @return float|null */
    private static function decimal($value)
    {
        $value = str_replace(array("\xc2\xa0", ' '), '', trim((string) $value));
        $value = str_replace(',', '.', $value);
        if ('' === $value || !is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }
}
