<?php
/**
 * Rate-limited background synchronization from the official EEA Discodata API.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_EEA_Sync
{
    /** Database schema version. */
    const SCHEMA_VERSION = '1.0.0';

    /** Official public SQL-to-JSON endpoint. */
    const API_URL = 'https://discodata.eea.europa.eu/sql';

    /** Maximum aggregated records handled in one remote request. */
    const PAGE_SIZE = 2000;

    /** Final EEA passenger-car reporting years processed by this pipeline. */
    const FIRST_FINAL_YEAR = 2010;
    const LAST_FINAL_YEAR  = 2021;

    /** @var Autolex_EEA_Sync|null */
    private static $instance = null;

    /** @return Autolex_EEA_Sync */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Installs the durable source-target queue. */
    public static function install_schema()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table           = self::tasks_table();
        $charset_collate = $wpdb->get_charset_collate();
        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                target_fingerprint char(64) NOT NULL,
                make varchar(120) NOT NULL DEFAULT '',
                commercial_name varchar(191) NOT NULL DEFAULT '',
                source_year smallint(5) unsigned NOT NULL,
                source_status varchar(10) NOT NULL DEFAULT 'F',
                page_number smallint(5) unsigned NOT NULL DEFAULT 1,
                status varchar(30) NOT NULL DEFAULT 'pending',
                priority smallint(5) unsigned NOT NULL DEFAULT 100,
                attempts smallint(5) unsigned NOT NULL DEFAULT 0,
                rows_read bigint(20) unsigned NOT NULL DEFAULT 0,
                vehicles_imported bigint(20) unsigned NOT NULL DEFAULT 0,
                engines_proposed bigint(20) unsigned NOT NULL DEFAULT 0,
                links_proposed bigint(20) unsigned NOT NULL DEFAULT 0,
                next_run_at datetime DEFAULT NULL,
                locked_at datetime DEFAULT NULL,
                last_error text DEFAULT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                completed_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY target_fingerprint (target_fingerprint),
                KEY queue_order (status, priority, id),
                KEY make_year (make, source_year),
                KEY next_run_at (next_run_at)
            ) {$charset_collate};"
        );

        update_option('autolex_eea_sync_schema_version', self::SCHEMA_VERSION, false);
        update_option('autolex_eea_sync_seeded', 0, false);
    }

    /** @return string */
    public static function tasks_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_eea_sync_tasks';
    }

    /**
     * Builds the allowlisted, read-only T-SQL query used by Discodata.
     *
     * @param string $make            Exact EEA make candidate.
     * @param string $commercial_name Exact EEA commercial-name candidate.
     * @param int    $year            Final reporting year.
     * @return string
     */
    public static function build_query($make, $commercial_name, $year)
    {
        $year = (int) $year;
        if ($year < self::FIRST_FINAL_YEAR || $year > self::LAST_FINAL_YEAR) {
            throw new InvalidArgumentException('Unsupported EEA final reporting year.');
        }

        $clean = static function ($value, $length) {
            $value = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value));
            if (function_exists('mb_substr')) {
                return mb_substr($value, 0, $length);
            }
            return substr($value, 0, $length);
        };
        $make            = $clean($make, 120);
        $commercial_name = $clean($commercial_name, 191);
        if ('' === $make || '' === $commercial_name) {
            throw new InvalidArgumentException('EEA make and commercial name are required.');
        }

        $make_sql  = str_replace("'", "''", $make);
        $model_sql = str_replace("'", "''", $commercial_name);

        return "SELECT [Mk] AS [mk], [Cn] AS [cn], [T] AS [t], [Va] AS [va], " .
            "[Ve] AS [ve], [Ct] AS [ct], [Ft] AS [ft], [Fm] AS [fm], " .
            "[Ec (cm3)] AS [ec], [Ep (KW)] AS [ep], COUNT(*) AS [r] " .
            "FROM [CO2Emission].[latest].[co2cars] " .
            "WHERE [Year] = {$year} AND [Status] = 'F' " .
            "AND [Mk] = '{$make_sql}' AND [Cn] = '{$model_sql}' " .
            "GROUP BY [Mk], [Cn], [T], [Va], [Ve], [Ct], [Ft], [Fm], " .
            "[Ec (cm3)], [Ep (KW)]";
    }

    /** Checks schema, seeds targets and schedules one bounded batch. */
    public function maybe_schedule()
    {
        if (self::SCHEMA_VERSION !== get_option('autolex_eea_sync_schema_version')) {
            self::install_schema();
        }

        if (!wp_next_scheduled('autolex_eea_sync_batch')) {
            wp_schedule_single_event(time() + 20, 'autolex_eea_sync_batch');
        }
    }

    /**
     * Creates exact make + commercial-name + year targets from both model and
     * engine labels. Production years restrict the official reporting years.
     *
     * @return int Number of target rows currently queued.
     */
    public function seed_tasks()
    {
        global $wpdb;

        $engine_tasks = Autolex_Engine_Catalog::tasks_table();
        $source_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$engine_tasks}");
        if (!$source_count) {
            update_option('autolex_eea_sync_seeded', 0, false);
            return 0;
        }

        $year_parts = array();
        for ($year = self::LAST_FINAL_YEAR; $year >= self::FIRST_FINAL_YEAR; --$year) {
            $year_parts[] = 'SELECT ' . $year . ' AS source_year';
        }
        $years_sql = implode(' UNION ALL ', $year_parts);
        $now       = current_time('mysql', true);

        foreach (array('engine_label' => 10, 'model' => 100) as $field => $base_priority) {
            $sql = $wpdb->prepare(
                'INSERT IGNORE INTO ' . self::tasks_table() . ' (
                    target_fingerprint, make, commercial_name, source_year, source_status,
                    page_number, status, priority, attempts, created_at, updated_at
                ) SELECT DISTINCT
                    SHA2(LOWER(CONCAT_WS(\'|\', years.source_year, \'F\', TRIM(tasks.make), TRIM(tasks.`' . $field . '`))), 256),
                    LEFT(TRIM(tasks.make), 120), LEFT(TRIM(tasks.`' . $field . '`), 191),
                    years.source_year, \'F\', 1, \'pending\',
                    LEAST(65535, %d + ((' . self::LAST_FINAL_YEAR . ' - years.source_year) * 10)),
                    0, %s, %s
                FROM ' . $engine_tasks . ' AS tasks
                JOIN (' . $years_sql . ') AS years
                    ON (tasks.production_start IS NULL OR tasks.production_start = 0 OR years.source_year >= tasks.production_start)
                    AND (tasks.production_end IS NULL OR tasks.production_end = 0 OR years.source_year <= tasks.production_end)
                WHERE TRIM(tasks.make) <> \'\' AND TRIM(tasks.`' . $field . '`) <> \'\'',
                $base_priority,
                $now,
                $now
            );
            if (false === $wpdb->query($sql)) {
                throw new RuntimeException('EEA sync target seeding failed: ' . $wpdb->last_error);
            }
        }

        update_option('autolex_eea_sync_seeded', 1, false);
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::tasks_table());
    }

    /** Processes one network page and yields quickly back to WordPress. */
    public function run_batch()
    {
        global $wpdb;

        if (!$this->acquire_lock()) {
            $this->schedule_next(60);
            return;
        }

        try {
            if (!get_option('autolex_eea_sync_seeded')) {
                if (!$this->seed_tasks()) {
                    $this->schedule_next(300);
                    return;
                }
            }

            $table = self::tasks_table();
            $now   = current_time('mysql', true);
            $task  = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table}
                    WHERE status IN ('pending', 'retry')
                        AND (next_run_at IS NULL OR next_run_at <= %s)
                    ORDER BY priority ASC, id ASC LIMIT 1",
                    $now
                ),
                ARRAY_A
            );
            if (!$task) {
                return;
            }

            $claimed = $wpdb->update(
                $table,
                array(
                    'status'     => 'running',
                    'attempts'   => ((int) $task['attempts']) + 1,
                    'locked_at'  => $now,
                    'updated_at' => $now,
                ),
                array('id' => (int) $task['id'], 'status' => $task['status']),
                array('%s', '%d', '%s', '%s'),
                array('%d', '%s')
            );
            if (!$claimed) {
                $this->schedule_next(30);
                return;
            }

            try {
                $rows = $this->fetch_page($task);
                $read = count($rows);
                $vehicles = 0;
                $engines  = 0;
                $links    = 0;

                $wpdb->query('START TRANSACTION');
                foreach ($rows as $row) {
                    $row = array_change_key_case((array) $row, CASE_LOWER);
                    $row['status'] = 'F';
                    $vehicle = Autolex_EEA_Importer::normalize_vehicle(
                        $row,
                        (int) $task['source_year'],
                        'M1'
                    );
                    if (!$vehicle) {
                        continue;
                    }
                    $eu_vehicle_id = Autolex_EEA_Importer::upsert_vehicle_snapshot($vehicle);
                    $proposal      = Autolex_Engine_Catalog::instance()->ingest_eea_vehicle($vehicle, $eu_vehicle_id);
                    ++$vehicles;
                    if (!empty($proposal['engine_variant_id'])) {
                        ++$engines;
                    }
                    $links += (int) ($proposal['links'] ?? 0);
                }
                $wpdb->query('COMMIT');

                $finished = $read < self::PAGE_SIZE;
                $overflow = !$finished && (int) $task['page_number'] >= 500;
                $wpdb->update(
                    $table,
                    array(
                        'page_number'      => $finished ? (int) $task['page_number'] : ((int) $task['page_number'] + 1),
                        'status'           => $overflow ? 'failed' : ($finished ? 'completed' : 'pending'),
                        'rows_read'        => ((int) $task['rows_read']) + $read,
                        'vehicles_imported'=> ((int) $task['vehicles_imported']) + $vehicles,
                        'engines_proposed' => ((int) $task['engines_proposed']) + $engines,
                        'links_proposed'   => ((int) $task['links_proposed']) + $links,
                        'next_run_at'      => ($finished || $overflow) ? null : gmdate('Y-m-d H:i:s', time() + 45),
                        'locked_at'        => null,
                        'last_error'       => $overflow ? 'EEA result exceeded 500 pages and requires a narrower source target.' : null,
                        'completed_at'     => $finished ? current_time('mysql', true) : null,
                        'updated_at'       => current_time('mysql', true),
                    ),
                    array('id' => (int) $task['id']),
                    array('%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
            } catch (Throwable $exception) {
                $wpdb->query('ROLLBACK');
                $attempts = ((int) $task['attempts']) + 1;
                $failed   = $attempts >= 8;
                $delay    = min(6 * HOUR_IN_SECONDS, (int) pow(2, min(10, $attempts)) * MINUTE_IN_SECONDS);
                $wpdb->update(
                    $table,
                    array(
                        'status'      => $failed ? 'failed' : 'retry',
                        'next_run_at' => $failed ? null : gmdate('Y-m-d H:i:s', time() + $delay),
                        'locked_at'   => null,
                        'last_error'  => substr($exception->getMessage(), 0, 65000),
                        'updated_at'  => current_time('mysql', true),
                    ),
                    array('id' => (int) $task['id']),
                    array('%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
            }
        } finally {
            $this->release_lock();
            $this->schedule_next(60);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function fetch_page($task)
    {
        $query = self::build_query($task['make'], $task['commercial_name'], (int) $task['source_year']);
        $url   = add_query_arg(
            array(
                'query'    => $query,
                'p'        => max(1, (int) $task['page_number']),
                'nrOfHits' => self::PAGE_SIZE,
            ),
            self::API_URL
        );
        $response = wp_safe_remote_get(
            $url,
            array(
                'timeout'             => 28,
                'redirection'         => 0,
                'reject_unsafe_urls'  => true,
                'limit_response_size' => 4 * MB_IN_BYTES,
                'user-agent'          => 'Autolex-Platform/' . AUTOLEX_PLATFORM_VERSION . ' (+https://autolex.hu/)',
                'headers'             => array('Accept' => 'application/json'),
            )
        );
        if (is_wp_error($response)) {
            throw new RuntimeException('EEA Discodata request failed: ' . $response->get_error_message());
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $status) {
            throw new RuntimeException('EEA Discodata returned HTTP ' . $status . '.');
        }
        $body = wp_remote_retrieve_body($response);
        $rows = json_decode($body, true);
        if (!is_array($rows) || JSON_ERROR_NONE !== json_last_error()) {
            throw new RuntimeException('EEA Discodata returned invalid JSON.');
        }
        if (isset($rows['error']) || isset($rows['errors'])) {
            throw new RuntimeException('EEA Discodata rejected the read-only query.');
        }
        return array_values(array_filter($rows, 'is_array'));
    }

    /** @return array<string,int|string|null> */
    public function get_status()
    {
        global $wpdb;

        $empty = array(
            'schema_version'       => self::SCHEMA_VERSION,
            'source'               => 'EEA Discodata / CO2Emission.latest.co2cars',
            'first_final_year'     => self::FIRST_FINAL_YEAR,
            'latest_final_year'    => self::LAST_FINAL_YEAR,
            'targets'              => 0,
            'completed_targets'    => 0,
            'pending_targets'      => 0,
            'failed_targets'       => 0,
            'rows_read'            => 0,
            'vehicles_imported'    => 0,
            'engine_proposals'     => 0,
            'link_proposals'       => 0,
            'last_completed_at'    => null,
        );
        if (self::SCHEMA_VERSION !== get_option('autolex_eea_sync_schema_version')) {
            return $empty;
        }

        $row = $wpdb->get_row(
            'SELECT COUNT(*) AS targets,
                SUM(status = \'completed\') AS completed_targets,
                SUM(status IN (\'pending\', \'retry\', \'running\')) AS pending_targets,
                SUM(status = \'failed\') AS failed_targets,
                COALESCE(SUM(rows_read), 0) AS rows_read,
                COALESCE(SUM(vehicles_imported), 0) AS vehicles_imported,
                COALESCE(SUM(engines_proposed), 0) AS engine_proposals,
                COALESCE(SUM(links_proposed), 0) AS link_proposals,
                MAX(completed_at) AS last_completed_at
            FROM ' . self::tasks_table(),
            ARRAY_A
        );
        if (!is_array($row)) {
            return $empty;
        }

        foreach (array('targets', 'completed_targets', 'pending_targets', 'failed_targets', 'rows_read', 'vehicles_imported', 'engine_proposals', 'link_proposals') as $field) {
            $row[$field] = (int) ($row[$field] ?? 0);
        }
        return array_merge($empty, $row);
    }

    /** @return WP_REST_Response */
    public function get_status_response()
    {
        $response = rest_ensure_response(
            array_merge(
                array(
                    'service'         => 'autolex-eea-sync',
                    'status'          => 'ok',
                    'source_policy'   => 'official_final_first',
                    'matching_policy' => 'exact_make_commercial_name_year_proposal',
                    'generated_at'    => gmdate('c'),
                ),
                $this->get_status()
            )
        );
        $response->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
        return $response;
    }

    /** @return bool */
    private function acquire_lock()
    {
        $lock = (int) get_option('autolex_eea_sync_lock', 0);
        if ($lock && $lock > (time() - 5 * MINUTE_IN_SECONDS)) {
            return false;
        }
        if ($lock) {
            delete_option('autolex_eea_sync_lock');
        }
        return add_option('autolex_eea_sync_lock', time(), '', false);
    }

    /** @return void */
    private function release_lock()
    {
        delete_option('autolex_eea_sync_lock');
    }

    /** @return void */
    private function schedule_next($delay)
    {
        global $wpdb;

        if (self::SCHEMA_VERSION === get_option('autolex_eea_sync_schema_version')) {
            $pending = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM " . self::tasks_table() . " WHERE status IN ('pending', 'retry', 'running')"
            );
            if (!$pending && get_option('autolex_eea_sync_seeded')) {
                update_option('autolex_eea_sync_seeded', 0, false);
                $delay = 6 * HOUR_IN_SECONDS;
            }
        }
        if (!wp_next_scheduled('autolex_eea_sync_batch')) {
            wp_schedule_single_event(time() + max(15, (int) $delay), 'autolex_eea_sync_batch');
        }
    }

    /** Registers hooks. */
    private function __construct()
    {
        add_action('init', array($this, 'maybe_schedule'), 7);
        add_action('autolex_eea_sync_batch', array($this, 'run_batch'));
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /** @return void */
    public function register_routes()
    {
        register_rest_route(
            'autolex/v1',
            '/eea-sync-status',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_status_response'),
                'permission_callback' => '__return_true',
            )
        );
    }
}
