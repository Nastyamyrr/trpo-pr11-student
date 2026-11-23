<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Способ доставки (строка из dataset/delivery_methods.json).
 */
final class DeliveryMethod
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly float $price,
    ) {
    }

    public function formattedPrice(): string
    {
        return $this->price <= 0 ? 'бесплатно' : number_format($this->price, 0, ',', ' ') . ' ₽';
    }
}
