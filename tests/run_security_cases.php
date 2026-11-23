<?php

declare(strict_types=1);

/**
 * Прогон dataset/security_cases.json против реального приложения (php -S):
 *   - input + "escaped"      → HTML из ввода выводится экранированным;
 *   - qty + "rejected"       → неверное количество отклоняется, корзина не меняется;
 *   - product_id + "not_found" → неизвестный товар не попадает в корзину, без warning.
 * Плюс дополнительные случаи: пустые значения, массивы в полях, XSS в разных полях, CSRF.
 *
 * Запуск: php tests/run_security_cases.php
 */

require __DIR__ . '/support.php';

$root = dirname(__DIR__);
$cases = json_decode((string) file_get_contents($root . '/dataset/security_cases.json'), true);
if (!is_array($cases)) {
    fwrite(STDERR, "Не удалось прочитать security_cases.json\n");
    exit(2);
}

$server = new TestServer($root);
$server->start();
$failed = 0;

/** Клиент с одним товаром в корзине. */
$clientWithCart = static function () use ($server): HttpClient {
    $client = new HttpClient($server->port);
    $client->post('/cart/add', ['product_id' => 1, 'redirect' => '/cart']);
    return $client;
};

$token = static function (HttpClient $client): string {
    $html = $client->get('/checkout')['body'];
    return preg_match('/name="_csrf" value="([a-f0-9]+)"/', $html, $m) === 1 ? $m[1] : '';
};

/** POST /checkout с токеном; остальные поля из $fields. */
$checkout = static function (HttpClient $client, array $fields) use ($token): array {
    return $client->post('/checkout', ['_csrf' => $token($client)] + $fields);
};

$check = static function (bool $ok, string $label, string $details = '') use (&$failed): void {
    $failed += $ok ? 0 : 1;
    report($ok, $label, $details);
};

$show = static fn(mixed $v): string => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

echo "--- dataset/security_cases.json ---\n";
foreach ($cases as $case) {
    if (isset($case['input']) && ($case['expected'] ?? '') === 'escaped') {
        $client = $clientWithCart();
        $res = $checkout($client, ['name' => $case['input'], 'email' => 'bad', 'delivery' => 'pickup']);
        $raw = str_contains($res['body'], $case['input']);
        $escaped = str_contains($res['body'], htmlspecialchars($case['input'], ENT_QUOTES, 'UTF-8'));
        $check($res['status'] === 422 && !$raw && $escaped, 'escaped  ' . $show($case['input']), "статус {$res['status']}, сырой вывод: " . ($raw ? 'ДА' : 'нет') . ', экранированный: ' . ($escaped ? 'да' : 'НЕТ'));
    } elseif (array_key_exists('qty', $case) && ($case['expected'] ?? '') === 'rejected') {
        $client = $clientWithCart();
        $client->post('/cart/update', ['product_id' => 1, 'quantity' => $case['qty']]);
        $cart = $client->get('/cart')['body'];
        $count = HttpClient::cartCount($cart);
        $check($count === 1 && str_contains($cart, 'Количество должно быть'), 'rejected ' . 'qty=' . $show($case['qty']), "в корзине $count шт. (было 1), сообщение об ошибке: " . (str_contains($cart, 'Количество должно быть') ? 'да' : 'НЕТ'));
    } elseif (array_key_exists('product_id', $case) && ($case['expected'] ?? '') === 'not_found') {
        $client = new HttpClient($server->port);
        $add = $client->post('/cart/add', ['product_id' => $case['product_id'], 'redirect' => '/cart']);
        $cart = $client->get('/cart')['body'];
        $page = $client->get('/catalog/' . (int) $case['product_id']);
        $check(
            $add['status'] === 302 && HttpClient::cartCount($cart) === 0 && str_contains($cart, 'Не удалось добавить') && $page['status'] === 404,
            'not_found product_id=' . $show($case['product_id']),
            "add→{$add['status']}, в корзине " . HttpClient::cartCount($cart) . ", GET /catalog/id → {$page['status']}"
        );
    } else {
        $check(false, 'неизвестный формат случая ' . $show($case));
    }
}

echo "--- дополнительные ---\n";

// Неверное количество: ноль, дробь, текст, пусто, слишком много, отрицательное
foreach (['0', '1.5', 'abc', '', '100', '-100', '1e3'] as $qty) {
    $client = $clientWithCart();
    $client->post('/cart/update', ['product_id' => 1, 'quantity' => $qty]);
    $count = HttpClient::cartCount($client->get('/cart')['body']);
    $check($count === 1, 'rejected qty=' . $show($qty), "в корзине $count шт. (должно остаться 1)");
}

