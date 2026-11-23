<?php

declare(strict_types=1);

/**
 * Прогон dataset/order_cases.json через OrderValidator (шаг 11 ПР-10),
 * плюс обязательные дополнительные случаи: пробелы вместо имени,
 * неизвестный способ доставки, курьер без адреса.
 * Допустимые способы доставки берутся из dataset/delivery_methods.json.
 *
 * Запуск: php tests/run_order_cases.php
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Repositories\DeliveryMethodRepository;
use App\Validation\OrderValidator;

$base = dirname(__DIR__);
$validator = new OrderValidator(new DeliveryMethodRepository($base . '/dataset/delivery_methods.json'));

$cases = json_decode((string) file_get_contents($base . '/dataset/order_cases.json'), true);
if (!is_array($cases)) {
    fwrite(STDERR, "Не удалось прочитать order_cases.json\n");
    exit(2);
}

// Дополнительные случаи (в dataset их нет, но задание требует проверить)
$extra = [
    ['case' => 'spaces_name', 'input' => ['name' => '   ', 'email' => 'a@b.co', 'delivery' => 'pickup'], 'valid' => false],
    ['case' => 'unknown_delivery', 'input' => ['name' => 'Иван', 'email' => 'ivan@example.test', 'delivery' => 'drone'], 'valid' => false],
    ['case' => 'courier_no_address', 'input' => ['name' => 'Иван', 'email' => 'ivan@example.test', 'delivery' => 'courier'], 'valid' => false],
    ['case' => 'courier_ok', 'input' => ['name' => 'Иван', 'email' => 'ivan@example.test', 'delivery' => 'courier', 'address' => 'Москва, ул. Тестовая, 1'], 'valid' => true],
    ['case' => 'array_in_field', 'input' => ['name' => ['x'], 'email' => 'a@b.co', 'delivery' => 'pickup'], 'valid' => false],
];

$failed = 0;
foreach ([['dataset/order_cases.json', $cases], ['дополнительные', $extra]] as [$title, $list]) {
    echo "--- $title ---\n";
    foreach ($list as $case) {
        $errors = $validator->validate($validator->normalize($case['input']));
        $valid = $errors === [];
        $ok = $valid === $case['valid'];
        $failed += $ok ? 0 : 1;

        printf("[%s] %-20s valid=%-5s (ожидалось %-5s) %s\n",
            $ok ? 'OK' : 'FAIL',
            $case['case'],
            $valid ? 'true' : 'false',
            $case['valid'] ? 'true' : 'false',
            $errors === [] ? '' : json_encode($errors, JSON_UNESCAPED_UNICODE));
    }
}

echo $failed === 0 ? "\nВсе сценарии заказа пройдены.\n" : "\nПровалено сценариев: $failed\n";
exit($failed === 0 ? 0 : 1);
