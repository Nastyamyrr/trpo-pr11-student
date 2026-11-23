<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * Бросается контроллером, если запрошенный объект не найден (например, товар).
 * Application превращает её в единый ответ 404.
 */
final class NotFoundException extends RuntimeException
{
}
