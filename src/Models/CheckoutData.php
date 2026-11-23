<?php

declare(strict_types=1);

namespace App\Models;

/**
 * DTO данных формы оформления заказа.
 * Создаётся ТОЛЬКО после успешной валидации (OrderValidator).
 * Контакт: name, email. Доставка: delivery, address. Прочее: comment.
 */
final class CheckoutData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $delivery,
        public readonly string $address = '',
        public readonly string $comment = '',
    ) {
    }

    /**
     * @param array{name:string,email:string,delivery:string,address:string,comment:string} $normalized
     */
    public static function fromNormalized(array $normalized): self
    {
        return new self(
            name: $normalized['name'],
            email: $normalized['email'],
            delivery: $normalized['delivery'],
            address: $normalized['address'],
            comment: $normalized['comment'],
        );
    }
}
