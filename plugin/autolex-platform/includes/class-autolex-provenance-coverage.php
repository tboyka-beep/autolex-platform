<?php
/**
 * Read-only provenance coverage reporting for Autolex 4.2.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Provenance_Coverage
{
    /** @var Autolex_Provenance_Coverage|null */
    private static $instance = null;

    /** @return Autolex_Provenance_Coverage */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** @return void */
    public function register()
    {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /** @return void */
    public function register_rest_routes()
    {
        register_rest_route(
            'autolex/v1',
            '/provenance-coverage',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_coverage_response'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /** @return WP_REST_Response|WP_Error */
    public function get_coverage_response()
    {
        $coverage = $this->get_coverage();
        if (is_wp_error($coverage)) {
            return $coverage;
        }

        $response = rest_ensure_response($coverage);
        $response->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60');
        return $response;
    }

    /** @return array<string,mixed>|WP_Error */
    public function get_coverage()
    {
        global $wpdb;

        $claims_table = Autolex_Source_Provenance::claims_table();
        $sources_table = Autolex_Source_Provenance::sources_table();
        $evidence_table = Autolex_Source_Provenance::evidence_table();

        $totals = $wpdb->get_row(
            "SELECT COUNT(*) AS claims, COUNT(DISTINCT CONCAT(entity_type, ':', entity_id)) AS entities, SUM(CASE WHEN conflict_count > 0 THEN 1 ELSE 0 END) AS conflicting_claims FROM {$claims_table}",
            ARRAY_A
        );
        if (null === $totals) {
            return new WP_Error('autolex_provenance_coverage_query_failed', __('A proveniencia-lefedettség nem kérdezhető le.', 'autolex-platform'), array('status' => 500));
        }

        $status_rows = $wpdb->get_results(
            "SELECT verification_status, COUNT(*) AS claim_count FROM {$claims_table} GROUP BY verification_status ORDER BY verification_status ASC",
            ARRAY_A
        );
        $type_rows = $wpdb->get_results(
            "SELECT s.source_type, COUNT(DISTINCT s.id) AS source_count, COUNT(e.id) AS evidence_count FROM {$sources_table} s LEFT JOIN {$evidence_table} e ON e.source_id = s.id GROUP BY s.source_type ORDER BY s.source_type ASC",
            ARRAY_A
        );
        $entity_rows = $wpdb->get_results(
            "SELECT entity_type, COUNT(DISTINCT entity_id) AS entity_count, COUNT(*) AS claim_count FROM {$claims_table} GROUP BY entity_type ORDER BY entity_type ASC",
            ARRAY_A
        );

        if (null === $status_rows || null === $type_rows || null === $entity_rows) {
            return new WP_Error('autolex_provenance_coverage_query_failed', __('A proveniencia-lefedettség nem kérdezhető le.', 'autolex-platform'), array('status' => 500));
        }

        $statuses = array();
        foreach ($status_rows as $row) {
            $status = Autolex_Source_Provenance::normalize_status(isset($row['verification_status']) ? $row['verification_status'] : '');
            $statuses[$status] = absint(isset($row['claim_count']) ? $row['claim_count'] : 0);
        }

        $source_types = array();
        foreach ($type_rows as $row) {
            $type = sanitize_key(isset($row['source_type']) ? $row['source_type'] : '');
            if ('' === $type) {
                continue;
            }
            $source_types[$type] = array(
                'sources'  => absint(isset($row['source_count']) ? $row['source_count'] : 0),
                'evidence' => absint(isset($row['evidence_count']) ? $row['evidence_count'] : 0),
            );
        }

        $entities = array();
        foreach ($entity_rows as $row) {
            $type = sanitize_key(isset($row['entity_type']) ? $row['entity_type'] : '');
            if (!Autolex_Source_Cards::validate_entity_type($type)) {
                continue;
            }
            $entities[$type] = array(
                'entities' => absint(isset($row['entity_count']) ? $row['entity_count'] : 0),
                'claims'   => absint(isset($row['claim_count']) ? $row['claim_count'] : 0),
            );
        }

        return array(
            'claims'             => absint(isset($totals['claims']) ? $totals['claims'] : 0),
            'entities'           => absint(isset($totals['entities']) ? $totals['entities'] : 0),
            'conflicting_claims' => absint(isset($totals['conflicting_claims']) ? $totals['conflicting_claims'] : 0),
            'statuses'           => $statuses,
            'source_types'       => $source_types,
            'entity_types'       => $entities,
            'generated_at'       => gmdate('c'),
        );
    }

    private function __construct() {}
}
