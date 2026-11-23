<?php

declare(strict_types=1);

namespace App\Support;

/**
 * HTTP-редирект (часть POST → Redirect → GET).
 * Разрешает только пути внутри приложения, чтобы нельзя было
 * подставить внешний адрес через скрытое поле redirect (open redirect).
 */
final class Redirect
{
    public static function isSafePath(string $url): bool
    {
        // Только "/path": без "//host" (protocol-relative), "\" и управляющих символов
        return preg_match('#^/(?![/\\\\])[^\x00-\x1F\x7F\\\\]*$#', $url) === 1;
    }

    public static function to(string $url, string $fallback = '/cart'): never
    {
        if (!self::isSafePath($url)) {
            $url = $fallback;
        }
        header('Location: ' . $url);
        exit;
    }
}
