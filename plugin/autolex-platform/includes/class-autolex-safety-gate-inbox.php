<?php
/**
 * Verified local inbox for Safety Gate payloads acquired by trusted CI.
 *
 * The inbox never performs outbound network requests. A trusted workflow writes
 * an official EU XML payload plus a manifest through the authenticated cPanel
 * channel. WordPress revalidates the source allowlist, size, SHA-256, freshness
 * and XML structure before importing into the existing Safety Gate table.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Safety_Gate_Inbox
{
    const CONTRACT = 'verified_inbox_v1';
    const MANIFEST_FILE = 'manifest.json';
    const IMPORT_HOOK = 'autolex_safety_gate_inbox_import';
    const MAX_MANIFEST_BYTES = 32768;
    const MAX_MANIFEST_AGE = 1209600; // 14 days.
    const MAX_FUTURE_SKEW = 600;

    /** @var Autolex_Safety_Gate_Inbox|null */
    private static $instance = null;

    /** @return Autolex_Safety_Gate_Inbox */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** @return string */
    public static function inbox_dir()
    {
        return rtrim(WP_CONTENT_DIR, '/\\') . '/autolex-safety-gate-inbox';
    }

    /**
     * Pure manifest validator used by production and deterministic tests.
     *
     * @param array<string,mixed> $manifest Manifest.
     * @param int|null            $now Current Unix timestamp.
     * @return array<string,mixed>|WP_Error
     */
    public static function validate_manifest(array $manifest, $now = null)
    {
        $required = array(
            'contract',
            'payload_file',
            'source_url',
            'metadata_source',
            'sha256',
            'bytes',
            'retrieved_at',
            'commit_sha',
            'workflow_run_id',
        );
        foreach ($required as $field) {
            if (!array_key_exists($field, $manifest) || '' === trim((string) $manifest[$field])) {
                return new WP_Error('autolex_safety_inbox_missing_field', 'Missing Safety Gate inbox manifest field: ' . $field);
            }
        }

        if (self::CONTRACT !== (string) $manifest['contract']) {
            return new WP_Error('autolex_safety_inbox_contract_mismatch', 'Unsupported Safety Gate inbox contract.');
        }

        $sha = strtolower(trim((string) $manifest['sha256']));
        if (!preg_match('/^[a-f0-9]{64}$/', $sha)) {
            return new WP_Error('autolex_safety_inbox_invalid_hash', 'Safety Gate payload SHA-256 is invalid.');
        }

        $payload_file = basename((string) $manifest['payload_file']);
        if ($payload_file !== (string) $manifest['payload_file']
            || ('safety-gate-' . $sha . '.xml') !== $payload_file) {
            return new WP_Error('autolex_safety_inbox_invalid_payload_name', 'Safety Gate inbox payload filename is invalid.');
        }

        $bytes = (int) $manifest['bytes'];
        if ($bytes <= 0 || $bytes > Autolex_Safety_Gate::MAX_XML_BYTES) {
            return new WP_Error('autolex_safety_inbox_invalid_size', 'Safety Gate inbox payload size is outside the accepted limit.');
        }

        foreach (array('source_url', 'metadata_source') as $url_field) {
            if (!self::is_allowed_url((string) $manifest[$url_field])) {
                return new WP_Error('autolex_safety_inbox_untrusted_source', 'Safety Gate inbox source is outside the official EU allowlist.');
            }
        }

        $retrieved = strtotime((string) $manifest['retrieved_at']);
        $now = null === $now ? time() : (int) $now;
        if (!$retrieved || $retrieved < ($now - self::MAX_MANIFEST_AGE) || $retrieved > ($now + self::MAX_FUTURE_SKEW)) {
            return new WP_Error('autolex_safety_inbox_stale_manifest', 'Safety Gate inbox manifest retrieval time is stale or invalid.');
        }

        $commit = strtolower(trim((string) $manifest['commit_sha']));
        if (!preg_match('/^[a-f0-9]{40}$/', $commit)) {
            return new WP_Error('autolex_safety_inbox_invalid_commit', 'Safety Gate inbox commit SHA is invalid.');
        }

        $run_id = (int) $manifest['workflow_run_id'];
        if ($run_id <= 0) {
            return new WP_Error('autolex_safety_inbox_invalid_run', 'Safety Gate inbox workflow run id is invalid.');
        }

        return array(
            'contract' => self::CONTRACT,
            'payload_file' => $payload_file,
            'source_url' => esc_url_raw((string) $manifest['source_url'], array('https')),
            'metadata_source' => esc_url_raw((string) $manifest['metadata_source'], array('https')),
            'sha256' => $sha,
            'bytes' => $bytes,
            'retrieved_at' => gmdate('c', $retrieved),
            'commit_sha' => $commit,
            'workflow_run_id' => $run_id,
        );
    }

    /**
     * Verifies bytes and hash before XML parsing.
     *
     * @param array<string,mixed> $manifest Validated manifest.
     * @param string              $xml Payload.
     * @return true|WP_Error
     */
    public static function verify_payload(array $manifest, $xml)
    {
        $xml = (string) $xml;
        if ('' === trim($xml)) {
            return new WP_Error('autolex_safety_inbox_empty_payload', 'Safety Gate inbox payload is empty.');
        }
        if (strlen($xml) !== (int) $manifest['bytes'] || strlen($xml) > Autolex_Safety_Gate::MAX_XML_BYTES) {
            return new WP_Error('autolex_safety_inbox_size_mismatch', 'Safety Gate inbox payload byte count does not match its manifest.');
        }
        $actual_hash = hash('sha256', $xml);
        if (!hash_equals((string) $manifest['sha256'], $actual_hash)) {
            return new WP_Error('autolex_safety_inbox_hash_mismatch', 'Safety Gate inbox payload SHA-256 does not match its manifest.');
        }
        if (false !== stripos($xml, '<!DOCTYPE')) {
            return new WP_Error('autolex_safety_inbox_doctype_denied', 'Safety Gate inbox XML must not contain a DOCTYPE declaration.');
        }
        return true;
    }

    /** Schedules a local import when CI has placed a complete manifest. */
    public function maybe_schedule()
    {
        if ($this->is_inbox_mode()) {
            wp_clear_scheduled_hook('autolex_safety_gate_sync');
            wp_clear_scheduled_hook(Autolex_Safety_Gate::RETRY_HOOK);
        }

        $manifest = self::inbox_dir() . '/' . self::MANIFEST_FILE;
        if (is_file($manifest) && !is_link($manifest) && !wp_next_scheduled(self::IMPORT_HOOK)) {
            wp_schedule_single_event(time() + 5, self::IMPORT_HOOK);
        }
    }

    /**
     * Prevents the known-broken server-to-EU transport from re-arming after a
     * verified inbox import has become the active transport contract.
     *
     * @param mixed    $pre Existing pre-schedule result.
     * @param stdClass $event Event.
     * @param bool     $wp_error Whether caller requested WP_Error.
     * @return mixed
     */
    public function block_legacy_transport_schedule($pre, $event, $wp_error)
    {
        unset($wp_error);
        if (null !== $pre || !$this->is_inbox_mode() || !is_object($event) || empty($event->hook)) {
            return $pre;
        }
        if (in_array($event->hook, array('autolex_safety_gate_sync', Autolex_Safety_Gate::RETRY_HOOK), true)) {
            return false;
        }
        return $pre;
    }

    /** Imports the locally verified payload. */
    public function process_inbox()
    {
        if (!add_option('autolex_safety_gate_lock', time(), '', false)) {
            if (!wp_next_scheduled(self::IMPORT_HOOK)) {
                wp_schedule_single_event(time() + 60, self::IMPORT_HOOK);
            }
            return;
        }

        $manifest_path = self::inbox_dir() . '/' . self::MANIFEST_FILE;
        $payload_path = '';
        try {
            $manifest = $this->read_manifest($manifest_path);
            if (is_wp_error($manifest)) {
                throw new RuntimeException($manifest->get_error_message());
            }

            $payload_path = self::inbox_dir() . '/' . $manifest['payload_file'];
            $xml = $this->read_payload($payload_path);
            if (is_wp_error($xml)) {
                throw new RuntimeException($xml->get_error_message());
            }

            $verified = self::verify_payload($manifest, $xml);
            if (is_wp_error($verified)) {
                throw new RuntimeException($verified->get_error_message());
            }

            $alerts = $this->parse_xml($xml, $manifest['source_url']);
            if (Autolex_Safety_Gate::SCHEMA_VERSION !== get_option('autolex_safety_gate_schema_version')) {
                Autolex_Safety_Gate::install_schema();
            }
            $imported = $this->upsert_alerts($alerts, $manifest['source_url'], $manifest['sha256']);
            $now = time();

            update_option('autolex_safety_gate_last_sync', $now, false);
            update_option('autolex_safety_gate_last_source_url', $manifest['source_url'], false);
            update_option('autolex_safety_gate_last_imported', $imported, false);
            update_option('autolex_safety_gate_transport_mode', self::CONTRACT, false);
            update_option('autolex_safety_gate_inbox_last_hash', $manifest['sha256'], false);
            update_option('autolex_safety_gate_inbox_last_retrieved_at', $manifest['retrieved_at'], false);
            update_option('autolex_safety_gate_inbox_last_imported_at', gmdate('c', $now), false);
            update_option('autolex_safety_gate_inbox_last_source_url', $manifest['source_url'], false);
            update_option('autolex_safety_gate_inbox_last_metadata_source', $manifest['metadata_source'], false);
            update_option('autolex_safety_gate_inbox_last_commit_sha', $manifest['commit_sha'], false);
            update_option('autolex_safety_gate_inbox_last_workflow_run_id', $manifest['workflow_run_id'], false);
            delete_option('autolex_safety_gate_last_error');
            delete_option('autolex_safety_gate_inbox_last_error');

            wp_clear_scheduled_hook('autolex_safety_gate_sync');
            wp_clear_scheduled_hook(Autolex_Safety_Gate::RETRY_HOOK);
            wp_clear_scheduled_hook(self::IMPORT_HOOK);
            $this->cleanup_pair($manifest_path, $payload_path);
        } catch (Throwable $exception) {
            $message = $this->limit_text($exception->getMessage(), 1000);
            update_option('autolex_safety_gate_last_error', $message, false);
            update_option('autolex_safety_gate_inbox_last_error', $message, false);
            $this->quarantine_pair($manifest_path, $payload_path);
        } finally {
            delete_option('autolex_safety_gate_lock');
        }
    }

    /** @return array<string,mixed> */
    public function get_status()
    {
        return array(
            'service' => 'autolex-safety-gate-ingest',
            'contract' => self::CONTRACT,
            'transport_mode' => (string) get_option('autolex_safety_gate_transport_mode', ''),
            'active' => $this->is_inbox_mode(),
            'pending_manifest' => is_file(self::inbox_dir() . '/' . self::MANIFEST_FILE),
            'last_payload_sha256' => (string) get_option('autolex_safety_gate_inbox_last_hash', ''),
            'last_retrieved_at' => (string) get_option('autolex_safety_gate_inbox_last_retrieved_at', ''),
            'last_imported_at' => (string) get_option('autolex_safety_gate_inbox_last_imported_at', ''),
            'last_source_url' => (string) get_option('autolex_safety_gate_inbox_last_source_url', ''),
            'last_metadata_source' => (string) get_option('autolex_safety_gate_inbox_last_metadata_source', ''),
            'last_commit_sha' => (string) get_option('autolex_safety_gate_inbox_last_commit_sha', ''),
            'last_workflow_run_id' => (int) get_option('autolex_safety_gate_inbox_last_workflow_run_id', 0),
            'last_error' => (string) get_option('autolex_safety_gate_inbox_last_error', ''),
            'generated_at' => gmdate('c'),
        );
    }

    /** @return WP_REST_Response */
    public function get_status_response()
    {
        return rest_ensure_response($this->get_status());
    }

    /** Registers the read-only evidence endpoint. */
    public function register_routes()
    {
        register_rest_route('autolex/v1', '/safety-gate-ingest-status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_status_response'),
            'permission_callback' => '__return_true',
        ));
    }

    /** @param string $path Manifest path. @return array<string,mixed>|WP_Error */
    private function read_manifest($path)
    {
        if (!is_file($path) || is_link($path) || !is_readable($path)) {
            return new WP_Error('autolex_safety_inbox_manifest_unreadable', 'Safety Gate inbox manifest is not a readable regular file.');
        }
        $size = (int) filesize($path);
        if ($size <= 0 || $size > self::MAX_MANIFEST_BYTES) {
            return new WP_Error('autolex_safety_inbox_manifest_size', 'Safety Gate inbox manifest size is invalid.');
        }
        $raw = file_get_contents($path);
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || JSON_ERROR_NONE !== json_last_error()) {
            return new WP_Error('autolex_safety_inbox_manifest_json', 'Safety Gate inbox manifest is not valid JSON.');
        }
        return self::validate_manifest($decoded);
    }

    /** @param string $path Payload path. @return string|WP_Error */
    private function read_payload($path)
    {
        $dir_real = realpath(self::inbox_dir());
        $payload_real = realpath($path);
        if (!$dir_real || !$payload_real || dirname($payload_real) !== $dir_real
            || !is_file($payload_real) || is_link($path) || !is_readable($payload_real)) {
            return new WP_Error('autolex_safety_inbox_payload_unreadable', 'Safety Gate inbox payload is outside the verified inbox or is unreadable.');
        }
        $size = (int) filesize($payload_real);
        if ($size <= 0 || $size > Autolex_Safety_Gate::MAX_XML_BYTES) {
            return new WP_Error('autolex_safety_inbox_payload_size', 'Safety Gate inbox payload file size is invalid.');
        }
        $body = file_get_contents($payload_real);
        if (false === $body) {
            return new WP_Error('autolex_safety_inbox_payload_read', 'Safety Gate inbox payload could not be read.');
        }
        return (string) $body;
    }

    /** @param string $xml XML. @param string $source_url Source URL. @return array<int,array<string,mixed>> */
    private function parse_xml($xml, $source_url)
    {
        if (!function_exists('simplexml_load_string')) {
            throw new RuntimeException('The PHP SimpleXML extension is required for Safety Gate inbox imports.');
        }
        $previous = libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$root) {
            $detail = $errors ? trim((string) $errors[0]->message) : 'unknown XML parser error';
            throw new RuntimeException('Safety Gate inbox returned invalid XML: ' . $detail);
        }

        $alerts = array();
        foreach ($root->xpath('//*') ?: array() as $node) {
            if (count($node->children()) < 4) {
                continue;
            }
            $fields = $this->flatten_node($node, 0);
            $alert = Autolex_Safety_Gate::normalize_alert($fields, $source_url);
            if ($alert) {
                $alerts[$alert['fingerprint']] = $alert;
            }
        }
        if (!$alerts) {
            throw new RuntimeException('No vehicle alerts were recognized in the verified Safety Gate inbox XML.');
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

    /** @param array<int,array<string,mixed>> $alerts Alerts. @param string $url Source. @param string $hash Hash. @return int */
    private function upsert_alerts($alerts, $url, $hash)
    {
        global $wpdb;
        $table = Autolex_Safety_Gate::table();
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
                throw new RuntimeException('Safety Gate inbox database write failed: ' . $wpdb->last_error);
            }
            ++$count;
        }
        return $count;
    }

    /** @param string $manifest Manifest. @param string $payload Payload. */
    private function cleanup_pair($manifest, $payload)
    {
        if ($payload && is_file($payload) && !is_link($payload)) {
            @unlink($payload);
        }
        if (is_file($manifest) && !is_link($manifest)) {
            @unlink($manifest);
        }
    }

    /** Quarantines the manifest so an invalid payload cannot loop forever. */
    private function quarantine_pair($manifest, $payload)
    {
        $suffix = '.failed-' . gmdate('YmdHis');
        if ($payload && is_file($payload) && !is_link($payload)) {
            @rename($payload, $payload . $suffix);
        }
        if (is_file($manifest) && !is_link($manifest)) {
            @rename($manifest, $manifest . $suffix);
        }
        wp_clear_scheduled_hook(self::IMPORT_HOOK);
    }

    /** @return bool */
    private function is_inbox_mode()
    {
        return self::CONTRACT === (string) get_option('autolex_safety_gate_transport_mode', '');
    }

    /** @param string $url URL. @return bool */
    private static function is_allowed_url($url)
    {
        if (!is_string($url) || 'https' !== strtolower((string) wp_parse_url($url, PHP_URL_SCHEME))) {
            return false;
        }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        return in_array($host, array('data.europa.eu', 'ec.europa.eu', 'webgate.ec.europa.eu'), true);
    }

    /** @param string $value Text. @param int $length Max length. @return string */
    private function limit_text($value, $length)
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }

    /** Registers local-only import hooks and read-only status. */
    private function __construct()
    {
        add_filter('pre_schedule_event', array($this, 'block_legacy_transport_schedule'), 10, 3);
        add_action('init', array($this, 'maybe_schedule'), 8);
        add_action(self::IMPORT_HOOK, array($this, 'process_inbox'));
        add_action('rest_api_init', array($this, 'register_routes'));
    }
}
