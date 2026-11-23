<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Session;
use App\Models\Product;
use App\Repositories\ProductRepository;

/**
 * Сервис корзины.
 * В session хранится только productId => quantity.
 * Данные о товарах подтягиваются через ProductRepository.
 * К $_SESSION сервис не обращается: работает через App\Http\Session.
 */
final class CartService
{
    private const SESSION_KEY = 'cart';
    private const FLASH_KEY = 'flash';

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly Session $session,
    ) {
    }

    /**
     * Добавить товар (увеличить количество на 1).
     */
    public function add(int $productId): bool
    {
        $product = $this->productRepository->findById($productId);
        if ($product === null) {
            return false;
        }

        $items = $this->raw();
        $items[$productId] = ($items[$productId] ?? 0) + 1;
        $this->save($items);
        $this->setFlash('Товар «' . $product->name . '» добавлен в корзину.');
        return true;
    }

    /**
     * Установить количество. 0 или меньше — удаляет позицию.
     */
    public function setQuantity(int $productId, int $quantity): bool
    {
        $product = $this->productRepository->findById($productId);
        if ($product === null) {
            return false;
        }

        if ($quantity <= 0) {
            return $this->remove($productId);
        }

        $items = $this->raw();
        $items[$productId] = $quantity;
        $this->save($items);
        $this->setFlash('Количество обновлено.');
        return true;
    }

    /**
     * Удалить позицию.
     */
    public function remove(int $productId): bool
    {
        $items = $this->raw();
        if (!isset($items[$productId])) {
            return false;
        }

        unset($items[$productId]);
        $this->save($items);
        $this->setFlash('Товар удалён из корзины.');
        return true;
    }

    /**
     * Очистить корзину.
     */
    public function clear(): void
    {
        $this->save([]);
        $this->setFlash('Корзина очищена.');
    }

    /**
     * Сырые данные: productId => quantity
     *
     * @return array<int, int>
     */
    public function raw(): array
    {
        $stored = $this->session->get(self::SESSION_KEY, []);
        if (!is_array($stored)) {
            return [];
        }

        $result = [];
        foreach ($stored as $id => $qty) {
            $result[(int) $id] = (int) $qty;
        }
        return $result;
    }

    /**
     * Готовые строки корзины для view.
     *
     * @return list<array{product: Product, quantity: int, lineTotal: float}>
     */
    public function lines(): array
    {
        $lines = [];
        foreach ($this->raw() as $productId => $quantity) {
            $product = $this->productRepository->findById($productId);
            if ($product === null) {
                continue;
            }
            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'lineTotal' => $product->price * $quantity,
            ];
        }
        return $lines;
    }

    /**
     * Общая сумма.
     */
    public function total(): float
    {
        $sum = 0.0;
        foreach ($this->lines() as $line) {
            $sum += $line['lineTotal'];
        }
        return $sum;
    }

    /**
     * Количество единиц товара (сумма quantity).
     */
    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function formattedTotal(): string
    {
        return number_format($this->total(), 0, ',', ' ') . ' ₽';
    }

    // --- Flash ---

    public function setFlash(string $message): void
    {
        $this->session->set(self::FLASH_KEY, $message);
    }

    public function getFlash(): ?string
    {
        $message = $this->session->pull(self::FLASH_KEY);
        return $message === null ? null : (string) $message;
    }

    /**
     * @param array<int, int> $items
     */
    private function save(array $items): void
    {
        $this->session->set(self::SESSION_KEY, $items);
    }
}
