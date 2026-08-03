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
    const SCHEMA_VERSION = '1.0.0';

    const STATUS_MANUFACTURER = 'manufacturer_source';
    const STATUS_OFFICIAL = 'official_registry';
    const STATUS_MULTI_SOURCE = 'multi_source_match';
    const STATUS_SINGLE_SOURCE = 'single_source_confirmed';
    const STATUS_CONFLICT = 'source_conflict';
    const STATUS_INCOMPLETE = 'incomplete';
    const STATUS_VIN_REQUIRED = 'vin_required';

    /** @var Autolex_Source_Provenance|null */
    private static $instance = null;

    /** @return Autolex_Source_Provenance */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** @return void */
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

        dbDelta("CREATE TABLE {$sources_table} (
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
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$claims_table} (
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
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$evidence_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            claim_id bigint(20) unsigned NOT NULL,
            source_id bigint(20) unsigned NOT NULL,
            observed_value longtext DEFAULT NULL,
            observed_value_hash char(64) NOT NULL,
            field_scope varchar(191) NOT NULL DEFAULT '',
            evidence_status varchar(40) NOT NULL DEFAULT 'single_source_confirmed',
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
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$conflicts_table} (
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
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$imports_table} (
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
        ) {$charset_collate};");

        update_option('autolex_source_provenance_schema_version', self::SCHEMA_VERSION, false);
    }

    /** @return array<int,string> */
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

    /** @param string $status Requested status. @return string */
    public static function normalize_status($status)
    {
        $status = sanitize_key((string) $status);
        return in_array($status, self::verification_statuses(), true) ? $status : self::STATUS_INCOMPLETE;
    }

    /**
     * Creates a stable source identity without using volatile retrieval timestamps.
     *
     * @param array<string,mixed> $source Source metadata.
     * @return string
     */
    public static function source_key(array $source)
    {
        $identity = array(
            'source_type' => sanitize_key(isset($source['source_type']) ? $source['source_type'] : ''),
            'publisher' => self::clean_text(isset($source['publisher']) ? $source['publisher'] : ''),
            'source_url' => self::clean_url(isset($source['source_url']) ? $source['source_url'] : ''),
            'document_identifier' => self::clean_text(isset($source['document_identifier']) ? $source['document_identifier'] : ''),
        );
        return hash('sha256', self::encode_value($identity));
    }

    /** @param mixed $value Value to hash. @return string */
    public static function value_hash($value)
    {
        return hash('sha256', self::encode_value($value));
    }

    /**
     * Idempotently inserts or refreshes source metadata.
     *
     * @param array<string,mixed> $source Source metadata.
     * @param bool                $dry_run Do not write.
     * @return array<string,mixed>|WP_Error
     */
    public function upsert_source(array $source, $dry_run = false)
    {
        global $wpdb;

        $title = self::clean_text(isset($source['title']) ? $source['title'] : '');
        $url = self::clean_url(isset($source['source_url']) ? $source['source_url'] : '');
        $retrieved_at = self::clean_datetime(isset($source['retrieved_at']) ? $source['retrieved_at'] : '');
        if ('' === $title || '' === $url || '' === $retrieved_at) {
            return new WP_Error('autolex_invalid_source', 'A forrás címe, HTTPS URL-je és lekérési időpontja kötelező.');
        }

        $key = self::source_key($source);
        $existing_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::sources_table() . ' WHERE source_key = %s', $key));
        $now = current_time('mysql', true);
        $data = array(
            'source_key' => $key,
            'source_type' => sanitize_key(isset($source['source_type']) ? $source['source_type'] : 'secondary_reference'),
            'title' => $title,
            'publisher' => self::clean_text(isset($source['publisher']) ? $source['publisher'] : ''),
            'source_url' => $url,
            'document_identifier' => self::clean_text(isset($source['document_identifier']) ? $source['document_identifier'] : ''),
            'retrieved_at' => $retrieved_at,
            'content_sha256' => self::clean_sha256(isset($source['content_sha256']) ? $source['content_sha256'] : ''),
            'licence_note' => self::clean_text(isset($source['licence_note']) ? $source['licence_note'] : ''),
            'updated_at' => $now,
        );

        if ($dry_run) {
            return array('id' => $existing_id, 'source_key' => $key, 'action' => $existing_id ? 'would_update' : 'would_create');
        }

        if ($existing_id) {
            $wpdb->update(self::sources_table(), $data, array('id' => $existing_id));
            return array('id' => $existing_id, 'source_key' => $key, 'action' => 'updated');
        }

        $data['created_at'] = $now;
        $wpdb->insert(self::sources_table(), $data);
        if (!$wpdb->insert_id) {
            return new WP_Error('autolex_source_write_failed', 'A forrás mentése sikertelen.');
        }
        return array('id' => (int) $wpdb->insert_id, 'source_key' => $key, 'action' => 'created');
    }

    /**
     * Idempotently creates or refreshes one canonical entity-field claim.
     *
     * @param string $entity_type Entity type.
     * @param int    $entity_id Entity ID.
     * @param string $field_path Stable field path.
     * @param mixed  $canonical_value Canonical value.
     * @param array<string,mixed> $args Optional status and normalization metadata.
     * @param bool   $dry_run Do not write.
     * @return array<string,mixed>|WP_Error
     */
    public function upsert_claim($entity_type, $entity_id, $field_path, $canonical_value, array $args = array(), $dry_run = false)
    {
        global $wpdb;
        $entity_type = sanitize_key($entity_type);
        $entity_id = absint($entity_id);
        $field_path = self::clean_field_path($field_path);
        if ('' === $entity_type || 0 === $entity_id || '' === $field_path) {
            return new WP_Error('autolex_invalid_claim', 'Az entitás, azonosító és mezőútvonal kötelező.');
        }

        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::claims_table() . ' WHERE entity_type = %s AND entity_id = %d AND field_path = %s',
            $entity_type,
            $entity_id,
            $field_path
        ));
        $now = current_time('mysql', true);
        $data = array(
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'field_path' => $field_path,
            'canonical_value' => self::encode_value($canonical_value),
            'canonical_value_hash' => self::value_hash($canonical_value),
            'verification_status' => self::normalize_status(isset($args['verification_status']) ? $args['verification_status'] : self::STATUS_INCOMPLETE),
            'normalization_rule' => self::clean_text(isset($args['normalization_rule']) ? $args['normalization_rule'] : ''),
            'updated_at' => $now,
        );

        if ($dry_run) {
            return array('id' => $existing_id, 'action' => $existing_id ? 'would_update' : 'would_create', 'value_hash' => $data['canonical_value_hash']);
        }
        if ($existing_id) {
            $wpdb->update(self::claims_table(), $data, array('id' => $existing_id));
            return array('id' => $existing_id, 'action' => 'updated', 'value_hash' => $data['canonical_value_hash']);
        }

        $data['created_at'] = $now;
        $wpdb->insert(self::claims_table(), $data);
        if (!$wpdb->insert_id) {
            return new WP_Error('autolex_claim_write_failed', 'Az állítás mentése sikertelen.');
        }
        return array('id' => (int) $wpdb->insert_id, 'action' => 'created', 'value_hash' => $data['canonical_value_hash']);
    }

    /**
     * Records idempotent evidence and preserves every differing observation as a conflict.
     *
     * @param int $claim_id Claim ID.
     * @param int $source_id Source ID.
     * @param mixed $observed_value Observed value.
     * @param array<string,mixed> $args Evidence metadata.
     * @param bool $dry_run Do not write.
     * @return array<string,mixed>|WP_Error
     */
    public function record_evidence($claim_id, $source_id, $observed_value, array $args = array(), $dry_run = false)
    {
        global $wpdb;
        $claim_id = absint($claim_id);
        $source_id = absint($source_id);
        if (0 === $claim_id || 0 === $source_id) {
            return new WP_Error('autolex_invalid_evidence', 'Az állítás- és forrásazonosító kötelező.');
        }

        $value_hash = self::value_hash($observed_value);
        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::evidence_table() . ' WHERE claim_id = %d AND source_id = %d AND observed_value_hash = %s',
            $claim_id,
            $source_id,
            $value_hash
        ));
        $different_ids = $wpdb->get_col($wpdb->prepare(
            'SELECT id FROM ' . self::evidence_table() . ' WHERE claim_id = %d AND observed_value_hash <> %s ORDER BY id ASC',
            $claim_id,
            $value_hash
        ));
        $different_ids = array_map('intval', is_array($different_ids) ? $different_ids : array());

        if ($dry_run) {
            return array(
                'id' => $existing_id,
                'action' => $existing_id ? 'would_skip_duplicate' : 'would_create',
                'value_hash' => $value_hash,
                'conflicts' => count($different_ids),
            );
        }
        if ($existing_id) {
            return array('id' => $existing_id, 'action' => 'skipped_duplicate', 'value_hash' => $value_hash, 'conflicts' => count($different_ids));
        }

        $now = current_time('mysql', true);
        $wpdb->insert(self::evidence_table(), array(
            'claim_id' => $claim_id,
            'source_id' => $source_id,
            'observed_value' => self::encode_value($observed_value),
            'observed_value_hash' => $value_hash,
            'field_scope' => self::clean_field_path(isset($args['field_scope']) ? $args['field_scope'] : ''),
            'evidence_status' => self::normalize_status(isset($args['evidence_status']) ? $args['evidence_status'] : self::STATUS_SINGLE_SOURCE),
            'source_locator' => self::clean_text(isset($args['source_locator']) ? $args['source_locator'] : ''),
            'observed_at' => self::clean_datetime(isset($args['observed_at']) ? $args['observed_at'] : ''),
            'imported_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ));
        $evidence_id = (int) $wpdb->insert_id;
        if (!$evidence_id) {
            return new WP_Error('autolex_evidence_write_failed', 'A bizonyíték mentése sikertelen.');
        }

        foreach ($different_ids as $other_id) {
            $first_id = min($evidence_id, $other_id);
            $second_id = max($evidence_id, $other_id);
            $wpdb->query($wpdb->prepare(
                'INSERT IGNORE INTO ' . self::conflicts_table() . ' (claim_id, evidence_id, conflicting_evidence_id, conflict_type, resolution_status, created_at, updated_at) VALUES (%d, %d, %d, %s, %s, %s, %s)',
                $claim_id,
                $first_id,
                $second_id,
                'value_mismatch',
                'open',
                $now,
                $now
            ));
        }

        $source_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(DISTINCT source_id) FROM ' . self::evidence_table() . ' WHERE claim_id = %d', $claim_id));
        $conflict_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::conflicts_table() . ' WHERE claim_id = %d AND resolution_status = %s', $claim_id, 'open'));
        $status = $conflict_count > 0 ? self::STATUS_CONFLICT : ($source_count > 1 ? self::STATUS_MULTI_SOURCE : self::STATUS_SINGLE_SOURCE);
        $wpdb->update(self::claims_table(), array(
            'verification_status' => $status,
            'source_count' => $source_count,
            'conflict_count' => $conflict_count,
            'updated_at' => $now,
        ), array('id' => $claim_id));

        return array('id' => $evidence_id, 'action' => 'created', 'value_hash' => $value_hash, 'conflicts' => $conflict_count, 'verification_status' => $status);
    }

    /** @param mixed $value Value. @return string */
    private static function encode_value($value)
    {
        if (is_array($value)) {
            ksort($value);
        }
        return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param mixed $value Value. @return string */
    private static function clean_text($value)
    {
        return trim(sanitize_text_field((string) $value));
    }

    /** @param mixed $value Value. @return string */
    private static function clean_url($value)
    {
        $url = esc_url_raw(trim((string) $value), array('https'));
        return 0 === strpos($url, 'https://') ? $url : '';
    }

    /** @param mixed $value Value. @return string */
    private static function clean_sha256($value)
    {
        $value = strtolower(trim((string) $value));
        return preg_match('/^[a-f0-9]{64}$/', $value) ? $value : '';
    }

    /** @param mixed $value Value. @return string */
    private static function clean_datetime($value)
    {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }
        $timestamp = strtotime($value);
        return false === $timestamp ? '' : gmdate('Y-m-d H:i:s', $timestamp);
    }

    /** @param mixed $value Value. @return string */
    private static function clean_field_path($value)
    {
        return trim(preg_replace('/[^a-zA-Z0-9_.\-]/', '', (string) $value), '.');
    }

    /** @return string */
    public static function sources_table() { global $wpdb; return $wpdb->prefix . 'autolex_sources'; }
    /** @return string */
    public static function claims_table() { global $wpdb; return $wpdb->prefix . 'autolex_source_claims'; }
    /** @return string */
    public static function evidence_table() { global $wpdb; return $wpdb->prefix . 'autolex_source_evidence'; }
    /** @return string */
    public static function conflicts_table() { global $wpdb; return $wpdb->prefix . 'autolex_source_conflicts'; }
    /** @return string */
    public static function imports_table() { global $wpdb; return $wpdb->prefix . 'autolex_source_imports'; }

    private function __construct() {}
}
