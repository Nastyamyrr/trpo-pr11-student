<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Заказ: контакт (customerName, email), доставка (deliveryId/Title/Price, address),
 * состав (items). Суммы считаются методами модели, не во view.
 */
final class Order
{
    /**
     * @param OrderItem[] $items
     */
    public function __construct(
        public readonly int $id,
        public readonly string $createdAt,
        public readonly string $customerName,
        public readonly string $email,
        public readonly string $deliveryId,
        public readonly string $deliveryTitle,
        public readonly float $deliveryPrice,
        public readonly string $address,
        public readonly string $comment,
        public readonly array $items,
    ) {
    }

    public function number(): string
    {
        return 'ORD-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function itemsTotal(): float
    {
        $sum = 0.0;
        foreach ($this->items as $item) {
            $sum += $item->lineTotal();
        }
        return $sum;
    }

    public function total(): float
    {
        return $this->itemsTotal() + $this->deliveryPrice;
    }

    public function formattedTotal(): string
    {
        return self::money($this->total());
    }

    public function formattedItemsTotal(): string
    {
        return self::money($this->itemsTotal());
    }

    public function formattedDeliveryPrice(): string
    {
        return $this->deliveryPrice <= 0 ? 'бесплатно' : self::money($this->deliveryPrice);
    }

    public static function money(float $value): string
    {
        return number_format($value, 0, ',', ' ') . ' ₽';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->createdAt,
            'customer_name' => $this->customerName,
            'email' => $this->email,
            'delivery_id' => $this->deliveryId,
            'delivery_title' => $this->deliveryTitle,
            'delivery_price' => $this->deliveryPrice,
            'address' => $this->address,
            'comment' => $this->comment,
            'items' => array_map(static fn(OrderItem $i): array => $i->toArray(), $this->items),
            'total' => $this->total(),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $items = [];
        foreach ((array) ($row['items'] ?? []) as $itemRow) {
            if (is_array($itemRow)) {
                $items[] = OrderItem::fromArray($itemRow);
            }
        }

        return new self(
            id: (int) ($row['id'] ?? 0),
            createdAt: (string) ($row['created_at'] ?? ''),
            customerName: (string) ($row['customer_name'] ?? ''),
            email: (string) ($row['email'] ?? ''),
            deliveryId: (string) ($row['delivery_id'] ?? ''),
            deliveryTitle: (string) ($row['delivery_title'] ?? ''),
            deliveryPrice: (float) ($row['delivery_price'] ?? 0),
            address: (string) ($row['address'] ?? ''),
            comment: (string) ($row['comment'] ?? ''),
            items: $items,
        );
    }
}
