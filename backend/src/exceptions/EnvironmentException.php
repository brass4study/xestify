<?php

declare(strict_types=1);

namespace Xestify\exceptions;

use RuntimeException;

/**
 * Thrown when a required environment variable is missing at boot time.
 */
class EnvironmentException extends RuntimeException
{
}
