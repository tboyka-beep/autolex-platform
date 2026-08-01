<?php
/**
 * Minimal pure-function smoke tests for the operations-center health model.
 */

define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/plugin/autolex-platform/includes/class-autolex-operations-center.php';

$progress = Autolex_Operations_Center::progress_percent(array(
    'targets'           => 80,
    'completed_targets' => 20,
));
if (25.0 !== $progress) {
    fwrite(STDERR, "Queue progress calculation failed.\n");
    exit(1);
}

$bounded = Autolex_Operations_Center::progress_percent(array(
    'targets'           => 2,
    'completed_targets' => 3,
));
if (100.0 !== $bounded) {
    fwrite(STDERR, "Queue progress must be bounded at 100 percent.\n");
    exit(1);
}

$critical = Autolex_Operations_Center::health_code(array(
    'pending_targets'        => 12,
    'failed_targets'         => 1,
    'running_targets'        => 0,
    'queue_lock_age_seconds' => 0,
    'next_scheduled_at'      => '2026-07-30T20:00:00Z',
));
if ('critical' !== $critical) {
    fwrite(STDERR, "Failed queue targets must be critical.\n");
    exit(1);
}

$warning = Autolex_Operations_Center::health_code(array(
    'pending_targets'        => 12,
    'failed_targets'         => 0,
    'running_targets'        => 0,
    'queue_lock_age_seconds' => 0,
    'next_scheduled_at'      => null,
));
if ('warning' !== $warning) {
    fwrite(STDERR, "A pending queue without a schedule must be a warning.\n");
    exit(1);
}

$healthy = Autolex_Operations_Center::health_code(array(
    'pending_targets'        => 12,
    'failed_targets'         => 0,
    'running_targets'        => 1,
    'queue_lock_age_seconds' => 45,
    'next_scheduled_at'      => '2026-07-30T20:00:00Z',
));
if ('healthy' !== $healthy) {
    fwrite(STDERR, "A scheduled active queue must be healthy.\n");
    exit(1);
}

$complete = Autolex_Operations_Center::health_code(array(
    'pending_targets'        => 0,
    'failed_targets'         => 0,
    'running_targets'        => 0,
    'queue_lock_age_seconds' => 0,
    'next_scheduled_at'      => null,
));
if ('complete' !== $complete) {
    fwrite(STDERR, "An empty queue must be complete.\n");
    exit(1);
}

echo "Autolex operations-center smoke tests passed.\n";
