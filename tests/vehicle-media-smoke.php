<?php
/** ALX-046 fail-closed vehicle media identity contract. */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!function_exists('remove_accents')) {
    function remove_accents($value)
    {
        return (string) $value;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value)
    {
        return $value;
    }
}

require_once __DIR__ . '/../plugin/autolex-platform/includes/class-autolex-vehicle-media.php';

$fail = static function ($message) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$corsa = Autolex_Vehicle_Media::resolve('Opel', 'Corsa');
if (empty($corsa['image']) || false === strpos($corsa['image'], 'Opel_Corsa_F')) {
    $fail('Opel Corsa must resolve to verified Corsa media.');
}
if (empty($corsa['source']) || false === strpos($corsa['source'], 'commons.wikimedia.org')) {
    $fail('Verified media must carry a source page.');
}
if (!empty(Autolex_Vehicle_Media::resolve('Opel', 'Astra'))) {
    $fail('Unknown models must fail closed instead of receiving generic Opel media.');
}
if (!empty(Autolex_Vehicle_Media::resolve('Opel', 'Corsa', 'E'))) {
    $fail('Known generation mismatch must fail closed.');
}
if (empty(Autolex_Vehicle_Media::resolve('Opel', 'Corsa', 'F'))) {
    $fail('Exact generation match must resolve.');
}

$script = file_get_contents(__DIR__ . '/../plugin/autolex-platform/assets/js/autolex-vehicle-media.js');
if (!is_string($script) || false === strpos($script, 'data-alx-verified-vehicle-media')) {
    $fail('Client enhancer must mark verified vehicle media.');
}
if (false === strpos($script, 'media.make') || false === strpos($script, 'media.model')) {
    $fail('Client enhancer must require both make and model identity.');
}

echo "vehicle-media-smoke: OK\n";
