<?php
/** @var int $code */
/** @var string $title */
/** @var string $message */
/** @var string|null $details */
?>
<div class="hero">
    <span class="pill"><?= e($code) ?></span>
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e($message) ?></p>
    <?php if (!empty($details)): ?>
        <p class="muted"><small>Локальный режим: <?= e($details) ?></small></p>
    <?php endif; ?>
    <p style="margin-top:20px;">
        <a class="btn" href="/catalog">Перейти в каталог</a>
    </p>
</div>
