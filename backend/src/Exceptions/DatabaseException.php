<?php

declare(strict_types=1);

namespace Xestify\exceptions;

use RuntimeException;

/**
 * Thrown when a database operation fails at the infrastructure level.
 */
class DatabaseException extends RuntimeException
{
}
