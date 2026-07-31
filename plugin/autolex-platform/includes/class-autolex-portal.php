<?php
/**
 * Information-dense public portal and faceted catalogue for Autolex.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/trait-autolex-portal-query.php';
require_once __DIR__ . '/trait-autolex-portal-home.php';
require_once __DIR__ . '/trait-autolex-portal-catalog.php';
require_once __DIR__ . '/trait-autolex-portal-utilities.php';

final class Autolex_Portal
{
    private static $instance = null;

    use Autolex_Portal_Query_Trait;
    use Autolex_Portal_Home_Trait;
    use Autolex_Portal_Catalog_Trait;
    use Autolex_Portal_Utilities_Trait;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Free, public and official source registry.
     *
     * Sources without a stable machine-readable distribution stay reference-only
     * until their access and reuse terms can be verified.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_source_registry()
    {
        return array(
            array(
                'code'        => 'eea_co2',
                'name'        => 'EEA CO₂ monitoring',
                'publisher'   => 'European Environment Agency',
                'url'         => 'https://co2cars.apps.eea.europa.eu/',
                'access'      => 'SQL/JSON API és CSV',
                'scope'       => 'EU/EGT személyautó: márka, kereskedelmi név, típus, variáns, verzió, üzemanyag, teljesítmény, hengerűrtartalom, tömeg és kibocsátás',
                'automation'  => 'active',
                'confidence'  => 'primary',
                'cost'        => 'free',
            ),
            array(
                'code'        => 'eurostat_transport',
                'name'        => 'Eurostat közúti közlekedés',
                'publisher'   => 'Eurostat',
                'url'         => 'https://ec.europa.eu/eurostat/web/transport/information-data/road-transport',
                'access'      => 'nyilvános REST API',
                'scope'       => 'járműállomány, új regisztrációk, ország- és hajtáslánc-szintű piaci háttéradatok',
                'automation'  => 'adapter_ready',
                'confidence'  => 'primary',
                'cost'        => 'free',
            ),
            array(
                'code'        => 'eafo',
                'name'        => 'European Alternative Fuels Observatory',
                'publisher'   => 'European Commission / EAFO',
                'url'         => 'https://alternative-fuels-observatory.ec.europa.eu/',
                'access'      => 'portál és letöltések',
                'scope'       => 'BEV, PHEV, hidrogén, LPG, CNG és LNG járműállomány, regisztrációk és infrastruktúra',
                'automation'  => 'adapter_ready',
                'confidence'  => 'primary',
                'cost'        => 'free',
            ),
            array(
                'code'        => 'safety_gate',
                'name'        => 'EU Safety Gate',
                'publisher'   => 'European Commission',
                'url'         => 'https://ec.europa.eu/safety-gate/',
                'access'      => 'nyilvános kereső, heti XML és Excel',
                'scope'       => 'veszélyes termékek és jármű-visszahívási riasztások',
                'automation'  => 'live_validated',
                'confidence'  => 'primary',
                'cost'        => 'free',
            ),
            array(
                'code'        => 'type_approval_register',
                'name'        => 'Type Approval Register',
                'publisher'   => 'Vehicle Certification Agency / data.europa.eu',
                'url'         => 'https://data.europa.eu/data/datasets/type-approval-register?locale=en',
                'access'      => 'katalógusrekord; stabil letöltési csatorna jelenleg nem igazolt',
                'scope'       => 'típusjóváhagyások, gyártói és fogyasztási/kibocsátási megerősítés',
                'automation'  => 'reference_only',
                'confidence'  => 'primary',
                'cost'        => 'free',
            ),
            array(
                'code'        => 'eu_coc_schema',
                'name'        => 'EU megfelelőségi és típusjóváhagyási séma',
                'publisher'   => 'EUR-Lex',
                'url'         => 'https://eur-lex.europa.eu/eli/reg_impl/2020/683/oj',
                'access'      => 'jogszabályi dokumentum',
                'scope'       => 'CoC és típusjóváhagyási mezők hivatalos jelentése és validációs szabályai',
                'automation'  => 'active_reference',
                'confidence'  => 'primary',
                'cost'        => 'free',
            ),
        );
    }

    /**
     * Pure quality grading used by the renderer and smoke tests.
     *
     * @param array<string,mixed> $vehicle Vehicle fields.
     * @return string A, B or C.
     */
    public static function calculate_quality_grade($vehicle)
    {
        $filled = static function ($key) use ($vehicle) {
            return isset($vehicle[$key]) && '' !== trim((string) $vehicle[$key]) && '0' !== trim((string) $vehicle[$key]);
        };

        $has_engine = $filled('engine_code') || $filled('engine');
        $has_power  = $filled('power_ps') || $filled('power_kw');
        $complete   = $has_engine && $filled('fuel_type') && $filled('capacity_cc') && $has_power && $filled('year_from');

        if ($complete) {
            return 'A';
        }
        if ($has_engine && ($filled('fuel_type') || $filled('capacity_cc') || $has_power)) {
            return 'B';
        }
        return 'C';
    }

    /** Registers public hooks. */
    private function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 120);
        add_filter('body_class', array($this, 'add_body_class'));
        add_filter('the_content', array($this, 'render_portal_page'), 80);
    }

    /** @return void */
    public function register_routes()
    {
        register_rest_route(
            'autolex/v1',
            '/portal/vehicles',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_vehicles_response'),
                'permission_callback' => '__return_true',
                'args'                => $this->vehicle_route_args(),
            )
        );

        register_rest_route(
            'autolex/v1',
            '/portal/facets',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_facets_response'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'make' => array('sanitize_callback' => 'sanitize_text_field'),
                ),
            )
        );

        register_rest_route(
            'autolex/v1',
            '/sources',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_sources_response'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /** @return array<string,array<string,mixed>> */
    private function vehicle_route_args()
    {
        return array(
            'q'            => array('sanitize_callback' => 'sanitize_text_field'),
            'make'         => array('sanitize_callback' => 'sanitize_text_field'),
            'model'        => array('sanitize_callback' => 'sanitize_text_field'),
            'generation'   => array('sanitize_callback' => 'sanitize_text_field'),
            'fuel'         => array('sanitize_callback' => 'sanitize_text_field'),
            'engine_code'  => array('sanitize_callback' => 'sanitize_text_field'),
            'year_min'     => array('sanitize_callback' => 'absint'),
            'year_max'     => array('sanitize_callback' => 'absint'),
            'power_min'    => array('sanitize_callback' => 'absint'),
            'power_max'    => array('sanitize_callback' => 'absint'),
            'grade'        => array('sanitize_callback' => 'sanitize_key'),
            'verification' => array('sanitize_callback' => 'sanitize_key'),
            'sort'         => array('sanitize_callback' => 'sanitize_key', 'default' => 'data_desc'),
            'page'         => array('sanitize_callback' => 'absint', 'default' => 1),
            'limit'        => array('sanitize_callback' => 'absint', 'default' => 24),
        );
    }

    /** @return void */
    public function enqueue_assets()
    {
        if (is_admin() || !(is_front_page() || is_page('autok') || is_singular('alx_vehicle'))) {
            return;
        }

        wp_enqueue_style(
            'autolex-portal-3',
            plugins_url('assets/css/autolex-portal-3.css', AUTOLEX_PLATFORM_FILE),
            array('autolex-platform-experience'),
            AUTOLEX_PLATFORM_VERSION
        );
        wp_enqueue_script(
            'autolex-portal-3',
            plugins_url('assets/js/autolex-portal-3.js', AUTOLEX_PLATFORM_FILE),
            array(),
            AUTOLEX_PLATFORM_VERSION,
            true
        );
        wp_localize_script(
            'autolex-portal-3',
            'AutolexPortal',
            array(
                'vehiclesEndpoint' => esc_url_raw(rest_url('autolex/v1/portal/vehicles')),
                'facetsEndpoint'   => esc_url_raw(rest_url('autolex/v1/portal/facets')),
                'catalogUrl'       => esc_url_raw(home_url('/autok/')),
                'labels'           => array(
                    'loading' => __('Adatok betöltése…', 'autolex-platform'),
                    'error'   => __('A szűrés átmenetileg nem érhető el.', 'autolex-platform'),
                    'results' => __('találat', 'autolex-platform'),
                ),
            )
        );
    }

    /** @param string[] $classes Body classes. @return string[] */
    public function add_body_class($classes)
    {
        if (is_front_page() || is_page('autok') || is_singular('alx_vehicle')) {
            $classes[] = 'autolex-portal-3';
        }
        return $classes;
    }

    /** @return WP_REST_Response */
    public function get_sources_response()
    {
        $response = rest_ensure_response(
            array(
                'service'      => 'autolex-source-registry',
                'status'       => 'ok',
                'policy'       => 'official_free_allowlisted',
                'sources'      => self::get_source_registry(),
                'generated_at' => gmdate('c'),
            )
        );
        $response->header('Cache-Control', 'public, max-age=3600, stale-while-revalidate=86400');
        return $response;
    }

    /** @return WP_REST_Response */
    public function get_facets_response(WP_REST_Request $request)
    {
        $response = rest_ensure_response($this->get_facets((string) $request->get_param('make')));
        $response->header('Cache-Control', 'public, max-age=1800, stale-while-revalidate=7200');
        return $response;
    }

    /** @return WP_REST_Response */
    public function get_vehicles_response(WP_REST_Request $request)
    {
        $filters = array();
        foreach (array_keys($this->vehicle_route_args()) as $key) {
            $filters[$key] = $request->get_param($key);
        }
        $data = $this->query_vehicles($filters);
        $response = rest_ensure_response($data);
        $response->header('Cache-Control', 'public, max-age=180, stale-while-revalidate=600');
        return $response;
    }

    /**
     * Replaces the legacy front page and catalogue content completely.
     *
     * @param string $content Original content.
     * @return string
     */
    public function render_portal_page($content)
    {
        if (is_admin() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        if (is_front_page()) {
            return $this->render_homepage();
        }
        if (is_page('autok')) {
            return $this->render_catalogue();
        }
        return $content;
    }
}
