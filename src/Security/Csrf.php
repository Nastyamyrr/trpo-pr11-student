<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\Session;

/**
 * Учебная защита формы от CSRF: случайный токен в session + скрытое поле формы.
 * POST принимается, только если токен из формы совпал с токеном из session.
 */
final class Csrf
{
    public const FIELD = '_csrf';
    private const SESSION_KEY = 'csrf_token';

    public function __construct(
        private readonly Session $session,
    ) {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    public function isValid(mixed $submitted): bool
    {
        $expected = $this->session->get(self::SESSION_KEY);

        return is_string($submitted) && is_string($expected) && hash_equals($expected, $submitted);
    }
}
