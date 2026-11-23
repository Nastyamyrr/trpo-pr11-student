<?php

declare(strict_types=1);

/**
 * Глобальные функции-помощники для представлений.
 * Подключается один раз из src/bootstrap.php (автозагрузка функции не умеет).
 */

if (!function_exists('e')) {
    /**
     * Единое экранирование вывода в HTML (защита от XSS).
     * Все пользовательские и внешние строки в views выводятся только через e().
     */
    function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
