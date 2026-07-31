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
        add_filter('the_content', array($this, 'render'), 90);
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
