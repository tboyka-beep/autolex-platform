<?php
/**
 * Hungarian public terminology and record-backed vehicle summary layer.
 *
 * Raw source/database values remain untouched. This class only normalizes
 * public presentation and adds factual content from already stored fields.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Public_Presentation
{
    /** @var Autolex_Public_Presentation|null */
    private static $instance = null;

    /** @return Autolex_Public_Presentation */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_filter('rest_post_dispatch', array($this, 'localize_rest_response'), 20, 3);
        add_filter('the_content', array($this, 'prepend_vehicle_fact_summary'), 75);
        add_action('template_redirect', array($this, 'start_html_localizer'), 1);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 155);
    }

    /**
     * Converts a source fuel value to a conservative Hungarian public label.
     * Unknown source terminology is preserved rather than guessed.
     *
     * @param mixed $value Raw source value.
     * @return string
     */
    public static function fuel_label($value)
    {
        $raw = trim((string) $value);
        if ('' === $raw) {
            return '';
        }

        $key = function_exists('remove_accents') ? remove_accents($raw) : $raw;
        $key = strtolower((string) $key);
        $key = str_replace(array('_', '–', '—'), array(' ', '-', '-'), $key);
        $key = preg_replace('/\s*\/\s*/u', '/', $key);
        $key = preg_replace('/\s+/u', ' ', trim((string) $key));

        $map = array(
            'petrol'                  => 'Benzin',
            'gasoline'                => 'Benzin',
            'benzin'                  => 'Benzin',
            'diesel'                  => 'Dízel',
            'gasoil'                  => 'Dízel',
            'dizel'                   => 'Dízel',
            'electric'                => 'Elektromos',
            'electricity'             => 'Elektromos',
            'electric vehicle'        => 'Elektromos',
            'bev'                     => 'Elektromos',
            'elektromos'              => 'Elektromos',
            'petrol/electric'         => 'Benzin / elektromos',
            'gasoline/electric'       => 'Benzin / elektromos',
            'petrol electric'         => 'Benzin / elektromos',
            'gasoline electric'       => 'Benzin / elektromos',
            'diesel/electric'         => 'Dízel / elektromos',
            'diesel electric'         => 'Dízel / elektromos',
            'hybrid'                  => 'Hibrid',
            'hybrid electric'         => 'Hibrid',
            'hev'                     => 'Hibrid',
            'plug-in hybrid'          => 'Plug-in hibrid',
            'plug in hybrid'          => 'Plug-in hibrid',
            'phev'                    => 'Plug-in hibrid',
            'mild hybrid'             => 'Lágy hibrid',
            'mhev'                    => 'Lágy hibrid',
            'lpg'                     => 'LPG (autógáz)',
            'liquefied petroleum gas' => 'LPG (autógáz)',
            'cng'                     => 'CNG (sűrített földgáz)',
            'compressed natural gas'  => 'CNG (sűrített földgáz)',
            'ng'                      => 'Földgáz (NG)',
            'natural gas'             => 'Földgáz (NG)',
            'lng'                     => 'LNG (cseppfolyósított földgáz)',
            'hydrogen'                => 'Hidrogén',
            'h2'                      => 'Hidrogén',
            'ethanol'                 => 'Etanol',
            'e85'                     => 'E85 (etanol)',
            'biodiesel'               => 'Biodízel',
            'petrol/lpg'              => 'Benzin / LPG (autógáz)',
            'gasoline/lpg'            => 'Benzin / LPG (autógáz)',
            'petrol/cng'              => 'Benzin / CNG (sűrített földgáz)',
            'gasoline/cng'            => 'Benzin / CNG (sűrített földgáz)',
            'other'                   => 'Egyéb',
            'unknown'                 => 'Ismeretlen',
            'not available'           => 'Ismeretlen',
            'n/a'                     => 'Ismeretlen',
        );

        if (isset($map[$key])) {
            return $map[$key];
        }

        if (preg_match('/^(petrol|gasoline)\s*(\(.+\)|[a-z0-9-]+)$/iu', $raw, $match)) {
            return 'Benzin ' . trim((string) $match[2]);
        }
        if (preg_match('/^diesel\s*(\(.+\)|[a-z0-9-]+)$/iu', $raw, $match)) {
            return 'Dízel ' . trim((string) $match[1]);
        }

        return $raw;
    }

    /** @param mixed $value Raw source fuel-mode value. @return string */
    public static function fuel_mode_label($value)
    {
        $raw = trim((string) $value);
        if ('' === $raw) {
            return '';
        }
        $key = strtolower(str_replace(array('_', '-'), ' ', $raw));
        $key = preg_replace('/\s+/u', ' ', trim((string) $key));
        $map = array(
            'mono fuel' => 'Egy üzemanyagú',
            'monofuel'  => 'Egy üzemanyagú',
            'bi fuel'   => 'Kétüzemű',
            'bifuel'    => 'Kétüzemű',
            'dual fuel' => 'Kettős üzemanyagú',
            'flex fuel' => 'Rugalmas üzemanyagú',
        );
        return $map[$key] ?? $raw;
    }

    /**
     * Recursively localizes public API data while retaining raw source values.
     *
     * @param mixed $data Payload.
     * @return mixed
     */
    public static function localize_payload($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $localized = array();
        foreach ($data as $key => $value) {
            if ('fuel_type' === $key && (is_string($value) || is_numeric($value))) {
                $localized['fuel_type_raw'] = (string) $value;
                $localized[$key] = self::fuel_label($value);
                continue;
            }
            if ('fuel_mode' === $key && (is_string($value) || is_numeric($value))) {
                $localized['fuel_mode_raw'] = (string) $value;
                $localized[$key] = self::fuel_mode_label($value);
                continue;
            }
            if ('fuels' === $key && is_array($value)) {
                $localized[$key] = array_map(static function ($item) {
                    if (!is_array($item) || !array_key_exists('value', $item)) {
                        return self::localize_payload($item);
                    }
                    $item['label'] = self::fuel_label($item['value']);
                    return self::localize_payload($item);
                }, $value);
                continue;
            }
            $localized[$key] = self::localize_payload($value);
        }
        return $localized;
    }

    /**
     * REST post-dispatch presentation filter for public Autolex vehicle routes.
     *
     * @param mixed $response REST response.
     * @param mixed $server   REST server.
     * @param mixed $request  REST request.
     * @return mixed
     */
    public function localize_rest_response($response, $server, $request)
    {
        if (!is_object($request) || !method_exists($request, 'get_route')) {
            return $response;
        }
        $route = (string) $request->get_route();
        $allowed = array(
            '/autolex/v1/portal/vehicles',
            '/autolex/v1/portal/facets',
            '/autolex/v1/maintenance/',
        );
        $matches = false;
        foreach ($allowed as $prefix) {
            if (0 === strpos($route, $prefix)) {
                $matches = true;
                break;
            }
        }
        if (!$matches || !is_object($response) || !method_exists($response, 'get_data') || !method_exists($response, 'set_data')) {
            return $response;
        }
        $response->set_data(self::localize_payload($response->get_data()));
        return $response;
    }

    /** Starts a narrowly scoped HTML text-node localizer for Autolex public pages. */
    public function start_html_localizer()
    {
        if (!$this->is_public_autolex_request()) {
            return;
        }
        ob_start(array(__CLASS__, 'localize_html'));
    }

    /**
     * Localizes only complete visible text nodes; scripts/styles/code stay raw.
     *
     * @param string $html HTML response.
     * @return string
     */
    public static function localize_html($html)
    {
        $parts = preg_split(
            '/(<(?:script|style|pre|code|textarea)\b[^>]*>.*?<\/(?:script|style|pre|code|textarea)>)/isu',
            (string) $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
        if (!is_array($parts)) {
            return (string) $html;
        }
        foreach ($parts as $index => $part) {
            if (preg_match('/^<(?:script|style|pre|code|textarea)\b/iu', $part)) {
                continue;
            }
            $parts[$index] = preg_replace_callback('/>([^<>]+)</u', static function ($match) {
                return '>' . self::localize_visible_text($match[1]) . '<';
            }, $part);
        }
        return implode('', $parts);
    }

    /** @param string $text Visible HTML text node. @return string */
    public static function localize_visible_text($text)
    {
        $leading = '';
        $trailing = '';
        if (preg_match('/^(\s*)/u', (string) $text, $match)) {
            $leading = $match[1];
        }
        if (preg_match('/(\s*)$/u', (string) $text, $match)) {
            $trailing = $match[1];
        }
        $trimmed = trim((string) $text);
        if ('' === $trimmed) {
            return (string) $text;
        }

        $fixed = array(
            'PRIMARY'          => 'ELSŐDLEGES',
            'SUPPORT'          => 'MEGERŐSÍTŐ',
            'LIVE QUERY'       => 'ÉLŐ LEKÉRDEZÉS',
            'FRISSAUTO SEARCH' => 'FRISSAUTO KERESÉS',
        );
        if (isset($fixed[$trimmed])) {
            return $leading . $fixed[$trimmed] . $trailing;
        }

        $fuel = self::fuel_label($trimmed);
        if ($fuel !== $trimmed) {
            return $leading . $fuel . $trailing;
        }

        if (preg_match('/^(.+?)\s+\(([0-9 .,.]+)\)$/u', $trimmed, $match)) {
            $label = self::fuel_label($match[1]);
            if ($label !== $match[1]) {
                return $leading . $label . ' (' . $match[2] . ')' . $trailing;
            }
        }

        return (string) $text;
    }

    /** Enqueues dynamic terminology normalization for client-rendered UI. */
    public function enqueue_assets()
    {
        if (!$this->is_public_autolex_request()) {
            return;
        }
        $relative = 'assets/js/autolex-public-presentation.js';
        $absolute = AUTOLEX_PLATFORM_DIR . $relative;
        if (!is_readable($absolute)) {
            return;
        }
        wp_enqueue_script(
            'autolex-public-presentation',
            plugins_url($relative, AUTOLEX_PLATFORM_FILE),
            array(),
            (string) filemtime($absolute),
            true
        );
    }

    /**
     * Adds useful factual content to every real vehicle detail without guessing.
     *
     * @param string $content Existing vehicle detail content.
     * @return string
     */
    public function prepend_vehicle_fact_summary($content)
    {
        if (is_admin() || !$this->is_vehicle_detail_request() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        if (false !== strpos((string) $content, 'data-autolex-public-facts="true"')) {
            return $content;
        }
        $vehicle_id = $this->vehicle_id_from_request();
        if (!$vehicle_id) {
            return $content;
        }
        $vehicle = $this->get_vehicle_record($vehicle_id);
        if (!$vehicle || empty($vehicle['make']) || empty($vehicle['model'])) {
            return $content;
        }
        $facts = self::build_vehicle_facts($vehicle);
        if (!$facts) {
            return $content;
        }
        $title = trim(implode(' ', array_filter(array($vehicle['make'], $vehicle['model'], $vehicle['generation']))));

        ob_start();
        ?>
        <section class="alxbc-section alx3-detail-section alx-public-facts" data-autolex-public-facts="true" aria-labelledby="alx-public-facts-title">
            <div class="alxbc-section-head alx3-detail-heading">
                <div>
                    <span><?php echo esc_html__('RÖGZÍTETT KATALÓGUSADATOK', 'autolex-platform'); ?></span>
                    <h2 id="alx-public-facts-title"><?php echo esc_html__('Röviden erről a változatról', 'autolex-platform'); ?></h2>
                </div>
                <b><?php echo esc_html(number_format_i18n(count($facts))); ?> <?php echo esc_html__('rögzített mező', 'autolex-platform'); ?></b>
            </div>
            <p>
                <?php
                printf(
                    esc_html__('A %s alábbi összefoglalója kizárólag az ehhez a katalógusrekordhoz rögzített műszaki mezőkből készül. Hiányzó adatot az Autolex nem becsül és nem talál ki.', 'autolex-platform'),
                    esc_html($title)
                );
                ?>
            </p>
            <dl class="alx-public-facts__grid">
                <?php foreach ($facts as $fact) : ?>
                    <div>
                        <dt><?php echo esc_html($fact['label']); ?></dt>
                        <dd><?php echo esc_html($fact['value']); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
        <?php
        return (string) ob_get_clean() . $content;
    }

    /**
     * Builds deterministic public facts only from non-empty stored fields.
     *
     * @param array<string,mixed> $vehicle Vehicle data.
     * @return array<int,array<string,string>>
     */
    public static function build_vehicle_facts($vehicle)
    {
        $facts = array();
        $add = static function ($label, $value) use (&$facts) {
            $value = trim((string) $value);
            if ('' !== $value && '0' !== $value) {
                $facts[] = array('label' => $label, 'value' => $value);
            }
        };
        $format = static function ($value, $decimals = 0) {
            if (function_exists('number_format_i18n')) {
                return number_format_i18n((float) $value, $decimals);
            }
            return number_format((float) $value, $decimals, ',', ' ');
        };

        $add('Generáció', $vehicle['generation'] ?? '');
        $add('Motorváltozat', $vehicle['engine'] ?? '');
        $add('Motorkód', $vehicle['engine_code'] ?? '');
        $add('Üzemanyag', self::fuel_label($vehicle['fuel_type'] ?? ''));

        $capacity = (int) ($vehicle['capacity_cc'] ?? 0);
        if ($capacity > 0) {
            $add('Hengerűrtartalom', $format($capacity) . ' cm³');
        }
        $kw = is_numeric($vehicle['power_kw'] ?? null) ? (float) $vehicle['power_kw'] : 0.0;
        $ps = is_numeric($vehicle['power_ps'] ?? null) ? (float) $vehicle['power_ps'] : 0.0;
        if ($ps > 0 || $kw > 0) {
            if ($ps <= 0 && $kw > 0) {
                $ps = round($kw * 1.3596216173);
            }
            $power = $ps > 0 ? $format($ps, $ps === floor($ps) ? 0 : 1) . ' LE' : '';
            if ($kw > 0) {
                $power .= ($power ? ' / ' : '') . $format($kw, $kw === floor($kw) ? 0 : 1) . ' kW';
            }
            $add('Teljesítmény', $power);
        }
        $from = (int) ($vehicle['year_from'] ?? 0);
        $to = (int) ($vehicle['year_to'] ?? 0);
        if ($from > 0) {
            $add('Gyártási időszak', $from . ($to > 0 ? '–' . $to : '–'));
        }
        return $facts;
    }

    /** @return bool */
    private function is_public_autolex_request()
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }
        if (function_exists('get_stylesheet') && 'autolex-theme' === get_stylesheet()) {
            return true;
        }
        return is_front_page() || is_page('autok') || $this->is_vehicle_detail_request();
    }

    /** @return bool */
    private function is_vehicle_detail_request()
    {
        return false !== strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), '/auto-adatlap/');
    }

    /** @return int */
    private function vehicle_id_from_request()
    {
        if (!preg_match('~/auto-adatlap/(\d+)(?:/|$)~', (string) ($_SERVER['REQUEST_URI'] ?? ''), $match)) {
            return 0;
        }
        return absint($match[1]);
    }

    /** @param int $vehicle_id Vehicle ID. @return array<string,mixed> */
    private function get_vehicle_record($vehicle_id)
    {
        global $wpdb;
        if (!class_exists('Autolex_Catalog_Browser')) {
            return array();
        }
        $map = Autolex_Catalog_Browser::instance()->get_legacy_mapping();
        if (!$map || empty($map['table']) || empty($map['id'])) {
            return array();
        }
        foreach (array_merge(array('table'), array_values($map)) as $identifier) {
            if ('' !== $identifier && !preg_match('/^[A-Za-z0-9_]+$/', (string) $identifier)) {
                return array();
            }
        }
        $fields = array('id', 'make', 'model', 'generation', 'engine', 'engine_code', 'fuel_type', 'capacity_cc', 'power_kw', 'power_ps', 'year_from', 'year_to');
        $select = array();
        foreach ($fields as $field) {
            $select[] = !empty($map[$field])
                ? '`' . $map[$field] . '` AS `' . $field . '`'
                : "'' AS `{$field}`";
        }
        $sql = 'SELECT ' . implode(', ', $select) . ' FROM `' . $map['table'] . '` WHERE `' . $map['id'] . '` = %d LIMIT 1';
        $row = $wpdb->get_row($wpdb->prepare($sql, $vehicle_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return is_array($row) ? $row : array();
    }
}
