<?php

declare(strict_types=1);

namespace Xestify\exceptions;

use RuntimeException;

/**
 * Thrown when an EntityService operation fails (schema not found,
 * record not found, database error in the service layer).
 */
class EntityServiceException extends RuntimeException
{
}
