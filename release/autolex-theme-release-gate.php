<?php
/**
 * Plugin Name: Autolex Theme Release Gate
 * Description: Authenticated release gate for atomic Autolex theme activation and same-slug code rollback.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/autolex-theme-release-filesystem.php';

const AUTOLEX_THEME_RELEASE_TOKEN = '__AUTOLEX_RELEASE_TOKEN__';
const AUTOLEX_THEME_RELEASE_SLUG = 'autolex-theme';
const AUTOLEX_THEME_RELEASE_OPTION = 'autolex_theme_release_state';

function autolex_theme_release_authorized(WP_REST_Request $request): bool
{
    $provided = (string) $request->get_header('x-autolex-release-token');

    return $provided !== ''
        && strlen(AUTOLEX_THEME_RELEASE_TOKEN) >= 32
        && hash_equals(AUTOLEX_THEME_RELEASE_TOKEN, $provided);
}

function autolex_theme_release_theme_root(): string
{
    return rtrim((string) WP_CONTENT_DIR, '/\\') . '/themes';
}

function autolex_theme_release_refresh_runtime(): void
{
    if (function_exists('wp_clean_themes_cache')) {
        wp_clean_themes_cache(true);
    }
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
}

/** @param mixed $value @return array<string,mixed> */
function autolex_theme_release_saved_state($value): array
{
    return is_array($value) ? $value : [];
}

/** @return array{available:bool,mode:string,release_sha:string} */
function autolex_theme_release_rollback_state(): array
{
    $saved = autolex_theme_release_saved_state(get_option(AUTOLEX_THEME_RELEASE_OPTION, []));
    $mode = sanitize_key((string) ($saved['mode'] ?? ''));
    $releaseSha = sanitize_text_field((string) ($saved['release_sha'] ?? ''));
    $available = false;

    if ($mode === 'atomic_code_swap' && preg_match('/^[a-f0-9]{40}$/', $releaseSha)) {
        $available = autolex_theme_release_atomic_rollback_available(
            autolex_theme_release_theme_root(),
            $releaseSha
        );
    } elseif ($mode === 'theme_switch') {
        $previous = sanitize_key((string) ($saved['previous_stylesheet'] ?? ''));
        if ($previous !== '' && $previous !== AUTOLEX_THEME_RELEASE_SLUG) {
            $theme = wp_get_theme($previous);
            $available = $theme->exists() && !$theme->errors();
        }
    }

    return [
        'available'   => $available,
        'mode'        => $mode,
        'release_sha' => $releaseSha,
    ];
}

/** @return array<string,mixed> */
function autolex_theme_release_state(): array
{
    $theme = wp_get_theme();
    $stylesheet = (string) get_option('stylesheet');
    $version = trim((string) $theme->get('Version'));
    $designMarker = $stylesheet === AUTOLEX_THEME_RELEASE_SLUG
        ? AUTOLEX_THEME_RELEASE_SLUG . '@' . ($version !== '' ? $version : 'unversioned')
        : '';

    return [
        'template'      => (string) get_option('template'),
        'stylesheet'    => $stylesheet,
        'release'       => (string) get_option('autolex_theme_release_sha', ''),
        'design_marker' => $designMarker,
        'code_release'  => autolex_theme_release_read_marker(
            autolex_theme_release_theme_root() . '/' . AUTOLEX_THEME_RELEASE_SLUG
        ),
        'rollback'      => autolex_theme_release_rollback_state(),
    ];
}

/** @param string $previousRelease */
function autolex_theme_release_restore_release_option(string $previousRelease): void
{
    if ($previousRelease !== '') {
        update_option('autolex_theme_release_sha', $previousRelease, false);
    } else {
        delete_option('autolex_theme_release_sha');
    }
}

/**
 * Executes the saved rollback plan. Supports both the legacy isolated
 * different-theme proof and the production same-slug atomic code rollback.
 *
 * @return array<string,mixed>|WP_Error
 */
