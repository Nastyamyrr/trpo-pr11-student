<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Позиция заказа. Название и цена фиксируются на момент оформления,
 * поэтому изменение products.json не меняет уже созданные заказы.
 */
final class OrderItem
{
    public function __construct(
        public readonly int $productId,
        public readonly string $name,
        public readonly float $price,
        public readonly int $quantity,
    ) {
    }

    public function lineTotal(): float
    {
        return $this->price * $this->quantity;
    }

    /**
     * @return array<string, int|float|string>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            productId: (int) ($row['product_id'] ?? 0),
            name: (string) ($row['name'] ?? ''),
            price: (float) ($row['price'] ?? 0),
            quantity: (int) ($row['quantity'] ?? 0),
        );
    }
}
