<?php

declare(strict_types=1);

namespace Ideative\T3BeLogin;

/**
 * Validates login-related hex colors stored in extension configuration (3- or 6-digit #RGB).
 */
final class LoginHexColor
{
    public static function isValid(string $color): bool
    {
        return (bool)preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color);
    }
}
