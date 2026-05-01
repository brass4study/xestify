<?php

declare(strict_types=1);

namespace Xestify\exceptions;

use RuntimeException;

/**
 * Thrown when a hook callback blocks an operation or signals a hook-level error.
 */
class HookException extends RuntimeException
{
}

