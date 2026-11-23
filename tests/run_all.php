<?php

declare(strict_types=1);

/**
 * Запускает все прогонщики сценариев подряд.
 * Запуск: php tests/run_all.php
 */

$exit = 0;
foreach (['run_lint.php', 'run_architecture_checks.php', 'run_catalog_cases.php', 'run_cart_scenarios.php', 'run_order_cases.php', 'run_route_tests.php', 'run_security_cases.php'] as $script) {
    $path = __DIR__ . '/' . $script;
    if (!is_file($path)) {
        continue; // прогонщик ещё не добавлен
    }
    echo "=== $script ===\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path), $code);
    echo "\n";
    $exit = $exit | $code;
}
exit($exit === 0 ? 0 : 1);
