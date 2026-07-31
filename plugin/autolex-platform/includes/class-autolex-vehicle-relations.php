<?php
/**
 * Related generations, engines and recall summary for vehicle detail pages.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Vehicle_Relations
{
    /** @var Autolex_Vehicle_Relations|null */
    private static $instance = null;

    /** @return Autolex_Vehicle_Relations */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 125);
    }

    /** @return void */
    public function register_routes()
    {
        register_rest_route('autolex/v1', '/vehicle-relations/(?P<vehicle_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_response'),
            'permission_callback' => '__return_true',
            'args'                => array('vehicle_id' => array('sanitize_callback' => 'absint')),
        ));
    }

    /** @return void */
    public function enqueue_assets()
    {
        if (false === strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), '/auto-adatlap/')) {
            return;
        }
        wp_enqueue_style(
            'autolex-vehicle-relations',
            plugins_url('assets/css/autolex-vehicle-relations.css', AUTOLEX_PLATFORM_FILE),
            array('autolex-portal-3'),
            AUTOLEX_PLATFORM_VERSION
        );
        wp_enqueue_script(
            'autolex-vehicle-relations',
            plugins_url('assets/js/autolex-vehicle-relations.js', AUTOLEX_PLATFORM_FILE),
            array(),
            AUTOLEX_PLATFORM_VERSION,
            true
        );
        wp_localize_script('autolex-vehicle-relations', 'AutolexVehicleRelations', array(
            'endpoint'   => esc_url_raw(rest_url('autolex/v1/vehicle-relations/')),
            'catalogUrl' => esc_url_raw(home_url('/autok/')),
            'version'    => AUTOLEX_PLATFORM_VERSION,
        ));
    }

    /** @return WP_REST_Response */
    public function get_response(WP_REST_Request $request)
    {
        $data = $this->get_relations(absint($request['vehicle_id']));
        $response = rest_ensure_response($data);
        $response->header('Cache-Control', 'public, max-age=900, stale-while-revalidate=3600');
        return $response;
    }

    /** @return array<string,mixed> */
    private function get_relations($vehicle_id)
    {
        global $wpdb;
        $empty = array(
            'status' => 'empty',
            'vehicle_id' => $vehicle_id,
            'vehicle' => array(),
            'generations' => array(),
            'engines' => array(),
            'recalls' => array('total' => 0, 'latest_at' => null, 'risk_types' => array()),
        );
        if (!class_exists('Autolex_Catalog_Browser')) {
            return $empty;
        }
        $map = Autolex_Catalog_Browser::instance()->get_legacy_mapping();
        if (!$this->valid_mapping($map)) {
            return $empty;
        }
        $vehicle = $this->fetch_vehicle($map, $vehicle_id);
        if (!$vehicle) {
            return $empty;
        }

        $generations = $this->fetch_related($map, $vehicle, false, 8);
        $engines = $this->fetch_related($map, $vehicle, true, 10);

        return array(
            'status' => 'ok',
            'vehicle_id' => $vehicle_id,
            'vehicle' => $vehicle,
            'generations' => $generations,
            'engines' => $engines,
            'recalls' => $this->recall_summary($vehicle),
            'policy' => array(
                'relations' => 'catalog_identity_only',
                'recalls' => 'text_match_not_vin_confirmation',
                'compatibility' => 'not_asserted',
            ),
        );
    }

    /** @param array<string,string>|false $map Mapping. @return bool */
    private function valid_mapping($map)
    {
        if (!$map || empty($map['table']) || empty($map['id']) || empty($map['make']) || empty($map['model'])) {
            return false;
        }
        foreach (array_merge(array('table'), array_values($map)) as $identifier) {
            if ('' !== $identifier && !preg_match('/^[A-Za-z0-9_]+$/', (string) $identifier)) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string,mixed>|false */
    private function fetch_vehicle($map, $vehicle_id)
    {
        global $wpdb;
        $fields = array('id','make','model','generation','engine','engine_code','fuel_type','capacity_cc','power_kw','power_ps','year_from','year_to');
        $select = array();
        foreach ($fields as $field) {
            $select[] = !empty($map[$field]) ? '`' . $map[$field] . '` AS `' . $field . '`' : "'' AS `{$field}`";
        }
        $sql = 'SELECT ' . implode(', ', $select) . ' FROM `' . $map['table'] . '` WHERE `' . $map['id'] . '` = %d LIMIT 1';
        $row = $wpdb->get_row($wpdb->prepare($sql, $vehicle_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array($row) ? $this->normalise_vehicle($row) : false;
    }

    /** @return array<int,array<string,mixed>> */
    private function fetch_related($map, $vehicle, $same_generation, $limit)
    {
        global $wpdb;
        $fields = array('id','make','model','generation','engine','engine_code','fuel_type','capacity_cc','power_kw','power_ps','year_from','year_to');
        $select = array();
        foreach ($fields as $field) {
            $select[] = !empty($map[$field]) ? '`' . $map[$field] . '` AS `' . $field . '`' : "'' AS `{$field}`";
        }
        $where = array('`' . $map['id'] . '` <> %d', 'LOWER(`' . $map['make'] . '`) = LOWER(%s)', 'LOWER(`' . $map['model'] . '`) = LOWER(%s)');
        $params = array((int) $vehicle['id'], (string) $vehicle['make'], (string) $vehicle['model']);
        if ($same_generation && !empty($map['generation']) && '' !== $vehicle['generation']) {
            $where[] = 'LOWER(`' . $map['generation'] . '`) = LOWER(%s)';
            $params[] = (string) $vehicle['generation'];
        }
        $order = $same_generation
            ? (!empty($map['engine_code']) ? "CASE WHEN TRIM(`{$map['engine_code']}`) <> '' THEN 0 ELSE 1 END, " : '') . '`' . $map['year_from'] . '` ASC'
            : (!empty($map['generation']) ? '`' . $map['generation'] . '` ASC, ' : '') . '`' . $map['year_from'] . '` ASC';
        $sql = 'SELECT ' . implode(', ', $select) . ' FROM `' . $map['table'] . '` WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order . ' LIMIT %d';
        $params[] = $limit;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $items = array();
        $seen = array();
        foreach ((array) $rows as $row) {
            $item = $this->normalise_vehicle($row);
            $key = $same_generation
                ? strtolower($item['engine_code'] . '|' . $item['engine'] . '|' . $item['power_ps'])
                : strtolower($item['generation'] . '|' . $item['year_from'] . '|' . $item['year_to']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = $item;
        }
        return $items;
    }

    /** @return array<string,mixed> */
    private function normalise_vehicle($row)
    {
        $vehicle = array(
            'id' => absint($row['id'] ?? 0),
            'make' => trim((string) ($row['make'] ?? '')),
            'model' => trim((string) ($row['model'] ?? '')),
            'generation' => trim((string) ($row['generation'] ?? '')),
            'engine' => trim((string) ($row['engine'] ?? '')),
            'engine_code' => trim((string) ($row['engine_code'] ?? '')),
            'fuel_type' => trim((string) ($row['fuel_type'] ?? '')),
            'capacity_cc' => absint($row['capacity_cc'] ?? 0),
            'power_kw' => is_numeric($row['power_kw'] ?? null) ? round((float) $row['power_kw'], 1) : 0,
            'power_ps' => is_numeric($row['power_ps'] ?? null) ? round((float) $row['power_ps'], 1) : 0,
            'year_from' => absint($row['year_from'] ?? 0),
            'year_to' => absint($row['year_to'] ?? 0),
        );
        $vehicle['title'] = trim(implode(' ', array_filter(array($vehicle['make'], $vehicle['model'], $vehicle['generation']))));
        $vehicle['url'] = home_url('/auto-adatlap/' . $vehicle['id'] . '/');
        return $vehicle;
    }

    /** @return array<string,mixed> */
    private function recall_summary($vehicle)
    {
        global $wpdb;
        $result = array('total' => 0, 'latest_at' => null, 'risk_types' => array());
        if (!class_exists('Autolex_Safety_Gate') || Autolex_Safety_Gate::SCHEMA_VERSION !== get_option('autolex_safety_gate_schema_version')) {
            return $result;
        }
        $table = Autolex_Safety_Gate::table();
        $where = array('LOWER(brand) LIKE %s');
        $params = array('%' . $wpdb->esc_like(strtolower((string) $vehicle['make'])) . '%');
        if ('' !== $vehicle['model']) {
            $where[] = "LOWER(CONCAT_WS(' ', model, product_name, type_number)) LIKE %s";
            $params[] = '%' . $wpdb->esc_like(strtolower((string) $vehicle['model'])) . '%';
        }
        $sql = 'SELECT COUNT(*) AS total, MAX(notified_at) AS latest_at FROM ' . $table . ' WHERE ' . implode(' AND ', $where);
        $row = $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A);
        if (is_array($row)) {
            $result['total'] = (int) ($row['total'] ?? 0);
            $result['latest_at'] = $row['latest_at'] ?: null;
        }
        $risk_sql = 'SELECT risk_type, COUNT(*) AS total FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . " AND TRIM(risk_type) <> '' GROUP BY risk_type ORDER BY total DESC, risk_type ASC LIMIT 4";
        $risks = $wpdb->get_results($wpdb->prepare($risk_sql, $params), ARRAY_A);
        foreach ((array) $risks as $risk) {
            $result['risk_types'][] = array('label' => (string) $risk['risk_type'], 'total' => (int) $risk['total']);
        }
        return $result;
    }
}