// Корректное количество принимается (контроль: проверки не слишком строгие)
$client = $clientWithCart();
$client->post('/cart/update', ['product_id' => 1, 'quantity' => 3]);
$count = HttpClient::cartCount($client->get('/cart')['body']);
$check($count === 3, 'accepted qty=3', "в корзине $count шт.");

// Неверный product_id при добавлении, обновлении и удалении
foreach (['', 'abc', '0', '-5', '1; DROP TABLE', '99999'] as $id) {
    $client = new HttpClient($server->port);
    $client->post('/cart/add', ['product_id' => $id]);
    $client->post('/cart/update', ['product_id' => $id, 'quantity' => 2]);
    $client->post('/cart/remove', ['product_id' => $id]);
    $count = HttpClient::cartCount($client->get('/cart')['body']);
    $check($count === 0, 'not_found product_id=' . $show($id), "после add/update/remove в корзине $count шт.");
}

// Без параметров вообще
$client = new HttpClient($server->port);
$res = $client->post('/cart/add');
$check($res['status'] === 302 && HttpClient::cartCount($client->get('/cart')['body']) === 0, 'POST /cart/add без параметров', "статус {$res['status']}");

// XSS в других полях и параметрах
$xss = '"><img src=x onerror=alert(1)>';
$client = $clientWithCart();
$res = $checkout($client, ['name' => 'Иван', 'email' => 'bad', 'delivery' => 'courier', 'address' => $xss, 'comment' => '<b>x</b>']);
$check(!str_contains($res['body'], $xss) && !str_contains($res['body'], '<b>x</b>'), 'escaped  address и comment', 'атрибуты и текст экранированы');

$res = (new HttpClient($server->port))->get('/catalog?category=' . urlencode('<script>alert(1)</script>') . '&sort=' . urlencode('<script>alert(2)</script>'));
$check($res['status'] === 200 && !str_contains($res['body'], '<script>alert'), 'escaped  параметры category и sort', "статус {$res['status']}");

// Пустые значения и массивы в форме заказа: ошибки валидации, не warning
$client = $clientWithCart();
$res = $checkout($client, []);
$check($res['status'] === 422 && str_contains($res['body'], 'Укажите имя'), 'пустая форма заказа', "статус {$res['status']}");
$client = $clientWithCart();
$res = $checkout($client, ['name' => ['x'], 'email' => ['y'], 'delivery' => ['z'], 'comment' => ['w']]);
$check($res['status'] === 422, 'массивы вместо строк в форме заказа', "статус {$res['status']}");

// CSRF
$client = $clientWithCart();
$res = $client->post('/checkout', ['name' => 'Иван', 'email' => 'ivan@example.test', 'delivery' => 'pickup']);
$check($res['status'] === 403, 'POST /checkout без CSRF-токена', "статус {$res['status']}");
$client = $clientWithCart();
$res = $client->post('/checkout', ['_csrf' => 'wrong', 'name' => 'Иван', 'email' => 'ivan@example.test', 'delivery' => 'pickup']);
$check($res['status'] === 403, 'POST /checkout с неверным токеном', "статус {$res['status']}");

// Open redirect
$client = new HttpClient($server->port);
foreach (['//evil.example', 'https://evil.example', '/\\evil.example'] as $target) {
    $res = $client->post('/cart/add', ['product_id' => 1, 'redirect' => $target]);
    $location = $res['headers']['location'] ?? '';
    $check($location === '/cart', 'redirect=' . $show($target), "Location: $location");
}

// Cookie сессии: HttpOnly и SameSite
$res = (new HttpClient($server->port))->get('/catalog');
$cookie = $res['headers']['set-cookie'] ?? '';
$check(stripos($cookie, 'HttpOnly') !== false && stripos($cookie, 'SameSite=Lax') !== false, 'cookie сессии HttpOnly + SameSite', $cookie);

$problems = $server->phpProblems();
$check($problems === [], 'PHP warnings/notices в логе сервера', $problems === [] ? 'нет' : $problems[0]);

$server->stop();
echo $failed === 0 ? "\nВсе проверки безопасности пройдены.\n" : "\nПровалено проверок: $failed\n";
exit($failed === 0 ? 0 : 1);
