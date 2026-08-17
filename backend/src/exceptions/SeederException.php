<?php

declare(strict_types=1);

namespace Xestify\exceptions;

use RuntimeException;

/**
 * Thrown when a data seeder (STORY 10.6 BusinessDataSeeder and its
 * generators) cannot complete: a required precondition is missing (admin
 * user, active plugin) or a data-generation invariant cannot be satisfied.
 */
class SeederException extends RuntimeException
{
}
