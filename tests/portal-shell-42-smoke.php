<?php
/**
 * Deterministic contract for the final Autolex 4.2 public shell.
 */

$root = dirname(__DIR__);
$entry = $root . '/plugin/autolex-platform/assets/css/autolex-home-41.css';
$shell = $root . '/plugin/autolex-platform/assets/css/autolex-4-shell.css';

foreach (array($entry, $shell) as $file) {
    if (!is_readable($file)) {
        fwrite(STDERR, "Missing Autolex 4.2 shell asset: {$file}\n");
        exit(1);
    }
}

$entry_css = (string) file_get_contents($entry);
$shell_css = (string) file_get_contents($shell);

$assertions = array(
    '4.1 entrypoint imports the final shell' => false !== strpos($entry_css, '@import url("autolex-4-shell.css")'),
    'legacy 4.1 hero rules retired' => false === strpos($entry_css, '.alx3-hero {'),
    'off-white page background token' => false !== strpos($shell_css, '--alx42-bg: #f6f8fb'),
    'graphite typography token' => false !== strpos($shell_css, '--alx42-graphite: #17202b'),
    'strong blue accent token' => false !== strpos($shell_css, '--alx42-blue: #1463ff'),
    'reserved safety red token' => false !== strpos($shell_css, '--alx42-safety: #d63c45'),
    'sticky public header' => false !== strpos($shell_css, 'position: sticky'),
    'light mobile drawer' => false !== strpos($shell_css, '#offcanvas'),
    'final light footer' => false !== strpos($shell_css, 'footer.ct-footer'),
    'black outer frame removal' => false !== strpos($shell_css, '#main-container') && false !== strpos($shell_css, 'border: 0 !important'),
    'keyboard focus contract' => false !== strpos($shell_css, ':focus-visible'),
    'reduced motion contract' => false !== strpos($shell_css, '@media (prefers-reduced-motion: reduce)'),
    'mobile breakpoint' => false !== strpos($shell_css, '@media (max-width: 689px)'),
    'tablet breakpoint' => false !== strpos($shell_css, '@media (max-width: 1024px)'),
    'no remote paid asset import' => false === preg_match('~@import\s+url\(["\']?https?://~i', $shell_css),
);

$failed = array_keys(array_filter($assertions, static fn ($passed) => !$passed));
if ($failed) {
    fwrite(STDERR, "Autolex 4.2 shell contract failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "Autolex 4.2 portal shell smoke test passed.\n";
