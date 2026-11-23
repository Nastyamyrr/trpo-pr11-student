<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\DeliveryMethod;

/**
 * Репозиторий способов доставки.
 * Варианты для формы берутся из JSON, а не прописываются в HTML.
 */
final class DeliveryMethodRepository
{
    public function __construct(
        private readonly string $file,
    ) {
    }

    /**
     * @return DeliveryMethod[]
     */
    public function all(): array
    {
        $rows = $this->loadRows();
        $methods = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'], $row['title'])) {
                continue; // пропускаем повреждённые строки
            }
            $methods[] = new DeliveryMethod(
                id: (string) $row['id'],
                title: (string) $row['title'],
                price: (float) ($row['price'] ?? 0),
            );
        }
        return $methods;
    }

    public function findById(string $id): ?DeliveryMethod
    {
        foreach ($this->all() as $method) {
            if ($method->id === $id) {
                return $method;
            }
        }
        return null;
    }

    /**
     * @return array<int, mixed>
     */
    private function loadRows(): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        $json = file_get_contents($this->file);
        $data = $json === false ? null : json_decode($json, true);

        return is_array($data) ? $data : [];
    }
}
