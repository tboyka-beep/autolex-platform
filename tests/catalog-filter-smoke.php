<?php

define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-portal.php';

$reflection = new ReflectionClass(Autolex_Portal::class);
$portal = $reflection->newInstanceWithoutConstructor();

$route_method = $reflection->getMethod('vehicle_route_args');
$route_method->setAccessible(true);
$route_args = $route_method->invoke($portal);
foreach (array('generation', 'verification') as $required_filter) {
    if (!isset($route_args[$required_filter])) {
        fwrite(STDERR, "Missing REST route filter: {$required_filter}.\n");
        exit(1);
    }
}

$normalize_method = $reflection->getMethod('normalize_filters');
$normalize_method->setAccessible(true);
$normalized = $normalize_method->invoke($portal, array(
    'generation'   => '  E46  ',
    'verification' => 'VERIFIED',
    'page'         => 0,
    'limit'        => 500,
));
if ('E46' !== $normalized['generation'] || 'verified' !== $normalized['verification']) {
    fwrite(STDERR, "Generation or verification normalization failed.\n");
    exit(1);
}
if (1 !== $normalized['page'] || 48 !== $normalized['limit']) {
    fwrite(STDERR, "Catalogue pagination bounds regressed.\n");
    exit(1);
}
$invalid = $normalize_method->invoke($portal, array('verification' => 'invented-state'));
if ('' !== $invalid['verification']) {
    fwrite(STDERR, "Unknown verification state was not rejected.\n");
    exit(1);
}

$catalog = file_get_contents(dirname(__DIR__) . '/plugin/autolex-platform/includes/trait-autolex-portal-catalog.php');
foreach (array(
    'id="alx3-filter-form"',
    'name="generation"',
    'name="verification"',
    'form="alx3-filter-form"',
    'aria-controls="alx3-filter-panel"',
) as $needle) {
    if (false === strpos($catalog, $needle)) {
        fwrite(STDERR, "Catalogue markup contract is missing: {$needle}.\n");
        exit(1);
    }
}
if (false !== strpos($catalog, 'id="alx3-sort-proxy"')) {
    fwrite(STDERR, "Detached sort proxy form returned.\n");
    exit(1);
}

$script = file_get_contents(dirname(__DIR__) . '/plugin/autolex-platform/assets/js/autolex-portal-3.js');
foreach (array(
    "model.value = '';",
    'updateModels(make.value).finally',
    "open?.setAttribute('aria-expanded'",
    "'generation', 'fuel', 'engine_code', 'grade', 'verification'",
) as $needle) {
    if (false === strpos($script, $needle)) {
        fwrite(STDERR, "Catalogue JavaScript contract is missing: {$needle}.\n");
        exit(1);
    }
}

echo "Autolex catalogue filter smoke tests passed.\n";
