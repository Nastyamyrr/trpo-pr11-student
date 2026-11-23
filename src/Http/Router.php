<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Router: сопоставляет метод и путь с обработчиком (действием контроллера).
 * Ничего не выводит и не знает про HTML: ответы 404 и 405 формируют
 * обработчики, которые регистрирует Application (ErrorController).
 *
 * Шаблон пути: "/catalog/{id}" — {id} это целое число, передаётся в обработчик как int.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable}> */
    private array $routes = [];

    /** @var callable|null */
    private $notFoundHandler = null;

    /** @var callable|null */
    private $methodNotAllowedHandler = null;

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /** Единый обработчик неизвестного маршрута: fn(): void */
    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /** Обработчик «путь есть, метод не тот»: fn(list<string> $allowedMethods): void */
    public function setMethodNotAllowedHandler(callable $handler): void
    {
        $this->methodNotAllowedHandler = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = self::normalizePath($uri);
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] === strtoupper($method)) {
                $params = array_map('intval', array_slice($matches, 1));
                ($route['handler'])(...$params);
                return;
            }

            $allowed[] = $route['method'];
        }

        if ($allowed !== [] && $this->methodNotAllowedHandler !== null) {
            ($this->methodNotAllowedHandler)(array_values(array_unique($allowed)));
            return;
        }

        if ($this->notFoundHandler !== null) {
            ($this->notFoundHandler)();
        }
    }

    /**
     * "/index.php/cart/?x=1" → "/cart"; "" → "/".
     */
    public static function normalizePath(string $uri): string
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $path = rtrim($path, '/') ?: '/';

        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
        }

        return $path;
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{[a-z_]+\}#i', '(\d+)', $pattern) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler];
    }
}
