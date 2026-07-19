<?php
/**
 * Normalized catalogue for vehicles with verified EU/EEA market presence.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_EU_Catalog
{
    /** Database schema version. */
    const SCHEMA_VERSION = '1.0.0';

    /** @var Autolex_EU_Catalog|null */
    private static $instance = null;

    /**
     * Returns the shared catalogue instance.
     *
     * @return Autolex_EU_Catalog
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Installs or upgrades the catalogue tables.
     *
     * @return void
     */
    public static function install_schema()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $vehicles_table  = self::vehicles_table();
        $markets_table   = self::markets_table();
        $imports_table   = self::imports_table();

        dbDelta(
            "CREATE TABLE {$vehicles_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                fingerprint char(64) NOT NULL,
                manufacturer varchar(191) NOT NULL DEFAULT '',
                make varchar(120) NOT NULL DEFAULT '',
                model varchar(191) NOT NULL DEFAULT '',
                type_approval varchar(80) NOT NULL DEFAULT '',
                variant varchar(80) NOT NULL DEFAULT '',
                version varchar(80) NOT NULL DEFAULT '',
                vehicle_category varchar(20) NOT NULL DEFAULT 'M1',
                fuel_type varchar(60) NOT NULL DEFAULT '',
                fuel_mode varchar(60) NOT NULL DEFAULT '',
                engine_capacity_cc int(10) unsigned DEFAULT NULL,
                engine_power_kw decimal(9,2) DEFAULT NULL,
                mass_kg decimal(9,2) DEFAULT NULL,
                co2_wltp decimal(9,2) DEFAULT NULL,
                wheelbase_mm decimal(9,2) DEFAULT NULL,
                track_width_front_mm decimal(9,2) DEFAULT NULL,
                track_width_rear_mm decimal(9,2) DEFAULT NULL,
                registration_count bigint(20) unsigned NOT NULL DEFAULT 0,
                first_seen_year smallint(5) unsigned DEFAULT NULL,
                last_seen_year smallint(5) unsigned DEFAULT NULL,
                source_code varchar(40) NOT NULL DEFAULT 'EEA_CO2',
                source_status varchar(30) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY fingerprint (fingerprint),
                KEY make_model (make, model),
                KEY category_year (vehicle_category, last_seen_year),
                KEY fuel_type (fuel_type),
                KEY source_code (source_code)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$markets_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                vehicle_id bigint(20) unsigned NOT NULL,
                country_code char(2) NOT NULL,
                registration_count bigint(20) unsigned NOT NULL DEFAULT 0,
                first_seen_year smallint(5) unsigned DEFAULT NULL,
                last_seen_year smallint(5) unsigned DEFAULT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY vehicle_country (vehicle_id, country_code),
                KEY country_year (country_code, last_seen_year),
                KEY vehicle_id (vehicle_id)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$imports_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                source_code varchar(40) NOT NULL,
                source_year smallint(5) unsigned NOT NULL,
                file_name varchar(191) NOT NULL DEFAULT '',
                file_sha256 char(64) NOT NULL DEFAULT '',
                status varchar(30) NOT NULL DEFAULT 'running',
                rows_read bigint(20) unsigned NOT NULL DEFAULT 0,
                rows_accepted bigint(20) unsigned NOT NULL DEFAULT 0,
                rows_skipped bigint(20) unsigned NOT NULL DEFAULT 0,
                started_at datetime NOT NULL,
                completed_at datetime DEFAULT NULL,
                last_error text DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY source_year (source_code, source_year),
                KEY status (status)
            ) {$charset_collate};"
        );

        update_option('autolex_eu_schema_version', self::SCHEMA_VERSION, false);
    }

    /**
     * Returns non-sensitive aggregate coverage figures.
     *
     * @return array<string,int|string|null>
     */
    public function get_coverage()
    {
        global $wpdb;

        if (self::SCHEMA_VERSION !== get_option('autolex_eu_schema_version')) {
            return array(
                'schema_version'   => self::SCHEMA_VERSION,
                'vehicles'         => 0,
                'makes'            => 0,
                'models'           => 0,
                'countries'        => 0,
                'registrations'    => 0,
                'latest_data_year' => null,
            );
        }

        $vehicles_table = self::vehicles_table();
        $markets_table  = self::markets_table();

        $vehicle_counts = $wpdb->get_row(
            "SELECT
                COUNT(*) AS vehicles,
                COUNT(DISTINCT make) AS makes,
                COUNT(DISTINCT CONCAT(make, '\\0', model)) AS models,
                COALESCE(SUM(registration_count), 0) AS registrations,
                MAX(last_seen_year) AS latest_data_year
            FROM {$vehicles_table}",
            ARRAY_A
        );
        $vehicle_counts = array_merge(
            array(
                'vehicles'         => 0,
                'makes'            => 0,
                'models'           => 0,
                'registrations'    => 0,
                'latest_data_year' => null,
            ),
            is_array($vehicle_counts) ? $vehicle_counts : array()
        );

        $countries = (int) $wpdb->get_var("SELECT COUNT(DISTINCT country_code) FROM {$markets_table}");

        return array(
            'schema_version'   => self::SCHEMA_VERSION,
            'vehicles'         => (int) $vehicle_counts['vehicles'],
            'makes'            => (int) $vehicle_counts['makes'],
            'models'           => (int) $vehicle_counts['models'],
            'countries'        => $countries,
            'registrations'    => (int) $vehicle_counts['registrations'],
            'latest_data_year' => $vehicle_counts['latest_data_year'] ? (int) $vehicle_counts['latest_data_year'] : null,
        );
    }

    /**
     * REST callback for aggregate EU catalogue coverage.
     *
     * @return WP_REST_Response
     */
    public function get_coverage_response()
    {
        return rest_ensure_response(
            array_merge(
                array(
                    'service'      => 'autolex-eu-catalog',
                    'status'       => 'ok',
                    'scope'        => array('M1', 'N1'),
                    'market'       => 'EU/EEA',
                    'generated_at' => gmdate('c'),
                ),
                $this->get_coverage()
            )
        );
    }

    /** @return string */
    public static function vehicles_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_eu_vehicles';
    }

    /** @return string */
    public static function markets_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_eu_markets';
    }

    /** @return string */
    public static function imports_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_eu_imports';
    }

    /**
     * Checks the schema once WordPress is initialized.
     */
    private function __construct()
    {
        add_action('init', array($this, 'maybe_install_schema'), 5);
    }

    /** @return void */
    public function maybe_install_schema()
    {
        if (self::SCHEMA_VERSION !== get_option('autolex_eu_schema_version')) {
            self::install_schema();
        }
    }
}
