<?php

declare(strict_types=1);

/**
 * Единственная точка входа приложения.
 * Только: session → автозагрузка → запуск Application.
 * Маршруты — в App\Application, HTML — во views.
 */

// Session стартует централизованно здесь; cookie без доступа из JS
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require dirname(__DIR__) . '/src/bootstrap.php';

// Локальный режим: встроенный сервер (php -S) или APP_DEBUG=1 — показываем детали ошибок
$debug = PHP_SAPI === 'cli-server' || getenv('APP_DEBUG') === '1';

(new App\Application(dirname(__DIR__), $debug))->run();
