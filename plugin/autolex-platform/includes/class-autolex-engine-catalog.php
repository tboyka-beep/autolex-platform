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
    const SCHEMA_VERSION = '1.0.0';

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
        $sql = $wpdb->prepare(
            'INSERT IGNORE INTO ' . self::tasks_table() . ' (
                legacy_vehicle_id, make, model, generation, engine_label, engine_code,
                status, priority, attempts, created_at, updated_at
            ) SELECT CAST(`' . $map['id'] . '` AS UNSIGNED), ' .
                $column('make', 120) . ', ' . $column('model', 191) . ', ' .
                $column('generation', 191) . ', ' . $column('engine', 191) . ', ' .
                $column('engine_code', 120) . ", 'pending', 100, 0, %s, %s
            FROM `" . $map['table'] . '`
            WHERE CAST(`' . $map['id'] . '` AS UNSIGNED) > 0',
            $now,
            $now
        );

        $result = $wpdb->query($sql);
        if (false === $result) {
            wp_schedule_single_event(time() + HOUR_IN_SECONDS, 'autolex_engine_seed_queue');
            return;
        }

        update_option('autolex_engine_queue_seeded', 1, false);
        delete_transient('autolex_engine_coverage_v1');
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
