<?php
/**
 * Normalized engine variants, source evidence and enrichment queue.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Engine_Catalog
{
    /** Database schema version. */
    const SCHEMA_VERSION = '1.1.0';

    /** @var Autolex_Engine_Catalog|null */
    private static $instance = null;

    /** @return Autolex_Engine_Catalog */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Installs or upgrades all engine-enrichment tables. */
    public static function install_schema()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $variants_table  = self::variants_table();
        $links_table     = self::links_table();
        $sources_table   = self::sources_table();
        $tasks_table     = self::tasks_table();
        $eu_links_table  = self::eu_links_table();

        dbDelta(
            "CREATE TABLE {$variants_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                fingerprint char(64) NOT NULL,
                make varchar(120) NOT NULL DEFAULT '',
                model varchar(191) NOT NULL DEFAULT '',
                generation varchar(191) NOT NULL DEFAULT '',
                engine_code varchar(120) NOT NULL DEFAULT '',
                engine_label varchar(191) NOT NULL DEFAULT '',
                fuel_type varchar(60) NOT NULL DEFAULT '',
                engine_capacity_cc int(10) unsigned DEFAULT NULL,
                engine_power_kw decimal(9,2) DEFAULT NULL,
                engine_power_ps decimal(9,2) DEFAULT NULL,
                cylinders smallint(5) unsigned DEFAULT NULL,
                aspiration varchar(40) NOT NULL DEFAULT '',
                production_start date DEFAULT NULL,
                production_end date DEFAULT NULL,
                verification_status varchar(30) NOT NULL DEFAULT 'unverified',
                source_count smallint(5) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY fingerprint (fingerprint),
                KEY make_model (make, model),
                KEY engine_code (engine_code),
                KEY verification_status (verification_status)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$links_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                legacy_vehicle_id bigint(20) unsigned NOT NULL,
                engine_variant_id bigint(20) unsigned NOT NULL,
                eu_vehicle_id bigint(20) unsigned DEFAULT NULL,
                match_method varchar(40) NOT NULL DEFAULT '',
                match_confidence decimal(5,2) NOT NULL DEFAULT 0,
                status varchar(30) NOT NULL DEFAULT 'proposed',
                reviewed_at datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY legacy_engine (legacy_vehicle_id, engine_variant_id),
                KEY engine_variant_id (engine_variant_id),
                KEY eu_vehicle_id (eu_vehicle_id),
                KEY status (status)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$sources_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                engine_variant_id bigint(20) unsigned NOT NULL,
                field_name varchar(80) NOT NULL DEFAULT '',
                source_fingerprint char(64) NOT NULL,
                source_code varchar(60) NOT NULL DEFAULT '',
                source_name varchar(191) NOT NULL DEFAULT '',
                source_url text NOT NULL,
                source_type varchar(40) NOT NULL DEFAULT '',
                publisher varchar(191) NOT NULL DEFAULT '',
                document_id varchar(120) NOT NULL DEFAULT '',
                accessed_at datetime NOT NULL,
                content_hash char(64) NOT NULL DEFAULT '',
                is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY source_fingerprint (source_fingerprint),
                KEY engine_field (engine_variant_id, field_name),
                KEY source_code (source_code)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$tasks_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                legacy_vehicle_id bigint(20) unsigned NOT NULL,
                make varchar(120) NOT NULL DEFAULT '',
                model varchar(191) NOT NULL DEFAULT '',
                generation varchar(191) NOT NULL DEFAULT '',
                engine_label varchar(191) NOT NULL DEFAULT '',
                engine_code varchar(120) NOT NULL DEFAULT '',
                production_start smallint(5) unsigned DEFAULT NULL,
                production_end smallint(5) unsigned DEFAULT NULL,
                status varchar(30) NOT NULL DEFAULT 'pending',
                priority smallint(5) unsigned NOT NULL DEFAULT 100,
                attempts smallint(5) unsigned NOT NULL DEFAULT 0,
                last_error text DEFAULT NULL,
                locked_at datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY legacy_vehicle_id (legacy_vehicle_id),
                KEY queue_order (status, priority, id),
                KEY make_model (make, model)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$eu_links_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                engine_variant_id bigint(20) unsigned NOT NULL,
                eu_vehicle_id bigint(20) unsigned NOT NULL,
                source_year smallint(5) unsigned NOT NULL,
                status varchar(30) NOT NULL DEFAULT 'official_observation',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY engine_eu_vehicle (engine_variant_id, eu_vehicle_id),
                KEY eu_vehicle_id (eu_vehicle_id),
                KEY source_year (source_year)
            ) {$charset_collate};"
        );

        update_option('autolex_engine_schema_version', self::SCHEMA_VERSION, false);
        update_option('autolex_engine_queue_seeded', 0, false);
    }

    /** @return array<string,mixed> */
    public function get_coverage()
    {
        global $wpdb;

        $legacy = Autolex_Catalog_Browser::instance()->get_engine_coverage();
        $empty  = array(
            'engine_variants'       => 0,
            'verified_variants'     => 0,
            'vin_required_variants' => 0,
            'linked_vehicles'       => 0,
            'queued_vehicles'       => 0,
            'pending_vehicles'      => 0,
            'conflicts'             => 0,
            'evidence_records'      => 0,
            'eu_variant_links'      => 0,
        );

        if (self::SCHEMA_VERSION !== get_option('autolex_engine_schema_version')) {
            return array_merge($legacy, $empty, array('engine_schema_version' => self::SCHEMA_VERSION));
        }

        $variants = $wpdb->get_row(
            'SELECT COUNT(*) AS engine_variants,
                SUM(verification_status = \'verified\') AS verified_variants,
                SUM(verification_status = \'vin_required\') AS vin_required_variants,
                SUM(verification_status = \'conflict\') AS conflicts
            FROM ' . self::variants_table(),
            ARRAY_A
        );
        $queue = $wpdb->get_row(
            'SELECT COUNT(*) AS queued_vehicles,
                SUM(status = \'pending\') AS pending_vehicles,
                SUM(status = \'conflict\') AS queue_conflicts
            FROM ' . self::tasks_table(),
            ARRAY_A
        );

        return array_merge(
            $legacy,
            $empty,
            array(
                'engine_schema_version' => self::SCHEMA_VERSION,
                'engine_variants'       => (int) ($variants['engine_variants'] ?? 0),
                'verified_variants'     => (int) ($variants['verified_variants'] ?? 0),
                'vin_required_variants' => (int) ($variants['vin_required_variants'] ?? 0),
                'linked_vehicles'       => (int) $wpdb->get_var('SELECT COUNT(DISTINCT legacy_vehicle_id) FROM ' . self::links_table()),
                'queued_vehicles'       => (int) ($queue['queued_vehicles'] ?? 0),
                'pending_vehicles'      => (int) ($queue['pending_vehicles'] ?? 0),
                'conflicts'             => (int) (($variants['conflicts'] ?? 0) + ($queue['queue_conflicts'] ?? 0)),
                'evidence_records'      => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::sources_table()),
                'eu_variant_links'      => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::eu_links_table()),
            )
        );
    }

    /** @return WP_REST_Response */
    public function get_coverage_response()
    {
        $response = rest_ensure_response(
            array_merge(
                array(
                    'service'             => 'autolex-engine-catalog',
                    'status'              => 'ok',
                    'market'              => 'EU/EEA',
                    'accuracy_policy'     => 'source_required',
                    'verification_states' => array('pending', 'proposed', 'reviewed', 'verified', 'vin_required', 'conflict'),
                    'generated_at'        => gmdate('c'),
                ),
                $this->get_coverage()
            )
        );
        $response->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
        return $response;
    }

    /** Ensures schema and schedules a DB-side queue seed. */
    public function maybe_install_schema()
    {
        if (self::SCHEMA_VERSION !== get_option('autolex_engine_schema_version')) {
            self::install_schema();
        }

        if (!get_option('autolex_engine_queue_seeded') && !wp_next_scheduled('autolex_engine_seed_queue')) {
            wp_schedule_single_event(time() + 5, 'autolex_engine_seed_queue');
        }
    }

    /** Copies every legacy catalogue row into the durable enrichment queue. */
    public function seed_queue()
    {
        global $wpdb;

        $map = Autolex_Catalog_Browser::instance()->get_legacy_mapping();
        if (!$map || empty($map['table']) || empty($map['id'])) {
            wp_schedule_single_event(time() + HOUR_IN_SECONDS, 'autolex_engine_seed_queue');
            return;
        }

        $column = static function ($key, $length) use ($map) {
            if (empty($map[$key])) {
                return "''";
            }
            return 'LEFT(COALESCE(`' . $map[$key] . "`, ''), " . (int) $length . ')';
        };
        $now = current_time('mysql', true);
        $year_from = $map['year_from']
            ? 'NULLIF(CAST(`' . $map['year_from'] . '` AS UNSIGNED), 0)'
            : 'NULL';
        $year_to = $map['year_to']
            ? 'NULLIF(CAST(`' . $map['year_to'] . '` AS UNSIGNED), 0)'
            : 'NULL';
        $seed_query = 'INSERT INTO ' . self::tasks_table() . ' (
                legacy_vehicle_id, make, model, generation, engine_label, engine_code,
                production_start, production_end, status, priority, attempts, created_at, updated_at
            ) SELECT CAST(`' . $map['id'] . '` AS UNSIGNED), ' .
            $column('make', 120) . ', ' . $column('model', 191) . ', ' .
            $column('generation', 191) . ', ' . $column('engine', 191) . ', ' .
            $column('engine_code', 120) . ', ' . $year_from . ', ' . $year_to . ",
            'pending', 100, 0, %s, %s
            FROM `{$map['table']}`
            WHERE CAST(`{$map['id']}` AS UNSIGNED) > 0
            ON DUPLICATE KEY UPDATE
                make = VALUES(make), model = VALUES(model), generation = VALUES(generation),
                engine_label = VALUES(engine_label), engine_code = VALUES(engine_code),
                production_start = VALUES(production_start), production_end = VALUES(production_end),
                updated_at = VALUES(updated_at)";
        $sql = $wpdb->prepare($seed_query, $now, $now);

        $result = $wpdb->query($sql);
        if (false === $result) {
            wp_schedule_single_event(time() + HOUR_IN_SECONDS, 'autolex_engine_seed_queue');
            return;
        }

        update_option('autolex_engine_queue_seeded', 1, false);
        delete_transient('autolex_engine_coverage_v1');
    }

    /**
     * Stores an EEA-derived engine specification and proposes conservative
     * links to legacy rows with the same make, commercial name and year.
     * EEA data never verifies an engine code because that field is not present
     * in the CO2 monitoring dataset.
     *
     * @param array<string,int|float|string|null> $vehicle      Normalized EEA row.
     * @param int                                 $eu_vehicle_id EU catalogue ID.
     * @return array<string,int>
     */
    public function ingest_eea_vehicle($vehicle, $eu_vehicle_id)
    {
        global $wpdb;

        $make     = trim((string) ($vehicle['make'] ?? ''));
        $model    = trim((string) ($vehicle['model'] ?? ''));
        $fuel     = trim((string) ($vehicle['fuel_type'] ?? ''));
        $capacity = (int) ($vehicle['engine_capacity_cc'] ?? 0);
        $power_kw = (float) ($vehicle['engine_power_kw'] ?? 0);
        $year     = (int) ($vehicle['source_year'] ?? 0);

        if (!$make || !$model || (!$fuel && !$capacity && !$power_kw) || $year < 2000) {
            return array('engine_variant_id' => 0, 'links' => 0, 'sources' => 0);
        }

        $power_ps = $power_kw ? round($power_kw * 1.3596216173, 2) : 0;
        $label_parts = array_filter(
            array(
                $model,
                $fuel,
                $capacity ? $capacity . ' cm³' : '',
                $power_kw ? sprintf('%g kW / %g LE', $power_kw, round($power_ps)) : '',
            )
        );
        $engine_label = implode(' · ', $label_parts);
        $fingerprint  = hash(
            'sha256',
            strtolower(implode('|', array($make, $model, $fuel, $capacity, number_format($power_kw, 2, '.', ''))))
        );
        $now = current_time('mysql', true);

        $variant_sql = $wpdb->prepare(
            'INSERT INTO ' . self::variants_table() . ' (
                fingerprint, make, model, generation, engine_code, engine_label,
                fuel_type, engine_capacity_cc, engine_power_kw, engine_power_ps,
                verification_status, source_count, created_at, updated_at
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %d, %f, %f, %s, 0, %s, %s)
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                engine_label = VALUES(engine_label),
                fuel_type = VALUES(fuel_type),
                engine_capacity_cc = NULLIF(VALUES(engine_capacity_cc), 0),
                engine_power_kw = NULLIF(VALUES(engine_power_kw), 0),
                engine_power_ps = NULLIF(VALUES(engine_power_ps), 0),
                updated_at = VALUES(updated_at)',
            $fingerprint,
            $make,
            $model,
            '',
            '',
            $engine_label,
            $fuel,
            $capacity,
            $power_kw,
            $power_ps,
            'proposed',
            $now,
            $now
        );
        if (false === $wpdb->query($variant_sql)) {
            throw new RuntimeException('EEA engine variant upsert failed: ' . $wpdb->last_error);
        }
        $engine_variant_id = (int) $wpdb->insert_id;

        $eu_link_sql = $wpdb->prepare(
            'INSERT INTO ' . self::eu_links_table() . ' (
                engine_variant_id, eu_vehicle_id, source_year, status, created_at, updated_at
            ) VALUES (%d, %d, %d, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                source_year = VALUES(source_year), status = VALUES(status), updated_at = VALUES(updated_at)',
            $engine_variant_id,
            (int) $eu_vehicle_id,
            $year,
            'official_observation',
            $now,
            $now
        );
        if (false === $wpdb->query($eu_link_sql)) {
            throw new RuntimeException('EEA engine-to-EU-variant link failed: ' . $wpdb->last_error);
        }

        $document_id = sprintf(
            'EEA-CO2-CARS-%d-%s',
            $year,
            strtoupper(substr((string) ($vehicle['source_status'] ?? 'F'), 0, 8))
        );
        $source_url = 'https://sdi.eea.europa.eu/catalogue/srv/api/records/fa8b1229-3db6-495d-b18e-9c9b3267c02b';
        $evidence = array(
            'market_presence'       => $model,
            'type_variant_version'  => implode('/', array_filter(array($vehicle['type_approval'] ?? '', $vehicle['variant'] ?? '', $vehicle['version'] ?? ''))),
            'fuel_type'             => $fuel,
            'engine_capacity_cc'    => $capacity,
            'engine_power_kw'       => $power_kw,
        );
        $source_count = 0;
        foreach ($evidence as $field => $value) {
            if ('' === (string) $value || 0 === $value || 0.0 === $value) {
                continue;
            }
            $source_fingerprint = hash('sha256', implode('|', array($engine_variant_id, $field, $document_id)));
            $content_hash       = hash('sha256', wp_json_encode(array($field => $value, 'vehicle' => $vehicle['fingerprint'] ?? '')));
            $source_sql = $wpdb->prepare(
                'INSERT INTO ' . self::sources_table() . ' (
                    engine_variant_id, field_name, source_fingerprint, source_code,
                    source_name, source_url, source_type, publisher, document_id,
                    accessed_at, content_hash, is_primary, created_at
                ) VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 1, %s)
                ON DUPLICATE KEY UPDATE
                    accessed_at = VALUES(accessed_at),
                    content_hash = VALUES(content_hash),
                    is_primary = 1',
                $engine_variant_id,
                $field,
                $source_fingerprint,
                'EEA_CO2',
                'EEA CO2 monitoring – passenger cars',
                $source_url,
                'official_registry',
                'European Environment Agency',
                $document_id,
                $now,
                $content_hash,
                $now
            );
            if (false === $wpdb->query($source_sql)) {
                throw new RuntimeException('EEA engine source upsert failed: ' . $wpdb->last_error);
            }
            ++$source_count;
        }

        $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . self::variants_table() . '
                SET source_count = (
                    SELECT COUNT(DISTINCT field_name) FROM ' . self::sources_table() . '
                    WHERE engine_variant_id = %d
                ), updated_at = %s
                WHERE id = %d',
                $engine_variant_id,
                $now,
                $engine_variant_id
            )
        );

        $candidates = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT legacy_vehicle_id,
                    CASE WHEN LOWER(TRIM(engine_label)) = LOWER(%s) THEN 92 ELSE 78 END AS confidence
                FROM ' . self::tasks_table() . '
                WHERE LOWER(TRIM(make)) = LOWER(%s)
                    AND (LOWER(TRIM(engine_label)) = LOWER(%s) OR LOWER(TRIM(model)) = LOWER(%s))
                    AND (production_start IS NULL OR production_start = 0 OR production_start <= %d)
                    AND (production_end IS NULL OR production_end = 0 OR production_end >= %d)',
                $model,
                $make,
                $model,
                $model,
                $year,
                $year
            ),
            ARRAY_A
        );
        $links = 0;
        foreach ((array) $candidates as $candidate) {
            $link_sql = $wpdb->prepare(
                'INSERT INTO ' . self::links_table() . ' (
                    legacy_vehicle_id, engine_variant_id, eu_vehicle_id, match_method,
                    match_confidence, status, created_at, updated_at
                ) VALUES (%d, %d, %d, %s, %f, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    eu_vehicle_id = VALUES(eu_vehicle_id),
                    match_confidence = GREATEST(match_confidence, VALUES(match_confidence)),
                    status = IF(status = \'verified\', status, VALUES(status)),
                    updated_at = VALUES(updated_at)',
                (int) $candidate['legacy_vehicle_id'],
                $engine_variant_id,
                (int) $eu_vehicle_id,
                'eea_commercial_name_year',
                (float) $candidate['confidence'],
                'proposed',
                $now,
                $now
            );
            if (false === $wpdb->query($link_sql)) {
                throw new RuntimeException('EEA vehicle-engine proposal failed: ' . $wpdb->last_error);
            }
            ++$links;
        }

        if ($links) {
            $legacy_ids = array_map('intval', wp_list_pluck((array) $candidates, 'legacy_vehicle_id'));
            $legacy_ids = array_filter(array_unique($legacy_ids));
            if ($legacy_ids) {
                $wpdb->query(
                    'UPDATE ' . self::tasks_table() . " SET status = 'proposed', updated_at = '" . esc_sql($now) . "'
                    WHERE status = 'pending' AND legacy_vehicle_id IN (" . implode(',', $legacy_ids) . ')'
                );
            }
        }

        delete_transient('autolex_engine_coverage_v1');
        return array(
            'engine_variant_id' => $engine_variant_id,
            'links'             => $links,
            'sources'           => $source_count,
        );
    }

    /** @return string */
    public static function variants_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_engine_variants';
    }

    /** @return string */
    public static function links_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_vehicle_engine_links';
    }

    /** @return string */
    public static function sources_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_engine_sources';
    }

    /** @return string */
    public static function tasks_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_engine_tasks';
    }

    /** @return string */
    public static function eu_links_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_engine_eu_links';
    }

    /** Registers hooks. */
    private function __construct()
    {
        add_action('init', array($this, 'maybe_install_schema'), 6);
        add_action('autolex_engine_seed_queue', array($this, 'seed_queue'));
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /** @return void */
    public function register_routes()
    {
        register_rest_route(
            'autolex/v1',
            '/engine-coverage',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_coverage_response'),
                'permission_callback' => '__return_true',
            )
        );
    }
}
