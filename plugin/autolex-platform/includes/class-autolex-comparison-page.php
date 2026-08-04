<?php
/** Routes the shareable comparison view through the existing catalogue page. */
if (!defined('ABSPATH')) { exit; }

final class Autolex_Comparison_Page
{
    private static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('template_redirect', array($this, 'redirect_public_route'), 1);
        add_filter('the_content', array($this, 'render'), 90);
    }

    /**
     * Keeps the documented public comparison URL functional without relying on
     * a manually created WordPress page or rewrite-flush side effect.
     *
     * @return void
     */
    public function redirect_public_route()
    {
        if (is_admin()) {
            return;
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) wp_unslash($_SERVER['REQUEST_METHOD'])) : 'GET';
        if (!in_array($method, array('GET', 'HEAD'), true)) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $normalized = '/' . trim($path, '/') . '/';
        if ('/osszehasonlitas/' !== $normalized) {
            return;
        }

        $args = array('compare' => '1');
        if (isset($_GET['vehicles'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $ids = Autolex_Vehicle_Comparison::normalize_ids(wp_unslash($_GET['vehicles'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ($ids) {
                $args['vehicles'] = implode(',', $ids);
            }
        }

        wp_safe_redirect(add_query_arg($args, home_url('/autok/')), 302, 'Autolex Platform');
        exit;
    }

    public function render($content)
    {
        if (is_admin() || !is_page('autok') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $comparison = Autolex_Vehicle_Comparison::instance();
        return $comparison->is_comparison_request() ? $comparison->render() : $content;
    }
}
