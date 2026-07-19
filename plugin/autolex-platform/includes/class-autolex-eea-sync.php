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
    const SCHEMA_VERSION = '1.1.0';

    /** Official public SQL-to-JSON endpoint. */
    const API_URL = 'https://discodata.eea.europa.eu/sql';

    /** Maximum aggregated records handled in one remote request. */
    const PAGE_SIZE = 2000;

    /** Smaller write batch for broad new-model discovery targets. */
    const DISCOVERY_PAGE_SIZE = 100;

    /** EEA passenger-car reporting years processed by this pipeline. */
    const FIRST_FINAL_YEAR = 2010;
    const LAST_FINAL_YEAR  = 2023;
    const LAST_REPORTING_YEAR = 2025;

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
                target_type varchar(30) NOT NULL DEFAULT 'commercial_name',
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
                KEY target_type (target_type, status),
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
     * Returns only explicitly allowlisted official EEA passenger-car tables.
     * Provisional years stay separate from final data until EEA publishes the
     * corresponding final Discodata table.
     *
     * @return array<int,array<string,string|int>>
     */
    public static function source_configurations()
    {
        $sources = array();
        $rank    = 15;
        for ($year = self::FIRST_FINAL_YEAR; $year <= 2019; ++$year) {
            $sources[$year] = array(
                'table'  => 'co2cars',
                'status' => 'F',
                'quality'=> 'final',
                'rank'   => $rank--,
            );
        }

        $sources[2020] = array('table' => 'co2cars_2020Fv22', 'status' => 'F', 'quality' => 'final', 'rank' => 5);
        $sources[2021] = array('table' => 'co2cars_2021Fv24', 'status' => 'F', 'quality' => 'final', 'rank' => 4);
        $sources[2022] = array('table' => 'co2cars_2022Fv26', 'status' => 'F', 'quality' => 'final', 'rank' => 3);
        $sources[2023] = array('table' => 'co2cars_2023Fv28', 'status' => 'F', 'quality' => 'final', 'rank' => 2);
        $sources[2024] = array('table' => 'co2cars_2024Pv29', 'status' => 'P', 'quality' => 'provisional', 'rank' => 1);
        $sources[2025] = array('table' => 'co2cars_2025Pv31', 'status' => 'P', 'quality' => 'provisional', 'rank' => 0);
        return $sources;
    }

    /**
     * Builds the allowlisted, read-only T-SQL query used by Discodata.
     *
     * @param string $make            Exact EEA make candidate.
     * @param string $commercial_name Exact EEA commercial-name candidate.
     * @param int    $year            Reporting year.
     * @param string $target_type     commercial_name, make_discovery or make_index.
     * @return string
     */
    public static function build_query($make, $commercial_name, $year, $target_type = 'commercial_name')
    {
        $year = (int) $year;
        $sources = self::source_configurations();
        if (!isset($sources[$year])) {
            throw new InvalidArgumentException('Unsupported EEA reporting year.');
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
        if (!in_array($target_type, array('commercial_name', 'make_discovery', 'make_index'), true)) {
            throw new InvalidArgumentException('Unsupported EEA source target type.');
        }
        if (('make_index' !== $target_type && '' === $make) ||
            ('commercial_name' === $target_type && '' === $commercial_name)) {
            throw new InvalidArgumentException('The EEA source target is incomplete.');
        }

        $make_sql  = str_replace("'", "''", $make);
        $model_sql = str_replace("'", "''", $commercial_name);

        $source      = $sources[$year];
        $table       = (string) $source['table'];
        $status      = (string) $source['status'];
        if ('make_index' === $target_type) {
            return "SELECT [Mk] AS [mk], COUNT(*) AS [r] " .
                "FROM [CO2Emission].[latest].[{$table}] " .
                "WHERE [Year] = {$year} AND [Status] = '{$status}' " .
                "AND NULLIF(LTRIM(RTRIM([Mk])), '') IS NOT NULL " .
                "GROUP BY [Mk] ORDER BY [Mk]";
        }

        $model_where = 'commercial_name' === $target_type ? " AND [Cn] = '{$model_sql}'" : '';

        return "SELECT [Mk] AS [mk], [Cn] AS [cn], [T] AS [t], [Va] AS [va], " .
            "[Ve] AS [ve], [Ct] AS [ct], [Ft] AS [ft], [Fm] AS [fm], " .
            "[Ec (cm3)] AS [ec], [Ep (KW)] AS [ep], COUNT(*) AS [r] " .
            "FROM [CO2Emission].[latest].[{$table}] " .
            "WHERE [Year] = {$year} AND [Status] = '{$status}' " .
            "AND [Mk] = '{$make_sql}'{$model_where} " .
            "GROUP BY [Mk], [Cn], [T], [Va], [Ve], [Ct], [Ft], [Fm], " .
            "[Ec (cm3)], [Ep (KW)] " .
            "ORDER BY [Mk], [Cn], [T], [Va], [Ve], [Ct], [Ft], [Fm], " .
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
     * Performs a small hourly queue cleanup without deleting provenance.
     * Completed rows are retained only as compact fingerprints and counters so
     * they cannot be accidentally re-imported on the next seed pass.
     */
    public function maybe_maintain_queue()
    {
        global $wpdb;

        $last_run = (int) get_option('autolex_eea_sync_last_maintenance', 0);
        if ($last_run > (time() - HOUR_IN_SECONDS)) {
            return;
        }

        $table = self::tasks_table();
        $now   = current_time('mysql', true);
        $stale = gmdate('Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS);
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                SET status = 'retry', locked_at = NULL, next_run_at = %s,
                    last_error = 'Recovered stale background lock.', updated_at = %s
                WHERE status = 'running' AND locked_at < %s",
                $now,
                $now,
                $stale
            )
        );
        $wpdb->query(
            "UPDATE {$table}
            SET locked_at = NULL, next_run_at = NULL, last_error = NULL
            WHERE status = 'completed'
                AND (locked_at IS NOT NULL OR next_run_at IS NOT NULL OR last_error IS NOT NULL)"
        );
        update_option('autolex_eea_sync_last_maintenance', time(), false);
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
        foreach (self::source_configurations() as $year => $source) {
            $year_parts[] = 'SELECT ' . (int) $year . ' AS source_year, \'' .
                (string) $source['status'] . '\' AS source_status, ' .
                (int) $source['rank'] . ' AS source_rank';
        }
        $years_sql = implode(' UNION ALL ', $year_parts);
        $now       = current_time('mysql', true);

        foreach (array('engine_label' => 10, 'model' => 100) as $field => $base_priority) {
            $sql = $wpdb->prepare(
                'INSERT IGNORE INTO ' . self::tasks_table() . ' (
                    target_fingerprint, target_type, make, commercial_name, source_year, source_status,
                    page_number, status, priority, attempts, created_at, updated_at
                ) SELECT DISTINCT
                    SHA2(LOWER(CONCAT_WS(\'|\', years.source_year, years.source_status, TRIM(tasks.make), TRIM(tasks.`' . $field . '`))), 256),
                    \'commercial_name\',
                    LEFT(TRIM(tasks.make), 120), LEFT(TRIM(tasks.`' . $field . '`), 191),
                    years.source_year, years.source_status, 1, \'pending\',
                    LEAST(65535, %d + (years.source_rank * 10)),
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

        $discovery_years = array_filter(
            self::source_configurations(),
            static function ($source, $year) {
                return $year >= 2022;
            },
            ARRAY_FILTER_USE_BOTH
        );
        $discovery_parts = array();
        foreach ($discovery_years as $year => $source) {
            $discovery_parts[] = 'SELECT ' . (int) $year . ' AS source_year, \'' .
                (string) $source['status'] . '\' AS source_status, ' .
                (int) $source['rank'] . ' AS source_rank';
        }
        $discovery_sql = $wpdb->prepare(
            'INSERT IGNORE INTO ' . self::tasks_table() . ' (
                target_fingerprint, target_type, make, commercial_name, source_year, source_status,
                page_number, status, priority, attempts, created_at, updated_at
            ) SELECT DISTINCT
                SHA2(LOWER(CONCAT_WS(\'|\', years.source_year, years.source_status, \'make_discovery\', TRIM(tasks.make))), 256),
                \'make_discovery\', LEFT(TRIM(tasks.make), 120), \'\',
                years.source_year, years.source_status, 1, \'pending\',
                LEAST(65535, years.source_rank), 0, %s, %s
            FROM ' . $engine_tasks . ' AS tasks
            CROSS JOIN (' . implode(' UNION ALL ', $discovery_parts) . ') AS years
            WHERE TRIM(tasks.make) <> \'\'',
            $now,
            $now
        );
        if (false === $wpdb->query($discovery_sql)) {
            throw new RuntimeException('EEA new-model discovery seeding failed: ' . $wpdb->last_error);
        }

        $index_rows = array();
        foreach ($discovery_years as $year => $source) {
            $fingerprint = hash('sha256', strtolower(implode('|', array($year, $source['status'], 'make_index'))));
            $index_rows[] = $wpdb->prepare(
                '(%s, \'make_index\', \'\', \'\', %d, %s, 1, \'pending\', %d, 0, %s, %s)',
                $fingerprint,
                $year,
                $source['status'],
                $source['rank'],
                $now,
                $now
            );
        }
        $index_sql = 'INSERT IGNORE INTO ' . self::tasks_table() . ' (
            target_fingerprint, target_type, make, commercial_name, source_year, source_status,
            page_number, status, priority, attempts, created_at, updated_at
        ) VALUES ' . implode(', ', $index_rows);
        if (false === $wpdb->query($index_sql)) {
            throw new RuntimeException('EEA make-index seeding failed: ' . $wpdb->last_error);
        }

        update_option('autolex_eea_sync_seeded', 1, false);
        update_option('autolex_eea_sync_last_seeded_at', time(), false);
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::tasks_table());
    }

    /** Processes one network page and yields quickly back to WordPress. */
    public function run_batch()
    {
        global $wpdb;

        if (!$this->acquire_lock()) {
            $this->schedule_next(30);
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
                if ('make_index' === ($task['target_type'] ?? '')) {
                    $this->enqueue_discovered_makes($rows, $task);
                } else {
                    foreach ($rows as $row) {
                        $row = array_change_key_case((array) $row, CASE_LOWER);
                        $row['status'] = (string) $task['source_status'];
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
                }
                $wpdb->query('COMMIT');

                $page_size = $this->page_size_for_task($task);
                $finished = $read < $page_size;
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
            $this->schedule_next(30);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function fetch_page($task)
    {
        $query = self::build_query(
            $task['make'],
            $task['commercial_name'],
            (int) $task['source_year'],
            $task['target_type'] ?? 'commercial_name'
        );
        $url   = add_query_arg(
            array(
                'query'    => $query,
                'p'        => max(1, (int) $task['page_number']),
                'nrOfHits' => $this->page_size_for_task($task),
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

    /**
     * Turns the compact official make index into bounded per-make discovery targets.
     *
     * @param array<int,array<string,mixed>> $rows Official make-index rows.
     * @param array<string,mixed>             $task Current make-index task.
     * @return void
     */
    private function enqueue_discovered_makes($rows, $task)
    {
        global $wpdb;

        $year   = (int) $task['source_year'];
        $status = (string) $task['source_status'];
        $sources = self::source_configurations();
        if (!isset($sources[$year])) {
            throw new RuntimeException('EEA make-index source is no longer allowlisted.');
        }

        $makes = array();
        foreach ($rows as $row) {
            $row  = array_change_key_case((array) $row, CASE_LOWER);
            $make = trim(wp_strip_all_tags((string) ($row['mk'] ?? '')));
            $make = function_exists('mb_substr') ? mb_substr($make, 0, 120) : substr($make, 0, 120);
            if ('' !== $make) {
                $makes[$make] = true;
            }
        }
        if (!$makes) {
            return;
        }

        $now = current_time('mysql', true);
        $values = array();
        foreach (array_keys($makes) as $make) {
            $identity = implode('|', array($year, $status, 'make_discovery', $make));
            $identity = function_exists('mb_strtolower') ? mb_strtolower($identity) : strtolower($identity);
            $values[] = $wpdb->prepare(
                '(%s, \'make_discovery\', %s, \'\', %d, %s, 1, \'pending\', %d, 0, %s, %s)',
                hash('sha256', $identity),
                $make,
                $year,
                $status,
                (int) $sources[$year]['rank'],
                $now,
                $now
            );
        }
        $sql = 'INSERT IGNORE INTO ' . self::tasks_table() . ' (
            target_fingerprint, target_type, make, commercial_name, source_year, source_status,
            page_number, status, priority, attempts, created_at, updated_at
        ) VALUES ' . implode(', ', $values);
        if (false === $wpdb->query($sql)) {
            throw new RuntimeException('EEA discovered-make target insert failed: ' . $wpdb->last_error);
        }
    }

    /** @return int */
    private function page_size_for_task($task)
    {
        return in_array(($task['target_type'] ?? ''), array('make_discovery', 'make_index'), true)
            ? self::DISCOVERY_PAGE_SIZE
            : self::PAGE_SIZE;
    }

    /** @return array<string,int|string|null> */
    public function get_status()
    {
        global $wpdb;

        $empty = array(
            'schema_version'       => self::SCHEMA_VERSION,
            'source'               => 'EEA Discodata / allowlisted annual passenger-car tables',
            'first_final_year'     => self::FIRST_FINAL_YEAR,
            'latest_final_year'    => self::LAST_FINAL_YEAR,
            'latest_reporting_year'=> self::LAST_REPORTING_YEAR,
            'provisional_years'    => array(2024, 2025),
            'targets'              => 0,
            'discovery_targets'    => 0,
            'make_index_targets'   => 0,
            'provisional_targets'  => 0,
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
                SUM(target_type = \'make_discovery\') AS discovery_targets,
                SUM(target_type = \'make_index\') AS make_index_targets,
                SUM(source_status = \'P\') AS provisional_targets,
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

        foreach (array('targets', 'discovery_targets', 'make_index_targets', 'provisional_targets', 'completed_targets', 'pending_targets', 'failed_targets', 'rows_read', 'vehicles_imported', 'engine_proposals', 'link_proposals') as $field) {
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
                    'source_policy'   => 'official_final_then_provisional',
                    'matching_policy' => 'exact_name_year_proposal_plus_full_make_discovery',
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
                $delay = DAY_IN_SECONDS;
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
        add_action('init', array($this, 'maybe_maintain_queue'), 8);
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
