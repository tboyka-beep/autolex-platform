<?php
$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/plugin/autolex-platform/autolex-platform.php');
$css = file_get_contents($root . '/plugin/autolex-platform/assets/css/autolex-visual-night.css');

$checks = array(
    'stylesheet file is readable' => $css !== false,
    'stylesheet is portal scoped' => strpos($css, 'body.autolex-portal-3') !== false,
    'hero receives premium dark layer' => strpos($css, '.alx3-hero') !== false && strpos($css, 'linear-gradient') !== false,
    'mobile breakpoint exists' => strpos($css, '@media (max-width:640px)') !== false,
    'reduced motion contract exists' => strpos($css, 'prefers-reduced-motion:reduce') !== false,
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
