# Практическая работа №11 — PHP MVC интернет-магазин. Рефакторинг, автозагрузка, безопасность

**Дисциплина:** Технологии разработки программного обеспечения  
**Тема:** Рефакторинг, автозагрузка, безопасность и проверка архитектуры

Продолжение ПР-09 (корзина) и ПР-10 (оформление заказа). Точки возврата в Git: теги `pr09-done`, `pr10-done` (состояние **до** рефакторинга ПР-11) и `v1.0-pr11` (финальная версия).

## Результат

Стабильная версия магазина: каталог, корзина, оформление заказа.

- `public/index.php` только запускает приложение; `App\Application` собирает зависимости и маршруты;
- `App\Http\Router` сопоставляет метод и путь с действием контроллера; неизвестный путь даёт 404, неверный метод — 405;
- любое изменение состояния (корзина, заказ) только через POST + redirect;
- единое экранирование вывода `e()`, единый рендер `View`, единые страницы 404/405/500;
- `$_SESSION` скрыт за `App\Http\Session`; ни модели, ни сервисы, ни репозитории не читают глобальные переменные;
- автоматическая проверка: маршруты, безопасность, архитектурный чек-лист, lint, сценарии корзины и заказов.

## Требования и запуск

- PHP 8.1+ с расширением `mbstring`. Composer не нужен.

```bash
git clone https://github.com/Nastyamyrr/trpo-pr11-student.git
cd trpo-pr11-student
php -S localhost:8080 -t public
```

- http://localhost:8080/catalog
- http://localhost:8080/cart
- http://localhost:8080/checkout (при пустой корзине перенаправит в `/cart`)

В режиме `php -S` включён «локальный режим»: при неожиданном исключении на странице 500 показывается текст ошибки. В обычном окружении (`APP_DEBUG` не равен `1`) пользователь видит только общее сообщение, подробности пишутся в `error_log`.

## Быстрая проверка после чистого клонирования

1. Выполнить `php tests/run_all.php`. Должны пройти все семь блоков, код завершения `0`.
2. Запустить сервер и открыть `/catalog`. Нажать «В корзину», открыть «Корзина», изменить количество, оформить заказ.
3. Проверить ошибочные сценарии: пустая форма заказа, количество `-1`, адрес http://localhost:8080/catalog/99999 (404), http://localhost:8080/cart/add в адресной строке (405: добавление только через POST).

## Маршруты

| Метод | Путь | Действие |
|-------|------|----------|
| GET | `/`, `/catalog` | `CatalogController::index` |
| GET | `/catalog/{id}` | `CatalogController::show` (нет товара → 404) |
| GET | `/cart` | `CartController::index` |
| POST | `/cart/add`, `/cart/update`, `/cart/remove`, `/cart/clear` | `CartController::add/update/remove/clear` |
| GET | `/checkout` | `CheckoutController::form` |
| POST | `/checkout` | `CheckoutController::submit` |
| GET | `/checkout/success` | `CheckoutController::success` |
| любой другой | — | 404 (`ErrorController::notFound`); существующий путь с другим методом → 405 |

## Архитектурный чек-лист (`dataset/architecture_checklist.csv`)

| Пункт | Где реализовано | Как проверяется |
|-------|-----------------|-----------------|
| Single entry point | `public/index.php` — единственный php-файл в document root (`-t public`); он только стартует session, подключает `src/bootstrap.php` и вызывает `Application::run()` | `tests/run_architecture_checks.php` |
| Router without HTML | `src/Http/Router.php`: сопоставление и вызов обработчика, ни `echo`, ни HTML, ни `View`; ответы 404/405 формируют обработчики из `Application` → `ErrorController` | там же + `run_route_tests.php` |
| Controllers choose views | `src/Controllers/*`: `View::render('catalog/index', [...])` и т. п.; HTML в контроллерах нет | там же |
| Models without HTTP globals | `src/Models/*` не используют `$_GET/$_POST/$_SESSION/$_SERVER`; сервисы, репозитории и валидатор — тоже. `$_SESSION` только в `Http/Session.php`, `$_GET/$_POST` — в контроллерах (HTTP-слой) | там же (разбор токенов, комментарии не мешают) |
| Views without data access | `views/*`: только вывод через `e()`; нет файловых функций, репозиториев, сервисов, суперглобальных | там же |
| Namespaces | `App\Controllers`, `App\Models`, `App\Repositories`, `App\Services`, `App\Validation`, `App\Security`, `App\Http`, `App\Support`; namespace и имя класса совпадают с путём файла | там же |
| Autoload | `src/bootstrap.php`: `spl_autoload_register` (PSR-4-подобный: `App\X\Y` → `src/X/Y.php`); ручных `require` классов нет | там же: каждый класс загружается без `require` |
| 404 handler | `Router::setNotFoundHandler` → `ErrorController::notFound` → `views/error.php`; товар не найден → `NotFoundException` → тот же 404 | `run_route_tests.php` (`/unknown`, `/catalog/99999`) |

