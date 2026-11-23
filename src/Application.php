<?php

declare(strict_types=1);

namespace App;

use App\Controllers\CartController;
use App\Controllers\CatalogController;
use App\Controllers\CheckoutController;
use App\Controllers\ErrorController;
use App\Http\NotFoundException;
use App\Http\Router;
use App\Http\Session;
use App\Repositories\CategoryRepository;
use App\Repositories\DeliveryMethodRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Security\Csrf;
use App\Services\CartService;
use App\Services\OrderService;
use App\Validation\OrderValidator;
use Throwable;

/**
 * Приложение: собирает зависимости (вручную, через конструкторы),
 * регистрирует маршруты и запускает обработку запроса.
 * public/index.php только создаёт Application и вызывает run().
 */
final class Application
{
    private readonly Router $router;
    private readonly ErrorController $errors;

    public function __construct(
        string $basePath,
        private readonly bool $debug = false,
    ) {
        // Хранилища (dataset + storage)
        $products = new ProductRepository($basePath . '/dataset/products.json');
        $categories = new CategoryRepository($basePath . '/dataset/categories.json');
        $delivery = new DeliveryMethodRepository($basePath . '/dataset/delivery_methods.json');
        $orders = new OrderRepository(
            $basePath . '/dataset/orders_seed.json',
            $basePath . '/storage/orders.json',
        );

        // Сервисы
        $session = new Session();
        $cart = new CartService($products, $session);
        $orderService = new OrderService($cart, $orders, $delivery, $session);

        // Контроллеры
        $catalog = new CatalogController($products, $categories, $cart);
        $cartController = new CartController($cart);
        $checkout = new CheckoutController($cart, $delivery, new OrderValidator($delivery), $orderService, new Csrf($session));
        $this->errors = new ErrorController($cart);

        // Маршруты: GET только показывает, любое изменение состояния — POST
        $router = new Router();
        $router->get('/', $catalog->index(...));
        $router->get('/catalog', $catalog->index(...));
        $router->get('/catalog/{id}', $catalog->show(...));

        $router->get('/cart', $cartController->index(...));
        $router->post('/cart/add', $cartController->add(...));
        $router->post('/cart/update', $cartController->update(...));
        $router->post('/cart/remove', $cartController->remove(...));
        $router->post('/cart/clear', $cartController->clear(...));

        $router->get('/checkout', $checkout->form(...));
        $router->post('/checkout', $checkout->submit(...));
        $router->get('/checkout/success', $checkout->success(...));

        $router->setNotFoundHandler(fn() => $this->errors->notFound());
        $router->setMethodNotAllowedHandler(fn(array $allowed) => $this->errors->methodNotAllowed($allowed));

        $this->router = $router;
    }

    public function run(): void
    {
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        try {
            $this->router->dispatch($method, $uri);
        } catch (NotFoundException $e) {
            $this->errors->notFound($e->getMessage());
        } catch (Throwable $e) {
            error_log((string) $e);
            $this->errors->serverError($e, $this->debug);
        }
    }
}
