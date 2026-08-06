<?php
/**
 * Plugin Name: Autolex Theme Release Gate
 * Description: Temporary authenticated release gate for atomic Autolex theme activation and rollback.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const AUTOLEX_THEME_RELEASE_TOKEN = '__AUTOLEX_RELEASE_TOKEN__';
const AUTOLEX_THEME_RELEASE_SLUG = 'autolex-theme';
const AUTOLEX_THEME_RELEASE_OPTION = 'autolex_theme_release_state';

function autolex_theme_release_authorized(WP_REST_Request $request): bool
{
    $provided = (string) $request->get_header('x-autolex-release-token');

    return $provided !== ''
        && AUTOLEX_THEME_RELEASE_TOKEN !== '__AUTOLEX_RELEASE_TOKEN__'
        && hash_equals(AUTOLEX_THEME_RELEASE_TOKEN, $provided);
}

function autolex_theme_release_state(): array
{
    $theme = wp_get_theme();
    $stylesheet = (string) get_option('stylesheet');
    $version = trim((string) $theme->get('Version'));
    $designMarker = $stylesheet === AUTOLEX_THEME_RELEASE_SLUG
        ? AUTOLEX_THEME_RELEASE_SLUG . '@' . ($version !== '' ? $version : 'unversioned')
        : '';

    return [
        'template' => (string) get_option('template'),
        'stylesheet' => $stylesheet,
        'release' => (string) get_option('autolex_theme_release_sha', ''),
        'design_marker' => $designMarker,
    ];
}

add_action('rest_api_init', static function (): void {
    register_rest_route('autolex-release/v1', '/theme-state', [
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => 'autolex_theme_release_authorized',
        'callback' => static function (): WP_REST_Response {
            return new WP_REST_Response([
                'status' => 'ok',
                'state' => autolex_theme_release_state(),
            ]);
        },
    ]);

    register_rest_route('autolex-release/v1', '/activate', [
        'methods' => WP_REST_Server::CREATABLE,
        'permission_callback' => 'autolex_theme_release_authorized',
        'callback' => static function (WP_REST_Request $request): WP_REST_Response|WP_Error {
            $releaseSha = sanitize_text_field((string) $request->get_param('release_sha'));
            if (!preg_match('/^[a-f0-9]{40}$/', $releaseSha)) {
                return new WP_Error('invalid_release_sha', 'A teljes 40 karakteres release SHA kötelező.', ['status' => 400]);
            }

            $theme = wp_get_theme(AUTOLEX_THEME_RELEASE_SLUG);
            if (!$theme->exists() || $theme->errors()) {
                return new WP_Error('theme_unavailable', 'Az Autolex téma nem elérhető vagy hibás.', ['status' => 409]);
            }

            $before = autolex_theme_release_state();
            update_option(AUTOLEX_THEME_RELEASE_OPTION, [
                'previous_template' => $before['template'],
                'previous_stylesheet' => $before['stylesheet'],
                'release_sha' => $releaseSha,
                'recorded_at' => gmdate('c'),
            ], false);

            switch_theme(AUTOLEX_THEME_RELEASE_SLUG);
            update_option('autolex_theme_release_sha', $releaseSha, false);

            $after = autolex_theme_release_state();
            if ($after['stylesheet'] !== AUTOLEX_THEME_RELEASE_SLUG) {
                return new WP_Error('activation_failed', 'A téma aktiválása nem igazolható.', ['status' => 500]);
            }

            return new WP_REST_Response([
                'status' => 'activated',
                'before' => $before,
                'after' => $after,
            ]);
        },
    ]);

    register_rest_route('autolex-release/v1', '/rollback', [
        'methods' => WP_REST_Server::CREATABLE,
        'permission_callback' => 'autolex_theme_release_authorized',
        'callback' => static function (): WP_REST_Response|WP_Error {
            $saved = get_option(AUTOLEX_THEME_RELEASE_OPTION, []);
            $previous = is_array($saved) ? sanitize_key((string) ($saved['previous_stylesheet'] ?? '')) : '';

            if ($previous === '' || $previous === AUTOLEX_THEME_RELEASE_SLUG) {
                return new WP_Error('rollback_unavailable', 'Nincs biztonságos előző téma rögzítve.', ['status' => 409]);
            }

            $theme = wp_get_theme($previous);
            if (!$theme->exists() || $theme->errors()) {
                return new WP_Error('rollback_theme_unavailable', 'A korábbi téma nem állítható vissza.', ['status' => 409]);
            }

            switch_theme($previous);
            delete_option('autolex_theme_release_sha');

            $after = autolex_theme_release_state();
            if ($after['stylesheet'] !== $previous) {
                return new WP_Error('rollback_failed', 'A rollback nem igazolható.', ['status' => 500]);
            }

            return new WP_REST_Response([
                'status' => 'rolled_back',
                'after' => $after,
            ]);
        },
    ]);
});
