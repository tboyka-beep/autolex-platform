<?php
/**
 * Standalone smoke test for plugin-owned public theme data hooks.
 */

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('AUTOLEX_PLATFORM_DIR', dirname(__DIR__) . '/plugin/autolex-platform/');
define('AUTOLEX_PLATFORM_FILE', AUTOLEX_PLATFORM_DIR . 'autolex-platform.php');

$GLOBALS['autolex_test_actions'] = array();
$GLOBALS['autolex_test_styles'] = array();
$GLOBALS['autolex_test_options'] = array('autolex_eu_schema_version' => '1.1.0');

function add_action($hook, $callback, $priority = 10)
{
    $GLOBALS['autolex_test_actions'][$hook][] = array($callback, $priority);
}

function is_admin()
{
    return false;
}

function get_stylesheet()
{
    return 'autolex-theme';
}

function wp_enqueue_style($handle, $src, $deps, $version)
{
    $GLOBALS['autolex_test_styles'][$handle] = compact('src', 'deps', 'version');
}

function plugins_url($path, $file)
{
    return 'https://example.test/wp-content/plugins/autolex-platform/' . ltrim($path, '/');
}

function __($text, $domain = null)
{
    return $text;
}

function esc_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_html__($text, $domain = null)
{
    return esc_html($text);
}

function esc_url($url)
{
    return (string) $url;
}

function number_format_i18n($number, $decimals = 0)
{
    return number_format((float) $number, (int) $decimals, '.', ' ');
}

function home_url($path = '/')
{
    return 'https://example.test' . '/' . ltrim((string) $path, '/');
}

function add_query_arg($key, $value = null, $url = null)
{
    if (is_array($key)) {
        $args = $key;
        $url = (string) $value;
    } else {
        $args = array($key => $value);
        $url = (string) $url;
    }

    foreach ($args as $arg_key => $arg_value) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . rawurlencode((string) $arg_key) . '=' . rawurlencode((string) $arg_value);
    }

    return $url;
}

function get_option($name)
{
    return $GLOBALS['autolex_test_options'][$name] ?? null;
}

function remove_accents($value)
{
    return strtr((string) $value, array('Š' => 'S', 'š' => 's', 'Á' => 'A', 'á' => 'a', 'É' => 'E', 'é' => 'e'));
}

final class Autolex_EU_Catalog
{
    const SCHEMA_VERSION = '1.1.0';

    public static $coverage = array(
        'vehicles' => 1234,
        'makes' => 18,
        'models' => 220,
        'countries' => 27,
        'latest_data_year' => 2025,
        'source_observations' => 9876,
    );

    public static function instance()
    {
        return new self();
    }

    public function get_coverage()
    {
        return self::$coverage;
    }

    public static function vehicles_table()
    {
        return 'wp_autolex_eu_vehicles';
    }
}

final class Autolex_Engine_Catalog
{
    public static function instance()
    {
        return new self();
    }

    public function get_coverage()
    {
        return array('verified_variants' => 77);
    }
}

final class Autolex_Test_Wpdb
{
    public function get_results($sql, $format)
    {
        if ($format !== ARRAY_A) {
            throw new RuntimeException('Theme bridge queries must request ARRAY_A.');
        }

        if (strpos($sql, 'GROUP BY make') !== false && strpos($sql, 'ORDER BY registrations DESC, variants DESC, make ASC') !== false) {
            return array(
                array('make' => 'Toyota', 'variants' => 42, 'registrations' => 1000),
                array('make' => 'Ford', 'variants' => 31, 'registrations' => 900),
            );
        }

        if (strpos($sql, 'LIMIT 30') !== false && strpos($sql, 'engine_power_kw') !== false) {
            return array(
                array('id' => 1, 'make' => 'BMW', 'model' => '3 Series', 'engine_capacity_cc' => 1998, 'engine_power_kw' => 135, 'co2_wltp' => 142, 'registration_count' => 1000, 'last_seen_year' => 2025),
                array('id' => 2, 'make' => 'Audi', 'model' => 'A4', 'engine_capacity_cc' => 1984, 'engine_power_kw' => 150, 'co2_wltp' => 151, 'registration_count' => 900, 'last_seen_year' => 2025),
                array('id' => 3, 'make' => 'BMW', 'model' => '5 Series', 'engine_capacity_cc' => 1998, 'engine_power_kw' => 140, 'co2_wltp' => 145, 'registration_count' => 800, 'last_seen_year' => 2024),
            );
        }

        throw new RuntimeException('Unexpected theme bridge get_results query: ' . $sql);
    }

