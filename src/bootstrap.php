<?php

declare(strict_types=1);

/**
 * Общий bootstrap: только автозагрузка классов (namespace App\...).
 * Session здесь НЕ запускается: её стартует точка входа public/index.php,
 * поэтому тесты в CLI могут подключать этот файл без session.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// Функции-помощники для views (e() — экранирование)
require_once __DIR__ . '/Support/functions.php';
