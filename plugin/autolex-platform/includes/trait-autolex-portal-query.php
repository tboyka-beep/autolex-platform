<?php
/** Query and evidence layer for Autolex Portal. */
if (!defined('ABSPATH')) { exit; }

trait Autolex_Portal_Query_Trait
{
    private function filters_from_query()
    {
        $get = static function ($key) {
            return isset($_GET[$key]) ? wp_unslash($_GET[$key]) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        };

        return $this->normalize_filters(
            array(
                'q'           => sanitize_text_field($get('q') ?: $get('kereses')),
                'make'        => sanitize_text_field($get('make') ?: $get('marka')),
                'model'       => sanitize_text_field($get('model')),
                'fuel'        => sanitize_text_field($get('fuel')),
                'engine_code' => sanitize_text_field($get('engine_code')),
                'year_min'    => absint($get('year_min')),
                'year_max'    => absint($get('year_max')),
                'power_min'   => absint($get('power_min')),
                'power_max'   => absint($get('power_max')),
                'grade'       => sanitize_key($get('grade')),
                'sort'        => sanitize_key($get('sort') ?: 'data_desc'),
                'page'        => absint($get('page') ?: $get('oldal') ?: 1),
                'limit'       => 24,
            )
        );
    }

    /** @param array<string,mixed> $filters Raw filters. @return array<string,mixed> */
    private function normalize_filters($filters)
    {
        $allowed_sorts = array('data_desc', 'make_asc', 'year_desc', 'power_desc');
        $grade = strtoupper((string) ($filters['grade'] ?? ''));
        return array(
            'q'           => trim((string) ($filters['q'] ?? '')),
            'make'        => trim((string) ($filters['make'] ?? '')),
            'model'       => trim((string) ($filters['model'] ?? '')),
            'fuel'        => trim((string) ($filters['fuel'] ?? '')),
            'engine_code' => trim((string) ($filters['engine_code'] ?? '')),
            'year_min'    => max(0, (int) ($filters['year_min'] ?? 0)),
            'year_max'    => max(0, (int) ($filters['year_max'] ?? 0)),
            'power_min'   => max(0, (int) ($filters['power_min'] ?? 0)),
            'power_max'   => max(0, (int) ($filters['power_max'] ?? 0)),
            'grade'       => in_array($grade, array('A', 'B', 'C'), true) ? $grade : '',
            'sort'        => in_array((string) ($filters['sort'] ?? ''), $allowed_sorts, true) ? (string) $filters['sort'] : 'data_desc',
            'page'        => max(1, (int) ($filters['page'] ?? 1)),
            'limit'       => min(48, max(1, (int) ($filters['limit'] ?? 24))),
        );
    }

