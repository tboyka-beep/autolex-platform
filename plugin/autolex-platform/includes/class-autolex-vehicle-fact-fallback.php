<?php
/**
 * Route-independent, server-rendered factual summary fallback for vehicle detail pages.
 *
 * The normal `the_content` presentation remains the primary renderer. This
 * fallback only injects the same record-backed facts into the final HTML when
 * a production route bypasses that WordPress content-filter path.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Vehicle_Fact_Fallback
{
    /** @var Autolex_Vehicle_Fact_Fallback|null */
    private static $instance = null;

    /** @return Autolex_Vehicle_Fact_Fallback */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('template_redirect', array($this, 'start_buffer'), 2);
    }

    /** Starts a fallback output buffer only for real public vehicle-detail routes. */
    public function start_buffer()
    {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || !$this->is_vehicle_detail_request()) {
            return;
        }
        ob_start(array($this, 'inject_vehicle_summary'));
    }

    /**
     * Adds the record-backed summary only when another renderer has not already
     * emitted it. Unknown/missing records fail closed and leave the HTML intact.
     *
     * @param string $html Complete server-rendered HTML.
     * @return string
     */
    public function inject_vehicle_summary($html)
    {
        $html = (string) $html;
        if (false !== strpos($html, 'data-autolex-public-facts="true"')) {
            return $html;
        }

        $vehicle_id = $this->vehicle_id_from_request();
        if ($vehicle_id <= 0) {
            return $html;
        }
        $vehicle = $this->get_vehicle_record($vehicle_id);
        if (!$vehicle || empty($vehicle['make']) || empty($vehicle['model'])) {
            return $html;
        }

        $summary = $this->render_summary($vehicle);
        if ('' === $summary) {
            return $html;
        }

        return self::inject_summary_html($html, $summary);
    }

    /**
     * Pure HTML insertion helper used by regression tests as well as runtime.
     *
     * @param string $html    Page HTML.
     * @param string $summary Trusted server-generated summary HTML.
     * @return string
     */
    public static function inject_summary_html($html, $summary)
    {
        $html = (string) $html;
        $summary = trim((string) $summary);
        if (
            '' === $summary
            || false !== strpos($html, 'data-autolex-public-facts="true"')
            || false === strpos($summary, 'data-autolex-public-facts="true"')
        ) {
            return $html;
        }

        foreach (array('</main>', '</body>') as $anchor) {
            $position = stripos($html, $anchor);
            if (false !== $position) {
                return substr($html, 0, $position)
                    . "\n" . $summary . "\n"
                    . substr($html, $position);
            }
        }

        // A malformed/non-document response is not a safe injection target.
        return $html;
    }

    /**
     * @param array<string,mixed> $vehicle Vehicle row.
     * @return string
     */
    private function render_summary($vehicle)
    {
        if (!class_exists('Autolex_Public_Presentation')) {
            return '';
        }
        $facts = Autolex_Public_Presentation::build_vehicle_facts($vehicle);
        if (!$facts) {
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
        <section class="alxbc-section alx3-detail-section alx-public-facts" data-autolex-public-facts="true" data-autolex-public-facts-fallback="true" aria-labelledby="alx-public-facts-title">
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
        return trim((string) ob_get_clean());
    }

    /** @return bool */
    private function is_vehicle_detail_request()
    {
        return 1 === preg_match(
            '~/auto-adatlap/\d+(?:/|$)~',
            (string) ($_SERVER['REQUEST_URI'] ?? '')
        );
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

        $fields = array(
            'id', 'make', 'model', 'generation', 'engine', 'engine_code',
            'fuel_type', 'capacity_cc', 'power_kw', 'power_ps', 'year_from', 'year_to',
        );
        $select = array();
        foreach ($fields as $field) {
            $select[] = !empty($map[$field])
                ? '`' . $map[$field] . '` AS `' . $field . '`'
                : "'' AS `{$field}`";
        }
        $sql = 'SELECT ' . implode(', ', $select)
            . ' FROM `' . $map['table'] . '` WHERE `' . $map['id'] . '` = %d LIMIT 1';
        $row = $wpdb->get_row(
            $wpdb->prepare($sql, $vehicle_id), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ARRAY_A
        );
        return is_array($row) ? $row : array();
    }
}
