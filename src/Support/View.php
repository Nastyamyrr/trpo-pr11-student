<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Рендер представлений: view -> $content -> layout.
 * Контроллеры не дублируют код буферизации, View только подключает шаблоны.
 */
final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = []): void
    {
        $root = dirname(__DIR__, 2) . '/views/';
        $viewFile = $root . $view . '.php';

        if (preg_match('#^[a-z0-9_/-]+$#i', $view) !== 1 || !is_file($viewFile)) {
            http_response_code(500);
            echo 'View not found';
            return;
        }

        $data += ['cartCount' => 0, 'flash' => null];

        $content = self::capture($viewFile, $data);
        self::capture($root . 'layouts/main.php', $data + ['content' => $content], true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $file, array $data, bool $echo = false): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean(); // не оставляем недорисованный буфер, ошибку обработает Application
            throw $e;
        }
        $html = (string) ob_get_clean();

        if ($echo) {
            echo $html;
        }
        return $html;
    }
}
