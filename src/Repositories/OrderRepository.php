<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Order;
use RuntimeException;

/**
 * Репозиторий заказов (учебное хранение в JSON).
 *
 * - dataset/orders_seed.json — начальные данные (пустой список);
 * - storage/orders.json — рабочий файл, создаётся при первом заказе (в .gitignore).
 *
 * Защита записи: блокировка flock на время «прочитать → добавить → записать»,
 * проверка, что существующий файл — корректный JSON (повреждённый файл
 * не перезаписывается), проверка результата fwrite.
 */
final class OrderRepository
{
    public function __construct(
        private readonly string $seedFile,
        private readonly string $storageFile,
    ) {
    }

    /**
     * @return Order[]
     */
    public function all(): array
    {
        $rows = is_file($this->storageFile) && filesize($this->storageFile) > 0
            ? $this->decodeFile($this->storageFile)
            : $this->readSeed();

        return array_map(
            static fn(array $row): Order => Order::fromArray($row),
            array_values(array_filter($rows, 'is_array'))
        );
    }

    public function findById(int $id): ?Order
    {
        foreach ($this->all() as $order) {
            if ($order->id === $id) {
                return $order;
            }
        }
        return null;
    }

    /**
     * Сохраняет заказ, присваивая ему следующий id. Возвращает заказ с id.
     *
     * @throws RuntimeException если файл нельзя прочитать, он повреждён или запись не удалась
     */
    public function add(Order $order): Order
    {
        $dir = dirname($this->storageFile);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Не удалось создать каталог хранилища заказов.');
        }

        $handle = fopen($this->storageFile, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('Не удалось открыть файл заказов.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Не удалось заблокировать файл заказов.');
            }

            $raw = stream_get_contents($handle);
            if ($raw === false) {
                throw new RuntimeException('Не удалось прочитать файл заказов.');
            }

            if (trim($raw) === '') {
                $rows = $this->readSeed();
            } else {
                $rows = json_decode($raw, true);
                if (!is_array($rows)) {
                    throw new RuntimeException('Файл заказов повреждён, запись отменена.');
                }
            }

            $maxId = 0;
            foreach ($rows as $row) {
                $maxId = max($maxId, (int) (is_array($row) ? ($row['id'] ?? 0) : 0));
            }

            $saved = Order::fromArray([...$order->toArray(), 'id' => $maxId + 1]);
            $rows[] = $saved->toArray();

            $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($json === false) {
                throw new RuntimeException('Не удалось закодировать заказы в JSON.');
            }

            if (!rewind($handle) || !ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
                throw new RuntimeException('Не удалось записать файл заказов.');
            }

            flock($handle, LOCK_UN);
            return $saved;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function readSeed(): array
    {
        return is_file($this->seedFile) ? $this->decodeFile($this->seedFile) : [];
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeFile(string $file): array
    {
        $json = file_get_contents($file);
        $data = $json === false ? null : json_decode($json, true);

        if (!is_array($data)) {
            throw new RuntimeException('Не удалось прочитать JSON: ' . basename($file));
        }
        return $data;
    }
}
