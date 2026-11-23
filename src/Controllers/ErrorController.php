<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CartService;
use App\Support\View;
use Throwable;

/**
 * Страницы ошибок: 404, 405 и 500. Выбор view и статуса — здесь,
 * Router и Application HTML не формируют.
 */
final class ErrorController
{
    public function __construct(
        private readonly CartService $cart,
    ) {
    }

    public function notFound(string $message = 'Запрошенная страница не найдена.'): void
    {
        http_response_code(404);
        $this->render(404, 'Страница не найдена', $message);
    }

    /**
     * @param list<string> $allowed
     */
    public function methodNotAllowed(array $allowed): void
    {
        http_response_code(405);
        header('Allow: ' . implode(', ', $allowed));
        $this->render(405, 'Метод не поддерживается', 'Для этого адреса разрешено: ' . implode(', ', $allowed) . '.');
    }

    /**
     * В локальном режиме ($debug) показываем текст исключения, иначе — общее сообщение.
     */
    public function serverError(Throwable $e, bool $debug): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }

        $details = $debug ? get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : null;
        $this->render(500, 'Внутренняя ошибка', 'Что-то пошло не так. Попробуйте позже.', $details);
    }

    private function render(int $code, string $title, string $message, ?string $details = null): void
    {
        View::render('error', [
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'details' => $details,
            'cartCount' => $this->safeCount(),
            'flash' => null, // flash не расходуем на странице ошибки
        ]);
    }

    private function safeCount(): int
    {
        try {
            return $this->cart->count();
        } catch (Throwable) {
            return 0; // сама ошибка могла быть в корзине
        }
    }
}
