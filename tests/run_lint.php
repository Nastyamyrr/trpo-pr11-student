<?php

declare(strict_types=1);

/**
 * PHP lint (php -l) для всех php-файлов проекта (шаг 13 ПР-11).
 * Запуск: php tests/run_lint.php
 */

$root = dirname(__DIR__);
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if ($file->getExtension() === 'php' && !str_contains($path, '/.git/') && !str_contains($path, '/vendor/')) {
        $files[] = $path;
    }
}
sort($files);

$failed = 0;
foreach ($files as $path) {
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    $relative = substr($path, strlen($root) + 1);
    if ($code !== 0) {
        $failed++;
        echo "[FAIL] $relative\n    " . implode("\n    ", $output) . "\n";
    }
}

printf("Проверено php-файлов: %d, ошибок синтаксиса: %d\n", count($files), $failed);
exit($failed === 0 ? 0 : 1);
