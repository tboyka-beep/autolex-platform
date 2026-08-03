<?php
/**
 * Automatic source-card integration for public Autolex entity pages.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Source_Integration
{
    /** @var Autolex_Source_Integration|null */
    private static $instance = null;

    /** @return Autolex_Source_Integration */
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
        add_filter('the_content', array($this, 'append_source_panel'), 95);
    }

    /**
     * Appends the source panel once to supported public detail pages.
     *
     * @param string $content Existing rendered page content.
     * @return string
     */
    public function append_source_panel($content)
    {
        if (is_admin() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        if (false !== strpos($content, 'alxp-source-panel')) {
            return $content;
        }

        $entity = $this->resolve_current_entity();
        if (!$entity) {
            return $content;
        }

        $panel = Autolex_Source_Cards::instance()->render_shortcode(
            array(
                'entity_type' => $entity['entity_type'],
                'entity_id'   => $entity['entity_id'],
                'limit'       => 40,
            )
        );

        return '' === trim($panel) ? $content : $content . $panel;
    }

    /**
     * Resolves the current public entity without database writes or guesses.
     *
     * @return array<string,mixed>|false
     */
    public function resolve_current_entity()
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        if (preg_match('#/auto-adatlap/(\d+)(?:/|$)#', $request_uri, $matches)) {
            return array(
                'entity_type' => 'vehicle',
                'entity_id'   => absint($matches[1]),
            );
        }

        $post_type_map = array(
            'alx_vehicle'    => 'vehicle',
            'alx_engine'     => 'engine',
            'alx_generation' => 'generation',
            'alx_model'      => 'model',
        );

        foreach ($post_type_map as $post_type => $entity_type) {
            if (!is_singular($post_type)) {
                continue;
            }

            $post_id = absint(get_queried_object_id());
            if (0 === $post_id) {
                return false;
            }

            $meta_keys = array(
                '_autolex_entity_id',
                'autolex_entity_id',
                '_autolex_' . $entity_type . '_id',
                'autolex_' . $entity_type . '_id',
            );
            foreach ($meta_keys as $meta_key) {
                $entity_id = absint(get_post_meta($post_id, $meta_key, true));
                if ($entity_id > 0) {
                    return array('entity_type' => $entity_type, 'entity_id' => $entity_id);
                }
            }

            return array('entity_type' => $entity_type, 'entity_id' => $post_id);
        }

        return false;
    }

    private function __construct() {}
}
