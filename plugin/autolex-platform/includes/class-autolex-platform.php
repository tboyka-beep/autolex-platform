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
        Autolex_EU_Catalog::install_schema();
    }

    /**
     * Registers WordPress hooks.
     */
    private function __construct()
    {
        Autolex_EU_Catalog::instance();
        Autolex_EEA_Importer::register();

        add_action('admin_menu', array($this, 'register_admin_page'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'), 30);
    }

    /**
     * Loads the shared Autolex visual layer after the legacy Blocksy styles.
     *
     * The selectors in this stylesheet are deliberately scoped to existing
     * Autolex components so regular WordPress and Blocksy screens stay intact.
     *
     * @return void
     */
    public function enqueue_public_assets()
    {
        wp_enqueue_style(
            'autolex-platform-experience',
            plugins_url('assets/css/autolex-experience.css', AUTOLEX_PLATFORM_FILE),
            array(),
            AUTOLEX_PLATFORM_VERSION
        );
    }

    /**
     * Registers public, read-only platform endpoints.
     *
     * @return void
     */
    public function register_rest_routes()
    {
        register_rest_route(
            'autolex/v1',
            '/status',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_platform_status'),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'autolex/v1',
            '/eu-coverage',
            array(
                'methods'             => 'GET',
                'callback'            => array(Autolex_EU_Catalog::instance(), 'get_coverage_response'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Returns a minimal health response without exposing sensitive data.
     *
     * @return WP_REST_Response
     */
    public function get_platform_status()
    {
        return rest_ensure_response(
            array(
                'service'      => 'autolex-platform',
                'status'       => 'ok',
                'version'      => AUTOLEX_PLATFORM_VERSION,
                'generated_at' => gmdate('c'),
            )
        );
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
            <p>
                <?php echo esc_html__('Rendszerállapot:', 'autolex-platform'); ?>
                <a href="<?php echo esc_url(rest_url('autolex/v1/status')); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html(rest_url('autolex/v1/status')); ?>
                </a>
            </p>
            <?php $coverage = Autolex_EU_Catalog::instance()->get_coverage(); ?>
            <h2><?php echo esc_html__('EU-katalógus', 'autolex-platform'); ?></h2>
            <p>
                <?php
                printf(
                    /* translators: 1: vehicle count, 2: make count, 3: model count. */
                    esc_html__('%1$s járműváltozat, %2$s márka és %3$s modell az új, ellenőrzött EU-adatmagban.', 'autolex-platform'),
                    esc_html(number_format_i18n($coverage['vehicles'])),
                    esc_html(number_format_i18n($coverage['makes'])),
                    esc_html(number_format_i18n($coverage['models']))
                );
                ?>
            </p>
            <p>
                <a href="<?php echo esc_url(rest_url('autolex/v1/eu-coverage')); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html(rest_url('autolex/v1/eu-coverage')); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
