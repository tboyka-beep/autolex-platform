<?php
/**
 * Fast, read-only browser for the existing Autolex catalogue.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Catalog_Browser
{
    /** @var Autolex_Catalog_Browser|null */
    private static $instance = null;

    /** @return Autolex_Catalog_Browser */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Registers public hooks. */
    private function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter('the_content', array($this, 'render_catalog_page'), 40);
        add_action('wp_enqueue_scripts', array($this, 'replace_legacy_search'), 100);
    }

    /** @return void */
    public function register_routes()
    {
        register_rest_route(
            'autolex/v1',
            '/vehicles',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_vehicles_response'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'q'     => array('sanitize_callback' => 'sanitize_text_field'),
                    'make'  => array('sanitize_callback' => 'sanitize_title'),
                    'page'  => array('sanitize_callback' => 'absint', 'default' => 1),
                    'limit' => array('sanitize_callback' => 'absint', 'default' => 24),
                ),
            )
        );
    }

    /**
     * Replaces the broken legacy search client while preserving its slider.
     *
     * @return void
     */
    public function replace_legacy_search()
    {
        wp_dequeue_script('alxbc-js');
        wp_deregister_script('alxbc-js');
        wp_enqueue_script(
            'autolex-catalog-browser',
            plugins_url('assets/js/autolex-catalog-browser.js', AUTOLEX_PLATFORM_FILE),
            array(),
            AUTOLEX_PLATFORM_VERSION,
            true
        );
        wp_localize_script(
            'autolex-catalog-browser',
            'AutolexCatalog',
            array(
                'endpoint' => esc_url_raw(rest_url('autolex/v1/vehicles')),
                'carsUrl'  => home_url('/autok/'),
            )
        );
    }

    /** @return WP_REST_Response */
    public function get_vehicles_response(WP_REST_Request $request)
    {
        $limit = min(48, max(1, (int) $request->get_param('limit')));
        $page  = max(1, (int) $request->get_param('page'));
        $data  = $this->query_vehicles(
            (string) $request->get_param('q'),
            (string) $request->get_param('make'),
            $page,
            $limit
        );

        $response = rest_ensure_response($data);
        $response->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
        return $response;
    }

    /**
     * Renders the catalogue on the existing /autok/ page.
     *
     * @param string $content Original page content.
     * @return string
     */
    public function render_catalog_page($content)
    {
        if (is_admin() || !is_page('autok') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $make = isset($_GET['marka']) ? sanitize_title(wp_unslash($_GET['marka'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $q    = isset($_GET['kereses']) ? sanitize_text_field(wp_unslash($_GET['kereses'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['oldal']) ? max(1, absint($_GET['oldal'])) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $data = $this->query_vehicles($q, $make, $page, 24);

        ob_start();
        ?>
        <section class="alxp-catalog" aria-labelledby="alxp-catalog-title">
            <header class="alxp-catalog__header">
                <div>
                    <span><?php echo esc_html__('EU autókatalógus', 'autolex-platform'); ?></span>
                    <h2 id="alxp-catalog-title">
                        <?php
                        echo esc_html(
                            $make
                                ? sprintf(__('%s modellek', 'autolex-platform'), $this->display_make($make))
                                : __('Autók keresése', 'autolex-platform')
                        );
                        ?>
                    </h2>
                </div>
                <strong><?php echo esc_html(number_format_i18n($data['total'])); ?> <?php echo esc_html__('találat', 'autolex-platform'); ?></strong>
            </header>
            <form class="alxp-catalog__search" action="<?php echo esc_url(home_url('/autok/')); ?>" method="get" role="search">
                <?php if ($make) : ?>
                    <input type="hidden" name="marka" value="<?php echo esc_attr($make); ?>">
                <?php endif; ?>
                <label class="screen-reader-text" for="alxp-catalog-query"><?php echo esc_html__('Keresés az autók között', 'autolex-platform'); ?></label>
                <input id="alxp-catalog-query" name="kereses" type="search" value="<?php echo esc_attr($q); ?>" placeholder="<?php echo esc_attr__('Márka, modell, generáció vagy motor', 'autolex-platform'); ?>">
                <button type="submit"><?php echo esc_html__('Keresés', 'autolex-platform'); ?></button>
            </form>
            <?php if ($data['items']) : ?>
                <div class="alxp-catalog__grid">
                    <?php foreach ($data['items'] as $vehicle) : ?>
                        <a class="alxp-vehicle-card" href="<?php echo esc_url($vehicle['url']); ?>">
                            <span><?php echo esc_html($vehicle['make']); ?></span>
                            <h3><?php echo esc_html(trim($vehicle['model'] . ' ' . $vehicle['generation'])); ?></h3>
                            <?php if ($vehicle['engine']) : ?><p><?php echo esc_html($vehicle['engine']); ?></p><?php endif; ?>
                            <small><?php echo esc_html($vehicle['years']); ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php echo wp_kses_post($this->pagination($data, $make, $q)); ?>
            <?php else : ?>
                <div class="alxp-catalog__empty">
                    <h3><?php echo esc_html__('Nincs megjeleníthető találat', 'autolex-platform'); ?></h3>
                    <p><?php echo esc_html__('Próbálj másik márkát vagy rövidebb keresőkifejezést.', 'autolex-platform'); ?></p>
                </div>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Uses a compatible legacy catalogue table, with published vehicle posts as fallback.
     *
     * @return array<string,mixed>
     */
    private function query_vehicles($query, $make, $page, $limit)
    {
        $mapping = $this->discover_legacy_table();
        if ($mapping) {
            return $this->query_legacy_table($mapping, $query, $make, $page, $limit);
        }
        return $this->query_vehicle_posts($query, $make, $page, $limit);
    }

    /**
     * Finds a table by capability, never by a hard-coded customer-specific name.
     * The result is cached to avoid schema inspection on normal requests.
     *
     * @return array<string,string>|false
     */
    private function discover_legacy_table()
    {
        global $wpdb;
        $cached = get_transient('autolex_catalog_table_v2');
        if (is_array($cached)) {
            return $cached;
        }

        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix) . '%'));
        foreach ($tables as $table) {
            $is_autolex_table = false !== stripos($table, 'autolex') || false !== stripos($table, 'alx');
            $is_new_eu_table  = false !== stripos($table, 'autolex_eu_');
            if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !$is_autolex_table || $is_new_eu_table) {
                continue;
            }
            $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $map     = $this->map_columns($columns);
            if ($map) {
                $map['table'] = $table;
                set_transient('autolex_catalog_table_v2', $map, DAY_IN_SECONDS);
                return $map;
            }
        }

        set_transient('autolex_catalog_table_v2', array(), HOUR_IN_SECONDS);
        return false;
    }

    /** @return array<string,string>|false */
    private function map_columns($columns)
    {
        $pick = static function ($candidates) use ($columns) {
            foreach ($candidates as $candidate) {
                if (in_array($candidate, $columns, true)) {
                    return $candidate;
                }
            }
            return '';
        };
        $map = array(
            'id'         => $pick(array('id', 'vehicle_id')),
            'make'       => $pick(array('brand', 'make', 'marka')),
            'model'      => $pick(array('model', 'modell')),
            'generation' => $pick(array('generation', 'generation_name', 'generacio')),
            'engine'     => $pick(array('engine', 'engine_name', 'motor')),
            'engine_code' => $pick(array('engine_code', 'motor_code', 'motor_kod', 'motorkod')),
            'fuel_type'   => $pick(array('fuel_type', 'fuel', 'uzemanyag')),
            'capacity_cc' => $pick(array('engine_capacity_cc', 'capacity_cc', 'displacement_cc', 'hengerurtartalom')),
            'power_kw'    => $pick(array('engine_power_kw', 'power_kw', 'teljesitmeny_kw')),
            'power_ps'    => $pick(array('engine_power_ps', 'power_ps', 'power_hp', 'horsepower', 'teljesitmeny_le')),
            'year_from'  => $pick(array('year_from', 'production_start', 'evjarat_tol')),
            'year_to'    => $pick(array('year_to', 'production_end', 'evjarat_ig')),
            'slug'       => $pick(array('slug', 'post_slug')),
        );
        return $map['id'] && $map['make'] && ($map['model'] || $map['generation']) ? $map : false;
    }

    /**
     * Returns the validated legacy mapping for internal enrichment jobs.
     *
     * @return array<string,string>|false
     */
    public function get_legacy_mapping()
    {
        return $this->discover_legacy_table();
    }

    /**
     * Audits how much engine identity is already present in the legacy catalogue.
     * Only aggregate figures are returned; physical table names stay private.
     *
     * @return array<string,mixed>
     */
    public function get_engine_coverage()
    {
        global $wpdb;

        $cached = get_transient('autolex_engine_coverage_v1');
        if (is_array($cached)) {
            return $cached;
        }

        $map = $this->discover_legacy_table();
        if (!$map) {
            return array(
                'catalog_source'          => 'wordpress',
                'catalog_vehicles'        => 0,
                'rows_with_engine_label'  => 0,
                'rows_with_engine_code'   => 0,
                'rows_with_engine_specs'  => 0,
                'rows_missing_engine'     => 0,
                'engine_identity_percent' => 0.0,
                'multi_engine_models'     => 0,
                'makes'                   => array(),
                'detected_fields'         => array(),
            );
        }

        $table = '`' . $map['table'] . '`';
        $filled = static function ($column) {
            return $column ? "TRIM(COALESCE(`{$column}`, '')) <> ''" : '0=1';
        };
        $engine_label = $filled($map['engine']);
        $engine_code  = $filled($map['engine_code']);
        $engine_any   = '(' . $engine_label . ' OR ' . $engine_code . ')';
        $spec_parts   = array($engine_any);
        foreach (array('fuel_type', 'capacity_cc', 'power_kw', 'power_ps') as $field) {
            if (!empty($map[$field])) {
                $spec_parts[] = $filled($map[$field]);
            }
        }
        $specification = count($spec_parts) > 1 ? '(' . implode(' AND ', $spec_parts) . ')' : '0=1';

        $counts = $wpdb->get_row(
            "SELECT COUNT(*) AS total,
                SUM({$engine_label}) AS with_label,
                SUM({$engine_code}) AS with_code,
                SUM({$specification}) AS with_specs,
                SUM(NOT {$engine_any}) AS missing_engine
            FROM {$table}",
            ARRAY_A
        );
        $counts = is_array($counts) ? $counts : array();
        $total  = (int) ($counts['total'] ?? 0);

        $identity_parts = array_filter(array($map['engine'], $map['engine_code'], $map['capacity_cc'], $map['power_kw'], $map['power_ps']));
        $multi_engine_models = 0;
        if ($identity_parts) {
            $identity_sql = "CONCAT_WS('|', " . implode(', ', array_map(static function ($column) {
                return "TRIM(COALESCE(`{$column}`, ''))";
            }, $identity_parts)) . ')';
            $group_columns = array_filter(array($map['make'], $map['model'], $map['generation']));
            $group_sql     = implode(', ', array_map(static function ($column) {
                return "`{$column}`";
            }, $group_columns));
            $multi_engine_models = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM (
                    SELECT 1 FROM {$table}
                    WHERE {$engine_any}
                    GROUP BY {$group_sql}
                    HAVING COUNT(DISTINCT {$identity_sql}) > 1
                ) AS autolex_multi_engine_models"
            );
        }

        $make_rows = $wpdb->get_results(
            "SELECT `{$map['make']}` AS make,
                COUNT(*) AS vehicles,
                SUM({$engine_any}) AS identified,
                SUM({$engine_code}) AS coded,
                SUM({$specification}) AS specified
            FROM {$table}
            GROUP BY `{$map['make']}`
            ORDER BY vehicles DESC, make ASC
            LIMIT 100",
            ARRAY_A
        );
        $makes = array_map(static function ($row) {
            $vehicles  = (int) $row['vehicles'];
            $identified = (int) $row['identified'];
            return array(
                'make'             => (string) $row['make'],
                'vehicles'         => $vehicles,
                'identified'       => $identified,
                'engine_codes'     => (int) $row['coded'],
                'specified'        => (int) $row['specified'],
                'coverage_percent' => $vehicles ? round(($identified / $vehicles) * 100, 2) : 0.0,
            );
        }, (array) $make_rows);

        $detected = array();
        foreach (array('engine', 'engine_code', 'fuel_type', 'capacity_cc', 'power_kw', 'power_ps') as $field) {
            $detected[$field] = !empty($map[$field]);
        }

        $identified = $total - (int) ($counts['missing_engine'] ?? 0);
        $result = array(
            'catalog_source'          => 'legacy',
            'catalog_vehicles'        => $total,
            'rows_with_engine_label'  => (int) ($counts['with_label'] ?? 0),
            'rows_with_engine_code'   => (int) ($counts['with_code'] ?? 0),
            'rows_with_engine_specs'  => (int) ($counts['with_specs'] ?? 0),
            'rows_missing_engine'     => (int) ($counts['missing_engine'] ?? 0),
            'engine_identity_percent' => $total ? round(($identified / $total) * 100, 2) : 0.0,
            'multi_engine_models'     => $multi_engine_models,
            'makes'                   => $makes,
            'detected_fields'         => $detected,
        );
        set_transient('autolex_engine_coverage_v1', $result, 15 * MINUTE_IN_SECONDS);
        return $result;
    }

    /** @return array<string,mixed> */
    private function query_legacy_table($map, $query, $make, $page, $limit)
    {
        global $wpdb;
        $table  = '`' . $map['table'] . '`';
        $where  = array('1=1');
        $params = array();
        if ($make) {
            $where[]  = "LOWER(REPLACE(`{$map['make']}`, '-', ' ')) = %s";
            $params[] = str_replace('-', ' ', strtolower($make));
        }
        if ($query) {
            $searchable = array_filter(array($map['make'], $map['model'], $map['generation'], $map['engine']));
            $tokens     = preg_split('/\s+/', trim($query), 6, PREG_SPLIT_NO_EMPTY);
            foreach ($tokens as $token) {
                $where[] = '(' . implode(' OR ', array_map(static function ($column) { return "`{$column}` LIKE %s"; }, $searchable)) . ')';
                $like    = '%' . $wpdb->esc_like($token) . '%';
                foreach ($searchable as $unused) {
                    $params[] = $like;
                }
            }
        }
        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total     = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $offset    = ($page - 1) * $limit;
        $select    = array();
        foreach (array('id', 'make', 'model', 'generation', 'engine', 'year_from', 'year_to', 'slug') as $alias) {
            $select[] = $map[$alias] ? "`{$map[$alias]}` AS `{$alias}`" : "'' AS `{$alias}`";
        }
        $data_sql    = 'SELECT ' . implode(', ', $select) . " FROM {$table} WHERE {$where_sql} ORDER BY `{$map['make']}`, `" . ($map['model'] ?: $map['generation']) . '` LIMIT %d OFFSET %d';
        $data_params = array_merge($params, array($limit, $offset));
        $rows        = $wpdb->get_results($wpdb->prepare($data_sql, $data_params), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return $this->result($rows, $total, $page, $limit, 'legacy');
    }

    /** @return array<string,mixed> */
    private function query_vehicle_posts($query, $make, $page, $limit)
    {
        $search = trim(str_replace('-', ' ', $make) . ' ' . $query);
        $wpq    = new WP_Query(array(
            'post_type'              => 'alx_vehicle',
            'post_status'            => 'publish',
            's'                      => $search,
            'posts_per_page'         => $limit,
            'paged'                  => $page,
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'no_found_rows'          => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));
        $rows = array_map(static function ($post) {
            $parts = preg_split('/\s+/', get_the_title($post), 2);
            return array(
                'id' => $post->ID, 'make' => $parts[0] ?? '', 'model' => $parts[1] ?? '',
                'generation' => '', 'engine' => '', 'year_from' => '', 'year_to' => '',
                'url' => get_permalink($post),
            );
        }, $wpq->posts);
        return $this->result($rows, (int) $wpq->found_posts, $page, $limit, 'wordpress');
    }

    /** @return array<string,mixed> */
    private function result($rows, $total, $page, $limit, $source)
    {
        $items = array();
        foreach ((array) $rows as $row) {
            $id   = absint($row['id'] ?? 0);
            $stored_slug = trim((string) ($row['slug'] ?? ''));
            $slug_source = $stored_slug ?: trim(($row['make'] ?? '') . '-' . ($row['generation'] ?? '') . '-' . ($row['model'] ?? ''));
            $slug = sanitize_title($slug_source);
            $url  = $row['url'] ?? home_url('/auto-adatlap/' . $id . '/' . $slug . '/');
            $from = trim((string) ($row['year_from'] ?? ''));
            $to   = trim((string) ($row['year_to'] ?? ''));
            $items[] = array(
                'id' => $id, 'make' => (string) ($row['make'] ?? ''), 'model' => (string) ($row['model'] ?? ''),
                'generation' => (string) ($row['generation'] ?? ''), 'engine' => (string) ($row['engine'] ?? ''),
                'years' => $from ? $from . ($to ? '–' . $to : '–') : '', 'url' => $url,
            );
        }
        return array('items' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $limit)), 'source' => $source);
    }

    /** @return string */
    private function pagination($data, $make, $query)
    {
        if ($data['pages'] < 2) {
            return '';
        }
        $base = array_filter(array('marka' => $make, 'kereses' => $query));
        $out  = '<nav class="alxp-pagination" aria-label="' . esc_attr__('Találati oldalak', 'autolex-platform') . '">';
        if ($data['page'] > 1) {
            $out .= '<a href="' . esc_url(add_query_arg(array_merge($base, array('oldal' => $data['page'] - 1)), home_url('/autok/'))) . '">← ' . esc_html__('Előző', 'autolex-platform') . '</a>';
        }
        $out .= '<span>' . sprintf(esc_html__('%1$d / %2$d oldal', 'autolex-platform'), $data['page'], $data['pages']) . '</span>';
        if ($data['page'] < $data['pages']) {
            $out .= '<a href="' . esc_url(add_query_arg(array_merge($base, array('oldal' => $data['page'] + 1)), home_url('/autok/'))) . '">' . esc_html__('Következő', 'autolex-platform') . ' →</a>';
        }
        return $out . '</nav>';
    }

    /** @return string */
    private function display_make($slug)
    {
        return ucwords(str_replace('-', ' ', $slug));
    }
}
