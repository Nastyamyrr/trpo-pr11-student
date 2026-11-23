<?php

declare(strict_types=1);

/**
 * Прогон dataset/catalog_cases.csv (ПР-08) через ProductRepository::filter().
 * Колонка expected_min — минимально ожидаемое число найденных товаров.
 *
 * Запуск: php tests/run_catalog_cases.php
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Repositories\ProductRepository;

$base = dirname(__DIR__);
$products = new ProductRepository($base . '/dataset/products.json');

$handle = fopen($base . '/dataset/catalog_cases.csv', 'rb');
if ($handle === false) {
    fwrite(STDERR, "Не удалось открыть catalog_cases.csv\n");
    exit(2);
}

$header = fgetcsv($handle, 0, ';', '"', '');
// Файл может быть сохранён с BOM (UTF-8) — убираем его из первого заголовка
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
$failed = 0;

while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
    if ($row === [null] || count($row) < count($header)) {
        continue; // пустая строка
    }
    $case = array_combine($header, $row);
    $categoryId = $case['category'] === '' ? null : (int) $case['category'];
    $found = count($products->filter($categoryId, $case['sort']));
    $ok = $found >= (int) $case['expected_min'];
    $failed += $ok ? 0 : 1;

    printf("[%s] %-8s category=%-4s sort=%-6s found=%d (min %d)\n",
        $ok ? 'OK' : 'FAIL', $case['case'], $case['category'] === '' ? '-' : $case['category'],
        $case['sort'], $found, (int) $case['expected_min']);
}
fclose($handle);

echo $failed === 0 ? "\nВсе сценарии каталога пройдены.\n" : "\nПровалено сценариев: $failed\n";
exit($failed === 0 ? 0 : 1);