    /** @param array<string,mixed> $filters Filters. @return array<string,mixed> */
    private function query_vehicles($filters)
    {
        global $wpdb;

        $filters = $this->normalize_filters($filters);
        $map = Autolex_Catalog_Browser::instance()->get_legacy_mapping();
        if (!$map || empty($map['table'])) {
            return array('items' => array(), 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters);
        }

        $table = $this->safe_table($map['table']);
        if (!$table) {
            return array('items' => array(), 'total' => 0, 'page' => 1, 'pages' => 1, 'filters' => $filters);
        }
        $alias = 'catalog';
        $column = static function ($key) use ($map, $alias) {
            return !empty($map[$key]) ? $alias . '.`' . $map[$key] . '`' : '';
        };
        $filled = static function ($sql) {
            return $sql ? "TRIM(COALESCE({$sql}, '')) <> '' AND TRIM(COALESCE({$sql}, '')) <> '0'" : '0=1';
        };
        $numeric = static function ($sql) {
            return $sql ? "CAST(NULLIF(TRIM(COALESCE({$sql}, '')), '') AS DECIMAL(12,2))" : 'NULL';
        };

        $where  = array('1=1');
        $params = array();

        if ('' !== $filters['q']) {
            $tokens = preg_split('/\s+/u', $filters['q'], 6, PREG_SPLIT_NO_EMPTY);
            foreach ((array) $tokens as $token) {
                $ors = array();
                foreach (array('make', 'model', 'generation', 'engine', 'engine_code') as $field) {
                    if ($column($field)) {
                        $ors[] = 'LOWER(COALESCE(' . $column($field) . ", '')) LIKE %s";
                        $params[] = '%' . $wpdb->esc_like(function_exists('mb_strtolower') ? mb_strtolower($token) : strtolower($token)) . '%';
                    }
                }
                if ($ors) {
                    $where[] = '(' . implode(' OR ', $ors) . ')';
                }
            }
        }
        foreach (array('make' => 'make', 'model' => 'model', 'fuel' => 'fuel_type') as $filter_key => $field) {
            if ('' !== $filters[$filter_key] && $column($field)) {
                $where[] = 'LOWER(TRIM(COALESCE(' . $column($field) . ", ''))) = LOWER(%s)";
                $params[] = $filters[$filter_key];
            }
        }
        if ('' !== $filters['engine_code'] && $column('engine_code')) {
            $where[] = 'LOWER(COALESCE(' . $column('engine_code') . ", '')) LIKE %s";
            $params[] = '%' . $wpdb->esc_like(function_exists('mb_strtolower') ? mb_strtolower($filters['engine_code']) : strtolower($filters['engine_code'])) . '%';
        }

        $year_from = $numeric($column('year_from'));
        $year_to   = $numeric($column('year_to'));
        if ($filters['year_min']) {
            $where[] = 'COALESCE(NULLIF(' . $year_to . ', 0), NULLIF(' . $year_from . ', 0), 9999) >= %d';
            $params[] = $filters['year_min'];
        }
        if ($filters['year_max']) {
            $where[] = 'COALESCE(NULLIF(' . $year_from . ', 0), 0) <= %d';
            $params[] = $filters['year_max'];
        }

        $power_ps = $numeric($column('power_ps'));
        $power_kw = $numeric($column('power_kw'));
        $power_expr = 'COALESCE(NULLIF(' . $power_ps . ', 0), NULLIF(' . $power_kw . ', 0) * 1.35962)';
        if ($filters['power_min']) {
            $where[] = $power_expr . ' >= %d';
            $params[] = $filters['power_min'];
        }
        if ($filters['power_max']) {
            $where[] = $power_expr . ' <= %d';
            $params[] = $filters['power_max'];
        }

        $has_engine = '(' . $filled($column('engine_code')) . ' OR ' . $filled($column('engine')) . ')';
        $has_power  = '(' . $filled($column('power_ps')) . ' OR ' . $filled($column('power_kw')) . ')';
        $quality_expr = "CASE
            WHEN {$has_engine} AND " . $filled($column('fuel_type')) . ' AND ' . $filled($column('capacity_cc')) . " AND {$has_power} AND " . $filled($column('year_from')) . " THEN 'A'
            WHEN {$has_engine} AND (" . $filled($column('fuel_type')) . ' OR ' . $filled($column('capacity_cc')) . " OR {$has_power}) THEN 'B'
            ELSE 'C' END";
        if ($filters['grade']) {
            $where[] = '(' . $quality_expr . ') = %s';
            $params[] = $filters['grade'];
        }

        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM {$table} AS {$alias} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $select = array();
        foreach (array('id', 'make', 'model', 'generation', 'engine', 'engine_code', 'fuel_type', 'capacity_cc', 'power_kw', 'power_ps', 'year_from', 'year_to', 'slug') as $field) {
            $select[] = $column($field) ? $column($field) . ' AS `' . $field . '`' : "'' AS `{$field}`";
        }
        $select[] = '(' . $quality_expr . ') AS data_grade';
        $select = array_merge($select, $this->evidence_selects($column('id')));

        switch ($filters['sort']) {
            case 'make_asc':
                $order = $column('make') . ' ASC, ' . ($column('model') ?: $column('generation')) . ' ASC';
                break;
            case 'year_desc':
                $order = $year_from . ' DESC, ' . $column('make') . ' ASC';
                break;
            case 'power_desc':
                $order = $power_expr . ' DESC, ' . $column('make') . ' ASC';
                break;
            default:
                $order = 'evidence_count DESC, source_count DESC, data_grade ASC, ' . $column('make') . ' ASC';
                break;
        }

        $offset = ($filters['page'] - 1) * $filters['limit'];
        $data_sql = 'SELECT ' . implode(', ', $select) . " FROM {$table} AS {$alias} WHERE {$where_sql} ORDER BY {$order} LIMIT %d OFFSET %d";
        $data_params = array_merge($params, array($filters['limit'], $offset));
        $rows = $wpdb->get_results($wpdb->prepare($data_sql, $data_params), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $items = array_map(array($this, 'format_vehicle'), (array) $rows);
        return array(
            'items'   => $items,
            'total'   => $total,
            'page'    => $filters['page'],
            'pages'   => max(1, (int) ceil($total / $filters['limit'])),
            'filters' => $filters,
        );
    }

    /** @param string $legacy_id_sql Validated legacy ID expression. @return string[] */
    private function evidence_selects($legacy_id_sql)
    {
        if (!$legacy_id_sql || !class_exists('Autolex_Engine_Catalog')) {
            return array("'' AS verification_status", '0 AS source_count', '0 AS evidence_count', '0 AS eu_observations');
        }
        $links    = $this->safe_table(Autolex_Engine_Catalog::links_table());
        $variants = $this->safe_table(Autolex_Engine_Catalog::variants_table());
        $sources  = $this->safe_table(Autolex_Engine_Catalog::sources_table());
        $eu_links = $this->safe_table(Autolex_Engine_Catalog::eu_links_table());
        if (!$links || !$variants || !$sources || !$eu_links) {
            return array("'' AS verification_status", '0 AS source_count', '0 AS evidence_count', '0 AS eu_observations');
        }

        $id = 'CAST(' . $legacy_id_sql . ' AS UNSIGNED)';
        return array(
            "COALESCE((SELECT v.verification_status FROM {$links} l INNER JOIN {$variants} v ON v.id = l.engine_variant_id WHERE l.legacy_vehicle_id = {$id} ORDER BY CASE v.verification_status WHEN 'verified' THEN 7 WHEN 'reviewed' THEN 6 WHEN 'vin_required' THEN 5 WHEN 'conflict' THEN 4 WHEN 'proposed' THEN 3 WHEN 'provisional' THEN 2 ELSE 1 END DESC LIMIT 1), '') AS verification_status",
            "COALESCE((SELECT MAX(v.source_count) FROM {$links} l INNER JOIN {$variants} v ON v.id = l.engine_variant_id WHERE l.legacy_vehicle_id = {$id}), 0) AS source_count",
            "COALESCE((SELECT COUNT(*) FROM {$links} l INNER JOIN {$sources} s ON s.engine_variant_id = l.engine_variant_id WHERE l.legacy_vehicle_id = {$id}), 0) AS evidence_count",
            "COALESCE((SELECT COUNT(*) FROM {$links} l INNER JOIN {$eu_links} e ON e.engine_variant_id = l.engine_variant_id WHERE l.legacy_vehicle_id = {$id}), 0) AS eu_observations",
        );
    }

    /** @param array<string,mixed> $row Database row. @return array<string,mixed> */
    private function format_vehicle($row)
    {
        $id = absint($row['id'] ?? 0);
        $stored_slug = trim((string) ($row['slug'] ?? ''));
        $slug_source = $stored_slug ?: trim(($row['make'] ?? '') . '-' . ($row['generation'] ?? '') . '-' . ($row['model'] ?? ''));
        $from = absint($row['year_from'] ?? 0);
        $to   = absint($row['year_to'] ?? 0);
        $kw   = is_numeric($row['power_kw'] ?? null) ? round((float) $row['power_kw'], 1) : 0;
        $ps   = is_numeric($row['power_ps'] ?? null) ? round((float) $row['power_ps'], 1) : ($kw ? round($kw * 1.35962) : 0);
        $capacity = absint($row['capacity_cc'] ?? 0);
        $verification = trim((string) ($row['verification_status'] ?? '')) ?: 'imported';

        return array(
            'id'                  => $id,
            'make'                => trim((string) ($row['make'] ?? '')),
            'model'               => trim((string) ($row['model'] ?? '')),
            'generation'          => trim((string) ($row['generation'] ?? '')),
            'engine'              => trim((string) ($row['engine'] ?? '')),
            'engine_code'         => trim((string) ($row['engine_code'] ?? '')),
            'fuel_type'           => trim((string) ($row['fuel_type'] ?? '')),
            'capacity_cc'         => $capacity,
            'power_kw'            => $kw,
            'power_ps'            => $ps,
            'year_from'           => $from,
            'year_to'             => $to,
            'years'               => $from ? $from . ($to ? '–' . $to : '–') : 'évjárat nincs megadva',
            'data_grade'          => in_array(($row['data_grade'] ?? ''), array('A', 'B', 'C'), true) ? $row['data_grade'] : self::calculate_quality_grade($row),
            'verification_status' => $verification,
            'verification_label'  => $this->verification_label($verification),
            'source_count'        => absint($row['source_count'] ?? 0),
            'evidence_count'      => absint($row['evidence_count'] ?? 0),
            'eu_observations'     => absint($row['eu_observations'] ?? 0),
            'url'                 => home_url('/auto-adatlap/' . $id . '/' . sanitize_title($slug_source) . '/'),
        );
    }

    /** @param string $make Exact make. @return array<string,mixed> */
    private function get_facets($make)
    {
        global $wpdb;

        $map = Autolex_Catalog_Browser::instance()->get_legacy_mapping();
        if (!$map || empty($map['table'])) {
            return array('makes' => array(), 'models' => array(), 'fuels' => array(), 'ranges' => array('year_min' => 0, 'year_max' => 0, 'power_min' => 0, 'power_max' => 0), 'available' => array());
        }
        $table = $this->safe_table($map['table']);
        if (!$table) {
            return array('makes' => array(), 'models' => array(), 'fuels' => array(), 'ranges' => array('year_min' => 0, 'year_max' => 0, 'power_min' => 0, 'power_max' => 0), 'available' => array());
        }
        $col = static function ($key) use ($map) {
            return !empty($map[$key]) ? '`' . $map[$key] . '`' : '';
        };
        $facet_rows = static function ($rows) {
            return array_values(array_filter(array_map(static function ($row) {
                $value = trim((string) ($row['value'] ?? ''));
                return '' === $value ? null : array('value' => $value, 'total' => (int) ($row['total'] ?? 0));
            }, (array) $rows)));
        };

        $makes = array();
        if ($col('make')) {
            $makes = $facet_rows($wpdb->get_results("SELECT {$col('make')} AS value, COUNT(*) AS total FROM {$table} WHERE TRIM(COALESCE({$col('make')}, '')) <> '' GROUP BY {$col('make')} ORDER BY total DESC, value ASC LIMIT 250", ARRAY_A));
        }
        $models = array();
        if ($col('model')) {
            $sql = "SELECT {$col('model')} AS value, COUNT(*) AS total FROM {$table} WHERE TRIM(COALESCE({$col('model')}, '')) <> ''";
            $params = array();
            if ('' !== trim($make) && $col('make')) {
                $sql .= " AND LOWER(TRIM({$col('make')})) = LOWER(%s)";
                $params[] = trim($make);
            }
            $sql .= " GROUP BY {$col('model')} ORDER BY total DESC, value ASC LIMIT 500";
            $models = $facet_rows($wpdb->get_results($params ? $wpdb->prepare($sql, $params) : $sql, ARRAY_A)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        $fuels = array();
        if ($col('fuel_type')) {
            $fuels = $facet_rows($wpdb->get_results("SELECT {$col('fuel_type')} AS value, COUNT(*) AS total FROM {$table} WHERE TRIM(COALESCE({$col('fuel_type')}, '')) <> '' GROUP BY {$col('fuel_type')} ORDER BY total DESC, value ASC LIMIT 60", ARRAY_A));
        }

        $year_min = $col('year_from') ? (int) $wpdb->get_var("SELECT MIN(CAST(NULLIF(TRIM({$col('year_from')}), '') AS UNSIGNED)) FROM {$table} WHERE CAST(NULLIF(TRIM({$col('year_from')}), '') AS UNSIGNED) BETWEEN 1900 AND 2200") : 0;
        $year_max = $col('year_to') ? (int) $wpdb->get_var("SELECT MAX(CAST(NULLIF(TRIM({$col('year_to')}), '') AS UNSIGNED)) FROM {$table} WHERE CAST(NULLIF(TRIM({$col('year_to')}), '') AS UNSIGNED) BETWEEN 1900 AND 2200") : 0;
        if (!$year_max && $col('year_from')) {
            $year_max = (int) $wpdb->get_var("SELECT MAX(CAST(NULLIF(TRIM({$col('year_from')}), '') AS UNSIGNED)) FROM {$table}");
        }
        $power_expr = $col('power_ps')
            ? "CAST(NULLIF(TRIM({$col('power_ps')}), '') AS DECIMAL(12,2))"
            : ($col('power_kw') ? "CAST(NULLIF(TRIM({$col('power_kw')}), '') AS DECIMAL(12,2)) * 1.35962" : 'NULL');
        $power_min = 'NULL' !== $power_expr ? (int) $wpdb->get_var("SELECT MIN({$power_expr}) FROM {$table} WHERE {$power_expr} > 0") : 0;
        $power_max = 'NULL' !== $power_expr ? (int) $wpdb->get_var("SELECT MAX({$power_expr}) FROM {$table} WHERE {$power_expr} > 0") : 0;

        return array(
            'makes'  => $makes,
            'models' => $models,
            'fuels'  => $fuels,
            'ranges' => array('year_min' => $year_min, 'year_max' => $year_max, 'power_min' => $power_min, 'power_max' => $power_max),
            'available' => array(
                'engine_code' => (bool) $col('engine_code'),
                'fuel'        => (bool) $col('fuel_type'),
                'year'        => (bool) ($col('year_from') || $col('year_to')),
                'power'       => (bool) ($col('power_ps') || $col('power_kw')),
            ),
        );
    }

    /** @return array<string,mixed> */
    private function safe_coverage()
    {
        $eu = class_exists('Autolex_EU_Catalog') ? Autolex_EU_Catalog::instance()->get_coverage() : array();
        $engine = class_exists('Autolex_Engine_Catalog') ? Autolex_Engine_Catalog::instance()->get_coverage() : array();
        $sync = class_exists('Autolex_EEA_Sync') ? Autolex_EEA_Sync::instance()->get_status() : array();
        $targets = (int) ($sync['targets'] ?? 0);
        $completed = (int) ($sync['completed_targets'] ?? 0);
        $failed = (int) ($sync['failed_targets'] ?? 0);
        $retry = (int) ($sync['retry_targets'] ?? 0);
        $progress = $targets ? min(100, round(($completed / $targets) * 100, 1)) : 0;
        $health = $failed ? 'warning' : ($retry ? 'attention' : 'healthy');
        $labels = array('healthy' => 'Működő', 'attention' => 'Újrapróbálás', 'warning' => 'Figyelmet kér');

        return array(
            'vehicles'         => (int) ($eu['vehicles'] ?? $engine['catalog_vehicles'] ?? 0),
            'makes'            => (int) ($eu['makes'] ?? count((array) ($engine['makes'] ?? array()))),
            'models'           => (int) ($eu['models'] ?? 0),
            'engine_variants'  => (int) ($engine['engine_variants'] ?? 0),
            'evidence_records' => (int) ($engine['evidence_records'] ?? 0),
            'sync_targets'     => $targets,
            'sync_completed'   => $completed,
            'sync_progress'    => $progress,
            'health'           => $health,
            'health_label'     => $labels[$health],
        );
    }

    /** @param array<int,array<string,mixed>> $items Vehicle items. @return string */
}
