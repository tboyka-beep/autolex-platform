<?php
/** Static regression contract for automatic source-card detail integration. */
$root = dirname(__DIR__);
$integration = file_get_contents($root . '/plugin/autolex-platform/includes/class-autolex-source-integration.php');
$platform = file_get_contents($root . '/plugin/autolex-platform/includes/class-autolex-platform.php');

$failures = array();
$expect = static function ($condition, $message) use (&$failures) {
    if (!$condition) {
        $failures[] = $message;
    }
};

$expect(false !== strpos($integration, "add_filter('the_content', array(\$this, 'append_source_panel'), 95)"), 'Source integration must hook after the portal renderer.');
$expect(false !== strpos($integration, "preg_match('#/auto-adatlap/(\\d+)(?:/|$)#'"), 'Legacy vehicle detail URLs must resolve their real catalogue entity ID.');
$expect(false !== strpos($integration, "'alx_vehicle'    => 'vehicle'"), 'Vehicle post details must be supported.');
$expect(false !== strpos($integration, "'alx_engine'     => 'engine'"), 'Engine post details must be supported.');
$expect(false !== strpos($integration, "'alx_generation' => 'generation'"), 'Generation post details must be supported.');
$expect(false !== strpos($integration, "'alx_model'      => 'model'"), 'Model post details must be supported.');
$expect(false !== strpos($integration, "false !== strpos(\$content, 'alxp-source-panel')"), 'Duplicate source panels must be prevented.');
$expect(false !== strpos($integration, "Autolex_Source_Cards::instance()->render_shortcode"), 'The integration must reuse the tested source-card renderer.');
$expect(false !== strpos($integration, "is_admin() || !in_the_loop() || !is_main_query()"), 'The integration must be restricted to the public main loop.');
$expect(false === preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|DROP|TRUNCATE|ALTER)\b/i', $integration), 'The public integration must remain read-only.');
$expect(false !== strpos($platform, "require_once __DIR__ . '/class-autolex-source-integration.php';"), 'The integration class must be loaded by the platform bootstrap.');
$expect(false !== strpos($platform, 'Autolex_Source_Integration::instance()->register();'), 'The integration class must be registered by the platform bootstrap.');

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Source integration smoke contract passed.\n";