function autolex_theme_release_execute_rollback()
{
    $saved = autolex_theme_release_saved_state(get_option(AUTOLEX_THEME_RELEASE_OPTION, []));
    $mode = sanitize_key((string) ($saved['mode'] ?? ''));
    $previous = sanitize_key((string) ($saved['previous_stylesheet'] ?? ''));
    $previousRelease = sanitize_text_field((string) ($saved['previous_release'] ?? ''));
    $releaseSha = sanitize_text_field((string) ($saved['release_sha'] ?? ''));

    if ($mode === '' || $previous === '' || !preg_match('/^[a-f0-9]{40}$/', $releaseSha)) {
        return new WP_Error('rollback_unavailable', 'Nincs biztonságos release rollback állapot.', ['status' => 409]);
    }

    try {
        $filesystem = [];
        if ($mode === 'atomic_code_swap') {
            if (!autolex_theme_release_atomic_rollback_available(autolex_theme_release_theme_root(), $releaseSha)) {
                return new WP_Error('rollback_unavailable', 'A korábbi Autolex theme kódsnapshot nem érhető el.', ['status' => 409]);
            }
            $filesystem = autolex_theme_release_atomic_rollback(
                autolex_theme_release_theme_root(),
                $releaseSha
            );
            autolex_theme_release_refresh_runtime();
        } elseif ($mode !== 'theme_switch') {
            return new WP_Error('rollback_mode_invalid', 'Ismeretlen release rollback mód.', ['status' => 409]);
        }

        if ($previous !== AUTOLEX_THEME_RELEASE_SLUG) {
            $theme = wp_get_theme($previous);
            if (!$theme->exists() || $theme->errors()) {
                return new WP_Error('rollback_theme_unavailable', 'A korábbi téma nem állítható vissza.', ['status' => 409]);
            }
            switch_theme($previous);
        }

        autolex_theme_release_restore_release_option($previousRelease);
        delete_option(AUTOLEX_THEME_RELEASE_OPTION);
        autolex_theme_release_refresh_runtime();

        $after = autolex_theme_release_state();
        if ($after['stylesheet'] !== $previous || $after['release'] !== $previousRelease) {
            return new WP_Error('rollback_failed', 'A rollback végállapota nem igazolható.', ['status' => 500]);
        }
        if ($mode === 'atomic_code_swap' && ($after['code_release'] ?? '') !== ($filesystem['restored_release'] ?? '')) {
            return new WP_Error('rollback_code_mismatch', 'A visszaállított theme kód release markere eltér.', ['status' => 500]);
        }

        return [
            'status'     => 'rolled_back',
            'mode'       => $mode,
            'filesystem' => $filesystem,
            'after'      => $after,
        ];
    } catch (Throwable $error) {
        return new WP_Error('rollback_exception', $error->getMessage(), ['status' => 500]);
    }
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

            $before = autolex_theme_release_state();
            if (
                $before['stylesheet'] === AUTOLEX_THEME_RELEASE_SLUG
                && $before['release'] === $releaseSha
                && $before['code_release'] === $releaseSha
            ) {
                return new WP_REST_Response([
                    'status' => 'activated',
                    'idempotent' => true,
                    'before' => $before,
                    'after' => $before,
                    'rollback' => $before['rollback'],
                ]);
            }

            $paths = autolex_theme_release_paths(autolex_theme_release_theme_root(), $releaseSha);
            $hasStagedCode = is_dir($paths['staged']);
            $mode = $hasStagedCode ? 'atomic_code_swap' : 'theme_switch';
            if (!$hasStagedCode && $before['stylesheet'] === AUTOLEX_THEME_RELEASE_SLUG) {
                return new WP_Error(
                    'staged_release_missing',
                    'Aktív Autolex theme frissítéséhez kötelező a teljes staged kódfa.',
                    ['status' => 409]
                );
            }

            $theme = wp_get_theme(AUTOLEX_THEME_RELEASE_SLUG);
            if (!$theme->exists() || $theme->errors()) {
                return new WP_Error('theme_unavailable', 'Az Autolex téma nem elérhető vagy hibás.', ['status' => 409]);
            }

            $saved = [
                'mode'                => $mode,
                'previous_template'   => $before['template'],
                'previous_stylesheet' => $before['stylesheet'],
                'previous_release'    => $before['release'],
                'release_sha'         => $releaseSha,
                'recorded_at'         => gmdate('c'),
            ];
            update_option(AUTOLEX_THEME_RELEASE_OPTION, $saved, false);

            $promoted = false;
            try {
                $filesystem = [];
                if ($mode === 'atomic_code_swap') {
                    $filesystem = autolex_theme_release_atomic_promote(
                        autolex_theme_release_theme_root(),
                        $releaseSha
                    );
                    $promoted = true;
                    autolex_theme_release_refresh_runtime();
                }

                if ($before['stylesheet'] !== AUTOLEX_THEME_RELEASE_SLUG) {
                    switch_theme(AUTOLEX_THEME_RELEASE_SLUG);
                }
                update_option('autolex_theme_release_sha', $releaseSha, false);
                autolex_theme_release_refresh_runtime();

                $after = autolex_theme_release_state();
                if (
                    $after['stylesheet'] !== AUTOLEX_THEME_RELEASE_SLUG
                    || $after['release'] !== $releaseSha
                    || ($mode === 'atomic_code_swap' && $after['code_release'] !== $releaseSha)
                    || empty($after['rollback']['available'])
                ) {
                    throw new RuntimeException('A theme aktiválása vagy rollback-előkészítése nem igazolható.');
                }

                return new WP_REST_Response([
                    'status'     => 'activated',
                    'mode'       => $mode,
                    'filesystem' => $filesystem,
                    'before'     => $before,
                    'after'      => $after,
                    'rollback'   => $after['rollback'],
                ]);
            } catch (Throwable $error) {
                if ($promoted && autolex_theme_release_atomic_rollback_available(autolex_theme_release_theme_root(), $releaseSha)) {
                    try {
                        autolex_theme_release_atomic_rollback(autolex_theme_release_theme_root(), $releaseSha);
                        autolex_theme_release_refresh_runtime();
                    } catch (Throwable $rollbackError) {
                        return new WP_Error(
                            'activation_and_rollback_failed',
                            $error->getMessage() . ' / Rollback: ' . $rollbackError->getMessage(),
                            ['status' => 500]
                        );
                    }
                }
                if ($before['stylesheet'] !== AUTOLEX_THEME_RELEASE_SLUG) {
                    $previousTheme = wp_get_theme((string) $before['stylesheet']);
                    if ($previousTheme->exists() && !$previousTheme->errors()) {
                        switch_theme((string) $before['stylesheet']);
                    }
                }
                autolex_theme_release_restore_release_option((string) $before['release']);
                delete_option(AUTOLEX_THEME_RELEASE_OPTION);
                autolex_theme_release_refresh_runtime();

                return new WP_Error('activation_failed_rolled_back', $error->getMessage(), ['status' => 500]);
            }
        },
    ]);

    register_rest_route('autolex-release/v1', '/rollback', [
        'methods' => WP_REST_Server::CREATABLE,
        'permission_callback' => 'autolex_theme_release_authorized',
        'callback' => static function (): WP_REST_Response|WP_Error {
            $result = autolex_theme_release_execute_rollback();
            return is_wp_error($result) ? $result : new WP_REST_Response($result);
        },
    ]);
});
