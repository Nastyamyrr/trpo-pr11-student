<?php

declare(strict_types=1);

/**
 * Прогон dataset/route_tests.csv (метод, путь, ожидаемый HTTP-статус)
 * против реального приложения на временном сервере `php -S`,
 * плюс дополнительные случаи: 405, неизвестные id, нормализация пути.
 *
 * Запуск: php tests/run_route_tests.php
 */

require __DIR__ . '/support.php';

$root = dirname(__DIR__);
$server = new TestServer($root);
$server->start();

$failed = 0;
echo "--- dataset/route_tests.csv ---\n";
foreach (read_csv($root . '/dataset/route_tests.csv') as $row) {
    $client = new HttpClient($server->port); // новая «сессия» на каждый маршрут
    $res = $client->request($row['method'], $row['path'], $row['method'] === 'POST' ? [] : null);
    $ok = $res['status'] === (int) $row['expected_status'];
    $failed += $ok ? 0 : 1;
    report($ok, sprintf('%-4s %-14s', $row['method'], $row['path']), sprintf('статус %d (ожидался %s)', $res['status'], $row['expected_status']));
}

echo "--- дополнительные ---\n";
$extra = [
    ['GET', '/cart/add', null, 405, 'изменение состояния через GET запрещено'],
    ['GET', '/cart/update', null, 405, 'изменение состояния через GET запрещено'],
    ['GET', '/cart/remove', null, 405, 'изменение состояния через GET запрещено'],
    ['GET', '/cart/clear', null, 405, 'изменение состояния через GET запрещено'],
    ['POST', '/catalog', [], 405, 'POST на страницу каталога'],
    ['POST', '/nope', [], 404, 'неизвестный маршрут (POST)'],
    ['GET', '/catalog/99999', null, 404, 'неизвестный id товара'],
    ['GET', '/catalog/abc', null, 404, 'нечисловой id'],
    ['GET', '/catalog/4', null, 200, 'карточка существующего товара'],
    ['GET', '/catalog/', null, 200, 'слэш в конце пути'],
    ['GET', '/index.php/catalog', null, 200, 'путь с /index.php'],
    ['GET', '/checkout', null, 302, 'пустая корзина: redirect в /cart'],
    ['GET', '/checkout/success', null, 302, 'нет заказа в сессии: redirect'],
];
foreach ($extra as [$method, $path, $form, $expected, $note]) {
    $client = new HttpClient($server->port);
    $res = $client->request($method, $path, $form);
    $ok = $res['status'] === $expected;
    $failed += $ok ? 0 : 1;
    report($ok, sprintf('%-4s %-18s', $method, $path), sprintf('статус %d (ожидался %d) — %s', $res['status'], $expected, $note));
}

// Заголовок Allow у ответа 405
$res = (new HttpClient($server->port))->get('/cart/add');
$ok = ($res['headers']['allow'] ?? '') === 'POST';
$failed += $ok ? 0 : 1;
report($ok, 'GET  /cart/add Allow', 'Allow: ' . ($res['headers']['allow'] ?? '—'));

// В обычных сценариях не должно быть PHP warning/notice/deprecated
$problems = $server->phpProblems();
$failed += $problems === [] ? 0 : 1;
report($problems === [], 'PHP warnings/notices в логе сервера', $problems === [] ? 'нет' : $problems[0]);

$server->stop();
echo $failed === 0 ? "\nВсе маршруты ведут себя как ожидается.\n" : "\nПровалено проверок: $failed\n";
exit($failed === 0 ? 0 : 1);
