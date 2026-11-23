<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CartService;
use App\Support\Redirect;
use App\Support\View;

/**
 * Контроллер корзины.
 * Изменяет состояние через CartService, затем redirect (POST → Redirect → GET).
 * View только отображает готовую корзину.
 */
final class CartController
{
    public function __construct(
        private readonly CartService $cart,
    ) {
    }

    /**
     * GET /cart — показать корзину.
     */
    public function index(): void
    {
        View::render('cart/index', [
            'lines' => $this->cart->lines(),
            'total' => $this->cart->total(),
            'formattedTotal' => $this->cart->formattedTotal(),
            'isEmpty' => $this->cart->isEmpty(),
            'flash' => $this->cart->getFlash(),
            'cartCount' => $this->cart->count(),
        ]);
    }

    /**
     * POST /cart/add — добавить товар.
     */
    public function add(): void
    {
        $productId = (int) ($_POST['product_id'] ?? 0);
        if ($productId <= 0 || !$this->cart->add($productId)) {
            $this->cart->setFlash('Не удалось добавить товар. Проверьте идентификатор.');
        }

        $redirect = $_POST['redirect'] ?? '/cart';
        $this->redirect(is_string($redirect) ? $redirect : '/cart');
    }

    /**
     * POST /cart/update — изменить количество.
     */
    public function update(): void
    {
        $productId = (int) ($_POST['product_id'] ?? 0);

        // Количество — положительное целое 1..99 (0 и "abc" больше не удаляют молча)
        $quantity = filter_var(
            $_POST['quantity'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 99]]
        );

        if ($productId <= 0) {
            $this->cart->setFlash('Некорректный идентификатор товара.');
            $this->redirect('/cart');
        }

        if ($quantity === false) {
            $this->cart->setFlash('Количество должно быть целым числом от 1 до 99.');
            $this->redirect('/cart');
        }

        if (!$this->cart->setQuantity($productId, $quantity)) {
            $this->cart->setFlash('Товар не найден.');
        }
        $this->redirect('/cart');
    }

    /**
     * POST /cart/remove — удалить позицию.
     */
    public function remove(): void
    {
        $productId = (int) ($_POST['product_id'] ?? 0);
        if ($productId > 0) {
            $this->cart->remove($productId);
        }

        $this->redirect('/cart');
    }

    /**
     * POST /cart/clear — очистить корзину.
     */
    public function clear(): void
    {
        $this->cart->clear();
        $this->redirect('/cart');
    }

    private function redirect(string $url): never
    {
        Redirect::to($url, '/cart');
    }
}
