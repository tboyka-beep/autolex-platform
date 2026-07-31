<?php
/**
 * Shareable, server-rendered comparison for up to three legacy catalogue vehicles.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Vehicle_Comparison
{
    /** @var Autolex_Vehicle_Comparison|null */
    private static $instance = null;

    /** @return Autolex_Vehicle_Comparison */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 125);
    }

    /** @return bool */
    public function is_comparison_request()
    {
        return isset($_GET['compare']) && '1' === (string) wp_unslash($_GET['compare']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    /** @return void */
    public function enqueue_assets()
    {
        if (is_admin() || !is_page('autok') || !$this->is_comparison_request()) {
            return;
        }
        wp_enqueue_style(
            'autolex-vehicle-comparison',
            plugins_url('assets/css/autolex-vehicle-comparison.css', AUTOLEX_PLATFORM_FILE),
            array('autolex-portal-3'),
            AUTOLEX_PLATFORM_VERSION
        );
    }

    /** @return int[] */
    public static function normalize_ids($raw)
    {
        $parts = is_array($raw) ? $raw : preg_split('/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        $ids = array();
        foreach ((array) $parts as $part) {
            $id = absint($part);
            if ($id && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
            if (3 === count($ids)) {
                break;
            }
        }
        return $ids;
    }

    /** @return string */
    public function render()
    {
        $raw = isset($_GET['vehicles']) ? wp_unslash($_GET['vehicles']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $ids = self::normalize_ids($raw);
        $vehicles = $this->load_vehicles($ids);

        ob_start();
        ?>
        <main class="alx3-compare" aria-labelledby="alx3-compare-title">
            <nav class="alx3-compare__crumbs" aria-label="<?php echo esc_attr__('Morzsamenü', 'autolex-platform'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html__('Főoldal', 'autolex-platform'); ?></a>
                <span aria-hidden="true">/</span>
                <a href="<?php echo esc_url(home_url('/autok/')); ?>"><?php echo esc_html__('Autók', 'autolex-platform'); ?></a>
                <span aria-hidden="true">/</span>
                <span aria-current="page"><?php echo esc_html__('Összehasonlítás', 'autolex-platform'); ?></span>
            </nav>
            <header class="alx3-compare__hero">
                <div>
                    <span><?php echo esc_html__('JÁRMŰ-ÖSSZEHASONLÍTÁS 1.0', 'autolex-platform'); ?></span>
                    <h1 id="alx3-compare-title"><?php echo esc_html__('Hasonlíts össze legfeljebb három autót', 'autolex-platform'); ?></h1>
                    <p><?php echo esc_html__('A táblázat a katalógusban ténylegesen eltárolt adatokat mutatja. A hiányzó mezőket nem becsüljük, a forrásállapot nem jelent automatikus alkatrész-kompatibilitást.', 'autolex-platform'); ?></p>
                </div>
                <strong><?php echo esc_html(count($vehicles)); ?><small>/3</small></strong>
            </header>

            <form class="alx3-compare__form" action="<?php echo esc_url(home_url('/autok/')); ?>" method="get">
                <input type="hidden" name="compare" value="1">
                <label for="alx3-compare-ids"><?php echo esc_html__('Járműazonosítók', 'autolex-platform'); ?></label>
                <div>
                    <input id="alx3-compare-ids" name="vehicles" value="<?php echo esc_attr(implode(',', $ids)); ?>" inputmode="numeric" pattern="[0-9, ]*" placeholder="<?php echo esc_attr__('például: 12, 48, 105', 'autolex-platform'); ?>">
                    <button type="submit"><?php echo esc_html__('Összehasonlítás', 'autolex-platform'); ?></button>
                </div>
                <small><?php echo esc_html__('Legfeljebb három egyedi katalógusazonosító adható meg. A megnyitott URL megosztható és JavaScript nélkül is működik.', 'autolex-platform'); ?></small>
            </form>

            <?php if (!$ids) : ?>
                <section class="alx3-compare__empty">
                    <h2><?php echo esc_html__('Még nincs kiválasztott jármű', 'autolex-platform'); ?></h2>
                    <p><?php echo esc_html__('Adj meg két vagy három katalógusazonosítót, vagy térj vissza az autókatalógushoz.', 'autolex-platform'); ?></p>
                    <a href="<?php echo esc_url(home_url('/autok/')); ?>"><?php echo esc_html__('Autók böngészése', 'autolex-platform'); ?> →</a>
                </section>
            <?php elseif (!$vehicles) : ?>
                <section class="alx3-compare__empty is-warning" role="status">
                    <h2><?php echo esc_html__('A megadott járművek nem találhatók', 'autolex-platform'); ?></h2>
                    <p><?php echo esc_html__('Ellenőrizd az azonosítókat. A rendszer nem helyettesíti a hiányzó rekordokat hasonló modellekkel.', 'autolex-platform'); ?></p>
                </section>
            <?php else : ?>
                <?php echo wp_kses_post($this->render_table($vehicles, $ids)); ?>
                <p class="alx3-compare__notice"><?php echo esc_html__('Safety Gate találat esetén is szükséges a VIN-alapú gyártói ellenőrzés. A „VIN szükséges” jelzés azt mutatja, hogy legalább egy eltárolt karbantartási állítás nem zárható le pusztán modell vagy motorkód alapján.', 'autolex-platform'); ?></p>
            <?php endif; ?>
        </main>
        <?php
        return (string) ob_get_clean();
    }

    /** @param array<int,array<string,mixed>> $vehicles Vehicles. @param int[] $selected Selected IDs. @return string */
    private function render_table($vehicles, $selected)
    {
        $rows = array(
            'generation'          => __('Generáció', 'autolex-platform'),
            'engine'              => __('Motor', 'autolex-platform'),
            'engine_code'         => __('Motorkód', 'autolex-platform'),
            'fuel_type'           => __('Üzemanyag', 'autolex-platform'),
            'years'               => __('Évjárat', 'autolex-platform'),
            'power'               => __('Teljesítmény', 'autolex-platform'),
            'capacity'            => __('Hengerűrtartalom', 'autolex-platform'),
            'data_grade'          => __('Adatminőség', 'autolex-platform'),
            'verification_status' => __('Forrásellenőrzés', 'autolex-platform'),
            'safety_gate'         => __('Safety Gate', 'autolex-platform'),
            'vin_required'        => __('VIN-köteles állítás', 'autolex-platform'),
        );

        ob_start();
        ?>
        <div class="alx3-compare__table-wrap" tabindex="0" role="region" aria-label="<?php echo esc_attr__('Járművek összehasonlító táblázata', 'autolex-platform'); ?>">
            <table class="alx3-compare__table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Tulajdonság', 'autolex-platform'); ?></th>
                        <?php foreach ($vehicles as $vehicle) : ?>
                            <th scope="col">
                                <span><?php echo esc_html($vehicle['make']); ?></span>
                                <strong><?php echo esc_html(trim($vehicle['model'] . ' ' . $vehicle['generation'])); ?></strong>
                                <a href="<?php echo esc_url($this->remove_url($selected, (int) $vehicle['id'])); ?>" aria-label="<?php echo esc_attr(sprintf(__('%s eltávolítása az összehasonlításból', 'autolex-platform'), trim($vehicle['make'] . ' ' . $vehicle['model']))); ?>"><?php echo esc_html__('Eltávolítás', 'autolex-platform'); ?></a>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $key => $label) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($label); ?></th>
                            <?php foreach ($vehicles as $vehicle) : ?>
                                <td class="is-<?php echo esc_attr($key); ?>"><?php echo wp_kses_post($this->display_value($key, $vehicle)); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @param string $key Key. @param array<string,mixed> $vehicle Vehicle. @return string */
    private function display_value($key, $vehicle)
    {
        if ('safety_gate' === $key) {
            return $vehicle['safety_gate_count']
                ? '<span class="alx3-compare__status is-warning">' . esc_html(sprintf(_n('%d szöveges találat', '%d szöveges találat', $vehicle['safety_gate_count'], 'autolex-platform'), $vehicle['safety_gate_count'])) . '</span>'
                : '<span class="alx3-compare__status is-neutral">' . esc_html__('Nincs eltárolt találat', 'autolex-platform') . '</span>';
        }
        if ('vin_required' === $key) {
            return $vehicle['vin_required']
                ? '<span class="alx3-compare__status is-warning">' . esc_html__('VIN szükséges', 'autolex-platform') . '</span>'
                : '<span class="alx3-compare__status is-neutral">' . esc_html__('Nincs ilyen jelzés', 'autolex-platform') . '</span>';
        }
        if ('data_grade' === $key) {
            return '<span class="alx3-compare__grade is-' . esc_attr(strtolower($vehicle[$key])) . '">' . esc_html($vehicle[$key]) . '</span>';
        }
        if ('verification_status' === $key) {
            $value = $vehicle[$key] ?: __('Importált / nem igazolt', 'autolex-platform');
            return '<span class="alx3-compare__status is-neutral">' . esc_html($value) . '</span>';
        }
        $value = trim((string) ($vehicle[$key] ?? ''));
        return '' !== $value ? esc_html($value) : '<span class="alx3-compare__missing">' . esc_html__('Nincs adat', 'autolex-platform') . '</span>';
    }

    /** @param int[] $selected Selected. @param int $remove Remove. @return string */
    private function remove_url($selected, $remove)
    {
        $remaining = array_values(array_filter($selected, static function ($id) use ($remove) { return $id !== $remove; }));
        return add_query_arg(array('compare' => '1', 'vehicles' => implode(',', $remaining)), home_url('/autok/'));
    }

    /** @param int[] $ids IDs. @return array<int,array<string,mixed>> */
    private function load_vehicles($ids)
    {
        global $wpdb;
        if (!$ids) {
            return array();
        }
        $map = Autolex_Catalog_Browser::instance()->get_legacy_mapping();
        if (!$map || empty($map['table']) || !preg_match('/^[A-Za-z0-9_]+$/', $map['table'])) {
            return array();
        }
        $table = '`' . $map['table'] . '`';
        $field = static function ($key) use ($map) {
            return !empty($map[$key]) && preg_match('/^[A-Za-z0-9_]+$/', $map[$key]) ? '`' . $map[$key] . '`' : '';
        };
        $select = array();
        foreach (array('id','make','model','generation','engine','engine_code','fuel_type','capacity_cc','power_kw','power_ps','year_from','year_to') as $key) {
            $select[] = $field($key) ? $field($key) . ' AS `' . $key . '`' : "'' AS `{$key}`";
        }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = 'SELECT ' . implode(', ', $select) . " FROM {$table} WHERE " . $field('id') . " IN ({$placeholders})";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $ids), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $by_id = array();
        foreach ((array) $rows as $row) {
            $id = absint($row['id']);
            $from = absint($row['year_from']);
            $to = absint($row['year_to']);
            $kw = is_numeric($row['power_kw']) ? round((float) $row['power_kw'], 1) : 0;
            $ps = is_numeric($row['power_ps']) ? round((float) $row['power_ps'], 1) : ($kw ? round($kw * 1.35962) : 0);
            $capacity = absint($row['capacity_cc']);
            $row['years'] = $from ? ($to && $to !== $from ? $from . '–' . $to : (string) $from) : '';
            $row['power'] = $ps ? $ps . ' LE' . ($kw ? ' / ' . $kw . ' kW' : '') : '';
            $row['capacity'] = $capacity ? number_format_i18n($capacity) . ' cm³' : '';
            $row['data_grade'] = Autolex_Portal::calculate_quality_grade($row);
            $row['verification_status'] = $this->verification_status($id);
            $row['safety_gate_count'] = $this->safety_gate_count((string) $row['make'], (string) $row['model']);
            $row['vin_required'] = $this->vin_required($id);
            $by_id[$id] = $row;
        }
        $ordered = array();
        foreach ($ids as $id) {
            if (isset($by_id[$id])) {
                $ordered[] = $by_id[$id];
            }
        }
        return $ordered;
    }

    /** @return string */
    private function verification_status($legacy_id)
    {
        global $wpdb;
        if (!class_exists('Autolex_Engine_Catalog')) {
            return '';
        }
        $links = Autolex_Engine_Catalog::links_table();
        $variants = Autolex_Engine_Catalog::variants_table();
        if (!preg_match('/^[A-Za-z0-9_]+$/', $links) || !preg_match('/^[A-Za-z0-9_]+$/', $variants)) {
            return '';
        }
        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT v.verification_status FROM `{$links}` l INNER JOIN `{$variants}` v ON v.id=l.engine_variant_id WHERE l.legacy_vehicle_id=%d ORDER BY CASE v.verification_status WHEN 'verified' THEN 8 WHEN 'reviewed' THEN 7 WHEN 'vin_required' THEN 6 WHEN 'conflict' THEN 5 WHEN 'proposed' THEN 4 ELSE 1 END DESC LIMIT 1",
            $legacy_id
        ));
    }

    /** @return int */
    private function safety_gate_count($make, $model)
    {
        global $wpdb;
        if (!class_exists('Autolex_Safety_Gate') || Autolex_Safety_Gate::SCHEMA_VERSION !== get_option('autolex_safety_gate_schema_version')) {
            return 0;
        }
        $table = Autolex_Safety_Gate::table();
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return 0;
        }
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE LOWER(brand) LIKE %s AND LOWER(CONCAT_WS(' ',model,product_name,type_number)) LIKE %s",
            '%' . $wpdb->esc_like(strtolower($make)) . '%',
            '%' . $wpdb->esc_like(strtolower($model)) . '%'
        ));
    }

    /** @return bool */
    private function vin_required($legacy_id)
    {
        global $wpdb;
        if (!class_exists('Autolex_Maintenance_Evidence')) {
            return false;
        }
        $table = Autolex_Maintenance_Evidence::claims_table();
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM `{$table}` WHERE legacy_vehicle_id=%d AND status='needs_vin' LIMIT 1", $legacy_id));
    }
}
