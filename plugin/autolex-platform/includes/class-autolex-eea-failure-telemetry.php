<?php
/**
 * Sanitized operational telemetry for failed EEA synchronization targets.
 *
 * No raw target names or exception messages are exposed publicly. The endpoint
 * only publishes bounded aggregate counts so production failures can be triaged
 * without leaking internal database or source details.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_EEA_Failure_Telemetry
{
    /** @var Autolex_EEA_Failure_Telemetry|null */
    private static $instance = null;

    /** Maximum failed rows inspected in one public telemetry request. */
    const MAX_ROWS = 1000;

    /** @return Autolex_EEA_Failure_Telemetry */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Maps an internal exception message to a fixed, non-sensitive category.
     *
     * @param string $message Internal last_error value.
     * @return string
     */
    public static function classify_failure_message($message)
    {
        $message = trim((string) $message);
        if ('' === $message) {
            return 'missing_error';
        }

        if (0 === strpos($message, 'EEA Discodata request failed:')) {
            return 'transport';
        }
        if (0 === strpos($message, 'EEA Discodata returned HTTP ')) {
            return 'http';
        }
        if (0 === strpos($message, 'EEA Discodata rejected the read-only query.')) {
            return 'api_rejected';
        }
        if (0 === strpos($message, 'EEA Discodata returned invalid JSON.') ||
            0 === strpos($message, 'EEA Discodata returned an invalid results envelope.') ||
            0 === strpos($message, 'EEA Discodata returned an unexpected JSON envelope.') ||
            0 === strpos($message, 'EEA Discodata results are not a row list.') ||
            0 === strpos($message, 'EEA Discodata returned a malformed result row.')) {
            return 'response_format';
        }
        if (0 === strpos($message, 'EEA result exceeded 500 pages')) {
            return 'pagination_overflow';
        }
        if (0 === strpos($message, 'Unsupported EEA ') ||
            0 === strpos($message, 'The EEA source target is incomplete.') ||
            0 === strpos($message, 'EEA make-index source is no longer allowlisted.')) {
            return 'source_configuration';
        }
        if (false !== stripos($message, 'insert failed:') ||
            false !== stripos($message, 'database') ||
            false !== stripos($message, 'wpdb')) {
            return 'database_write';
        }

        return 'other';
    }

    /** @return array<string,mixed> */
    public function get_summary()
    {
        global $wpdb;

        $empty = array(
            'failed_targets'   => 0,
            'inspected_targets'=> 0,
            'truncated'        => false,
            'categories'       => array(),
            'target_types'     => array(),
            'source_years'     => array(),
            'max_attempts'     => 0,
            'oldest_failed_at' => null,
            'newest_failed_at' => null,
        );

        if (!class_exists('Autolex_EEA_Sync') ||
            Autolex_EEA_Sync::SCHEMA_VERSION !== get_option('autolex_eea_sync_schema_version')) {
            return $empty;
        }

        $table = Autolex_EEA_Sync::tasks_table();
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'failed'");
        if ($total <= 0) {
            return $empty;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT target_type, source_year, attempts, last_error, updated_at
                 FROM {$table}
                 WHERE status = 'failed'
                 ORDER BY id ASC
                 LIMIT %d",
                self::MAX_ROWS
            ),
            ARRAY_A
        );
        if (!is_array($rows)) {
            return array_merge($empty, array('failed_targets' => $total));
        }

        $categories = array();
        $types      = array();
        $years      = array();
        $max_attempts = 0;
        $oldest = null;
        $newest = null;

        foreach ($rows as $row) {
            $category = self::classify_failure_message($row['last_error'] ?? '');
            $categories[$category] = ($categories[$category] ?? 0) + 1;

            $type = (string) ($row['target_type'] ?? 'unknown');
            if (!in_array($type, array('commercial_name', 'make_discovery', 'make_index'), true)) {
                $type = 'other';
            }
            $types[$type] = ($types[$type] ?? 0) + 1;

            $year = (int) ($row['source_year'] ?? 0);
            $year_key = ($year >= Autolex_EEA_Sync::FIRST_FINAL_YEAR && $year <= Autolex_EEA_Sync::LAST_REPORTING_YEAR)
                ? (string) $year
                : 'other';
            $years[$year_key] = ($years[$year_key] ?? 0) + 1;

            $max_attempts = max($max_attempts, (int) ($row['attempts'] ?? 0));
            $updated = trim((string) ($row['updated_at'] ?? ''));
            if ('' !== $updated) {
                if (null === $oldest || $updated < $oldest) {
                    $oldest = $updated;
                }
                if (null === $newest || $updated > $newest) {
                    $newest = $updated;
                }
            }
        }

        ksort($categories);
        ksort($types);
        ksort($years, SORT_NATURAL);

        return array(
            'failed_targets'    => $total,
            'inspected_targets' => count($rows),
            'truncated'         => $total > count($rows),
            'categories'        => $categories,
            'target_types'      => $types,
            'source_years'      => $years,
            'max_attempts'      => $max_attempts,
            'oldest_failed_at'  => $oldest,
            'newest_failed_at'  => $newest,
        );
    }

    /** @return WP_REST_Response */
    public function get_response()
    {
        $response = rest_ensure_response(
            array_merge(
                array(
                    'service'      => 'autolex-eea-failure-telemetry',
                    'status'       => 'ok',
                    'privacy'      => 'aggregate_only',
                    'generated_at' => gmdate('c'),
                ),
                $this->get_summary()
            )
        );
        $response->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=120');
        return $response;
    }

    /** @return void */
    public function register_routes()
    {
        register_rest_route(
            'autolex/v1',
            '/eea-failure-summary',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_response'),
                'permission_callback' => '__return_true',
            )
        );
    }

    private function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
}
