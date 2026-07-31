<?php
/**
 * Evidence-safe SEO and structured data for dynamic vehicle detail pages.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Vehicle_SEO
{
    /** @var Autolex_Vehicle_SEO|null */
    private static $instance = null;

    /** @var array<string,mixed>|null */
    private $vehicle = null;

    /** @return Autolex_Vehicle_SEO */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('template_redirect', array($this, 'prepare'), 1);
        add_filter('document_title_parts', array($this, 'filter_title_parts'), 30);
        add_filter('wp_robots', array($this, 'filter_robots'), 30);
        add_action('wp_head', array($this, 'render_head'), 2);
    }

    /** Loads one cacheable vehicle snapshot for the current dynamic detail URL. */
    public function prepare()
    {
        $vehicle_id = self::vehicle_id_from_uri((string) ($_SERVER['REQUEST_URI'] ?? ''));
        if (!$vehicle_id) {
            return;
        }
        $cache_key = 'autolex_vehicle_seo_' . $vehicle_id . '_' . AUTOLEX_PLATFORM_VERSION;
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            $this->vehicle = $cached;
        } elseif (class_exists('Autolex_Vehicle_Relations')) {
            $snapshot = Autolex_Vehicle_Relations::instance()->get_vehicle_snapshot($vehicle_id);
            if (is_array($snapshot) && !empty($snapshot['id']) && !empty($snapshot['make']) && !empty($snapshot['model'])) {
                $this->vehicle = $snapshot;
                set_transient($cache_key, $snapshot, 15 * MINUTE_IN_SECONDS);
            }
        }
        if ($this->vehicle) {
            remove_action('wp_head', 'rel_canonical');
        }
    }

    /** @param array<string,string> $parts Title parts. @return array<string,string> */
    public function filter_title_parts($parts)
    {
        if (!$this->vehicle) {
            return $parts;
        }
        $parts['title'] = self::seo_title($this->vehicle);
        return $parts;
    }

    /** @param array<string,bool|string> $robots Robots directives. @return array<string,bool|string> */
    public function filter_robots($robots)
    {
        if (!$this->vehicle) {
            return $robots;
        }
        unset($robots['noindex'], $robots['nofollow']);
        $robots['index'] = true;
        $robots['follow'] = true;
        $robots['max-image-preview'] = 'large';
        return $robots;
    }

    /** Outputs canonical, description and JSON-LD without inventing fields. */
    public function render_head()
    {
        if (!$this->vehicle) {
            return;
        }
        $canonical = home_url('/auto-adatlap/' . absint($this->vehicle['id']) . '/');
        $description = self::meta_description($this->vehicle);
        $graph = self::schema_graph($this->vehicle, $canonical, home_url('/autok/'));
        echo "\n" . '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        echo '<script type="application/ld+json">' . wp_json_encode(
            array('@context' => 'https://schema.org', '@graph' => $graph),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . '</script>' . "\n";
    }

    /** @return int */
    public static function vehicle_id_from_uri($uri)
    {
        return preg_match('~/auto-adatlap/(\d+)(?:/|$)~', (string) $uri, $matches)
            ? absint($matches[1])
            : 0;
    }

    /** @param array<string,mixed> $vehicle Vehicle. @return string */
    public static function seo_title($vehicle)
    {
        $name = self::vehicle_name($vehicle);
        $engine = trim((string) ($vehicle['engine'] ?? ''));
        $code = trim((string) ($vehicle['engine_code'] ?? ''));
        $detail = $engine ?: $code;
        return trim($name . ($detail ? ' – ' . $detail : '') . ' műszaki adatok');
    }

    /** @param array<string,mixed> $vehicle Vehicle. @return string */
    public static function meta_description($vehicle)
    {
        $facts = array();
        if (!empty($vehicle['year_from'])) {
            $years = (string) absint($vehicle['year_from']);
            if (!empty($vehicle['year_to'])) {
                $years .= '–' . absint($vehicle['year_to']);
            }
            $facts[] = $years;
        }
        foreach (array('fuel_type', 'engine_code') as $field) {
            $value = trim((string) ($vehicle[$field] ?? ''));
            if ($value) {
                $facts[] = $value;
            }
        }
        if (!empty($vehicle['power_ps'])) {
            $facts[] = (string) round((float) $vehicle['power_ps']) . ' LE';
        } elseif (!empty($vehicle['power_kw'])) {
            $facts[] = (string) round((float) $vehicle['power_kw']) . ' kW';
        }
        $suffix = $facts ? ' ' . implode(', ', $facts) . '.' : '.';
        return self::limit_text(self::vehicle_name($vehicle) . ' ellenőrizhető műszaki adatlapja.' . $suffix . ' Forrásjelzések, motorváltozatok és EU Safety Gate információk az Autolex rendszerében.', 158);
    }

    /** @return array<int,array<string,mixed>> */
    public static function schema_graph($vehicle, $canonical, $catalog_url)
    {
        $name = self::vehicle_name($vehicle);
        $vehicle_schema = array(
            '@type' => 'Vehicle',
            '@id' => $canonical . '#vehicle',
            'name' => $name,
            'url' => $canonical,
            'brand' => array('@type' => 'Brand', 'name' => trim((string) ($vehicle['make'] ?? ''))),
            'model' => trim((string) ($vehicle['model'] ?? '')),
        );
        $optional = array(
            'vehicleConfiguration' => trim(implode(' ', array_filter(array(
                trim((string) ($vehicle['generation'] ?? '')),
                trim((string) ($vehicle['engine'] ?? '')),
                trim((string) ($vehicle['engine_code'] ?? '')),
            )))),
            'fuelType' => trim((string) ($vehicle['fuel_type'] ?? '')),
        );
        if (!empty($vehicle['year_from'])) {
            $optional['vehicleModelDate'] = (string) absint($vehicle['year_from']);
        }
        if (!empty($vehicle['capacity_cc'])) {
            $optional['vehicleEngine'] = array(
                '@type' => 'EngineSpecification',
                'engineDisplacement' => array(
                    '@type' => 'QuantitativeValue',
                    'value' => absint($vehicle['capacity_cc']),
                    'unitCode' => 'CMQ',
                ),
            );
        }
        foreach ($optional as $key => $value) {
            if ('' !== $value && array() !== $value) {
                $vehicle_schema[$key] = $value;
            }
        }
        return array(
            $vehicle_schema,
            array(
                '@type' => 'BreadcrumbList',
                '@id' => $canonical . '#breadcrumb',
                'itemListElement' => array(
                    array('@type' => 'ListItem', 'position' => 1, 'name' => 'Autolex', 'item' => home_url('/')),
                    array('@type' => 'ListItem', 'position' => 2, 'name' => 'Autók', 'item' => $catalog_url),
                    array('@type' => 'ListItem', 'position' => 3, 'name' => $name, 'item' => $canonical),
                ),
            ),
        );
    }

    /** @return string */
    private static function vehicle_name($vehicle)
    {
        return trim(implode(' ', array_filter(array(
            trim((string) ($vehicle['make'] ?? '')),
            trim((string) ($vehicle['model'] ?? '')),
            trim((string) ($vehicle['generation'] ?? '')),
        ))));
    }

    /** @return string */
    private static function limit_text($text, $limit)
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));
        if (function_exists('mb_strlen') && mb_strlen($text) > $limit) {
            return rtrim(mb_substr($text, 0, $limit - 1), " ,.;:-") . '…';
        }
        if (!function_exists('mb_strlen') && strlen($text) > $limit) {
            return rtrim(substr($text, 0, $limit - 1), " ,.;:-") . '…';
        }
        return $text;
    }
}
