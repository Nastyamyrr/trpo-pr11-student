<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Единственная обёртка над $_SESSION.
 * Сервисы и модели не работают с глобальными переменными напрямую:
 * они получают Session через конструктор. Сама session стартует в public/index.php.
 */
final class Session
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Прочитать значение и сразу удалить (flash-сообщения).
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }
}
