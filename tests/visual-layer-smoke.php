<?php
$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/plugin/autolex-platform/autolex-platform.php');
$catalog_css = file_get_contents($root . '/plugin/autolex-platform/assets/css/autolex-catalog-light.css');
$vehicle_css = file_get_contents($root . '/plugin/autolex-platform/assets/css/autolex-vehicle-experience-light.css');
$home_css = file_get_contents($root . '/plugin/autolex-platform/assets/css/autolex-home-41.css');

$checks = array(
    'plugin source is readable' => $plugin !== false,
    'catalogue stylesheet is readable' => $catalog_css !== false,
    'vehicle stylesheet is readable' => $vehicle_css !== false,
    'home stylesheet is readable' => $home_css !== false,
    'single light catalogue layer is enqueued' => strpos($plugin, "'autolex-catalog-light'") !== false,
    'vehicle layer follows catalogue layer' => strpos($plugin, "'autolex-vehicle-experience-light'") !== false && strpos($plugin, "array('autolex-catalog-light')") !== false,
    'home layer follows vehicle layer' => strpos($plugin, "'autolex-home-41'") !== false && strpos($plugin, "array('autolex-vehicle-experience-light')") !== false,
    'assets use deterministic filemtime cache busting' => strpos($plugin, 'filemtime($absolute_path)') !== false,
    'legacy night layer is not enqueued' => strpos($plugin, "wp_enqueue_style(\n            'autolex-visual-night'") === false,
    'legacy adaptive layer is not enqueued' => strpos($plugin, "wp_enqueue_style(\n            'autolex-adaptive-theme'") === false,
    'catalogue is portal scoped' => strpos($catalog_css, 'body.autolex-portal-3 .alx3-catalog') !== false,
    'catalogue hero uses premium light treatment' => strpos($catalog_css, '.alx3-catalog-hero') !== false && strpos($catalog_css, 'radial-gradient') !== false,
    'desktop filter remains sticky' => strpos($catalog_css, '.alx3-filters') !== false && strpos($catalog_css, 'position: sticky') !== false,
    'mobile filter becomes a drawer' => strpos($catalog_css, 'position: fixed') !== false && strpos($catalog_css, 'translateX(-105%)') !== false,
    'vehicle cards use premium hover treatment' => strpos($catalog_css, '.alx3-vehicle-card:hover') !== false && strpos($catalog_css, 'translateY(-5px)') !== false,
    'long vehicle data can wrap safely' => strpos($catalog_css, 'overflow-wrap: anywhere') !== false,
    'verification states are visually distinct' => strpos($catalog_css, '.is-verified') !== false && strpos($catalog_css, '.is-conflict') !== false,
    'mobile catalogue breakpoint exists' => strpos($catalog_css, '@media (max-width: 680px)') !== false,
    'reduced motion contract exists' => strpos($catalog_css, 'prefers-reduced-motion: reduce') !== false,
    'home hero remains premium and responsive' => strpos($home_css, '.alx3-hero') !== false && strpos($home_css, '@media (max-width: 689px)') !== false,
    'vehicle experience remains portal scoped' => strpos($vehicle_css, 'body.autolex-portal-3') !== false,
);

$failed = false;
foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        $failed = true;
        continue;
    }
    echo "PASS: {$label}\n";
}

exit($failed ? 1 : 0);
