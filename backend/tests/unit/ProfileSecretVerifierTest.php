<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../src/services/ProfileSecretVerifier.php';

use Xestify\services\ProfileSecretVerifier;

const SAME_EMAIL = 'same@example.com';

$verifier = new ProfileSecretVerifier();

// -----------------------------------------------------------------------
// isEmailChange / isPasswordChange
// -----------------------------------------------------------------------

TestSuite::run('isEmailChange() is true when a non-empty email is present', function () use ($verifier): void {
    assertTrue($verifier->isEmailChange(['email' => 'new@example.com']));
});

TestSuite::run('isEmailChange() is false when email is missing or empty', function () use ($verifier): void {
    assertFalse($verifier->isEmailChange([]));
    assertFalse($verifier->isEmailChange(['email' => '']));
});

TestSuite::run('isPasswordChange() is true when a non-empty password is present', function () use ($verifier): void {
    assertTrue($verifier->isPasswordChange(['password' => 'new-secret']));
});

TestSuite::run('isPasswordChange() is false when password is missing or empty', function () use ($verifier): void {
    assertFalse($verifier->isPasswordChange([]));
    assertFalse($verifier->isPasswordChange(['password' => '']));
});

// -----------------------------------------------------------------------
// requiresVerification()
// -----------------------------------------------------------------------

TestSuite::run('requiresVerification() is true for any password change', function () use ($verifier): void {
    $profile = ['email' => SAME_EMAIL];
    assertTrue($verifier->requiresVerification(['password' => 'x'], $profile, false, true));
});

TestSuite::run('requiresVerification() is true when the email actually changes', function () use ($verifier): void {
    $profile = ['email' => 'old@example.com'];
    assertTrue($verifier->requiresVerification(['email' => 'new@example.com'], $profile, true, false));
});

TestSuite::run('requiresVerification() is false when the "new" email matches the current one', function () use ($verifier): void {
    $profile = ['email' => SAME_EMAIL];
    assertFalse($verifier->requiresVerification(['email' => SAME_EMAIL], $profile, true, false));
});

TestSuite::run('requiresVerification() is false when neither email nor password change', function () use ($verifier): void {
    $profile = ['email' => SAME_EMAIL];
    assertFalse($verifier->requiresVerification(['name' => 'New Name'], $profile, false, false));
});

// -----------------------------------------------------------------------
// matches()
// -----------------------------------------------------------------------

TestSuite::run('matches() is true when the current password matches the stored hash', function () use ($verifier): void {
    $profile = ['password_hash' => password_hash('correct-horse', PASSWORD_BCRYPT)];
    assertTrue($verifier->matches('correct-horse', $profile));
});

TestSuite::run('matches() is false when the current password is wrong', function () use ($verifier): void {
    $profile = ['password_hash' => password_hash('correct-horse', PASSWORD_BCRYPT)];
    assertFalse($verifier->matches('wrong-password', $profile));
});

TestSuite::run('matches() is false when the profile has no stored hash', function () use ($verifier): void {
    assertFalse($verifier->matches('anything', []));
});

// -----------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
