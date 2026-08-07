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
        add_action('autolex_theme_featured_vehicle', array($this, 'render_featured_vehicle'));
        add_action('autolex_theme_comparison_preview', array($this, 'render_comparison_preview'));
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
                        <?php echo $this->render_brand_mark($name); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled mapping + escaped fallback. ?>
                        <span class="alx-live-brand-copy">
                            <strong><?php echo esc_html($name); ?></strong>
                            <small>
                                <?php
                                printf(
                                    esc_html__('%s változat', 'autolex-platform'),
                                    esc_html(number_format_i18n((int) ($brand['variants'] ?? 0)))
                                );
                                ?>
                            </small>
                        </span>
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

    /** Render one real, highly represented catalogue record for the featured card. */
    public function render_featured_vehicle()
    {
        $vehicle = $this->get_featured_vehicle();
        if (empty($vehicle)) {
            return;
        }

        $name = trim((string) ($vehicle['make'] ?? '') . ' ' . (string) ($vehicle['model'] ?? ''));
        if ($name === '') {
            return;
        }

        $url = add_query_arg(
            array(
                'brand' => (string) ($vehicle['make'] ?? ''),
                'model' => (string) ($vehicle['model'] ?? ''),
            ),
            home_url('/autok/')
        );

        $specs = array();
        if (!empty($vehicle['engine_capacity_cc'])) {
            $specs[__('Motor', 'autolex-platform')] = number_format_i18n((int) $vehicle['engine_capacity_cc']) . ' cm³';
        }
        if (!empty($vehicle['engine_power_kw'])) {
            $specs[__('Teljesítmény', 'autolex-platform')] = number_format_i18n((float) $vehicle['engine_power_kw'], 0) . ' kW';
        }
        if (!empty($vehicle['fuel_type'])) {
            $specs[__('Üzemanyag', 'autolex-platform')] = (string) $vehicle['fuel_type'];
        }
        if (!empty($vehicle['last_seen_year'])) {
            $specs[__('Adatév', 'autolex-platform')] = (string) (int) $vehicle['last_seen_year'];
        }
        ?>
        <div class="alx-featured-data">
            <h2><?php echo esc_html($name); ?></h2>
            <p><?php esc_html_e('Valós EU/EGT katalógusrekord, forrásolt műszaki adatokkal.', 'autolex-platform'); ?></p>
            <?php if ($specs) : ?>
                <div class="alx-featured-stats">
                    <?php foreach ($specs as $label => $value) : ?>
                        <span><strong><?php echo esc_html($value); ?></strong><small><?php echo esc_html($label); ?></small></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a class="alx-card-action" href="<?php echo esc_url($url); ?>"><?php esc_html_e('Részletek megtekintése', 'autolex-platform'); ?> →</a>
        </div>
        <?php
    }

    /** Render a truthful two-vehicle comparison preview from catalogue rows. */
    public function render_comparison_preview()
    {
        $vehicles = $this->get_comparison_vehicles();
        if (count($vehicles) < 2) {
            return;
        }

        $left = $vehicles[0];
        $right = $vehicles[1];
        $left_name = trim((string) ($left['make'] ?? '') . ' ' . (string) ($left['model'] ?? ''));
        $right_name = trim((string) ($right['make'] ?? '') . ' ' . (string) ($right['model'] ?? ''));
        if ($left_name === '' || $right_name === '') {
            return;
        }

        $rows = array(
            __('Teljesítmény', 'autolex-platform') => array(
                $this->format_kw($left['engine_power_kw'] ?? null),
                $this->format_kw($right['engine_power_kw'] ?? null),
            ),
            __('Motor', 'autolex-platform') => array(
                $this->format_cc($left['engine_capacity_cc'] ?? null),
                $this->format_cc($right['engine_capacity_cc'] ?? null),
            ),
            __('CO₂ (WLTP)', 'autolex-platform') => array(
                $this->format_co2($left['co2_wltp'] ?? null),
                $this->format_co2($right['co2_wltp'] ?? null),
            ),
        );
        ?>
        <div class="alx-compare-data" data-alx-real-comparison="true">
            <div class="alx-compare-names">
                <strong><?php echo esc_html($left_name); ?></strong>
                <span aria-hidden="true">VS.</span>
                <strong><?php echo esc_html($right_name); ?></strong>
            </div>
            <dl class="alx-compare-lines">
                <?php foreach ($rows as $label => $values) : ?>
                    <div>
                        <dt><?php echo esc_html($label); ?></dt>
                        <dd><?php echo esc_html($values[0]); ?> / <?php echo esc_html($values[1]); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
        <?php
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

    /** @return array<string,mixed> */
    private function get_featured_vehicle()
    {
        global $wpdb;

        if (!class_exists('Autolex_EU_Catalog') || Autolex_EU_Catalog::SCHEMA_VERSION !== get_option('autolex_eu_schema_version')) {
            return array();
        }

        $table = Autolex_EU_Catalog::vehicles_table();
        $row = $wpdb->get_row(
            "SELECT id, make, model, variant, version, fuel_type, engine_capacity_cc, engine_power_kw, co2_wltp, last_seen_year, registration_count
             FROM {$table}
             WHERE make <> '' AND model <> ''
             ORDER BY registration_count DESC, last_seen_year DESC, id ASC
             LIMIT 1",
            ARRAY_A
        );

        return is_array($row) ? $row : array();
    }

    /** @return array<int,array<string,mixed>> */
    private function get_comparison_vehicles()
    {
        global $wpdb;

        if (!class_exists('Autolex_EU_Catalog') || Autolex_EU_Catalog::SCHEMA_VERSION !== get_option('autolex_eu_schema_version')) {
            return array();
        }

        $table = Autolex_EU_Catalog::vehicles_table();
        $rows = $wpdb->get_results(
            "SELECT id, make, model, engine_capacity_cc, engine_power_kw, co2_wltp, registration_count, last_seen_year
             FROM {$table}
             WHERE make <> '' AND model <> ''
             ORDER BY registration_count DESC, last_seen_year DESC, id ASC
             LIMIT 30",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return array();
        }

        $picked = array();
        $seen_makes = array();
        foreach ($rows as $row) {
            $make = trim((string) ($row['make'] ?? ''));
            if ($make === '' || isset($seen_makes[$make])) {
                continue;
            }
            $seen_makes[$make] = true;
            $picked[] = $row;
            if (count($picked) === 2) {
                break;
            }
        }

        return $picked;
    }

    /** @return string */
    private function render_brand_mark($name)
    {
        $key = strtolower(remove_accents((string) $name));
        $key = str_replace(array(' ', '-', '_'), '', $key);
        $logos = array(
            'bmw'          => 'https://cdn.simpleicons.org/bmw/0066B1',
            'mercedesbenz' => 'https://cdn.simpleicons.org/mercedes/111827',
            'audi'         => 'https://cdn.simpleicons.org/audi/BB0A30',
            'volkswagen'   => 'https://cdn.simpleicons.org/volkswagen/001E50',
            'toyota'       => 'https://cdn.simpleicons.org/toyota/EB0A1E',
            'honda'        => 'https://cdn.simpleicons.org/honda/E40521',
            'ford'         => 'https://cdn.simpleicons.org/ford/003478',
            'skoda'        => 'https://cdn.simpleicons.org/skoda/0E3A2F',
        );

        if (isset($logos[$key])) {
            return sprintf(
                '<span class="alx-brand-logo"><img src="%1$s" alt="" width="17" height="17" loading="lazy" decoding="async"></span>',
                esc_url($logos[$key])
            );
        }

        return sprintf(
            '<span class="alx-brand-logo alx-brand-logo--fallback">%s</span>',
            esc_html(mb_substr((string) $name, 0, 1))
        );
    }

    /** @param mixed $value */
    private function format_kw($value)
    {
        return is_numeric($value) && (float) $value > 0 ? number_format_i18n((float) $value, 0) . ' kW' : __('n.a.', 'autolex-platform');
    }

    /** @param mixed $value */
    private function format_cc($value)
    {
        return is_numeric($value) && (float) $value > 0 ? number_format_i18n((int) $value) . ' cm³' : __('n.a.', 'autolex-platform');
    }

    /** @param mixed $value */
    private function format_co2($value)
    {
        return is_numeric($value) && (float) $value > 0 ? number_format_i18n((float) $value, 0) . ' g/km' : __('n.a.', 'autolex-platform');
    }

    private function __construct()
    {
    }
}
