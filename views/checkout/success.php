<?php
/** @var App\Models\Order $order */
?>
<div class="hero">
    <span class="pill">заказ оформлен</span>
    <h1>Спасибо за заказ!</h1>
    <p class="muted">Номер заказа: <strong><?= e($order->number()) ?></strong></p>
</div>

<table class="table">
    <thead>
    <tr><th>Товар</th><th>Цена</th><th>Кол-во</th><th>Сумма</th></tr>
    </thead>
    <tbody>
    <?php foreach ($order->items as $item): ?>
        <tr>
            <td><?= e($item->name) ?></td>
            <td><?= e(App\Models\Order::money($item->price)) ?></td>
            <td><?= (int) $item->quantity ?></td>
            <td><strong><?= e(App\Models\Order::money($item->lineTotal())) ?></strong></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="cart-summary">
    <div>
        <div class="muted">Товары: <?= e($order->formattedItemsTotal()) ?></div>
        <div class="muted">Доставка (<?= e($order->deliveryTitle) ?>): <?= e($order->formattedDeliveryPrice()) ?></div>
        <span class="muted">Итого к оплате:</span>
        <div class="total"><?= e($order->formattedTotal()) ?></div>
    </div>
    <a class="btn" href="/catalog">Вернуться в каталог</a>
</div>