Выбран **учебный `spl_autoload_register`**, а не Composer: меньше «магии», весь механизм умещается в 15 строк `src/bootstrap.php`. При росте проекта его заменил бы Composer PSR-4 (`"App\\": "src/"`).

## Схема MVC и зависимости

```
Браузер ──HTTP──► public/index.php ──► App\Application ──► Http\Router
                  (session, bootstrap)   (DI + маршруты)        │ метод + путь
                                                                 ▼
                 ┌──────────────────────── Controllers ────────────────────────┐
                 │ CatalogController  (ProductRepository, CategoryRepository,  │
                 │                     CartService)                            │
                 │ CartController     (CartService)                            │
                 │ CheckoutController (CartService, DeliveryMethodRepository,  │
                 │                     OrderValidator, OrderService, Csrf)     │
                 │ ErrorController   (CartService)                             │
                 └───────┬──────────────────────────────────────┬──────────────┘
                         │ данные                               │ View::render(...)
                         ▼                                      ▼
   Services: CartService(ProductRepository, Session)     Support\View ──► views/**.php
             OrderService(CartService, OrderRepository,   (шаблон → $content → layouts/main.php)
                          DeliveryMethodRepository, Session)    только вывод через e()
   Validation: OrderValidator(DeliveryMethodRepository)
   Security: Csrf(Session)        Http: Session (единственная работа с $_SESSION)
                         │
                         ▼
   Repositories: Product / Category / DeliveryMethod / Order  ──► dataset/*.json, storage/orders.json
   Models: Product, Category, DeliveryMethod, CheckoutData (DTO), Order, OrderItem
```

Ответственность слоёв:

- **Router** — только сопоставляет «метод + путь → действие»; HTML не формирует, потому что отвечать за внешний вид должны view, а Router можно было бы тогда не заменить и не протестировать отдельно.
- **Controller** — читает вход ($_GET/$_POST), вызывает сервисы, выбирает view и статус ответа.
- **Model/DTO** — данные и простые вычисления (цена, итог); HTTP не знает.
- **Repository** — чтение/запись JSON.
- **View** — выводит готовые данные, экранируя их через `e()`.

## Безопасность

