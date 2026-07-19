<?php
/**
 * Autolex Platform bootstrap class.
 *
 * @package Autolex_Platform
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Autolex_Platform
{
    /**
     * Shared plugin instance.
     *
     * @var Autolex_Platform|null
     */
    private static $instance = null;

    /**
     * Returns the shared plugin instance.
     *
     * @return Autolex_Platform
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Stores the installed version without modifying site content.
     *
     * @return void
     */
    public static function activate()
    {
        update_option('autolex_platform_version', AUTOLEX_PLATFORM_VERSION, false);
    }

    /**
     * Registers WordPress hooks.
     */
    private function __construct()
    {
        add_action('admin_menu', array($this, 'register_admin_page'));
    }

    /**
     * Adds the platform status page.
     *
     * @return void
     */
    public function register_admin_page()
    {
        add_menu_page(
            __('Autolex Platform', 'autolex-platform'),
            __('Autolex Platform', 'autolex-platform'),
            'manage_options',
            'autolex-platform',
            array($this, 'render_admin_page'),
            'dashicons-car',
            26
        );
    }

    /**
     * Renders the minimal platform status screen.
     *
     * @return void
     */
    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Autolex Platform', 'autolex-platform'); ?></h1>
            <p><?php echo esc_html__('A GitHub-alapú fejlesztési rendszer aktív.', 'autolex-platform'); ?></p>
            <p>
                <?php
                printf(
                    '%s %s',
                    esc_html__('Verzió:', 'autolex-platform'),
                    esc_html(AUTOLEX_PLATFORM_VERSION)
                );
                ?>
            </p>
        </div>
        <?php
    }
}
