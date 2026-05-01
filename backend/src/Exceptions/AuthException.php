<?php

declare(strict_types=1);

namespace Xestify\Exceptions;

use RuntimeException;

/**
 * Thrown on authentication failures (invalid credentials, bad token, etc.).
 */
class AuthException extends RuntimeException
{
}
