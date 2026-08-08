<?php
/** ALX-050C atomic same-slug theme release filesystem smoke. */

declare(strict_types=1);

require_once __DIR__ . '/../release/autolex-theme-release-filesystem.php';

$fail = static function (string $message): void {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path)) {
        return;
    }
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    $items = scandir($path);
    if (is_array($items)) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $removeTree($path . '/' . $item);
        }
    }
    @rmdir($path);
};

$root = sys_get_temp_dir() . '/autolex-theme-release-smoke-' . bin2hex(random_bytes(8));
$newSha = str_repeat('b', 40);
$oldSha = str_repeat('a', 40);

try {
    mkdir($root . '/autolex-theme', 0755, true);
    file_put_contents($root . '/autolex-theme/style.css', "/* Version: old */\n");
    file_put_contents($root . '/autolex-theme/version.txt', "old\n");
    file_put_contents($root . '/autolex-theme/.autolex-release-sha', $oldSha . "\n");

    $paths = autolex_theme_release_paths($root, $newSha);
    mkdir($paths['staged'], 0755, true);
    file_put_contents($paths['staged'] . '/style.css', "/* Version: new */\n");
    file_put_contents($paths['staged'] . '/version.txt', "new\n");
    file_put_contents($paths['staged'] . '/.autolex-release-sha', $newSha . "\n");

    $promotion = autolex_theme_release_atomic_promote($root, $newSha);
    if (($promotion['previous_release'] ?? '') !== $oldSha) {
        $fail('Promotion did not record the previous theme release SHA.');
    }
    if (empty($promotion['rollback_available']) || !autolex_theme_release_atomic_rollback_available($root, $newSha)) {
        $fail('Promotion did not create a same-slug rollback snapshot.');
    }
    if (trim((string) file_get_contents($root . '/autolex-theme/version.txt')) !== 'new') {
        $fail('Staged theme did not become active after atomic promotion.');
    }
    if (autolex_theme_release_read_marker($root . '/autolex-theme') !== $newSha) {
        $fail('Active release marker does not match the promoted SHA.');
    }
    if (trim((string) file_get_contents($paths['backup'] . '/version.txt')) !== 'old') {
        $fail('Rollback snapshot does not contain the previous theme tree.');
    }

    $rollback = autolex_theme_release_atomic_rollback($root, $newSha);
    if (($rollback['restored_release'] ?? '') !== $oldSha || ($rollback['failed_release'] ?? '') !== $newSha) {
        $fail('Rollback did not report the restored/failed release identities.');
    }
    if (trim((string) file_get_contents($root . '/autolex-theme/version.txt')) !== 'old') {
        $fail('Same-slug rollback did not restore the previous theme code.');
    }
    if (autolex_theme_release_read_marker($root . '/autolex-theme') !== $oldSha) {
        $fail('Rollback did not restore the previous release marker.');
    }
    if (!is_dir($paths['failed']) || trim((string) file_get_contents($paths['failed'] . '/version.txt')) !== 'new') {
        $fail('Failed release tree was not preserved for forensics.');
    }
    if (autolex_theme_release_atomic_rollback_available($root, $newSha)) {
        $fail('Consumed rollback snapshot must not remain reported as available.');
    }

    echo "theme-release-filesystem-smoke: OK\n";
} finally {
    $removeTree($root);
}
