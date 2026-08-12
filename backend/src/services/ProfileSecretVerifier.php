<?php

declare(strict_types=1);

namespace Xestify\services;

/**
 * Pure predicate logic for verifying a user's current password before
 * accepting a self-service email or password change (UserController::updateMe()).
 * No I/O, no HTTP — callers own fetching the profile and reporting failures.
 */
final class ProfileSecretVerifier
{
    /**
     * @param array<string, mixed> $payload
     */
    public function isEmailChange(array $payload): bool
    {
        return isset($payload['email']) && (string) $payload['email'] !== '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function isPasswordChange(array $payload): bool
    {
        return isset($payload['password']) && (string) $payload['password'] !== '';
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $profile
     */
    public function requiresVerification(array $payload, array $profile, bool $isEmailChange, bool $isPasswordChange): bool
    {
        if ($isPasswordChange) {
            return true;
        }

        return $isEmailChange && (string) ($payload['email'] ?? '') !== (string) ($profile['email'] ?? '');
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function matches(string $currentPassword, array $profile): bool
    {
        $storedHash = (string) ($profile['password_hash'] ?? '');
        return $storedHash !== '' && password_verify($currentPassword, $storedHash);
    }
}
