<?php
/**
 * One-time bounded recovery for historical EEA make-discovery API rejections.
 *
 * The live source now accepts the production query shape for the largest makes,
 * so previously exhausted, pristine make-discovery targets receive exactly one
 * fresh attempt. Any target still rejected returns immediately to `failed`.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_EEA_API_Rejection_Recovery
{
    /** One-time migration marker. */
    const OPTION = 'autolex_eea_api_rejection_recovery_v1';

    /** Exact historical exception emitted by the EEA response decoder. */
    const REJECTED_ERROR = 'EEA Discodata rejected the read-only query.';

    /**
     * Starting at seven means run_batch() increments to eight on claim, so a
     * repeated rejection becomes failed immediately instead of entering a new
     * exponential retry cycle.
     */
    const PROBATION_ATTEMPTS = 7;

    /** @var Autolex_EEA_API_Rejection_Recovery|null */
    private static $instance = null;

    /** @return Autolex_EEA_API_Rejection_Recovery */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Requeues only pristine, exhausted make-discovery tasks with the exact
     * historical API-rejected signature. No partially imported target is reset.
     *
     * @return int Number of tasks admitted to one-attempt probation.
     */
    public function recover()
    {
        global $wpdb;

        if (get_option(self::OPTION, false)) {
            return 0;
        }
        if (!class_exists('Autolex_EEA_Sync') ||
            Autolex_EEA_Sync::SCHEMA_VERSION !== get_option('autolex_eea_sync_schema_version')) {
            return 0;
        }

        $table = Autolex_EEA_Sync::tasks_table();
        $now   = current_time('mysql', true);
        $sql   = $wpdb->prepare(
            "UPDATE {$table}
             SET status = 'retry',
                 attempts = %d,
                 next_run_at = NULL,
                 locked_at = NULL,
                 completed_at = NULL,
                 updated_at = %s
             WHERE status = 'failed'
                 AND target_type = 'make_discovery'
                 AND attempts >= 8
                 AND page_number = 1
                 AND rows_read = 0
                 AND vehicles_imported = 0
                 AND engines_proposed = 0
                 AND links_proposed = 0
                 AND last_error = %s",
            self::PROBATION_ATTEMPTS,
            $now,
            self::REJECTED_ERROR
        );
        $requeued = $wpdb->query($sql);
        if (false === $requeued) {
            update_option(
                'autolex_eea_api_rejection_recovery_error',
                substr((string) $wpdb->last_error, 0, 1000),
                false
            );
            return 0;
        }

        update_option(
            self::OPTION,
            array(
                'requeued_tasks' => (int) $requeued,
                'requeued_at'    => $now,
                'attempt_policy' => 'one_fresh_attempt_only',
            ),
            false
        );
        delete_option('autolex_eea_api_rejection_recovery_error');
        return (int) $requeued;
    }

    private function __construct()
    {
        // Run immediately before the normal EEA scheduler hook at priority 7.
        add_action('init', array($this, 'recover'), 6);
    }
}
