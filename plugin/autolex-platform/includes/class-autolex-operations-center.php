<?php
/**
 * Secure operational dashboard for the Autolex background import queues.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Operations_Center
{
    /** @var Autolex_Operations_Center|null */
    private static $instance = null;

    /** @return Autolex_Operations_Center */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Registers admin hooks. */
    private function __construct()
    {
        add_action('admin_menu', array($this, 'register_page'), 30);
        add_action('admin_post_autolex_operations_action', array($this, 'handle_action'));
        add_action('admin_notices', array($this, 'render_notice'));
    }

    /** @return void */
    public function register_page()
    {
        add_submenu_page(
            'autolex-platform',
            __('Autolex műveleti központ', 'autolex-platform'),
            __('Műveleti központ', 'autolex-platform'),
            'manage_options',
            'autolex-operations',
            array($this, 'render_page')
        );
    }

    /**
     * Returns a stable machine-readable health code for tests and rendering.
     *
     * @param array<string,mixed> $status EEA queue status.
     * @return string
     */
    public static function health_code($status)
    {
        $pending = (int) ($status['pending_targets'] ?? 0);
        $failed  = (int) ($status['failed_targets'] ?? 0);
        $running = (int) ($status['running_targets'] ?? 0);
        $lock_age = (int) ($status['queue_lock_age_seconds'] ?? 0);

        if ($failed > 0) {
            return 'critical';
        }
        if ($pending <= 0) {
            return 'complete';
        }
        if (empty($status['next_scheduled_at']) || ($running > 0 && $lock_age > 300)) {
            return 'warning';
        }
        return 'healthy';
    }

    /**
     * Calculates bounded queue progress.
     *
     * @param array<string,mixed> $status EEA queue status.
     * @return float
     */
    public static function progress_percent($status)
    {
        $targets   = max(0, (int) ($status['targets'] ?? 0));
        $completed = max(0, (int) ($status['completed_targets'] ?? 0));
        if (!$targets) {
            return 0.0;
        }
        return round(min(100, ($completed / $targets) * 100), 1);
    }

    /** @return void */
    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $status          = Autolex_EEA_Sync::instance()->get_status();
        $engine_coverage = Autolex_Engine_Catalog::instance()->get_coverage();
        $eu_coverage     = Autolex_EU_Catalog::instance()->get_coverage();
        $failed_tasks    = $this->get_failed_tasks();
        $health          = self::health_code($status);
        $progress        = self::progress_percent($status);
        $health_labels   = array(
            'healthy'  => __('A feldolgozás rendben fut', 'autolex-platform'),
            'warning'  => __('A feldolgozás figyelmet igényel', 'autolex-platform'),
            'critical' => __('Hibás forrásfeladatok vannak', 'autolex-platform'),
            'complete' => __('A jelenlegi feldolgozási sor kész', 'autolex-platform'),
        );
        ?>
        <div class="wrap autolex-operations">
            <h1><?php echo esc_html__('Autolex műveleti központ', 'autolex-platform'); ?></h1>
            <p><?php echo esc_html__('Az EU-járműadatok, motorváltozatok és háttérimport állapota egy helyen.', 'autolex-platform'); ?></p>

            <style>
                .autolex-operations{max-width:1280px}.alxo-status{padding:18px 20px;border-left:5px solid #2271b1;background:#fff;margin:18px 0}.alxo-status--healthy{border-color:#00a32a}.alxo-status--warning{border-color:#dba617}.alxo-status--critical{border-color:#d63638}.alxo-status--complete{border-color:#646970}.alxo-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:18px 0}.alxo-card{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:18px}.alxo-card strong{display:block;font-size:26px;line-height:1.2;margin-top:8px}.alxo-progress{height:12px;background:#dcdcde;border-radius:99px;overflow:hidden;margin-top:12px}.alxo-progress span{display:block;height:100%;background:#2271b1}.alxo-actions{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 24px}.alxo-actions form{margin:0}.alxo-table{background:#fff}.alxo-table td{vertical-align:top}.alxo-error{max-width:440px;white-space:normal;word-break:break-word}.alxo-meta{color:#646970}
            </style>

            <section class="alxo-status alxo-status--<?php echo esc_attr($health); ?>">
                <h2><?php echo esc_html($health_labels[$health]); ?></h2>
                <p>
                    <?php
                    printf(
                        esc_html__('%1$s/%2$s forrásfeladat kész, összesített előrehaladás: %3$s%%.', 'autolex-platform'),
                        esc_html(number_format_i18n((int) $status['completed_targets'])),
                        esc_html(number_format_i18n((int) $status['targets'])),
                        esc_html(number_format_i18n($progress, 1))
                    );
                    ?>
                </p>
                <div class="alxo-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr((string) $progress); ?>">
                    <span style="width:<?php echo esc_attr((string) $progress); ?>%"></span>
                </div>
                <p class="alxo-meta">
                    <?php
                    printf(
                        esc_html__('Következő ütemezett futás: %1$s · Utolsó aktivitás: %2$s', 'autolex-platform'),
                        esc_html($this->format_datetime($status['next_scheduled_at'] ?? null)),
                        esc_html($this->format_datetime($status['last_attempt_at'] ?? null))
                    );
                    ?>
                </p>
            </section>

            <div class="alxo-grid">
                <?php $this->render_metric_card(__('EU-járműváltozatok', 'autolex-platform'), (int) ($eu_coverage['vehicles'] ?? 0), __('normalizált rekord', 'autolex-platform')); ?>
                <?php $this->render_metric_card(__('Motorváltozatok', 'autolex-platform'), (int) ($engine_coverage['engine_variants'] ?? 0), __('külön motorrekord', 'autolex-platform')); ?>
                <?php $this->render_metric_card(__('Feldolgozás alatt', 'autolex-platform'), (int) $status['pending_targets'], __('függő vagy futó cél', 'autolex-platform')); ?>
                <?php $this->render_metric_card(__('Hibás célok', 'autolex-platform'), (int) $status['failed_targets'], __('kézi ellenőrzést kér', 'autolex-platform')); ?>
                <?php $this->render_metric_card(__('Beolvasott sorok', 'autolex-platform'), (int) $status['rows_read'], __('EEA forrássor', 'autolex-platform')); ?>
                <?php $this->render_metric_card(__('Járműkapcsolatok', 'autolex-platform'), (int) $status['link_proposals'], __('konzervatív javaslat', 'autolex-platform')); ?>
            </div>

            <h2><?php echo esc_html__('Biztonságos műveletek', 'autolex-platform'); ?></h2>
            <p><?php echo esc_html__('A műveletek nem törölnek jármű- vagy forrásadatot. Csak az import ütemezését és a feldolgozási sor állapotát javítják.', 'autolex-platform'); ?></p>
            <div class="alxo-actions">
                <?php $this->render_action_form('wake_queue', __('Import azonnali felébresztése', 'autolex-platform'), 'button button-primary'); ?>
                <?php $this->render_action_form('recover_stale', __('Beragadt feladatok helyreállítása', 'autolex-platform'), 'button'); ?>
                <?php if ((int) $status['failed_targets'] > 0) : ?>
                    <?php $this->render_action_form('retry_failed', __('Hibás célok újrapróbálása', 'autolex-platform'), 'button', __('A hibás célok ismét feldolgozásra kerülnek. Folytatod?', 'autolex-platform')); ?>
                <?php endif; ?>
            </div>

            <h2><?php echo esc_html__('Hibás forrásfeladatok', 'autolex-platform'); ?></h2>
            <?php if (!$failed_tasks) : ?>
                <p><?php echo esc_html__('Jelenleg nincs hibás EEA-forrásfeladat.', 'autolex-platform'); ?></p>
            <?php else : ?>
                <table class="widefat striped alxo-table">
                    <thead><tr>
                        <th><?php echo esc_html__('Típus', 'autolex-platform'); ?></th>
                        <th><?php echo esc_html__('Járműcél', 'autolex-platform'); ?></th>
                        <th><?php echo esc_html__('Év', 'autolex-platform'); ?></th>
                        <th><?php echo esc_html__('Próbálkozások', 'autolex-platform'); ?></th>
                        <th><?php echo esc_html__('Utolsó hiba', 'autolex-platform'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($failed_tasks as $task) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $task['target_type']); ?></td>
                            <td><?php echo esc_html(trim((string) $task['make'] . ' ' . (string) $task['commercial_name'])); ?></td>
                            <td><?php echo esc_html((string) $task['source_year']); ?></td>
                            <td><?php echo esc_html((string) $task['attempts']); ?></td>
                            <td class="alxo-error"><?php echo esc_html((string) $task['last_error']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /** @return void */
    public function handle_action()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Nincs jogosultságod ehhez a művelethez.', 'autolex-platform'));
        }
        check_admin_referer('autolex_operations');

        $operation = isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
        $message   = '';
        $type      = 'success';

        switch ($operation) {
            case 'wake_queue':
                wp_clear_scheduled_hook('autolex_eea_sync_batch');
                wp_schedule_single_event(time() + 5, 'autolex_eea_sync_batch');
                $message = __('Az EEA-import öt másodpercen belül újraindul.', 'autolex-platform');
                break;
            case 'recover_stale':
                $recovered = $this->recover_stale_tasks();
                wp_clear_scheduled_hook('autolex_eea_sync_batch');
                wp_schedule_single_event(time() + 5, 'autolex_eea_sync_batch');
                $message = sprintf(__('%d beragadt feladat került biztonságosan újrapróbálható állapotba.', 'autolex-platform'), $recovered);
                break;
            case 'retry_failed':
                $retried = $this->retry_failed_tasks();
                wp_clear_scheduled_hook('autolex_eea_sync_batch');
                wp_schedule_single_event(time() + 5, 'autolex_eea_sync_batch');
                $message = sprintf(__('%d hibás forrásfeladat került vissza a feldolgozási sorba.', 'autolex-platform'), $retried);
                break;
            default:
                $message = __('Ismeretlen művelet, nem történt módosítás.', 'autolex-platform');
                $type    = 'error';
        }

        set_transient('autolex_operations_notice_' . get_current_user_id(), array('message' => $message, 'type' => $type), MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=autolex-operations'));
        exit;
    }

    /** @return void */
    public function render_notice()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $key    = 'autolex_operations_notice_' . get_current_user_id();
        $notice = get_transient($key);
        if (!is_array($notice) || empty($notice['message'])) {
            return;
        }
        delete_transient($key);
        $class = 'error' === ($notice['type'] ?? '') ? 'notice notice-error' : 'notice notice-success';
        printf('<div class="%1$s is-dismissible"><p>%2$s</p></div>', esc_attr($class), esc_html((string) $notice['message']));
    }

    /** @return int */
    private function recover_stale_tasks()
    {
        global $wpdb;
        $table = Autolex_EEA_Sync::tasks_table();
        $now   = current_time('mysql', true);
        $stale = gmdate('Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS);
        $count = (int) $wpdb->query($wpdb->prepare("UPDATE {$table} SET status = 'retry', locked_at = NULL, next_run_at = %s, last_error = 'Recovered manually from a stale background lock.', updated_at = %s WHERE status = 'running' AND (locked_at IS NULL OR locked_at < %s)", $now, $now, $stale));
        $lock = (int) get_option('autolex_eea_sync_lock', 0);
        if ($lock && $lock < (time() - 5 * MINUTE_IN_SECONDS)) {
            delete_option('autolex_eea_sync_lock');
        }
        return max(0, $count);
    }

    /** @return int */
    private function retry_failed_tasks()
    {
        global $wpdb;
        $table = Autolex_EEA_Sync::tasks_table();
        $now   = current_time('mysql', true);
        $count = (int) $wpdb->query($wpdb->prepare("UPDATE {$table} SET status = 'retry', attempts = 0, locked_at = NULL, next_run_at = %s, completed_at = NULL, updated_at = %s WHERE status = 'failed'", $now, $now));
        return max(0, $count);
    }

    /** @return array<int,array<string,mixed>> */
    private function get_failed_tasks()
    {
        global $wpdb;
        $table = Autolex_EEA_Sync::tasks_table();
        $rows  = $wpdb->get_results("SELECT target_type, make, commercial_name, source_year, attempts, last_error FROM {$table} WHERE status = 'failed' ORDER BY updated_at DESC, id DESC LIMIT 20", ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    /** @return void */
    private function render_metric_card($label, $value, $description)
    {
        ?>
        <section class="alxo-card">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html(number_format_i18n($value)); ?></strong>
            <small><?php echo esc_html($description); ?></small>
        </section>
        <?php
    }

    /** @return void */
    private function render_action_form($operation, $label, $class, $confirmation = '')
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"<?php echo $confirmation ? ' onsubmit="return confirm(' . esc_attr(wp_json_encode($confirmation)) . ');"' : ''; ?>>
            <input type="hidden" name="action" value="autolex_operations_action">
            <input type="hidden" name="operation" value="<?php echo esc_attr($operation); ?>">
            <?php wp_nonce_field('autolex_operations'); ?>
            <button type="submit" class="<?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></button>
        </form>
        <?php
    }

    /** @return string */
    private function format_datetime($value)
    {
        if (!$value) {
            return __('nincs ütemezve', 'autolex-platform');
        }
        $timestamp = strtotime((string) $value . ' UTC');
        if (!$timestamp) {
            return (string) $value;
        }
        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }
}