| Мера | Где |
|------|-----|
| Экранирование всего вывода (`htmlspecialchars` в одной функции `e()`) | `src/Support/functions.php`, все `views/**` |
| Изменение состояния только POST; GET на POST-маршрут → 405 + `Allow: POST` | `Router`, маршруты в `Application` |
| Валидация ввода: количество — целое 1..99, `product_id` — положительный и существующий, форма заказа — `OrderValidator` | `CartController::update/add`, `OrderValidator` |
| Защита от open redirect (`//host`, `\`, `http://…`) | `Support\Redirect::isSafePath` |
| CSRF-токен в форме заказа | `Security\Csrf`, `views/checkout/form.php` |
| Cookie сессии `HttpOnly` + `SameSite=Lax` | `public/index.php` |
| Подробности исключений только в локальном режиме | `Application::run`, `ErrorController::serverError` |
| Нет секретов и временных файлов в Git, `.gitignore` | `tests/run_architecture_checks.php` (раздел «гигиена репозитория») |

## Что хранится в session

```php
$_SESSION['cart']          = [productId => quantity];  // только id и количество
$_SESSION['flash']         = 'сообщение';              // показывается один раз
$_SESSION['csrf_token']    = '64 hex-символа';         // токен формы checkout
$_SESSION['last_order_id'] = 1;                        // для страницы подтверждения
```

Все обращения идут через `App\Http\Session`. Заказы хранятся не в session, а в `storage/orders.json`.

## Откуда приходят данные (контрольный вопрос 1)

- пользовательский ввод: `$_GET` (категория, сортировка), `$_POST` (корзина, форма заказа), путь URL (`/catalog/{id}`) — всё это читают только контроллеры и Router;
- файлы `dataset/*.json` — товары, категории, способы доставки, начальные заказы;
- `storage/orders.json` — рабочее хранилище заказов;
- session — состояние пользователя.

## Тесты и dataset

```bash
php tests/run_all.php   # все блоки подряд
```

| Скрипт | Что проверяет | Файл dataset |
|--------|---------------|--------------|
| `run_lint.php` | `php -l` для всех php-файлов (шаг 13) | — |
| `run_architecture_checks.php` | пункты архитектурного чек-листа и гигиену репозитория | `architecture_checklist.csv` |
| `run_route_tests.php` | HTTP-статус каждого маршрута на живом `php -S` + 405, 404, нормализация пути | `route_tests.csv` |
| `run_security_cases.php` | экранирование, отрицательное количество, неизвестный товар, пустой ввод, CSRF, open redirect, cookie | `security_cases.json` |
| `run_cart_scenarios.php` | сценарии корзины (состав и сумма) | `cart_scenarios.json` |
| `run_order_cases.php` | валидация заказа | `order_cases.json`, `delivery_methods.json` |
| `run_catalog_cases.php` | фильтрация и сортировка каталога | `catalog_cases.csv` |

Остальные файлы dataset используются приложением: `products.json`, `categories.json`, `delivery_methods.json` читаются репозиториями, `orders_seed.json` — начальные данные хранилища заказов.

Результаты прогона и ручной проверки: `docs/test-results.md`. Чек-лист: `docs/checklist-pr11.md`.

## Что изменилось по шагам ПР-11

- **Шаг 1.** Перед рефакторингом поставлены теги `pr09-done` и `pr10-done`; работа разбита на небольшие коммиты (`git log --oneline`).
- **Шаги 3–5.** Namespace и автозагрузка уже были; добавлены `src/bootstrap.php` (автозагрузка вынесена из `index.php`) и `Application`, где видны все зависимости конструкторов. Лишних зависимостей нет.
- **Шаг 6.** Добавлен `Http\Session`: `CartService`, `OrderService`, `Csrf` больше не трогают `$_SESSION`.
- **Шаг 7.** Всё `htmlspecialchars(...)` во views заменено на одну функцию `e()`.
- **Шаги 8–9.** Добавлен `Router` с 404/405; `ensurePost()` в контроллере убран за ненадобностью; `ErrorController` и `NotFoundException` дают единую страницу 404 и страницу 500 с деталями только локально.
- **Шаги 10–11, 13.** Dataset-тесты маршрутов и безопасности работают на живом сервере; добавлен lint.
- **Шаг 12.** `.gitignore` расширен (`/storage/*` кроме `.gitkeep`, `*.bak`, `.env*`); проверка отсутствия секретов — в `run_architecture_checks.php`.
- **Шаг 15.** Появился раздел «Ограничения проекта» (ниже).

## Ограничения проекта

Это учебная версия, а не production-решение:

- вместо базы данных — JSON-файлы; нет транзакций и миграций, блокировка `flock` защищает только запись `orders.json` на одном сервере;
- нет регистрации и авторизации, заказы привязаны к session, а не к пользователю; история заказов не показывается;
- остаток товара (`stock`) не проверяется и не списывается (иначе не проходит сценарий `add:4, set:4:3` из `cart_scenarios.json`);
- CSRF-токен есть только у формы заказа; POST-формы корзины его не используют (из-за этого `POST /cart/add` без токена даёт 302, как ждёт `route_tests.csv`);
- нет оплаты, уведомлений по email, ограничения частоты запросов, HTTPS-настроек (`Secure`-cookie, HSTS), заголовков безопасности (CSP);
- цены хранятся как `float` (для денег в боевой системе нужны целые копейки или decimal);
- свой маленький Router и ручная сборка зависимостей вместо DI-контейнера и фреймворка;
- нет юнит-тестов PHPUnit: проверки сделаны скриптами на сценариях dataset;
- нет пагинации каталога, поиска, кэширования, логирования действий.

## Структура

```
public/index.php                 # session + bootstrap + Application::run()
src/
  bootstrap.php                  # spl_autoload_register, функции-помощники
  Application.php                # зависимости и маршруты
  Http/                          # Router, Session, NotFoundException
  Controllers/                   # Catalog, Cart, Checkout, Error
  Models/                        # Product, Category, DeliveryMethod, CheckoutData, Order, OrderItem
  Repositories/                  # Product, Category, DeliveryMethod, Order
  Services/                      # CartService, OrderService
  Validation/OrderValidator.php
  Security/Csrf.php
  Support/                       # View, Redirect, functions.php (e())
views/                           # layouts/main.php, catalog/, cart/, checkout/, error.php
dataset/                         # json и csv
storage/                         # orders.json создаётся при первом заказе (в .gitignore)
tests/                           # run_all.php и прогонщики, support.php
docs/                            # чек-листы и результаты проверок
```

## Схема checkout (последовательность)

```
Браузер        Router        CheckoutController   OrderValidator   OrderService/Repo        View
   | GET /checkout |                |                  |                  |                  |
   |-------------->|-- form() ----->|-- корзина пуста? → redirect /cart    |                  |
   |               |                |-- render form (способы из delivery_methods.json) ---->|
   |<-------------------------------------------------------- HTML + CSRF-токен -------------|
   | POST /checkout|                |                  |                  |                  |
   |-------------->|-- submit() --->|-- Csrf::isValid? → (нет) 403 + форма                    |
   |               |                |-- normalize/validate ->|             |                  |
   |               |                |-- ошибки → 422 + та же форма + введённые значения ---->|
   |               |                |-- ошибок нет → CheckoutData (DTO) → place() ----------->|
   |               |                |                  |   Order → flock → storage/orders.json
   |               |                |                  |   корзина очищена, last_order_id
   |<-- 302 Location: /checkout/success                |                  |                  |
   | GET /checkout/success          |-- lastOrder() ----------------------->|                  |
   |<-------------------------------------------------- номер, сумма, доставка ----------------|
```
