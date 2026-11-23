# Результаты проверки ПР-11

Дата проверки: 2026-11-23. PHP 8.3, ОС Linux, встроенный сервер `php -S`.
Команда: `php tests/run_all.php` (код завершения 0).

## 1. Автоматические проверки

```
=== run_lint.php ===
Проверено php-файлов: 43, ошибок синтаксиса: 0

=== run_architecture_checks.php ===
--- dataset/architecture_checklist.csv ---
[OK] Single entry point
[OK] Router without HTML
[OK] Controllers choose views
[OK] Models without HTTP globals
[OK] Views without data access
[OK] Namespaces
[OK] Autoload
[OK] 404 handler
--- гигиена репозитория (шаг 12) ---
[OK] в Git нет временных/локальных файлов
[OK] в Git нет паролей и секретов
[OK] .gitignore покрывает storage/ и .env

Архитектурный чек-лист выполнен.

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

=== run_route_tests.php ===
--- dataset/route_tests.csv ---
[OK] GET  /               статус 200 (ожидался 200)
[OK] GET  /catalog        статус 200 (ожидался 200)
[OK] GET  /cart           статус 200 (ожидался 200)
[OK] GET  /unknown        статус 404 (ожидался 404)
[OK] POST /cart/add       статус 302 (ожидался 302)
--- дополнительные ---
[OK] GET  /cart/add           статус 405 (ожидался 405) — изменение состояния через GET запрещено
[OK] GET  /cart/update        статус 405 (ожидался 405) — изменение состояния через GET запрещено
[OK] GET  /cart/remove        статус 405 (ожидался 405) — изменение состояния через GET запрещено
[OK] GET  /cart/clear         статус 405 (ожидался 405) — изменение состояния через GET запрещено
[OK] POST /catalog            статус 405 (ожидался 405) — POST на страницу каталога
[OK] POST /nope               статус 404 (ожидался 404) — неизвестный маршрут (POST)
[OK] GET  /catalog/99999      статус 404 (ожидался 404) — неизвестный id товара
[OK] GET  /catalog/abc        статус 404 (ожидался 404) — нечисловой id
[OK] GET  /catalog/4          статус 200 (ожидался 200) — карточка существующего товара
[OK] GET  /catalog/           статус 200 (ожидался 200) — слэш в конце пути
[OK] GET  /index.php/catalog  статус 200 (ожидался 200) — путь с /index.php
[OK] GET  /checkout           статус 302 (ожидался 302) — пустая корзина: redirect в /cart
[OK] GET  /checkout/success   статус 302 (ожидался 302) — нет заказа в сессии: redirect
[OK] GET  /cart/add Allow  Allow: POST
[OK] PHP warnings/notices в логе сервера  нет

Все маршруты ведут себя как ожидается.

=== run_security_cases.php ===
--- dataset/security_cases.json ---
[OK] escaped  "<script>alert(1)</script>"  статус 422, сырой вывод: нет, экранированный: да
[OK] rejected qty=-1  в корзине 1 шт. (было 1), сообщение об ошибке: да
[OK] not_found product_id=99999  add→302, в корзине 0, GET /catalog/id → 404
--- дополнительные ---
[OK] rejected qty="0"  в корзине 1 шт. (должно остаться 1)
[OK] rejected qty="1.5"  в корзине 1 шт. (должно остаться 1)
[OK] rejected qty="abc"  в корзине 1 шт. (должно остаться 1)
[OK] rejected qty=""  в корзине 1 шт. (должно остаться 1)
[OK] rejected qty="100"  в корзине 1 шт. (должно остаться 1)
[OK] rejected qty="-100"  в корзине 1 шт. (должно остаться 1)
[OK] rejected qty="1e3"  в корзине 1 шт. (должно остаться 1)
[OK] accepted qty=3  в корзине 3 шт.
[OK] not_found product_id=""  после add/update/remove в корзине 0 шт.
[OK] not_found product_id="abc"  после add/update/remove в корзине 0 шт.
[OK] not_found product_id="0"  после add/update/remove в корзине 0 шт.
[OK] not_found product_id="-5"  после add/update/remove в корзине 0 шт.
[OK] not_found product_id="1; DROP TABLE"  после add/update/remove в корзине 0 шт.
[OK] not_found product_id="99999"  после add/update/remove в корзине 0 шт.
[OK] POST /cart/add без параметров  статус 302
[OK] escaped  address и comment  атрибуты и текст экранированы
[OK] escaped  параметры category и sort  статус 200
[OK] пустая форма заказа  статус 422
[OK] массивы вместо строк в форме заказа  статус 422
[OK] POST /checkout без CSRF-токена  статус 403
[OK] POST /checkout с неверным токеном  статус 403
[OK] redirect="//evil.example"  Location: /cart
[OK] redirect="https://evil.example"  Location: /cart
[OK] redirect="/\\evil.example"  Location: /cart
[OK] cookie сессии HttpOnly + SameSite  PHPSESSID=qt38ip82ra7hoa3qakenae2ohh; path=/; HttpOnly; SameSite=Lax
[OK] PHP warnings/notices в логе сервера  нет

Все проверки безопасности пройдены.

```

## 2. Проверка самих проверок (мутационный тест)

Чтобы убедиться, что `run_architecture_checks.php` реально находит нарушения, в код временно вносились ошибки (затем изменения откатывались):

| Внесённое нарушение | Результат |
|---------------------|-----------|
| `$_POST` в `src/Models/Product.php` | FAIL: «Models without HTTP globals» |
| `echo "<div>"` в `src/Http/Router.php` | FAIL: «Router without HTML» |
| `file_get_contents` во `views/cart/index.php` | FAIL: «Views without data access» |
| неверный namespace в `src/Models/Category.php` | FAIL: «Namespaces» и «Autoload» |

## 3. Ручная проверка по HTTP (curl)

| № | Сценарий | Результат |
|---|----------|-----------|
| 1 | `GET /`, `/catalog`, `/cart` | 200, предупреждений в логе сервера нет |
| 2 | `GET /unknown`, `POST /nope` | 404 |
| 3 | `GET /cart/add` (изменение состояния через GET) | 405, заголовок `Allow: POST` |
| 4 | `GET /catalog/99999`, `/catalog/abc` | 404, текст «Товар с идентификатором 99999 не найден.» |
| 5 | `GET /index.php/catalog`, `/catalog/` | 200 (путь нормализуется) |
| 6 | Исключение в view (временно сломан `views/cart/index.php`) | 500; страница ошибки с текстом `RuntimeException: demo` (локальный режим), буфер вывода не остаётся недорисованным; файл восстановлен |
| 7 | Полный сценарий: 2 × «Продукт 2» → форма → корректный заказ (курьер) | 302 → `/checkout/success`, `ORD-0001`, 1 380 ₽ + доставка 450 ₽ = 1 830 ₽, корзина пуста, в `storage/orders.json` одна запись |

## 4. Что не проверялось

Работа в реальном браузере (Console), запуск на macOS/Windows, параллельные запросы на запись заказов.
