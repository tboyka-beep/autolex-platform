<?php
/**
 * Publishes the most recently refreshed catalogue vehicles on the Autolex home page.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Home_Recent_Updates
{
    /** @var Autolex_Home_Recent_Updates|null */
    private static $instance = null;

    /** @var bool */
    private $registered = false;

    /** @return Autolex_Home_Recent_Updates */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Register the homepage render hook and its scoped stylesheet exactly once. */
    public function register()
    {
        if ($this->registered) {
            return;
        }

        add_action('autolex_theme_recently_updated', array($this, 'render_recently_updated'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 40);

        $this->registered = true;
    }

    /** Load only the small recent-update presentation layer for the Autolex theme. */
    public function enqueue_assets()
    {
        if (is_admin() || !function_exists('get_stylesheet') || get_stylesheet() !== 'autolex-theme') {
            return;
        }

        $relative = 'assets/css/autolex-home-recent-updates.css';
        $absolute = AUTOLEX_PLATFORM_DIR . $relative;
        if (!is_readable($absolute)) {
            return;
        }

        wp_enqueue_style(
            'autolex-home-recent-updates',
            plugins_url($relative, AUTOLEX_PLATFORM_FILE),
            array('autolex-theme-data-bridge'),
            (string) filemtime($absolute)
        );
    }

    /** Render three distinct make/model pairs ordered by their real database update time. */
    public function render_recently_updated()
    {
        $vehicles = $this->get_recent_vehicles();
        if (empty($vehicles)) {
            return;
        }
        ?>
        <div class="alx-recent-grid" data-alx-recent-updates="true">
            <?php foreach ($vehicles as $vehicle) : ?>
                <?php
                $make = trim((string) ($vehicle['make'] ?? ''));
                $model = trim((string) ($vehicle['model'] ?? ''));
                $name = trim($make . ' ' . $model);
                if ($name === '') {
                    continue;
                }

                $edition = trim(implode(' ', array_filter(array(
                    trim((string) ($vehicle['variant'] ?? '')),
                    trim((string) ($vehicle['version'] ?? '')),
                ))));

                $meta = array();
                if ($edition !== '') {
                    $meta[] = $edition;
                }
                if (!empty($vehicle['fuel_type'])) {
                    $meta[] = (string) $vehicle['fuel_type'];
                }
                if (!empty($vehicle['engine_power_kw']) && is_numeric($vehicle['engine_power_kw'])) {
                    $meta[] = number_format_i18n((float) $vehicle['engine_power_kw'], 0) . ' kW';
                }
                if (!empty($vehicle['last_seen_year'])) {
                    $meta[] = (string) (int) $vehicle['last_seen_year'];
                }

                $url = add_query_arg(
                    array(
                        'brand' => $make,
                        'model' => $model,
                    ),
                    home_url('/autok/')
                );
                $updated_at = trim((string) ($vehicle['updated_at'] ?? ''));
                $updated_label = $this->format_updated_at($updated_at);
                $datetime = $updated_at !== '' ? str_replace(' ', 'T', $updated_at) : '';
                ?>
                <a class="alx-recent-item" href="<?php echo esc_url($url); ?>">
                    <span class="alx-recent-icon" aria-hidden="true">↻</span>
                    <span class="alx-recent-copy">
                        <strong><?php echo esc_html($name); ?></strong>
                        <?php if ($meta) : ?><small><?php echo esc_html(implode(' · ', $meta)); ?></small><?php endif; ?>
                        <?php if ($updated_label !== '') : ?>
                            <time class="alx-recent-time" datetime="<?php echo esc_attr($datetime); ?>">
                                <?php
                                printf(
                                    esc_html__('Frissítve: %s', 'autolex-platform'),
                                    esc_html($updated_label)
                                );
                                ?>
                            </time>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /** @return array<int,array<string,mixed>> */
    private function get_recent_vehicles()
    {
        global $wpdb;

        if (!class_exists('Autolex_EU_Catalog') || Autolex_EU_Catalog::SCHEMA_VERSION !== get_option('autolex_eu_schema_version')) {
            return array();
        }

        $table = Autolex_EU_Catalog::vehicles_table();
        $rows = $wpdb->get_results(
            "SELECT id, make, model, variant, version, fuel_type, engine_capacity_cc, engine_power_kw, last_seen_year, updated_at
             FROM {$table}
             WHERE make <> '' AND model <> '' AND updated_at IS NOT NULL AND updated_at <> '0000-00-00 00:00:00'
             ORDER BY updated_at DESC, last_seen_year DESC, id DESC
             LIMIT 24",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return array();
        }

        $picked = array();
        $seen = array();
        foreach ($rows as $row) {
            $make = trim((string) ($row['make'] ?? ''));
            $model = trim((string) ($row['model'] ?? ''));
            if ($make === '' || $model === '') {
                continue;
            }

            $key = strtolower($make . "\0" . $model);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $picked[] = $row;
            if (count($picked) === 3) {
                break;
            }
        }

        return $picked;
    }

    /** @return string */
    private function format_updated_at($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if (!$date) {
            return $value;
        }

        return $date->format('Y.m.d. H:i');
    }

    private function __construct()
    {
    }
}
