<?php
/**
 * Normal WordPress content-filter fallback for record-backed vehicle facts.
 *
 * This intentionally avoids response-wide output buffering. It only prepends a
 * deterministic summary to the queried object's `the_content` output on the
 * virtual /auto-adatlap/<id>/ route when the primary renderer has not already
 * emitted the summary marker.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Vehicle_Fact_Content
{
    /** @var Autolex_Vehicle_Fact_Content|null */
    private static $instance = null;

    /** @return Autolex_Vehicle_Fact_Content */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_filter('the_content', array($this, 'ensure_vehicle_fact_summary'), 76);
    }

    /**
     * @param string $content Existing WordPress content.
     * @return string
     */
    public function ensure_vehicle_fact_summary($content)
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return $content;
        }
        if (false !== strpos((string) $content, 'data-autolex-public-facts="true"')) {
            return $content;
        }

        $vehicle_id = self::vehicle_id_from_uri((string) ($_SERVER['REQUEST_URI'] ?? ''));
        if ($vehicle_id <= 0) {
            return $content;
        }

        // The dedicated page-jarmu.php shell deliberately invokes the_content
        // for the queried backing page. Do not let secondary content queries on
        // the same virtual URL receive the vehicle summary first.
        if (function_exists('get_queried_object_id') && function_exists('get_post')) {
            $queried_id = (int) get_queried_object_id();
            $post = get_post();
            if ($queried_id > 0 && is_object($post) && isset($post->ID) && (int) $post->ID !== $queried_id) {
                return $content;
            }
        }

        $vehicle = $this->get_vehicle_record($vehicle_id);
        if (!$vehicle || empty($vehicle['make']) || empty($vehicle['model'])) {
            return $content;
        }
        if (!class_exists('Autolex_Public_Presentation')) {
            return $content;
        }
        $facts = Autolex_Public_Presentation::build_vehicle_facts($vehicle);
        if (!$facts) {
            return $content;
        }

        return self::render_summary($vehicle, $facts) . $content;
    }

    /** @param string $uri Request URI. @return int */
    public static function vehicle_id_from_uri($uri)
    {
        if (!preg_match('~/auto-adatlap/(\d+)(?:/|$)~', (string) $uri, $match)) {
            return 0;
        }
        return max(0, (int) $match[1]);
    }

    /**
     * @param array<string,mixed> $vehicle Vehicle record.
     * @param array<int,array<string,string>> $facts Fact rows.
     * @return string
     */
    public static function render_summary($vehicle, $facts)
    {
        if (!$facts || empty($vehicle['make']) || empty($vehicle['model'])) {
            return '';
        }
        $title = trim(implode(' ', array_filter(array(
            (string) ($vehicle['make'] ?? ''),
            (string) ($vehicle['model'] ?? ''),
            (string) ($vehicle['generation'] ?? ''),
        ))));
        if ('' === $title) {
            return '';
        }

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
            <p><?php
                printf(
                    esc_html__('A %s alábbi összefoglalója kizárólag az ehhez a katalógusrekordhoz rögzített műszaki mezőkből készül. Hiányzó adatot az Autolex nem becsül és nem talál ki.', 'autolex-platform'),
                    esc_html($title)
                );
            ?></p>
            <dl class="alx-public-facts__grid">
                <?php foreach ($facts as $fact) : ?>
                    <div>
                        <dt><?php echo esc_html((string) ($fact['label'] ?? '')); ?></dt>
                        <dd><?php echo esc_html((string) ($fact['value'] ?? '')); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
        <?php
        return (string) ob_get_clean();
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
