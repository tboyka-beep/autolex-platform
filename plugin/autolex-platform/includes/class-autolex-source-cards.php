<?php
/**
 * Public, read-only source and verification cards for Autolex entities.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Source_Cards
{
    /** @var Autolex_Source_Cards|null */
    private static $instance = null;

    /** @return Autolex_Source_Cards */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** @return void */
    public function register()
    {
        add_shortcode('autolex_sources', array($this, 'render_shortcode'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /** @return void */
    public function register_rest_routes()
    {
        register_rest_route(
            'autolex/v1',
            '/sources/(?P<entity_type>[a-z0-9_-]+)/(?P<entity_id>\d+)',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_sources_response'),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'entity_type' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                        'validate_callback' => array(__CLASS__, 'validate_entity_type'),
                    ),
                    'entity_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array(__CLASS__, 'validate_entity_id'),
                    ),
                    'limit' => array(
                        'default'           => 40,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => array(__CLASS__, 'validate_limit'),
                    ),
                ),
            )
        );
    }

    /** @param mixed $value Value. @return bool */
    public static function validate_entity_type($value)
    {
        return in_array(sanitize_key($value), array('vehicle', 'engine', 'generation', 'model', 'market_stat'), true);
    }

    /** @param mixed $value Value. @return bool */
    public static function validate_entity_id($value)
    {
        return absint($value) > 0;
    }

    /** @param mixed $value Value. @return bool */
    public static function validate_limit($value)
    {
        $limit = absint($value);
        return $limit >= 1 && $limit <= 100;
    }

    /** @param WP_REST_Request $request Request. @return WP_REST_Response|WP_Error */
    public function get_sources_response($request)
    {
        $entity_type = sanitize_key($request->get_param('entity_type'));
        $entity_id = absint($request->get_param('entity_id'));
        $limit = absint($request->get_param('limit'));
        $limit = $limit > 0 ? min(100, $limit) : 40;

        if (!self::validate_entity_type($entity_type) || !self::validate_entity_id($entity_id)) {
            return new WP_Error('autolex_invalid_source_entity', __('Érvénytelen forrásentitás.', 'autolex-platform'), array('status' => 400));
        }

        $rows = $this->get_entity_sources($entity_type, $entity_id, $limit);
        if (is_wp_error($rows)) {
            return $rows;
        }

        $response = rest_ensure_response(
            array(
                'entity_type' => $entity_type,
                'entity_id'   => $entity_id,
                'summary'     => self::summarize($rows),
                'sources'     => array_values($rows),
                'generated_at'=> gmdate('c'),
            )
        );
        $response->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60');
        $response->header('X-Autolex-Source-Count', (string) count($rows));
        return $response;
    }

    /** @param array<string,mixed> $atts Attributes. @return string */
    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(array('entity_type' => 'vehicle', 'entity_id' => 0, 'limit' => 40), is_array($atts) ? $atts : array(), 'autolex_sources');
        $entity_type = sanitize_key($atts['entity_type']);
        $entity_id = absint($atts['entity_id']);
        $limit = min(100, max(1, absint($atts['limit'])));

        if (!self::validate_entity_type($entity_type) || 0 === $entity_id) {
            return self::render_state('incomplete', __('Nincs megadható forráskapcsolat ehhez az adatlaphoz.', 'autolex-platform'));
        }

        $rows = $this->get_entity_sources($entity_type, $entity_id, $limit);
        if (is_wp_error($rows)) {
            return self::render_state('source_conflict', __('A források jelenleg nem tölthetők be.', 'autolex-platform'));
        }
        if (!$rows) {
            return self::render_state('incomplete', __('Ehhez az adatlaphoz még nincs mezőszintű forrás rögzítve.', 'autolex-platform'));
        }

        $summary = self::summarize($rows);
        ob_start();
        ?>
        <section class="alxp-source-panel" aria-labelledby="alxp-source-title-<?php echo esc_attr($entity_id); ?>">
            <div class="alxp-source-panel__header">
                <div>
                    <p class="alxp-eyebrow"><?php echo esc_html__('Adatbizonyítás', 'autolex-platform'); ?></p>
                    <h2 id="alxp-source-title-<?php echo esc_attr($entity_id); ?>"><?php echo esc_html__('Források és megerősítés', 'autolex-platform'); ?></h2>
                </div>
                <span class="alxp-source-panel__summary" aria-label="<?php echo esc_attr(sprintf(__('Összesen %d forráskapcsolat', 'autolex-platform'), $summary['claims'])); ?>">
                    <?php echo esc_html(sprintf(__('%d mező', 'autolex-platform'), $summary['claims'])); ?>
                </span>
            </div>
            <div class="alxp-source-statuses" role="list" aria-label="<?php echo esc_attr__('Megerősítési állapotok', 'autolex-platform'); ?>">
                <?php foreach ($summary['statuses'] as $status => $count) : ?>
                    <span role="listitem" class="alxp-source-status" data-source-status="<?php echo esc_attr($status); ?>"><?php echo esc_html(self::status_label($status)); ?> · <?php echo esc_html($count); ?></span>
                <?php endforeach; ?>
            </div>
            <div class="alxp-source-list">
                <?php foreach ($rows as $row) : ?>
                    <article class="alxp-source-card" data-source-status="<?php echo esc_attr($row['verification_status']); ?>">
                        <div class="alxp-source-card__topline">
                            <span class="alxp-source-status" data-source-status="<?php echo esc_attr($row['verification_status']); ?>"><?php echo esc_html(self::status_label($row['verification_status'])); ?></span>
                            <code><?php echo esc_html($row['field_path']); ?></code>
                        </div>
                        <h3><?php echo esc_html($row['title']); ?></h3>
                        <p class="alxp-source-card__publisher"><?php echo esc_html($row['publisher']); ?></p>
                        <dl class="alxp-source-card__meta">
                            <?php if ('' !== $row['document_identifier']) : ?><div><dt><?php echo esc_html__('Dokumentum', 'autolex-platform'); ?></dt><dd><?php echo esc_html($row['document_identifier']); ?></dd></div><?php endif; ?>
                            <div><dt><?php echo esc_html__('Lekérés', 'autolex-platform'); ?></dt><dd><?php echo esc_html(mysql2date('Y-m-d', $row['retrieved_at'], true)); ?></dd></div>
                            <div><dt><?php echo esc_html__('Forrástípus', 'autolex-platform'); ?></dt><dd><?php echo esc_html(self::source_type_label($row['source_type'])); ?></dd></div>
                        </dl>
                        <?php if ('' !== $row['source_url']) : ?>
                            <a class="alxp-source-card__link" href="<?php echo esc_url($row['source_url']); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo esc_html__('Hivatalos forrás megnyitása', 'autolex-platform'); ?><span aria-hidden="true">↗</span></a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    /** @return array<int,array<string,mixed>>|WP_Error */
    public function get_entity_sources($entity_type, $entity_id, $limit = 40)
    {
        global $wpdb;
        $entity_type = sanitize_key($entity_type);
        $entity_id = absint($entity_id);
        $limit = min(100, max(1, absint($limit)));
        if (!self::validate_entity_type($entity_type) || 0 === $entity_id) {
            return new WP_Error('autolex_invalid_source_entity', 'Érvénytelen forrásentitás.');
        }

        $sql = $wpdb->prepare(
            'SELECT c.field_path, c.verification_status, c.source_count, c.conflict_count, s.source_type, s.title, s.publisher, s.source_url, s.document_identifier, s.retrieved_at, e.source_locator
             FROM ' . Autolex_Source_Provenance::claims_table() . ' c
             INNER JOIN ' . Autolex_Source_Provenance::evidence_table() . ' e ON e.claim_id = c.id
             INNER JOIN ' . Autolex_Source_Provenance::sources_table() . ' s ON s.id = e.source_id
             WHERE c.entity_type = %s AND c.entity_id = %d
             ORDER BY c.conflict_count DESC, c.field_path ASC, s.publisher ASC, s.id ASC
             LIMIT %d',
            $entity_type,
            $entity_id,
            $limit
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (null === $rows) {
            return new WP_Error('autolex_source_query_failed', 'A forráslekérdezés sikertelen.');
        }
        return array_map(array(__CLASS__, 'normalize_row'), is_array($rows) ? $rows : array());
    }

    /** @return array<string,mixed> */
    public static function normalize_row(array $row)
    {
        $status = Autolex_Source_Provenance::normalize_status(isset($row['verification_status']) ? $row['verification_status'] : '');
        $url = esc_url_raw(isset($row['source_url']) ? $row['source_url'] : '', array('https'));
        return array(
            'field_path'          => preg_replace('/[^a-zA-Z0-9_.\-]/', '', (string) (isset($row['field_path']) ? $row['field_path'] : '')),
            'verification_status' => $status,
            'source_count'        => absint(isset($row['source_count']) ? $row['source_count'] : 0),
            'conflict_count'      => absint(isset($row['conflict_count']) ? $row['conflict_count'] : 0),
            'source_type'         => sanitize_key(isset($row['source_type']) ? $row['source_type'] : ''),
            'title'               => sanitize_text_field(isset($row['title']) ? $row['title'] : ''),
            'publisher'           => sanitize_text_field(isset($row['publisher']) ? $row['publisher'] : ''),
            'source_url'          => 0 === strpos($url, 'https://') ? $url : '',
            'document_identifier' => sanitize_text_field(isset($row['document_identifier']) ? $row['document_identifier'] : ''),
            'retrieved_at'        => sanitize_text_field(isset($row['retrieved_at']) ? $row['retrieved_at'] : ''),
            'source_locator'      => sanitize_text_field(isset($row['source_locator']) ? $row['source_locator'] : ''),
        );
    }

    /** @return array<string,mixed> */
    public static function summarize(array $rows)
    {
        $statuses = array();
        foreach ($rows as $row) {
            $status = Autolex_Source_Provenance::normalize_status(isset($row['verification_status']) ? $row['verification_status'] : '');
            $statuses[$status] = isset($statuses[$status]) ? $statuses[$status] + 1 : 1;
        }
        ksort($statuses);
        return array('claims' => count($rows), 'statuses' => $statuses);
    }

    /** @return string */
    public static function status_label($status)
    {
        $labels = array(
            Autolex_Source_Provenance::STATUS_MANUFACTURER => __('Gyártói forrás', 'autolex-platform'),
            Autolex_Source_Provenance::STATUS_OFFICIAL => __('Hivatalos nyilvántartás', 'autolex-platform'),
            Autolex_Source_Provenance::STATUS_MULTI_SOURCE => __('Több forrásból egyező', 'autolex-platform'),
            Autolex_Source_Provenance::STATUS_SINGLE_SOURCE => __('Egy forrásból igazolt', 'autolex-platform'),
            Autolex_Source_Provenance::STATUS_CONFLICT => __('Ellentmondó források', 'autolex-platform'),
            Autolex_Source_Provenance::STATUS_INCOMPLETE => __('Hiányos', 'autolex-platform'),
            Autolex_Source_Provenance::STATUS_VIN_REQUIRED => __('VIN alapján ellenőrizendő', 'autolex-platform'),
        );
        return $labels[Autolex_Source_Provenance::normalize_status($status)];
    }

    /** @return string */
    public static function source_type_label($type)
    {
        $labels = array(
            'manufacturer' => __('Gyártói dokumentum', 'autolex-platform'),
            'official_registry' => __('Hivatalos nyilvántartás', 'autolex-platform'),
            'official_statistics' => __('Hivatalos statisztika', 'autolex-platform'),
            'trusted_secondary' => __('Megbízható másodlagos forrás', 'autolex-platform'),
        );
        $type = sanitize_key($type);
        return isset($labels[$type]) ? $labels[$type] : __('Egyéb ellenőrzött forrás', 'autolex-platform');
    }

    /** @return string */
    private static function render_state($status, $message)
    {
        return sprintf(
            '<section class="alxp-source-panel alxp-source-panel--state" data-source-status="%1$s" role="status"><h2>%2$s</h2><p>%3$s</p></section>',
            esc_attr(Autolex_Source_Provenance::normalize_status($status)),
            esc_html__('Források és megerősítés', 'autolex-platform'),
            esc_html($message)
        );
    }

    private function __construct() {}
}
