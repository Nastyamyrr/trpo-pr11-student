<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CheckoutData;
use App\Repositories\DeliveryMethodRepository;
use App\Security\Csrf;
use App\Services\CartService;
use App\Services\OrderService;
use App\Support\Redirect;
use App\Support\View;
use App\Validation\OrderValidator;
use DomainException;
use RuntimeException;

/**
 * Контроллер оформления заказа.
 *
 *  GET  /checkout          — форма
 *  POST /checkout          — валидация; ошибки → та же форма, успех → заказ + redirect
 *  GET  /checkout/success  — страница подтверждения
 *
 * Контроллер только координирует: проверки — в OrderValidator,
 * создание заказа — в OrderService, HTML — во views/checkout/*.
 */
final class CheckoutController
{
    public function __construct(
        private readonly CartService $cart,
        private readonly DeliveryMethodRepository $deliveryMethods,
        private readonly OrderValidator $validator,
        private readonly OrderService $orders,
        private readonly Csrf $csrf,
    ) {
    }

    public function form(): void
    {
        $this->requireNonEmptyCart();
        $this->renderForm([], $this->validator->normalize([]));
    }

    public function submit(): void
    {
        $this->requireNonEmptyCart();

        $old = $this->validator->normalize($_POST);

        if (!$this->csrf->isValid($_POST[Csrf::FIELD] ?? null)) {
            http_response_code(403);
            $this->renderForm(['_form' => 'Форма устарела. Проверьте данные и отправьте её ещё раз.'], $old);
            return;
        }

        $errors = $this->validator->validate($old);
        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm($errors, $old);
            return;
        }

        // Объект заказа создаётся только после успешной валидации
        try {
            $this->orders->place(CheckoutData::fromNormalized($old));
        } catch (DomainException $e) {
            http_response_code(422);
            $this->renderForm(['_form' => $e->getMessage()], $old);
            return;
        } catch (RuntimeException) {
            http_response_code(500);
            $this->renderForm(['_form' => 'Не удалось сохранить заказ. Попробуйте позже.'], $old);
            return;
        }

        Redirect::to('/checkout/success', '/catalog');
    }

    public function success(): void
    {
        $order = $this->orders->lastOrder();
        if ($order === null) {
            Redirect::to('/catalog');
        }

        View::render('checkout/success', [
            'order' => $order,
            'flash' => $this->cart->getFlash(),
            'cartCount' => $this->cart->count(),
        ]);
    }

    private function requireNonEmptyCart(): void
    {
        if ($this->cart->isEmpty()) {
            $this->cart->setFlash('Корзина пуста: добавьте товары перед оформлением заказа.');
            Redirect::to('/cart');
        }
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    private function renderForm(array $errors, array $old): void
    {
        View::render('checkout/form', [
            'errors' => $errors,
            'old' => $old,
            'deliveryMethods' => $this->deliveryMethods->all(),
            'csrfField' => Csrf::FIELD,
            'csrfToken' => $this->csrf->token(),
            'lines' => $this->cart->lines(),
            'formattedTotal' => $this->cart->formattedTotal(),
            'flash' => $this->cart->getFlash(),
            'cartCount' => $this->cart->count(),
        ]);
    }
}
