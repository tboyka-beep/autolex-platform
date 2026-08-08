<?php
/**
 * Pure filesystem helpers for reversible Autolex theme code releases.
 *
 * This file intentionally has no WordPress dependency so the atomic directory
 * swap and same-slug rollback can be exercised in CI against temporary trees.
 */

declare(strict_types=1);

function autolex_theme_release_assert_sha(string $sha): void
{
    if (!preg_match('/^[a-f0-9]{40}$/', $sha)) {
        throw new InvalidArgumentException('A teljes 40 karakteres release SHA kötelező.');
    }
}

/** @return array{active:string,stage_root:string,staged:string,backup_root:string,backup:string,failed_root:string,failed:string} */
function autolex_theme_release_paths(string $themeRoot, string $releaseSha): array
{
    autolex_theme_release_assert_sha($releaseSha);
    $root = rtrim($themeRoot, '/\\');
    if ($root === '' || !is_dir($root)) {
        throw new RuntimeException('A theme gyökérkönyvtár nem érhető el.');
    }

    return [
        'active'      => $root . '/autolex-theme',
        'stage_root'  => $root . '/.autolex-release/' . $releaseSha,
        'staged'      => $root . '/.autolex-release/' . $releaseSha . '/autolex-theme',
        'backup_root' => $root . '/.autolex-rollback/' . $releaseSha,
        'backup'      => $root . '/.autolex-rollback/' . $releaseSha . '/autolex-theme',
        'failed_root' => $root . '/.autolex-failed/' . $releaseSha,
        'failed'      => $root . '/.autolex-failed/' . $releaseSha . '/autolex-theme',
    ];
}

function autolex_theme_release_read_marker(string $themeDir): string
{
    $marker = rtrim($themeDir, '/\\') . '/.autolex-release-sha';
    if (!is_file($marker) || !is_readable($marker)) {
        return '';
    }
    $value = trim((string) file_get_contents($marker));
    return preg_match('/^[a-f0-9]{40}$/', $value) ? $value : '';
}

function autolex_theme_release_mkdir(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    if (!mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException('Nem hozható létre release könyvtár: ' . basename($path));
    }
}

/**
 * Atomically promotes a fully staged theme tree while retaining the previous
 * same-slug code tree as a rollback snapshot.
 *
 * @return array{previous_release:string,rollback_available:bool}
 */
function autolex_theme_release_atomic_promote(string $themeRoot, string $releaseSha): array
{
    $paths = autolex_theme_release_paths($themeRoot, $releaseSha);
    if (!is_dir($paths['active'])) {
        throw new RuntimeException('Az aktív Autolex theme könyvtár hiányzik.');
    }
    if (!is_dir($paths['staged'])) {
        throw new RuntimeException('A staged Autolex theme könyvtár hiányzik.');
    }
    if (!is_file($paths['staged'] . '/style.css')) {
        throw new RuntimeException('A staged theme style.css fájlja hiányzik.');
    }
    if (autolex_theme_release_read_marker($paths['staged']) !== $releaseSha) {
        throw new RuntimeException('A staged theme release SHA markere hibás.');
    }
    if (file_exists($paths['backup_root'])) {
        throw new RuntimeException('Ehhez a release SHA-hoz már létezik rollback snapshot.');
    }
    if (file_exists($paths['failed_root'])) {
        throw new RuntimeException('Ehhez a release SHA-hoz már létezik failed release snapshot.');
    }

    $previousRelease = autolex_theme_release_read_marker($paths['active']);
    autolex_theme_release_mkdir(dirname($paths['backup_root']));
    autolex_theme_release_mkdir($paths['backup_root']);

    if (!rename($paths['active'], $paths['backup'])) {
        @rmdir($paths['backup_root']);
        throw new RuntimeException('Az aktív theme rollback snapshotba mozgatása sikertelen.');
    }

    if (!rename($paths['staged'], $paths['active'])) {
        $restored = @rename($paths['backup'], $paths['active']);
        @rmdir($paths['backup_root']);
        if (!$restored) {
            throw new RuntimeException('A staged theme aktiválása és az automatikus fájl-visszaállítás is sikertelen.');
        }
        throw new RuntimeException('A staged theme aktiválása sikertelen; az előző kód visszaállt.');
    }

    @rmdir($paths['stage_root']);
    if (autolex_theme_release_read_marker($paths['active']) !== $releaseSha) {
        // The promoted tree is unexpected. Attempt an immediate same-slug code rollback.
        autolex_theme_release_atomic_rollback($themeRoot, $releaseSha);
        throw new RuntimeException('Az aktivált theme release SHA markere nem igazolható; rollback lefutott.');
    }

    return [
        'previous_release'   => $previousRelease,
        'rollback_available' => is_dir($paths['backup']),
    ];
}

/**
 * Restores the previous same-slug theme code tree and preserves the failed
 * release for forensics instead of deleting it.
 *
 * @return array{restored_release:string,failed_release:string}
 */
function autolex_theme_release_atomic_rollback(string $themeRoot, string $releaseSha): array
{
    $paths = autolex_theme_release_paths($themeRoot, $releaseSha);
    if (!is_dir($paths['active'])) {
        throw new RuntimeException('Rollback előtt az aktív theme könyvtár hiányzik.');
    }
    if (!is_dir($paths['backup'])) {
        throw new RuntimeException('Ehhez a release-hez nincs visszaállítható theme snapshot.');
    }
    if (file_exists($paths['failed_root'])) {
        throw new RuntimeException('A failed release célkönyvtár már létezik; automatikus felülírás tiltott.');
    }

    $failedRelease = autolex_theme_release_read_marker($paths['active']);
    $restoredRelease = autolex_theme_release_read_marker($paths['backup']);
    autolex_theme_release_mkdir(dirname($paths['failed_root']));
    autolex_theme_release_mkdir($paths['failed_root']);

    if (!rename($paths['active'], $paths['failed'])) {
        @rmdir($paths['failed_root']);
        throw new RuntimeException('Az aktuális release failed snapshotba mozgatása sikertelen.');
    }

    if (!rename($paths['backup'], $paths['active'])) {
        $restoredCurrent = @rename($paths['failed'], $paths['active']);
        @rmdir($paths['failed_root']);
        if (!$restoredCurrent) {
            throw new RuntimeException('A rollback és az aktuális release helyreállítása is sikertelen.');
        }
        throw new RuntimeException('A rollback snapshot visszaállítása sikertelen; az aktuális release visszaállt.');
    }

    @rmdir($paths['backup_root']);
    return [
        'restored_release' => $restoredRelease,
        'failed_release'   => $failedRelease,
    ];
}

function autolex_theme_release_atomic_rollback_available(string $themeRoot, string $releaseSha): bool
{
    try {
        $paths = autolex_theme_release_paths($themeRoot, $releaseSha);
    } catch (Throwable $error) {
        return false;
    }
    return is_dir($paths['backup']) && is_dir($paths['active']);
}
