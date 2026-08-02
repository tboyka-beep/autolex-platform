<?php
/**
 * Field-level source provenance and conflict evidence for Autolex vehicle data.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Source_Provenance
{
    /** Database schema version. */
    const SCHEMA_VERSION = '1.0.0';

    /** Supported evidence states. */
    const STATUS_MANUFACTURER = 'manufacturer_source';
    const STATUS_OFFICIAL = 'official_registry';
    const STATUS_MULTI_SOURCE = 'multi_source_match';
    const STATUS_SINGLE_SOURCE = 'single_source_verified';
    const STATUS_CONFLICT = 'source_conflict';
    const STATUS_INCOMPLETE = 'incomplete';
    const STATUS_VIN_REQUIRED = 'vin_required';

    /** @var Autolex_Source_Provenance|null */
    private static $instance = null;

    /**
     * Returns the shared provenance service.
     *
     * @return Autolex_Source_Provenance
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Installs additive provenance tables without modifying catalogue rows.
     *
     * @return void
     */
    public static function install_schema()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $sources_table = self::sources_table();
        $claims_table = self::claims_table();
        $evidence_table = self::evidence_table();
        $conflicts_table = self::conflicts_table();
        $imports_table = self::imports_table();

        dbDelta(
            "CREATE TABLE {$sources_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                source_key char(64) NOT NULL,
                source_type varchar(40) NOT NULL,
                title varchar(255) NOT NULL,
                publisher varchar(191) NOT NULL DEFAULT '',
                source_url text NOT NULL,
                document_identifier varchar(191) NOT NULL DEFAULT '',
                retrieved_at datetime NOT NULL,
                content_sha256 char(64) NOT NULL DEFAULT '',
                licence_note varchar(255) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY source_key (source_key),
                KEY source_type (source_type),
                KEY publisher (publisher)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$claims_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                entity_type varchar(40) NOT NULL,
                entity_id bigint(20) unsigned NOT NULL,
                field_path varchar(191) NOT NULL,
                canonical_value longtext DEFAULT NULL,
                canonical_value_hash char(64) NOT NULL,
                verification_status varchar(40) NOT NULL DEFAULT 'incomplete',
                source_count smallint(5) unsigned NOT NULL DEFAULT 0,
                conflict_count smallint(5) unsigned NOT NULL DEFAULT 0,
                normalization_rule varchar(191) NOT NULL DEFAULT '',
                reviewed_at datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY entity_field (entity_type, entity_id, field_path),
                KEY verification_status (verification_status),
                KEY entity_type_id (entity_type, entity_id)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$evidence_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                claim_id bigint(20) unsigned NOT NULL,
                source_id bigint(20) unsigned NOT NULL,
                observed_value longtext DEFAULT NULL,
                observed_value_hash char(64) NOT NULL,
                field_scope varchar(191) NOT NULL DEFAULT '',
                evidence_status varchar(40) NOT NULL DEFAULT 'single_source_verified',
                source_locator varchar(255) NOT NULL DEFAULT '',
                observed_at datetime DEFAULT NULL,
                imported_at datetime NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY claim_source_value (claim_id, source_id, observed_value_hash),
                KEY claim_id (claim_id),
                KEY source_id (source_id),
                KEY evidence_status (evidence_status)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$conflicts_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                claim_id bigint(20) unsigned NOT NULL,
                evidence_id bigint(20) unsigned NOT NULL,
                conflicting_evidence_id bigint(20) unsigned NOT NULL,
                conflict_type varchar(60) NOT NULL DEFAULT 'value_mismatch',
                resolution_status varchar(40) NOT NULL DEFAULT 'open',
                resolution_note text DEFAULT NULL,
                resolved_by bigint(20) unsigned DEFAULT NULL,
                resolved_at datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY evidence_pair (claim_id, evidence_id, conflicting_evidence_id),
                KEY claim_status (claim_id, resolution_status),
                KEY resolution_status (resolution_status)
            ) {$charset_collate};"
        );

        dbDelta(
            "CREATE TABLE {$imports_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                batch_key char(64) NOT NULL,
                adapter_code varchar(60) NOT NULL,
                mode varchar(20) NOT NULL DEFAULT 'dry_run',
                status varchar(30) NOT NULL DEFAULT 'running',
                source_count bigint(20) unsigned NOT NULL DEFAULT 0,
                claims_read bigint(20) unsigned NOT NULL DEFAULT 0,
                claims_created bigint(20) unsigned NOT NULL DEFAULT 0,
                claims_updated bigint(20) unsigned NOT NULL DEFAULT 0,
                claims_skipped bigint(20) unsigned NOT NULL DEFAULT 0,
                conflicts_found bigint(20) unsigned NOT NULL DEFAULT 0,
                errors_found bigint(20) unsigned NOT NULL DEFAULT 0,
                report_json longtext DEFAULT NULL,
                started_at datetime NOT NULL,
                completed_at datetime DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY batch_key (batch_key),
                KEY adapter_status (adapter_code, status),
                KEY mode (mode)
            ) {$charset_collate};"
        );

        update_option('autolex_source_provenance_schema_version', self::SCHEMA_VERSION, false);
    }

    /**
     * Returns the allow-listed verification states.
     *
     * @return array<int,string>
     */
    public static function verification_statuses()
    {
        return array(
            self::STATUS_MANUFACTURER,
            self::STATUS_OFFICIAL,
            self::STATUS_MULTI_SOURCE,
            self::STATUS_SINGLE_SOURCE,
            self::STATUS_CONFLICT,
            self::STATUS_INCOMPLETE,
            self::STATUS_VIN_REQUIRED,
        );
    }

    /**
     * Normalizes a requested status without promoting unknown evidence.
     *
     * @param string $status Requested status.
     * @return string
     */
    public static function normalize_status($status)
    {
        $status = sanitize_key((string) $status);

        return in_array($status, self::verification_statuses(), true)
            ? $status
            : self::STATUS_INCOMPLETE;
    }

    /** @return string */
    public static function sources_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_sources';
    }

    /** @return string */
    public static function claims_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_source_claims';
    }

    /** @return string */
    public static function evidence_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_source_evidence';
    }

    /** @return string */
    public static function conflicts_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_source_conflicts';
    }

    /** @return string */
    public static function imports_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_source_imports';
    }

    /** Prevent direct construction. */
    private function __construct()
    {
    }
}
