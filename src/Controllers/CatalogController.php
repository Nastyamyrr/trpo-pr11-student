<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\NotFoundException;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Services\CartService;
use App\Support\View;

/**
 * Контроллер каталога.
 * Получает репозитории и CartService через конструктор.
 */
final class CatalogController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly CartService $cart,
    ) {
    }

    public function index(): void
    {
        $categoryParam = $_GET['category'] ?? null;
        $sort = $_GET['sort'] ?? 'name';

        $allowedSorts = ['name', 'name_desc', 'price', 'price_desc'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        $categoryId = null;
        if ($categoryParam !== null && $categoryParam !== '') {
            $categoryId = (int) $categoryParam;
        }

        $products = $this->productRepository->filter($categoryId, $sort);
        $categories = $this->categoryRepository->all();

        $currentCategory = null;
        if ($categoryId !== null) {
            $currentCategory = $this->categoryRepository->findById($categoryId);
        }

        View::render('catalog/index', [
            'products' => $products,
            'categories' => $categories,
            'currentCategoryId' => $categoryId,
            'currentCategory' => $currentCategory,
            'sort' => $sort,
            'isEmpty' => count($products) === 0,
            'isUnknownCategory' => $categoryId !== null && $currentCategory === null,
            'flash' => $this->cart->getFlash(),
            'cartCount' => $this->cart->count(),
        ]);
    }

    public function show(int $id): void
    {
        $product = $this->productRepository->findById($id);

        if ($product === null) {
            // Application превратит исключение в единый ответ 404
            throw new NotFoundException('Товар с идентификатором ' . $id . ' не найден.');
        }

        $category = $this->categoryRepository->findById($product->categoryId);

        View::render('catalog/show', [
            'product' => $product,
            'category' => $category,
            'flash' => $this->cart->getFlash(),
            'cartCount' => $this->cart->count(),
        ]);
    }
}
