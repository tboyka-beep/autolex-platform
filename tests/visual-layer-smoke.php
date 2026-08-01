<?php
$root = dirname(__DIR__);
$plugin = file_get_contents($root . '/plugin/autolex-platform/autolex-platform.php');
$css = file_get_contents($root . '/plugin/autolex-platform/assets/css/autolex-visual-night.css');
$detail_css = file_get_contents($root . '/plugin/autolex-platform/assets/css/autolex-vehicle-detail-premium.css');
$adaptive_css = file_get_contents($root . '/plugin/autolex-platform/assets/css/autolex-adaptive-theme.css');

$checks = array(
    'stylesheet file is readable' => $css !== false,
    'stylesheet is portal scoped' => strpos($css, 'body.autolex-portal-3') !== false,
    'hero receives premium visual layer' => strpos($css, '.alx3-hero') !== false && strpos($css, 'linear-gradient') !== false,
    'catalogue hero receives premium layer' => strpos($css, '.alx3-catalog-hero') !== false && strpos($css, 'alx3-catalog-glow') !== false,
    'catalogue filter is sticky on desktop' => strpos($css, '.alx3-filters') !== false && strpos($css, 'position:sticky') !== false,
    'vehicle cards have premium hover treatment' => strpos($css, '.alx3-vehicle-card:hover') !== false && strpos($css, 'translateY(-7px)') !== false,
    'comparison hero receives premium layer' => strpos($css, '.alx3-compare__hero') !== false && strpos($css, 'alx3-compare-glow') !== false,
    'comparison table has sticky header treatment' => strpos($css, '.alx3-compare__table thead th') !== false,
    'comparison form has accessible focus treatment' => strpos($css, '.alx3-compare__form input:focus') !== false && strpos($css, '0 0 0 4px rgba(197,72,45,.12)') !== false,
    'mobile breakpoint exists' => strpos($css, '@media (max-width:640px)') !== false,
    'mobile card hover is neutralized' => strpos($css, '.alx3-vehicle-card:hover,') !== false && strpos($css, '.alx3-compare__form button:hover { transform:none; }') !== false,
    'reduced motion contract exists' => strpos($css, 'prefers-reduced-motion:reduce') !== false,
    'catalogue animation is disabled for reduced motion' => strpos($css, '.alx3-catalog-hero:before,') !== false,
    'comparison animation is disabled for reduced motion' => strpos($css, '.alx3-compare__hero:before { transition:none!important;animation:none!important; }') !== false,
    'plugin enqueues visual stylesheet' => strpos($plugin, "'autolex-visual-night'") !== false,
    'base portal style is dependency' => strpos($plugin, "array('autolex-portal-3')") !== false,
    'asset version uses filemtime' => strpos($plugin, 'filemtime($absolute_path)') !== false,
    'detail premium stylesheet is readable' => $detail_css !== false,
    'detail premium stylesheet is page scoped' => strpos($detail_css, 'body.autolex-vehicle-detail') !== false,
    'detail overview receives premium layer' => strpos($detail_css, '.alx3-detail-overview') !== false && strpos($detail_css, 'alx3-detail-glow') !== false,
    'detail confidence panel uses glass treatment' => strpos($detail_css, '.alx3-detail-confidence') !== false && strpos($detail_css, 'backdrop-filter:blur(22px)') !== false,
    'detail navigation has accessible focus treatment' => strpos($detail_css, '.alx3-detail-nav a:focus-visible') !== false && strpos($detail_css, 'outline:3px solid') !== false,
    'detail cards have premium hover treatment' => strpos($detail_css, '.alx3-claim-card:hover') !== false && strpos($detail_css, 'translateY(-6px)') !== false,
    'detail mobile hover is neutralized' => strpos($detail_css, '@media (max-width:640px)') !== false && strpos($detail_css, '.alx3-source-card:hover { transform:none; }') !== false,
    'detail reduced motion contract exists' => strpos($detail_css, 'prefers-reduced-motion:reduce') !== false && strpos($detail_css, 'animation:none!important') !== false,
    'plugin enqueues detail premium stylesheet' => strpos($plugin, "'autolex-vehicle-detail-premium'") !== false,
    'detail premium layer depends on global visual layer' => strpos($plugin, "array('autolex-visual-night')") !== false,
    'detail asset version uses filemtime' => strpos($plugin, 'filemtime($detail_absolute_path)') !== false,
    'adaptive theme stylesheet is readable' => $adaptive_css !== false,
    'adaptive theme is portal scoped' => strpos($adaptive_css, 'body.autolex-portal-3') !== false,
    'adaptive theme is light first' => strpos($adaptive_css, 'color-scheme:light') !== false && strpos($adaptive_css, 'background:#f6f8f7') !== false,
    'adaptive theme does not auto-switch to dark' => strpos($adaptive_css, 'prefers-color-scheme:dark') === false,
    'adaptive theme supports increased contrast' => strpos($adaptive_css, 'prefers-contrast:more') !== false,
    'adaptive theme exposes visible focus contract' => strpos($adaptive_css, ':focus-visible') !== false && strpos($adaptive_css, 'outline:3px solid') !== false,
    'adaptive theme protects reduced motion users' => strpos($adaptive_css, 'prefers-reduced-motion:reduce') !== false && strpos($adaptive_css, 'transition-duration:.01ms!important') !== false,
    'plugin enqueues adaptive theme stylesheet' => strpos($plugin, "'autolex-adaptive-theme'") !== false,
    'adaptive layer depends on global visual layer' => strpos($plugin, "array('autolex-visual-night')") !== false,
    'adaptive asset version uses filemtime' => strpos($plugin, 'filemtime($adaptive_absolute_path)') !== false,
);

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}
