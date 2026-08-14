<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../src/services/ProfileSecretVerifier.php';
require_once __DIR__ . '/../../src/services/ProfileUpdateAuthorizer.php';

use Xestify\services\ProfileUpdateAuthorizer;

const PU_EMAIL = 'same@example.com';

function buildProfile(bool $isSeed = false): array
{
    return [
        'email' => PU_EMAIL,
        'password_hash' => password_hash('correct-horse', PASSWORD_BCRYPT),
        'is_seed' => $isSeed,
    ];
}

$authorizer = new ProfileUpdateAuthorizer();

// -----------------------------------------------------------------------
// No real change requested
// -----------------------------------------------------------------------

TestSuite::run('authorize() allows (null) when neither email nor password change', function () use ($authorizer): void {
    $reason = $authorizer->authorize(['name' => 'New Name'], buildProfile());
    assertNull($reason);
});

TestSuite::run('authorize() allows (null) when the payload resends the same email unchanged', function () use ($authorizer): void {
    $reason = $authorizer->authorize(['name' => 'New Name', 'email' => PU_EMAIL], buildProfile());
    assertNull($reason);
});

TestSuite::run('authorize() allows (null) when a seed profile only renames itself (email unchanged)', function () use ($authorizer): void {
    $reason = $authorizer->authorize(['name' => 'New Name', 'email' => PU_EMAIL], buildProfile(true));
    assertNull($reason);
});

// -----------------------------------------------------------------------
// Missing profile
// -----------------------------------------------------------------------

TestSuite::run('authorize() denies with NOT_FOUND when the profile does not exist', function () use ($authorizer): void {
    $reason = $authorizer->authorize(['password' => 'new-secret', 'current_password' => 'x'], null);
    assertEquals(ProfileUpdateAuthorizer::NOT_FOUND, $reason);
});

// -----------------------------------------------------------------------
// Protected seed accounts
// -----------------------------------------------------------------------

TestSuite::run('authorize() denies with PROTECTED_SEED when a seed profile attempts a real email change', function () use ($authorizer): void {
    $reason = $authorizer->authorize(['email' => 'new@example.com', 'current_password' => 'correct-horse'], buildProfile(true));
    assertEquals(ProfileUpdateAuthorizer::PROTECTED_SEED, $reason);
});

TestSuite::run('authorize() denies with PROTECTED_SEED when a seed profile attempts a password change', function () use ($authorizer): void {
    $reason = $authorizer->authorize(['password' => 'new-secret', 'current_password' => 'correct-horse'], buildProfile(true));
    assertEquals(ProfileUpdateAuthorizer::PROTECTED_SEED, $reason);
});

// -----------------------------------------------------------------------
// Current-password requirement (non-seed profiles)
// -----------------------------------------------------------------------

TestSuite::run('authorize() denies with EMAIL_SECRET_REQUIRED when changing email without current_password', function () use ($authorizer): void {
    $reason = $authorizer->authorize(['email' => 'new@example.com'], buildProfile());
    assertEquals(ProfileUpdateAuthorizer::EMAIL_SECRET_REQUIRED, $reason);
});

TestSuite::run('authorize() denies with PASSWORD_SECRET_REQUIRED when changing password without current_password', function () use ($authorizer): void {
    $reason = $authorizer->authorize(['password' => 'new-secret'], buildProfile());
    assertEquals(ProfileUpdateAuthorizer::PASSWORD_SECRET_REQUIRED, $reason);
});

TestSuite::run('authorize() denies with SECRET_MISMATCH when current_password is wrong', function () use ($authorizer): void {
    $reason = $authorizer->authorize(['password' => 'new-secret', 'current_password' => 'wrong'], buildProfile());
    assertEquals(ProfileUpdateAuthorizer::SECRET_MISMATCH, $reason);
});

TestSuite::run('authorize() allows (null) a real email change with the correct current_password', function () use ($authorizer): void {
    $reason = $authorizer->authorize(['email' => 'new@example.com', 'current_password' => 'correct-horse'], buildProfile());
    assertNull($reason);
});

TestSuite::run('authorize() allows (null) a password change with the correct current_password', function () use ($authorizer): void {
    $reason = $authorizer->authorize(['password' => 'new-secret', 'current_password' => 'correct-horse'], buildProfile());
    assertNull($reason);
});

// -----------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
