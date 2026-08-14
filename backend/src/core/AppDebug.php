<?php

declare(strict_types=1);

namespace Xestify\core;

/**
 * Single source of truth for reading APP_DEBUG, so every place gating
 * debug-only behavior (health endpoint, seed-user login/listing) parses the
 * env var the same way and fails closed (debug off) when it is unset.
 */
final class AppDebug
{
    public static function enabled(): bool
    {
        return filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
