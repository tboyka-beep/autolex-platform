<?php
/**
 * Fail-closed importer for official EU Safety Gate vehicle alerts.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Safety_Gate
{
    const SCHEMA_VERSION = '1.0.0';
    const DATASET_APIS = array(
        'https://data.europa.eu/api/hub/search/datasets/rapex-rapid-alert-system-non-food',
        'https://data.europa.eu/api/hub/repo/datasets/rapex-rapid-alert-system-non-food/distributions?valueType=metadata',
    );
    const MAX_XML_BYTES = 20971520;

    /** @var Autolex_Safety_Gate|null */
    private static $instance = null;

    /** @return Autolex_Safety_Gate */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** @return string */
    public static function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'autolex_safety_gate_alerts';
    }

    /** Installs the normalized recall table. */
    public static function install_schema()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta(
            "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                fingerprint char(64) NOT NULL,
                reference_no varchar(120) NOT NULL DEFAULT '',
                notified_at date DEFAULT NULL,
                category varchar(191) NOT NULL DEFAULT '',
                product_name varchar(191) NOT NULL DEFAULT '',
                brand varchar(191) NOT NULL DEFAULT '',
                model varchar(191) NOT NULL DEFAULT '',
                type_number varchar(191) NOT NULL DEFAULT '',
                risk_type varchar(191) NOT NULL DEFAULT '',
                risk_description text DEFAULT NULL,
                measures text DEFAULT NULL,
                notifying_country varchar(120) NOT NULL DEFAULT '',
                source_url text NOT NULL,
                source_hash char(64) NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY fingerprint (fingerprint),
                KEY brand_model (brand(80), model(80)),
                KEY notified_at (notified_at),
                KEY reference_no (reference_no)
            ) {$charset};"
        );
        update_option('autolex_safety_gate_schema_version', self::SCHEMA_VERSION, false);
    }

    /**
     * Finds the most likely official XML distribution in data.europa metadata.
     * Pure helper used by tests.
     *
     * @param mixed $metadata Decoded JSON metadata.
     * @return string
     */
    public static function discover_xml_url($metadata)
    {
        $candidates = array();
        $walk = static function ($value, $path = '', $context = '') use (&$walk, &$candidates) {
            if (is_array($value)) {
                $local = $context;
                foreach ($value as $key => $child) {
                    if (is_scalar($child) && preg_match('/title|format|description|label|name/i', (string) $key)) {
                        $local .= ' ' . (string) $child;
                    }
                }
                foreach ($value as $key => $child) {
                    $walk($child, $path . '/' . (string) $key, $local);
                }
                return;
            }
            if (!is_string($value) || 0 !== strpos($value, 'https://')) {
                return;
            }
            $text = strtolower($path . ' ' . $context . ' ' . $value);
            $score = 0;
            if (false !== strpos($text, 'xml')) {
                $score += 8;
            }
            if (preg_match('/\.xml(?:\?|$)/i', $value)) {
                $score += 10;
            }
            if (false !== strpos($text, 'weekly')) {
                $score += 4;
            }
            if (false !== strpos($text, 'safety') || false !== strpos($text, 'rapex')) {
                $score += 3;
            }
            if (preg_match('/download|access|distribution|url/i', $path)) {
                $score += 2;
            }
            $host = strtolower((string) wp_parse_url($value, PHP_URL_HOST));
            if (in_array($host, array('data.europa.eu', 'ec.europa.eu', 'webgate.ec.europa.eu'), true)) {
                $score += 5;
            } else {
                $score -= 20;
            }
            if ($score > 0) {
                $candidates[] = array('url' => $value, 'score' => $score);
            }
        };
        $walk($metadata);
        usort($candidates, static function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        return $candidates ? (string) $candidates[0]['url'] : '';
    }

    /**
     * Normalizes an alert-like associative map. Pure helper used by tests.
     *
     * @param array<string,mixed> $fields Flattened alert fields.
     * @param string              $source_url Official source URL.
     * @return array<string,mixed>|false
     */
    public static function normalize_alert($fields, $source_url = 'https://ec.europa.eu/safety-gate/')
    {
        $normalized = array();
        foreach ($fields as $key => $value) {
            $clean_key = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', (string) $key));
            $text = trim(wp_strip_all_tags(is_scalar($value) ? (string) $value : ''));
            if ('' !== $clean_key && '' !== $text && !isset($normalized[$clean_key])) {
                $normalized[$clean_key] = $text;
            }
        }
        $pick = static function ($aliases) use ($normalized) {
            foreach ($aliases as $alias) {
                $alias = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $alias));
                if (isset($normalized[$alias])) {
                    return $normalized[$alias];
                }
            }
            foreach ($normalized as $key => $value) {
                foreach ($aliases as $alias) {
                    $alias = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $alias));
                    if (strlen($alias) >= 4 && false !== strpos($key, $alias)) {
                        return $value;
                    }
                }
            }
            return '';
        };

        $alert = array(
            'reference_no'      => $pick(array('reference', 'reference_no', 'alertnumber', 'notificationnumber', 'rapexnumber')),
            'notified_at'       => $pick(array('notificationdate', 'publisheddate', 'date', 'weekdate')),
            'category'          => $pick(array('productcategory', 'category', 'producttype')),
            'product_name'      => $pick(array('productname', 'product', 'description')),
            'brand'             => $pick(array('brand', 'make', 'trademark')),
            'model'             => $pick(array('model', 'modelname', 'commercialname')),
            'type_number'       => $pick(array('typenumber', 'typeapproval', 'vehicletype', 'batchnumber', 'barcode')),
            'risk_type'         => $pick(array('risktype', 'risk', 'riskcategory')),
            'risk_description'  => $pick(array('riskdescription', 'riskdetails', 'descriptionofrisk')),
            'measures'          => $pick(array('measures', 'measure', 'action', 'compulsorymeasures', 'voluntarymeasures')),
            'notifying_country' => $pick(array('notifyingcountry', 'country', 'countryoforigin')),
            'source_url'        => $source_url,
        );

        if (!self::is_vehicle_alert($alert)) {
            return false;
        }
        foreach (array('reference_no', 'category', 'product_name', 'brand', 'model', 'type_number', 'risk_type', 'notifying_country') as $key) {
            $alert[$key] = self::limit_text($alert[$key], 191);
        }
        $alert['reference_no'] = self::limit_text($alert['reference_no'], 120);
        $alert['notifying_country'] = self::limit_text($alert['notifying_country'], 120);
        $alert['risk_description'] = self::limit_text($alert['risk_description'], 60000);
        $alert['measures'] = self::limit_text($alert['measures'], 60000);
        $date = strtotime((string) $alert['notified_at']);
        $alert['notified_at'] = $date ? gmdate('Y-m-d', $date) : null;
        $identity = $alert['reference_no']
            ? 'safety-gate|' . strtolower($alert['reference_no'])
            : strtolower(implode('|', array(
                $alert['brand'], $alert['model'], $alert['type_number'],
                $alert['risk_type'], $alert['notified_at'] ?: '',
            )));
        $alert['fingerprint'] = hash('sha256', $identity);
        return $alert;
    }

    /** @param array<string,mixed> $alert Alert. @return bool */
    public static function is_vehicle_alert($alert)
    {
        $category = strtolower((string) ($alert['category'] ?? ''));
        $product  = strtolower((string) ($alert['product_name'] ?? ''));
        $type     = strtolower((string) ($alert['type_number'] ?? ''));
        $haystack = implode(' ', array($category, $product, $type));

        // Safety Gate also contains toy cars and scale models. Reject these
        // explicitly before matching vehicle terminology.
        if (preg_match('/\btoy(?:s)?\b|miniature|scale model|model toy/i', $haystack)) {
            return false;
        }

        $vehicle = preg_match(
            '/motor vehicles?|passenger (?:cars?|vehicles?)|commercial vehicles?|vehicle components?|automotive|automobiles?|motorcycles?|mopeds?|vans?|lorr(?:y|ies)|trucks?|buses?|coaches?|jármű|személygépkocsi/i',
            $haystack
        );
        $identity = '' !== trim((string) ($alert['brand'] ?? '')) || '' !== trim((string) ($alert['model'] ?? ''));
        return (bool) $vehicle && $identity;
    }

    /** @param string $value Text. @param int $length Length. @return string */
    private static function limit_text($value, $length)
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }

    /** Ensures the schema and weekly schedule. */
    public function maybe_schedule()
    {
        if (self::SCHEMA_VERSION !== get_option('autolex_safety_gate_schema_version')) {
            self::install_schema();
        }
        if (!wp_next_scheduled('autolex_safety_gate_sync')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', 'autolex_safety_gate_sync');
        }
    }

    /** Downloads and imports one official weekly XML snapshot. */
    public function sync()
    {
        if (!add_option('autolex_safety_gate_lock', time(), '', false)) {
            return;
        }
        try {
            $xml_url = '';
            $metadata_errors = array();
            foreach (self::DATASET_APIS as $metadata_url) {
                try {
                    $candidate = self::discover_xml_url($this->fetch_json($metadata_url));
                    if ($this->is_allowed_url($candidate)) {
                        $xml_url = $candidate;
                        break;
                    }
                    $metadata_errors[] = 'No XML distribution in ' . $metadata_url;
                } catch (Throwable $exception) {
                    $metadata_errors[] = $exception->getMessage();
                }
            }
            if (!$xml_url) {
                throw new RuntimeException(
                    'Safety Gate XML distribution was not found on an allowlisted EU host. ' .
                    self::limit_text(implode(' | ', $metadata_errors), 700)
                );
            }
            $xml = $this->fetch_text($xml_url, self::MAX_XML_BYTES);
            $alerts = $this->parse_xml($xml, $xml_url);
            $imported = $this->upsert_alerts($alerts, $xml_url, hash('sha256', $xml));
            update_option('autolex_safety_gate_last_sync', time(), false);
            update_option('autolex_safety_gate_last_source_url', esc_url_raw($xml_url), false);
            update_option('autolex_safety_gate_last_imported', $imported, false);
            delete_option('autolex_safety_gate_last_error');
        } catch (Throwable $exception) {
            update_option('autolex_safety_gate_last_error', self::limit_text($exception->getMessage(), 1000), false);
        } finally {
            delete_option('autolex_safety_gate_lock');
        }
    }

    /** @param string $url URL. @return array<string,mixed> */
    private function fetch_json($url)
    {
        $body = $this->fetch_text($url, 4 * MB_IN_BYTES, array('Accept' => 'application/json'));
        $data = json_decode($body, true);
        if (!is_array($data) || JSON_ERROR_NONE !== json_last_error()) {
            throw new RuntimeException('data.europa returned invalid Safety Gate metadata.');
        }
        return $data;
    }

    /** @param string $url URL. @param int $limit Byte limit. @param array<string,string> $headers Headers. @return string */
    private function fetch_text($url, $limit, $headers = array())
    {
        $response = wp_safe_remote_get($url, array(
            'timeout' => 45,
            'redirection' => 3,
            'reject_unsafe_urls' => true,
            'limit_response_size' => $limit,
            'user-agent' => 'Autolex-Platform/' . AUTOLEX_PLATFORM_VERSION . ' (+https://autolex.hu/)',
            'headers' => $headers,
        ));
        if (is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $status) {
            throw new RuntimeException('Official Safety Gate source returned HTTP ' . $status . '.');
        }
        $body = (string) wp_remote_retrieve_body($response);
        if ('' === trim($body)) {
            throw new RuntimeException('Official Safety Gate source returned an empty response.');
        }
        return $body;
    }

    /** @param string $url URL. @return bool */
    private function is_allowed_url($url)
    {
        if (!is_string($url) || 0 !== strpos($url, 'https://')) {
            return false;
        }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        return in_array($host, array('data.europa.eu', 'ec.europa.eu', 'webgate.ec.europa.eu'), true);
    }

    /** @param string $xml XML. @param string $source_url Source. @return array<int,array<string,mixed>> */
    private function parse_xml($xml, $source_url)
    {
        if (!function_exists('simplexml_load_string')) {
            throw new RuntimeException('The PHP SimpleXML extension is required for Safety Gate imports.');
        }
        $previous = libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$root) {
            throw new RuntimeException('Safety Gate returned invalid XML.');
        }
        $alerts = array();
        foreach ($root->xpath('//*') ?: array() as $node) {
            if (count($node->children()) < 4) {
                continue;
            }
            $fields = $this->flatten_node($node, 0);
            $alert = self::normalize_alert($fields, $source_url);
            if ($alert) {
                $alerts[$alert['fingerprint']] = $alert;
            }
        }
        if (!$alerts) {
            throw new RuntimeException('No vehicle alerts were recognized in the current Safety Gate XML schema.');
        }
        return array_values($alerts);
    }

    /** @param SimpleXMLElement $node Node. @param int $depth Depth. @return array<string,string> */
    private function flatten_node($node, $depth)
    {
        $fields = array();
        if ($depth > 3) {
            return $fields;
        }
        foreach ($node->children() as $name => $child) {
            $key = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', (string) $name));
            $text = trim((string) $child);
            if ('' !== $key && '' !== $text && !isset($fields[$key])) {
                $fields[$key] = $text;
            }
            if (count($child->children())) {
                $fields = array_merge($fields, $this->flatten_node($child, $depth + 1));
            }
        }
        return $fields;
    }

    /** @param array<int,array<string,mixed>> $alerts Alerts. @param string $url URL. @param string $hash Hash. @return int */
    private function upsert_alerts($alerts, $url, $hash)
    {
        global $wpdb;
        $table = self::table();
        $now = current_time('mysql', true);
        $count = 0;
        foreach ($alerts as $alert) {
            $data = array_merge($alert, array(
                'source_url' => esc_url_raw($url),
                'source_hash' => $hash,
                'updated_at' => $now,
            ));
            $existing = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE fingerprint = %s", $alert['fingerprint']));
            if ($existing) {
                $wpdb->update($table, $data, array('id' => $existing));
            } else {
                $data['created_at'] = $now;
                $wpdb->insert($table, $data);
            }
            if ($wpdb->last_error) {
                throw new RuntimeException('Safety Gate database write failed: ' . $wpdb->last_error);
            }
            ++$count;
        }
        return $count;
    }

    /** @return array<string,mixed> */
    public function get_status()
    {
        global $wpdb;
        $total = self::SCHEMA_VERSION === get_option('autolex_safety_gate_schema_version')
            ? (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::table())
            : 0;
        return array(
            'service' => 'autolex-safety-gate',
            'status' => get_option('autolex_safety_gate_last_error') ? 'attention' : 'ok',
            'alerts' => $total,
            'last_sync_at' => ($ts = (int) get_option('autolex_safety_gate_last_sync', 0)) ? gmdate('c', $ts) : null,
            'last_imported' => (int) get_option('autolex_safety_gate_last_imported', 0),
            'last_error' => (string) get_option('autolex_safety_gate_last_error', ''),
            'source_url' => (string) get_option('autolex_safety_gate_last_source_url', ''),
            'policy' => 'official_eu_xml_fail_closed',
            'generated_at' => gmdate('c'),
        );
    }

    /** @return WP_REST_Response */
    public function get_status_response()
    {
        return rest_ensure_response($this->get_status());
    }

    /** @param WP_REST_Request $request Request. @return WP_REST_Response */
    public function get_recalls_response(WP_REST_Request $request)
    {
        global $wpdb;
        if (self::SCHEMA_VERSION !== get_option('autolex_safety_gate_schema_version')) {
            return rest_ensure_response(array('items' => array(), 'total' => 0, 'generated_at' => gmdate('c')));
        }
        $make = trim((string) $request->get_param('make'));
        $model = trim((string) $request->get_param('model'));
        $limit = min(100, max(1, (int) $request->get_param('limit')));
        $where = array('1=1');
        $params = array();
        if ('' !== $make) {
            $where[] = 'LOWER(brand) LIKE %s';
            $params[] = '%' . $wpdb->esc_like(strtolower($make)) . '%';
        }
        if ('' !== $model) {
            $where[] = 'LOWER(CONCAT_WS(\' \', model, product_name, type_number)) LIKE %s';
            $params[] = '%' . $wpdb->esc_like(strtolower($model)) . '%';
        }
        $sql = 'SELECT reference_no, notified_at, category, product_name, brand, model, type_number, risk_type, risk_description, measures, notifying_country, source_url FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY notified_at DESC, id DESC LIMIT %d';
        $params[] = $limit;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        $response = rest_ensure_response(array('items' => (array) $rows, 'total' => count((array) $rows), 'generated_at' => gmdate('c')));
        $response->header('Cache-Control', 'public, max-age=900, stale-while-revalidate=3600');
        return $response;
    }

    /** Registers hooks. */
    private function __construct()
    {
        add_action('init', array($this, 'maybe_schedule'), 9);
        add_action('autolex_safety_gate_sync', array($this, 'sync'));
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /** @return void */
    public function register_routes()
    {
        register_rest_route('autolex/v1', '/safety-gate-status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_status_response'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route('autolex/v1', '/recalls', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_recalls_response'),
            'permission_callback' => '__return_true',
            'args' => array(
                'make' => array('sanitize_callback' => 'sanitize_text_field'),
                'model' => array('sanitize_callback' => 'sanitize_text_field'),
                'limit' => array('sanitize_callback' => 'absint', 'default' => 25),
            ),
        ));
    }
}
