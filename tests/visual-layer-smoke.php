<?php
$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/plugin/autolex-platform/autolex-platform.php');
$css = file_get_contents($root . '/plugin/autolex-platform/assets/css/autolex-visual-night.css');

$checks = array(
    'stylesheet file is readable' => $css !== false,
    'stylesheet is portal scoped' => strpos($css, 'body.autolex-portal-3') !== false,
    'hero receives premium dark layer' => strpos($css, '.alx3-hero') !== false && strpos($css, 'linear-gradient') !== false,
    'catalogue hero receives premium layer' => strpos($css, '.alx3-catalog-hero') !== false && strpos($css, 'alx3-catalog-glow') !== false,
    'catalogue filter is sticky on desktop' => strpos($css, '.alx3-filters') !== false && strpos($css, 'position:sticky') !== false,
    'vehicle cards have premium hover treatment' => strpos($css, '.alx3-vehicle-card:hover') !== false && strpos($css, 'translateY(-7px)') !== false,
    'comparison hero receives premium layer' => strpos($css, '.alx3-compare__hero') !== false && strpos($css, 'alx3-compare-glow') !== false,
    'comparison table has dark sticky header treatment' => strpos($css, '.alx3-compare__table thead th') !== false && strpos($css, 'linear-gradient(145deg,#202b26,#33453b)') !== false,
    'comparison form has accessible focus treatment' => strpos($css, '.alx3-compare__form input:focus') !== false && strpos($css, '0 0 0 4px rgba(197,72,45,.12)') !== false,
    'mobile breakpoint exists' => strpos($css, '@media (max-width:640px)') !== false,
    'mobile card hover is neutralized' => strpos($css, '.alx3-vehicle-card:hover,') !== false && strpos($css, '.alx3-compare__form button:hover { transform:none; }') !== false,
    'reduced motion contract exists' => strpos($css, 'prefers-reduced-motion:reduce') !== false,
    'catalogue animation is disabled for reduced motion' => strpos($css, '.alx3-catalog-hero:before,') !== false,
    'comparison animation is disabled for reduced motion' => strpos($css, '.alx3-compare__hero:before { transition:none!important;animation:none!important; }') !== false,
    'plugin enqueues visual stylesheet' => strpos($plugin, "'autolex-visual-night'") !== false,
    'base portal style is dependency' => strpos($plugin, "array('autolex-portal-3')") !== false,
    'asset version uses filemtime' => strpos($plugin, 'filemtime($absolute_path)') !== false,
);

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}
