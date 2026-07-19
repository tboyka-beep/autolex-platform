<?php
/**
 * Source-backed maintenance facts and specification-led product matching.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Maintenance_Evidence
{
    const SCHEMA_VERSION = '1.1.0';

    /** @var Autolex_Maintenance_Evidence|null */
    private static $instance = null;

    /** @return Autolex_Maintenance_Evidence */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('init', array($this, 'maybe_install'), 6);
        add_action('rest_api_init', array($this, 'register_route'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 110);
    }

    /** @return void */
    public function maybe_install()
    {
        if (self::SCHEMA_VERSION !== get_option('autolex_maintenance_schema_version')) {
            self::install_schema();
            $this->seed_bmw_e87_118d();
        }
    }

    /** @return void */
    public static function install_schema()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $collate = $wpdb->get_charset_collate();
        $claims  = self::claims_table();
        $sources = self::sources_table();
        $links   = self::links_table();
        $rules   = self::rules_table();

        dbDelta("CREATE TABLE {$claims} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            legacy_vehicle_id bigint(20) unsigned NOT NULL,
            engine_code varchar(80) NOT NULL DEFAULT '',
            field_key varchar(80) NOT NULL,
            label varchar(191) NOT NULL,
            value_text varchar(255) NOT NULL,
            note_text text DEFAULT NULL,
            status varchar(30) NOT NULL DEFAULT 'review',
            confidence tinyint(3) unsigned NOT NULL DEFAULT 0,
            checked_at date DEFAULT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY vehicle_field (legacy_vehicle_id, engine_code, field_key),
            KEY vehicle_engine (legacy_vehicle_id, engine_code),
            KEY status (status)
        ) {$collate};");

        dbDelta("CREATE TABLE {$sources} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_key varchar(100) NOT NULL,
            publisher varchar(191) NOT NULL,
            title varchar(255) NOT NULL,
            source_type varchar(40) NOT NULL,
            source_url text NOT NULL,
            is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
            checked_at date DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source_key (source_key),
            KEY publisher (publisher),
            KEY source_type (source_type)
        ) {$collate};");

        dbDelta("CREATE TABLE {$links} (
            claim_id bigint(20) unsigned NOT NULL,
            source_id bigint(20) unsigned NOT NULL,
            support_level varchar(30) NOT NULL DEFAULT 'supports',
            source_note varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (claim_id, source_id),
            KEY source_id (source_id)
        ) {$collate};");

        dbDelta("CREATE TABLE {$rules} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            legacy_vehicle_id bigint(20) unsigned NOT NULL,
            engine_code varchar(80) NOT NULL DEFAULT '',
            category_key varchar(80) NOT NULL,
            label varchar(191) NOT NULL,
            required_spec varchar(191) NOT NULL DEFAULT '',
            search_query varchar(191) NOT NULL,
            rule_type varchar(30) NOT NULL DEFAULT 'exact_search',
            fallback_reason varchar(255) NOT NULL DEFAULT '',
            priority tinyint(3) unsigned NOT NULL DEFAULT 50,
            PRIMARY KEY (id),
            UNIQUE KEY vehicle_category (legacy_vehicle_id, engine_code, category_key),
            KEY vehicle_engine (legacy_vehicle_id, engine_code)
        ) {$collate};");

        update_option('autolex_maintenance_schema_version', self::SCHEMA_VERSION, false);
    }

    /** @return void */
    public function register_route()
    {
        register_rest_route('autolex/v1', '/maintenance/(?P<vehicle_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_response'),
            'permission_callback' => '__return_true',
            'args'                => array('vehicle_id' => array('sanitize_callback' => 'absint')),
        ));
    }

    /** @return WP_REST_Response */
    public function get_response(WP_REST_Request $request)
    {
        $data = $this->get_vehicle_data(absint($request['vehicle_id']));
        $res  = rest_ensure_response($data);
        $res->header('Cache-Control', 'public, max-age=900, stale-while-revalidate=1800');
        return $res;
    }

    /** @return void */
    public function enqueue_assets()
    {
        if (false === strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), '/auto-adatlap/')) {
            return;
        }
        wp_enqueue_script('autolex-maintenance-evidence', plugins_url('assets/js/autolex-maintenance-evidence.js', AUTOLEX_PLATFORM_FILE), array(), AUTOLEX_PLATFORM_VERSION, true);
        wp_localize_script('autolex-maintenance-evidence', 'AutolexMaintenance', array(
            'endpoint' => esc_url_raw(rest_url('autolex/v1/maintenance/')),
            'version'  => AUTOLEX_PLATFORM_VERSION,
        ));
    }

    /** @return array<string,mixed> */
    private function get_vehicle_data($vehicle_id)
    {
        global $wpdb;
        $claims = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::claims_table() . ' WHERE legacy_vehicle_id = %d ORDER BY id',
            $vehicle_id
        ), ARRAY_A);
        $items = array();
        foreach ($claims as $claim) {
            $sources = $wpdb->get_results($wpdb->prepare(
                'SELECT s.*, l.support_level, l.source_note FROM ' . self::links_table() . ' l INNER JOIN ' . self::sources_table() . ' s ON s.id = l.source_id WHERE l.claim_id = %d ORDER BY s.is_primary DESC, s.publisher',
                $claim['id']
            ), ARRAY_A);
            $items[] = array(
                'key' => $claim['field_key'], 'label' => $claim['label'], 'value' => $claim['value_text'],
                'note' => $claim['note_text'], 'status' => $claim['status'], 'confidence' => (int) $claim['confidence'],
                'checked_at' => $claim['checked_at'], 'sources' => array_map(array($this, 'public_source'), $sources),
            );
        }
        $rules = $wpdb->get_results($wpdb->prepare(
            'SELECT category_key, label, required_spec, search_query, rule_type, fallback_reason FROM ' . self::rules_table() . ' WHERE legacy_vehicle_id = %d ORDER BY priority DESC',
            $vehicle_id
        ), ARRAY_A);
        foreach ($rules as &$rule) {
            $rule['url'] = add_query_arg(array(
                'search' => $rule['search_query'], 'utm_source' => 'autolex', 'utm_medium' => 'vehicle-fitment',
                'utm_campaign' => 'maintenance-evidence',
            ), 'https://www.frissauto.hu/shop_search.php');
        }
        unset($rule);
        $unique_sources = array();
        foreach ($items as $item) {
            foreach ($item['sources'] as $source) {
                $unique_sources[$source['key']] = $source;
            }
        }
        return array(
            'vehicle_id' => $vehicle_id, 'engine_code' => $claims[0]['engine_code'] ?? '',
            'claims' => $items, 'sources' => array_values($unique_sources), 'recommendations' => $rules,
            'status' => $items ? 'ok' : 'empty',
            'disclaimer' => __('A szín nem helyettesíti a gyártói folyadékspecifikációt. Vásárlás előtt VIN és motorkód alapján ellenőrizd a kompatibilitást.', 'autolex-platform'),
        );
    }

    /** @return array<string,mixed> */
    private function public_source($source)
    {
        return array(
            'key' => $source['source_key'], 'publisher' => $source['publisher'], 'title' => $source['title'],
            'type' => $source['source_type'], 'url' => esc_url_raw($source['source_url']),
            'primary' => (bool) $source['is_primary'], 'checked_at' => $source['checked_at'],
            'support' => $source['support_level'], 'note' => $source['source_note'],
        );
    }

    /** Seeds the first reviewed vehicle without duplicating rows. */
    private function seed_bmw_e87_118d()
    {
        global $wpdb;
        $today = gmdate('Y-m-d');
        $sources = array(
            'bmw_manuals' => array('BMW AG', 'BMW Driver’s Guide – VIN-alapú kezelési kézikönyv', 'manufacturer_manual', 'https://baonline.bmw.com/', 1),
            'bmw_uk_manual' => array('BMW UK', 'BMW Owners Manuals & Guides', 'manufacturer_portal', 'https://www.bmw.co.uk/en/topics/owners/bmw-owners-manual.html', 1),
            'castrol_bmw' => array('Castrol', 'BMW olajok és üzemi folyadékok', 'fluid_manufacturer', 'https://www.castrol.com/en_gb/united-kingdom/home/products/automotive/oem-oil-and-fluids/bmw.html', 0),
            'basf_g48' => array('BASF GLYSANTIN', 'GLYSANTIN G48 koncentrátum – termék és jóváhagyások', 'fluid_manufacturer', 'https://www.glysantin.com/global/en/product-finder/glysantin-g48-concentrate', 0),
        );
        foreach ($sources as $key => $s) {
            $wpdb->replace(self::sources_table(), array('source_key' => $key, 'publisher' => $s[0], 'title' => $s[1], 'source_type' => $s[2], 'source_url' => $s[3], 'is_primary' => $s[4], 'checked_at' => $today), array('%s','%s','%s','%s','%s','%d','%s'));
        }
        $claims = array(
            'engine_oil' => array('Motorolaj', 'BMW Longlife-04; tipikusan SAE 5W-30', 'A jóváhagyás az elsődleges. A pontos feltöltési mennyiséget VIN-alapú kézikönyv vagy szervizadat alapján kell lezárni.', 'review', 85, array('bmw_manuals','castrol_bmw')),
            'coolant' => array('Hűtőfolyadék', 'BMW-kompatibilis G48 jellegű hibrid hűtőfolyadék', 'Jellemző szín: kék–zöld. A szín csak tájékoztató; keverés előtt a BMW-jóváhagyást és a meglévő folyadékot kell ellenőrizni.', 'review', 80, array('bmw_uk_manual','basf_g48')),
            'brake_fluid' => array('Fékfolyadék', 'BMW által jóváhagyott DOT 4', 'A csereperiódust és az alacsony viszkozitású változat szükségességét VIN alapján kell ellenőrizni.', 'review', 75, array('bmw_manuals','castrol_bmw')),
            'oil_capacity' => array('Motorolaj feltöltési mennyiség', 'VIN alapján ellenőrizendő', 'A N47D20 kivitelek között lehet eltérés; ellenőrizetlen literadat nem jelenhet meg kész értékként.', 'needs_vin', 45, array('bmw_manuals')),
        );
        foreach ($claims as $key => $c) {
            $wpdb->replace(self::claims_table(), array('legacy_vehicle_id' => 1, 'engine_code' => 'N47D20', 'field_key' => $key, 'label' => $c[0], 'value_text' => $c[1], 'note_text' => $c[2], 'status' => $c[3], 'confidence' => $c[4], 'checked_at' => $today, 'updated_at' => current_time('mysql', true)), array('%d','%s','%s','%s','%s','%s','%s','%d','%s','%s'));
            $claim_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::claims_table() . ' WHERE legacy_vehicle_id=1 AND engine_code=%s AND field_key=%s', 'N47D20', $key));
            $wpdb->delete(self::links_table(), array('claim_id' => $claim_id), array('%d'));
            foreach ($c[5] as $source_key) {
                $source_id = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::sources_table() . ' WHERE source_key=%s', $source_key));
                $wpdb->insert(self::links_table(), array('claim_id' => $claim_id, 'source_id' => $source_id, 'support_level' => 'supports', 'source_note' => 'Motor- és évjárat-egyezés a véglegesítés előtt ellenőrizendő.'), array('%d','%d','%s','%s'));
            }
        }
        $rules = array(
            array('engine_oil','Motorolaj','BMW Longlife-04 / 5W-30','BMW Longlife-04 5W-30','exact_search','Elsődleges, specifikáció szerinti keresés.',100),
            array('coolant','Hűtőfolyadék','BMW-kompatibilis G48','G48 fagyálló hűtőfolyadék','exact_search','Elsődleges, specifikáció szerinti keresés.',90),
            array('oil_filter','Olajszűrő','BMW E87 118d / N47D20','BMW E87 118d olajszűrő','exact_search','Elsődleges, motorkód szerinti keresés.',80),
            array('wiper_care','Ablaktörlő és szélvédőápolás','Méret és csatlakozás ellenőrzendő','ablaktörlő szélvédőápolás','fallback','Ha nincs megfelelő folyadék vagy szűrő, biztonságos általános alternatíva.',60),
            array('steering_cover','Kormányvédő','Kormányátmérő alapján választható','kormányvédő','fallback','Univerzális termék, de az átmérőt vásárlás előtt ellenőrizni kell.',50),
            array('car_care','Autóápolás','Külső és belső ápolási termékek','autóápolás','fallback','Járműspecifikus alkatrészillesztést nem igénylő ajánlat.',40),
        );
        foreach ($rules as $r) {
            $wpdb->replace(self::rules_table(), array('legacy_vehicle_id'=>1,'engine_code'=>'N47D20','category_key'=>$r[0],'label'=>$r[1],'required_spec'=>$r[2],'search_query'=>$r[3],'rule_type'=>$r[4],'fallback_reason'=>$r[5],'priority'=>$r[6]), array('%d','%s','%s','%s','%s','%s','%s','%s','%d'));
        }
    }

    public static function claims_table() { global $wpdb; return $wpdb->prefix . 'autolex_maintenance_claims'; }
    public static function sources_table() { global $wpdb; return $wpdb->prefix . 'autolex_maintenance_sources'; }
    public static function links_table() { global $wpdb; return $wpdb->prefix . 'autolex_maintenance_evidence'; }
    public static function rules_table() { global $wpdb; return $wpdb->prefix . 'autolex_product_rules'; }
}
