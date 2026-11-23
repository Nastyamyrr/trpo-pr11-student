<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Session;
use App\Models\CheckoutData;
use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\DeliveryMethodRepository;
use App\Repositories\OrderRepository;
use DomainException;

/**
 * Создание заказа из корзины и проверенных данных формы.
 * Решение «создавать заказ или нет» принимают контроллер + этот сервис, не view.
 */
final class OrderService
{
    private const LAST_ORDER_KEY = 'last_order_id';

    public function __construct(
        private readonly CartService $cart,
        private readonly OrderRepository $orders,
        private readonly DeliveryMethodRepository $deliveryMethods,
        private readonly Session $session,
    ) {
    }

    /**
     * @throws DomainException   пустая корзина или неизвестная доставка
     * @throws \RuntimeException не удалось сохранить заказ (корзина при этом НЕ очищается)
     */
    public function place(CheckoutData $data): Order
    {
        $lines = $this->cart->lines();
        if ($lines === []) {
            throw new DomainException('Корзина пуста.');
        }

        $method = $this->deliveryMethods->findById($data->delivery);
        if ($method === null) {
            throw new DomainException('Неизвестный способ доставки.');
        }

        $items = array_map(
            static fn(array $line): OrderItem => new OrderItem(
                productId: $line['product']->id,
                name: $line['product']->name,
                price: $line['product']->price,
                quantity: $line['quantity'],
            ),
            $lines
        );

        $draft = new Order(
            id: 0,
            createdAt: date('Y-m-d H:i:s'),
            customerName: $data->name,
            email: $data->email,
            deliveryId: $method->id,
            deliveryTitle: $method->title,
            deliveryPrice: $method->price,
            address: $data->address,
            comment: $data->comment,
            items: $items,
        );

        $saved = $this->orders->add($draft);

        // Корзина очищается только после успешной записи заказа
        $this->cart->clear();
        $this->session->set(self::LAST_ORDER_KEY, $saved->id);
        $this->cart->setFlash('Заказ ' . $saved->number() . ' оформлен.');

        return $saved;
    }

    /**
     * Последний заказ текущей сессии (страница подтверждения).
     * По id из URL заказы не открываются, чужой заказ посмотреть нельзя.
     */
    public function lastOrder(): ?Order
    {
        $id = (int) $this->session->get(self::LAST_ORDER_KEY, 0);
        return $id > 0 ? $this->orders->findById($id) : null;
    }
}
