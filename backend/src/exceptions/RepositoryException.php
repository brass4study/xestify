<?php

declare(strict_types=1);

namespace Xestify\exceptions;

use RuntimeException;

/**
 * Thrown when a repository operation fails (record not found, constraint
 * violation, etc.). Distinct from DatabaseException which covers raw PDO
 * connectivity/infrastructure failures.
 */
class RepositoryException extends RuntimeException
{
}
