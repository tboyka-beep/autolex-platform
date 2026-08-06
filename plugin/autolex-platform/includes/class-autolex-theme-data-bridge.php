<?php
/**
 * Supplies verified plugin-owned data to the public Autolex theme hooks.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Theme_Data_Bridge
{
    /** @var Autolex_Theme_Data_Bridge|null */
    private static $instance = null;

    /** @var bool */
    private $registered = false;

    /** @return Autolex_Theme_Data_Bridge */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Register the theme-facing render hooks exactly once. */
    public function register()
    {
        if ($this->registered) {
            return;
        }

        add_action('autolex_theme_coverage_panel', array($this, 'render_coverage_panel'));
        add_action('autolex_theme_popular_brands', array($this, 'render_popular_brands'));
        add_action('autolex_theme_metric_strip', array($this, 'render_metric_strip'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 35);

        $this->registered = true;
    }

    /** Load the bridge stylesheet only for the dedicated Autolex theme. */
    public function enqueue_assets()
    {
        if (is_admin() || !function_exists('get_stylesheet') || get_stylesheet() !== 'autolex-theme') {
            return;
        }

        $relative = 'assets/css/autolex-theme-data-bridge.css';
        $absolute = AUTOLEX_PLATFORM_DIR . $relative;
        if (!is_readable($absolute)) {
            return;
        }

        wp_enqueue_style(
            'autolex-theme-data-bridge',
            plugins_url($relative, AUTOLEX_PLATFORM_FILE),
            array('autolex-theme-home'),
            (string) filemtime($absolute)
        );
    }

    /** Render verified aggregate catalogue coverage. */
    public function render_coverage_panel()
    {
        $coverage = $this->get_catalogue_coverage();
        if (!$this->has_catalogue_data($coverage)) {
            return;
        }

        $rows = array(
            __('Járműváltozatok', 'autolex-platform') => (int) $coverage['vehicles'],
            __('Márkák', 'autolex-platform')           => (int) $coverage['makes'],
            __('Modellek', 'autolex-platform')         => (int) $coverage['models'],
            __('EU/EGT piacok', 'autolex-platform')    => (int) $coverage['countries'],
        );
        ?>
        <dl class="alx-live-coverage">
            <?php foreach ($rows as $label => $value) : ?>
                <div class="alx-live-coverage__row">
                    <dt><?php echo esc_html($label); ?></dt>
                    <dd><?php echo esc_html(number_format_i18n($value)); ?></dd>
                </div>
            <?php endforeach; ?>
            <?php if (!empty($coverage['latest_data_year'])) : ?>
                <div class="alx-live-coverage__row">
                    <dt><?php echo esc_html__('Legfrissebb adatév', 'autolex-platform'); ?></dt>
                    <dd><?php echo esc_html((string) (int) $coverage['latest_data_year']); ?></dd>
                </div>
            <?php endif; ?>
        </dl>
        <?php
    }

    /** Render the most represented makes from verified catalogue records. */
    public function render_popular_brands()
    {
        $brands = $this->get_popular_brands();
        if (empty($brands)) {
            return;
        }
        ?>
        <ul class="alx-live-brands">
            <?php foreach ($brands as $brand) : ?>
                <?php
                $name = isset($brand['make']) ? trim((string) $brand['make']) : '';
                if ($name === '') {
                    continue;
                }
                $url = add_query_arg('brand', $name, home_url('/autok/'));
                ?>
                <li>
                    <a href="<?php echo esc_url($url); ?>">
                        <span><?php echo esc_html($name); ?></span>
                        <small>
                            <?php
                            printf(
                                esc_html__('%s változat', 'autolex-platform'),
                                esc_html(number_format_i18n((int) ($brand['variants'] ?? 0)))
                            );
                            ?>
                        </small>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /** Render five truthful, database-backed public metrics. */
    public function render_metric_strip()
    {
        $coverage = $this->get_catalogue_coverage();
        if (!$this->has_catalogue_data($coverage)) {
            return;
        }

        $engine = $this->get_engine_coverage();
        $metrics = array(
            __('Márkák', 'autolex-platform')              => (int) $coverage['makes'],
            __('Modellek', 'autolex-platform')            => (int) $coverage['models'],
            __('Járműváltozatok', 'autolex-platform')     => (int) $coverage['vehicles'],
            __('Ellenőrzött motorok', 'autolex-platform') => (int) ($engine['verified_variants'] ?? 0),
            __('Forrásrekordok', 'autolex-platform')      => (int) ($coverage['source_observations'] ?? 0),
        );

        foreach ($metrics as $label => $value) {
            ?>
            <span class="alx-live-metric">
                <strong><?php echo esc_html(number_format_i18n($value)); ?></strong>
                <small><?php echo esc_html($label); ?></small>
            </span>
            <?php
        }
    }

    /** @return array<string,mixed> */
    private function get_catalogue_coverage()
    {
        if (!class_exists('Autolex_EU_Catalog')) {
            return array();
        }

        $coverage = Autolex_EU_Catalog::instance()->get_coverage();
        return is_array($coverage) ? $coverage : array();
    }

    /** @return array<string,mixed> */
    private function get_engine_coverage()
    {
        if (!class_exists('Autolex_Engine_Catalog')) {
            return array();
        }

        $coverage = Autolex_Engine_Catalog::instance()->get_coverage();
        return is_array($coverage) ? $coverage : array();
    }

    /** @param array<string,mixed> $coverage */
    private function has_catalogue_data($coverage)
    {
        return !empty($coverage['vehicles']) || !empty($coverage['makes']) || !empty($coverage['models']);
    }

    /** @return array<int,array<string,mixed>> */
    private function get_popular_brands()
    {
        global $wpdb;

        if (!class_exists('Autolex_EU_Catalog')) {
            return array();
        }

        if (Autolex_EU_Catalog::SCHEMA_VERSION !== get_option('autolex_eu_schema_version')) {
            return array();
        }

        $table = Autolex_EU_Catalog::vehicles_table();
        $rows = $wpdb->get_results(
            "SELECT make,
                COUNT(*) AS variants,
                COALESCE(SUM(registration_count), 0) AS registrations
            FROM {$table}
            WHERE make <> ''
            GROUP BY make
            ORDER BY registrations DESC, variants DESC, make ASC
            LIMIT 6",
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    private function __construct()
    {
    }
}