    public function get_row($sql, $format)
    {
        if ($format !== ARRAY_A || strpos($sql, 'LIMIT 1') === false || strpos($sql, 'registration_count DESC') === false) {
            throw new RuntimeException('Featured vehicle query contract changed unexpectedly.');
        }

        return array(
            'id' => 1,
            'make' => 'BMW',
            'model' => '3 Series',
            'variant' => 'G20',
            'version' => '320i',
            'fuel_type' => 'Petrol',
            'engine_capacity_cc' => 1998,
            'engine_power_kw' => 135,
            'co2_wltp' => 142,
            'last_seen_year' => 2025,
            'registration_count' => 1000,
        );
    }
}

$GLOBALS['wpdb'] = new Autolex_Test_Wpdb();

require AUTOLEX_PLATFORM_DIR . 'includes/class-autolex-theme-data-bridge.php';

$bridge = Autolex_Theme_Data_Bridge::instance();
$bridge->register();
$bridge->register();

foreach (array(
    'autolex_theme_coverage_panel',
    'autolex_theme_popular_brands',
    'autolex_theme_metric_strip',
    'autolex_theme_featured_vehicle',
    'autolex_theme_comparison_preview',
    'wp_enqueue_scripts',
) as $hook) {
    if (count($GLOBALS['autolex_test_actions'][$hook] ?? array()) !== 1) {
        throw new RuntimeException('Hook must be registered exactly once: ' . $hook);
    }
}

ob_start();
$bridge->render_coverage_panel();
$coverage = ob_get_clean();
if (strpos($coverage, '1 234') === false || strpos($coverage, 'Legfrissebb adatév') === false) {
    throw new RuntimeException('Verified coverage markup is incomplete.');
}

ob_start();
$bridge->render_popular_brands();
$brands = ob_get_clean();
if (strpos($brands, 'Toyota') === false || strpos($brands, 'brand=Toyota') === false || strpos($brands, '42 változat') === false) {
    throw new RuntimeException('Popular brand markup is incomplete.');
}

ob_start();
$bridge->render_metric_strip();
$metrics = ob_get_clean();
if (substr_count($metrics, 'class="alx-live-metric"') !== 5 || strpos($metrics, '77') === false || strpos($metrics, '9 876') === false) {
    throw new RuntimeException('Metric strip must contain five verified values.');
}

ob_start();
$bridge->render_featured_vehicle();
$featured = ob_get_clean();
if (strpos($featured, 'BMW 3 Series') === false || strpos($featured, '1 998 cm³') === false || strpos($featured, '135 kW') === false || strpos($featured, 'brand=BMW') === false) {
    throw new RuntimeException('Featured vehicle must use real catalogue fields.');
}

ob_start();
$bridge->render_comparison_preview();
$comparison = ob_get_clean();
if (strpos($comparison, 'BMW 3 Series') === false || strpos($comparison, 'Audi A4') === false || strpos($comparison, '135 kW / 150 kW') === false || strpos($comparison, '142 g/km / 151 g/km') === false) {
    throw new RuntimeException('Comparison preview must use two distinct real catalogue makes and verified fields.');
}

$bridge->enqueue_assets();
$style = $GLOBALS['autolex_test_styles']['autolex-theme-data-bridge'] ?? null;
if (!is_array($style) || $style['deps'] !== array('autolex-theme-home') || empty($style['version'])) {
    throw new RuntimeException('Scoped bridge stylesheet was not enqueued correctly.');
}

Autolex_EU_Catalog::$coverage = array(
    'vehicles' => 0,
    'makes' => 0,
    'models' => 0,
    'countries' => 0,
    'latest_data_year' => null,
    'source_observations' => 0,
);
ob_start();
$bridge->render_coverage_panel();
$empty_coverage = ob_get_clean();
if ($empty_coverage !== '') {
    throw new RuntimeException('Empty catalogue data must leave the theme fallback authoritative.');
}

ob_start();
$bridge->render_metric_strip();
$empty_metrics = ob_get_clean();
if ($empty_metrics !== '') {
    throw new RuntimeException('Empty catalogue data must not publish zero-valued metric cards.');
}

echo "Autolex theme data bridge smoke test passed.\n";
