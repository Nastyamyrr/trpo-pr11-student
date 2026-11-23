<?php
/** @var App\Models\Product $product */
/** @var App\Models\Category|null $category */
?>
<a class="back-link" href="/catalog">← Назад в каталог</a>

<div class="product-detail">
    <span class="pill">карточка товара</span>
    <h1><?= e($product->name) ?></h1>

    <p class="price" style="font-size:28px;font-weight:800;margin:12px 0;">
        <?= e($product->formattedPrice()) ?>
    </p>

    <table class="table" style="max-width:420px;margin-top:20px;">
        <tr>
            <th>ID</th>
            <td><?= (int) $product->id ?></td>
        </tr>
        <tr>
            <th>Категория</th>
            <td>
                <?php if ($category !== null): ?>
                    <?= e($category->name) ?>
                    (id <?= (int) $category->id ?>)
                <?php else: ?>
                    #<?= (int) $product->categoryId ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Остаток</th>
            <td class="<?= $product->inStock() ? ($product->stock <= 3 ? 'stock-low' : 'stock-ok') : 'stock-out' ?>">
                <?= e($product->stockLabel()) ?>
            </td>
        </tr>
    </table>

    <div style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap;">
        <?php if ($product->inStock()): ?>
            <form method="post" action="/cart/add">
                <input type="hidden" name="product_id" value="<?= (int) $product->id ?>">
                <input type="hidden" name="redirect" value="/cart">
                <button type="submit" class="btn">Добавить в корзину</button>
            </form>
        <?php else: ?>
            <span class="pill" style="background:#fee2e2;color:#b91c1c;">Нет в наличии</span>
        <?php endif; ?>
        <a class="btn btn-outline" href="/catalog">К списку</a>
    </div>
</div>
