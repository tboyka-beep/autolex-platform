<?php
/**
 * Deterministic contract for the final Autolex 4.2 public shell and homepage.
 */

$root = dirname(__DIR__);
$entry = $root . '/plugin/autolex-platform/assets/css/autolex-home-41.css';
$shell = $root . '/plugin/autolex-platform/assets/css/autolex-4-shell.css';
$home = $root . '/plugin/autolex-platform/assets/css/autolex-home-42.css';

foreach (array($entry, $shell, $home) as $file) {
    if (!is_readable($file)) {
        fwrite(STDERR, "Missing Autolex 4.2 visual asset: {$file}\n");
        exit(1);
    }
}

$entry_css = (string) file_get_contents($entry);
$shell_css = (string) file_get_contents($shell);
$home_css = (string) file_get_contents($home);
$combined_css = $shell_css . "\n" . $home_css;
$remote_import_count = preg_match_all('~@import\s+url\(["\']?https?://~i', $combined_css);
$remote_image_count = preg_match_all('~url\(["\']?https?://~i', $combined_css);

$assertions = array(
    '4.1 entrypoint imports the final shell' => false !== strpos($entry_css, '@import url("autolex-4-shell.css")'),
    '4.1 entrypoint imports the final homepage' => false !== strpos($entry_css, '@import url("autolex-home-42.css")'),
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
    'reduced motion contract' => false !== strpos($combined_css, '@media (prefers-reduced-motion: reduce)'),
    'mobile breakpoint' => false !== strpos($combined_css, '@media (max-width: 689px)'),
    'tablet breakpoint' => false !== strpos($combined_css, '@media (max-width: 1024px)'),
    'light existing homepage hero' => false !== strpos($home_css, '.alx-ui-hero') && false !== strpos($home_css, 'linear-gradient(132deg, #ffffff'),
    'homepage search is light and accessible' => false !== strpos($home_css, '.alxbc-search input') && false !== strpos($home_css, 'var(--alx42-focus)'),
    'homepage uses real existing markup' => false !== strpos($home_css, '.alx-front') && false !== strpos($home_css, '.alxbc-section'),
    'real-data responsive grid' => false !== strpos($home_css, '.alxbc-stats') && false !== strpos($home_css, 'repeat(auto-fit, minmax(min(100%, 230px), 1fr))'),
    'dynamic cards have focus-within state' => false !== strpos($home_css, ':focus-within'),
    'warning and conflict state styling' => false !== strpos($home_css, '[data-status="conflict"]') && false !== strpos($home_css, 'var(--alx42-safety)'),
    'partial and incomplete state styling' => false !== strpos($home_css, '[data-status="incomplete"]') && false !== strpos($home_css, 'border-style: dashed'),
    'loading state is aria-aware' => false !== strpos($home_css, '[aria-busy="true"]') && false !== strpos($home_css, '@keyframes alx42-skeleton'),
    'empty state preserves layout' => false !== strpos($home_css, '[data-state="empty"]') && false !== strpos($home_css, 'min-height: 150px'),
    'skeleton stops under reduced motion' => false !== strpos($home_css, '[aria-busy="true"])::after { animation: none; }'),
    'remote background image retired' => false === strpos($home_css, 'images.unsplash.com'),
    'no remote paid asset import' => 0 === $remote_import_count,
    'no remote visual asset' => 0 === $remote_image_count,
);

$failed = array_keys(array_filter($assertions, static fn ($passed) => !$passed));
if ($failed) {
    fwrite(STDERR, "Autolex 4.2 shell contract failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "Autolex 4.2 portal shell and homepage smoke test passed.\n";