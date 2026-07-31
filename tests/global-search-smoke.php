<?php

$root = dirname(__DIR__);
$js_path = $root . '/plugin/autolex-platform/assets/js/autolex-portal-3.js';
$css_path = $root . '/plugin/autolex-platform/assets/css/autolex-search-3.css';
$home_path = $root . '/plugin/autolex-platform/includes/trait-autolex-portal-home.php';

foreach (array($js_path, $css_path, $home_path) as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Global search dependency is missing: {$path}\n");
        exit(1);
    }
}

$js = (string) file_get_contents($js_path);
$css = (string) file_get_contents($css_path);
$home = (string) file_get_contents($home_path);

$contracts = array(
    'role=combobox' => "setAttribute('role', 'combobox')",
    'aria autocomplete' => "setAttribute('aria-autocomplete', 'list')",
    'listbox' => "setAttribute('role', 'listbox')",
    'active descendant' => 'aria-activedescendant',
    'keyboard down' => "event.key === 'ArrowDown'",
    'keyboard up' => "event.key === 'ArrowUp'",
    'escape close' => "event.key === 'Escape'",
    'abortable fetch' => 'new AbortController()',
    'shareable q parameter' => "searchParams.set('q', query)",
    'catalog breadcrumb' => 'data-autolex-breadcrumbs',
    'server fallback action' => "home_url('/autok/')",
);
foreach ($contracts as $label => $needle) {
    $haystack = 'server fallback action' === $label ? $home : $js;
    if (false === strpos($haystack, $needle)) {
        fwrite(STDERR, "Global search contract failed: {$label}\n");
        exit(1);
    }
}

foreach (array('.alx3-search-results', '.alx3-breadcrumbs', '.alx3-quick-routes', '@media(max-width:720px)', '@media(prefers-reduced-motion:reduce)') as $selector) {
    if (false === strpos($css, $selector)) {
        fwrite(STDERR, "Global search style contract failed: {$selector}\n");
        exit(1);
    }
}

if (false !== strpos($js, 'innerHTML = query') || false !== strpos($js, '${query}')) {
    fwrite(STDERR, "Raw search query must not be interpolated into HTML.\n");
    exit(1);
}

if (false === strpos($js, 'Enterrel a szerveroldali keresés')) {
    fwrite(STDERR, "Fail-open server-side search guidance is missing.\n");
    exit(1);
}

echo "Autolex global search smoke tests passed.\n";
