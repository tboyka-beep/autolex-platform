<?php
/**
 * Verified vehicle media resolver and public card enhancer.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Vehicle_Media
{
    /** @var Autolex_Vehicle_Media|null */
    private static $instance = null;

    /** @return Autolex_Vehicle_Media */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 150);
    }

    /** @return array<string,array<string,string>> */
    public static function verified_map()
    {
        $map = array(
            'opel|corsa' => array(
                'make'       => 'Opel',
                'model'      => 'Corsa',
                'generation' => 'F',
                'image'      => 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6f/Opel_Corsa_F_IMG_5815.jpg/1280px-Opel_Corsa_F_IMG_5815.jpg',
                'source'     => 'https://commons.wikimedia.org/wiki/File:Opel_Corsa_F_IMG_5815.jpg',
                'credit'     => 'Alexander Migl / Wikimedia Commons',
                'alt'        => 'Opel Corsa F ötajtós ferdehátú',
            ),
        );

        /**
         * Filters media mappings after the built-in verified set.
         *
         * Implementations must keep exact normalized `make|model` keys and
         * provide image/source/credit metadata. Unknown vehicles must not be
         * substituted with generic stock imagery.
         */
        $map = apply_filters('autolex_verified_vehicle_media_map', $map);
        return is_array($map) ? $map : array();
    }

    /** @param string $value @return string */
    public static function normalize($value)
    {
        $value = strtolower(remove_accents(trim((string) $value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
    }

    /** @return array<string,string> */
    public static function resolve($make, $model, $generation = '')
    {
        $make_key = self::normalize($make);
        $model_key = self::normalize($model);
        if ($make_key === '' || $model_key === '') {
            return array();
        }

        $map = self::verified_map();
        $key = $make_key . '|' . $model_key;
        if (!isset($map[$key]) || !is_array($map[$key])) {
            return array();
        }

        $media = $map[$key];
        if (empty($media['image']) || empty($media['source']) || empty($media['credit'])) {
            return array();
        }

        $required_generation = isset($media['generation']) ? self::normalize($media['generation']) : '';
        $actual_generation = self::normalize($generation);
        if ($required_generation !== '' && $actual_generation !== '' && $required_generation !== $actual_generation) {
            return array();
        }

        return $media;
    }

    /** Enqueue the exact-match media enhancer only on public Autolex surfaces. */
    public function enqueue_assets()
    {
        if (is_admin()) {
            return;
        }

        $relative = 'assets/js/autolex-vehicle-media.js';
        $absolute = AUTOLEX_PLATFORM_DIR . $relative;
        if (!is_readable($absolute)) {
            return;
        }

        wp_enqueue_script(
            'autolex-vehicle-media',
            plugins_url($relative, AUTOLEX_PLATFORM_FILE),
            array(),
            (string) filemtime($absolute),
            true
        );

        wp_localize_script(
            'autolex-vehicle-media',
            'AutolexVehicleMedia',
            array(
                'map' => self::verified_map(),
            )
        );
    }
}
