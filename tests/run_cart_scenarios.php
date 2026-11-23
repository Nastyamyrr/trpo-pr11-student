<?php

declare(strict_types=1);

/**
 * Прогон dataset/cart_scenarios.json через CartService (шаг 12 ПР-09).
 * Для каждого сценария сверяется состав корзины (id => количество)
 * и итоговая сумма, посчитанная независимо от CartService.
 *
 * Запуск: php tests/run_cart_scenarios.php
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Http\Session;
use App\Repositories\ProductRepository;
use App\Services\CartService;

$base = dirname(__DIR__);
$products = new ProductRepository($base . '/dataset/products.json');
$scenarios = json_decode((string) file_get_contents($base . '/dataset/cart_scenarios.json'), true);

if (!is_array($scenarios)) {
    fwrite(STDERR, "Не удалось прочитать cart_scenarios.json\n");
    exit(2);
}

$failed = 0;

foreach ($scenarios as $i => $scenario) {
    $_SESSION = []; // чистая «сессия» для каждого сценария
    $cart = new CartService($products, new Session());

    foreach ($scenario['steps'] as $step) {
        $parts = explode(':', $step);
        match ($parts[0]) {
            'add' => $cart->add((int) $parts[1]),
            'remove' => $cart->remove((int) $parts[1]),
            'set' => $cart->setQuantity((int) $parts[1], (int) $parts[2]),
            default => throw new InvalidArgumentException('Неизвестный шаг: ' . $step),
        };
    }

    $expected = [];
    foreach ($scenario['expected'] as $id => $qty) {
        $expected[(int) $id] = (int) $qty;
    }
    ksort($expected);

    $actual = $cart->raw();
    ksort($actual);

    // Ожидаемая сумма — отдельно, прямо из products.json
    $expectedTotal = 0.0;
    foreach ($expected as $id => $qty) {
        $expectedTotal += $products->findById($id)->price * $qty;
    }

    $ok = $actual === $expected && $cart->total() === $expectedTotal;
    $failed += $ok ? 0 : 1;

    printf(
        "#%d [%s] steps=%s\n    expected=%s total=%s\n    actual  =%s total=%s\n",
        $i + 1,
        $ok ? 'OK' : 'FAIL',
        implode(', ', $scenario['steps']),
        json_encode($expected),
        $expectedTotal,
        json_encode($actual),
        $cart->total()
    );
}

echo $failed === 0 ? "\nВсе сценарии корзины пройдены.\n" : "\nПровалено сценариев: $failed\n";
exit($failed === 0 ? 0 : 1);
