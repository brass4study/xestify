<?php

declare(strict_types=1);

namespace Xestify\exceptions;

use RuntimeException;

/**
 * Thrown on authentication failures (invalid credentials, bad token, etc.).
 */
class AuthException extends RuntimeException
{
}
