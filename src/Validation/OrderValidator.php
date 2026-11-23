<?php

declare(strict_types=1);

namespace App\Validation;

use App\Repositories\DeliveryMethodRepository;

/**
 * Серверная валидация формы заказа.
 * Контроллер не содержит цепочку проверок: он вызывает normalize() и validate().
 */
final class OrderValidator
{
    /** Способы доставки, для которых нужен адрес. */
    private const ADDRESS_REQUIRED_FOR = ['courier'];

    public function __construct(
        private readonly DeliveryMethodRepository $deliveryMethods,
    ) {
    }

    /**
     * Нормализация: только строки, trim, схлопывание пробелов.
     * Массивы и другие типы из $_POST (name[]=x) превращаются в пустую строку.
     *
     * @param array<mixed> $input
     * @return array{name:string,email:string,delivery:string,address:string,comment:string}
     */
    public function normalize(array $input): array
    {
        $line = static function (mixed $value): string {
            if (!is_string($value)) {
                return '';
            }
            return trim((string) preg_replace('/\s+/u', ' ', $value));
        };

        return [
            'name' => $line($input['name'] ?? null),
            'email' => $line($input['email'] ?? null),
            'delivery' => $line($input['delivery'] ?? null),
            'address' => $line($input['address'] ?? null),
            // В комментарии сохраняем переносы строк
            'comment' => is_string($input['comment'] ?? null) ? trim($input['comment']) : '',
        ];
    }

    /**
     * @param array{name:string,email:string,delivery:string,address:string,comment:string} $data
     * @return array<string, string> поле => сообщение (пустой массив = ошибок нет)
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Имя
        if ($data['name'] === '') {
            $errors['name'] = 'Укажите имя.';
        } elseif (mb_strlen($data['name']) < 2) {
            $errors['name'] = 'Имя слишком короткое (минимум 2 символа).';
        } elseif (mb_strlen($data['name']) > 100) {
            $errors['name'] = 'Имя слишком длинное (максимум 100 символов).';
        }

        // Email
        if ($data['email'] === '') {
            $errors['email'] = 'Укажите email.';
        } elseif (mb_strlen($data['email']) > 254 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Проверьте email: ожидается формат name@example.com.';
        }

        // Способ доставки — только из delivery_methods.json
        $method = null;
        if ($data['delivery'] === '') {
            $errors['delivery'] = 'Выберите способ доставки.';
        } else {
            $method = $this->deliveryMethods->findById($data['delivery']);
            if ($method === null) {
                $errors['delivery'] = 'Неизвестный способ доставки.';
            }
        }

        // Адрес — обязателен только для доставки курьером
        if ($method !== null && in_array($method->id, self::ADDRESS_REQUIRED_FOR, true)) {
            if ($data['address'] === '') {
                $errors['address'] = 'Укажите адрес доставки.';
            } elseif (mb_strlen($data['address']) < 5) {
                $errors['address'] = 'Адрес слишком короткий.';
            }
        }
        if (!isset($errors['address']) && mb_strlen($data['address']) > 200) {
            $errors['address'] = 'Адрес слишком длинный (максимум 200 символов).';
        }

        // Комментарий — необязателен
        if (mb_strlen($data['comment']) > 500) {
            $errors['comment'] = 'Комментарий слишком длинный (максимум 500 символов).';
        }

        return $errors;
    }
}
