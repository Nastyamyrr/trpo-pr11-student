# Результаты проверки ПР-10

Дата проверки: 2026-11-23. PHP 8.3, встроенный сервер `php -S 127.0.0.1:8099 -t public`.

## 1. Автоматические прогонщики (`php tests/run_all.php`)

```
=== run_catalog_cases.php ===
[OK] all      category=-    sort=name   found=24 (min 20)
[OK] books    category=1    sort=price  found=6 (min 4)
[OK] unknown  category=99   sort=name   found=0 (min 0)

Все сценарии каталога пройдены.

=== run_cart_scenarios.php ===
#1 [OK] steps=add:1, add:1, add:2
    expected={"1":2,"2":1} total=1830
    actual  ={"1":2,"2":1} total=1830
#2 [OK] steps=add:3, remove:3
    expected=[] total=0
    actual  =[] total=0
#3 [OK] steps=add:4, set:4:3
    expected={"4":3} total=2790
    actual  ={"4":3} total=2790

Все сценарии корзины пройдены.

=== run_order_cases.php ===
--- dataset/order_cases.json ---
[OK] empty                valid=false (ожидалось false) {"name":"Укажите имя.","email":"Укажите email.","delivery":"Выберите способ доставки."}
[OK] bad_email            valid=false (ожидалось false) {"email":"Проверьте email: ожидается формат name@example.com.","address":"Укажите адрес доставки."}
[OK] ok                   valid=true  (ожидалось true ) 
--- дополнительные ---
[OK] spaces_name          valid=false (ожидалось false) {"name":"Укажите имя."}
[OK] unknown_delivery     valid=false (ожидалось false) {"delivery":"Неизвестный способ доставки."}
[OK] courier_no_address   valid=false (ожидалось false) {"address":"Укажите адрес доставки."}
[OK] courier_ok           valid=true  (ожидалось true ) 
[OK] array_in_field       valid=false (ожидалось false) {"name":"Укажите имя."}

Все сценарии заказа пройдены.

```

## 2. Ручная проверка по HTTP (curl, отдельный cookie-файл = «новая приватная вкладка»)

| № | Действие | Ожидание | Результат |
|---|----------|----------|-----------|
| 1 | `GET /cart` в новой сессии | 200, без warning/notice | 200, в логе сервера предупреждений нет |
| 2 | `GET /checkout` при пустой корзине | redirect в `/cart` | 302 |
| 3 | `POST /cart/add` с `redirect=//evil.com` | redirect на `/cart`, не на внешний адрес | `Location: /cart` |
| 4 | `POST /cart/update`, `quantity=abc` | товар не удаляется, flash-ошибка, redirect `/cart` | `Location: /cart` |
| 5 | `GET /checkout` с товарами | 200, радио-кнопки из `delivery_methods.json`, CSRF-токен (64 символа) | 200, 2 варианта доставки, токен 64 символа |
| 6 | `POST /checkout`, пустая форма | 422, ошибки имени, email, доставки | 422: «Укажите имя.», «Укажите email.», «Выберите способ доставки.» |
| 7 | неверный email + курьер без адреса | 422, значения сохранены | 422: «Проверьте email…», «Укажите адрес доставки.», имя «Иван» осталось в поле |
| 8 | неизвестный способ доставки (`drone`) | 422, ошибка поля `delivery` | 422: «Неизвестный способ доставки.» |
| 9 | POST без CSRF-токена | 403 + форма | 403: «Форма устарела…» |
| 10 | `<script>` в поле имени | выводится экранированным | в HTML `&lt;script&gt;`, сырого `<script>alert` нет |
| 11 | корректный заказ (курьер) | 302 на `/checkout/success` | 302 → `/checkout/success` |
| 12 | `GET /checkout/success` | номер, состав, итог = товары + доставка, без email | `ORD-0001`, 3 000 ₽ + 450 ₽ = 3 450 ₽, email на странице отсутствует |
| 13 | повторное открытие страницы подтверждения (F5) | 200, второй заказ не создаётся | 200 |
| 14 | `GET /cart` после заказа | корзина пуста | «Корзина пуста» |
| 15 | `GET /checkout/success` в новой сессии | redirect, чужой заказ не виден | 302 |
| 16 | `storage/orders.json` | одна запись с позициями, доставкой и суммой | одна запись, `total = 3450` |

## 3. Защита хранилища

Проверено скриптом: при повреждённом `orders.json` (`{broken`) `OrderRepository::add()` бросает `RuntimeException («Файл заказов повреждён, запись отменена.»)`, файл остаётся нетронутым. Для чистого хранилища два подряд заказа получают id 1 и 2 (`ORD-0002`).

## 4. Что не проверялось

Реальный браузер (Console), параллельные запросы на запись и запуск на Windows/macOS.
