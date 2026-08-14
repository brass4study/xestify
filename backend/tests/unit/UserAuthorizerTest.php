<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../src/core/Request.php';
require_once __DIR__ . '/../../src/services/UserAuthorizer.php';

use Xestify\core\Request;
use Xestify\services\UserAuthorizer;

const TARGET_USER_ID = 'user-target';

function adminRequest(): Request
{
    $request = new Request();
    $request->setUser(['sub' => 'admin-1', 'roles' => ['admin']]);
    return $request;
}

function operatorRequest(): Request
{
    $request = new Request();
    $request->setUser(['sub' => 'operator-1', 'roles' => ['operador']]);
    return $request;
}

$authorizer = new UserAuthorizer();

// -----------------------------------------------------------------------
// requireAdmin()
// -----------------------------------------------------------------------

TestSuite::run('requireAdmin() is true when the request has the admin role', function () use ($authorizer): void {
    assertTrue($authorizer->requireAdmin(adminRequest()));
});

TestSuite::run('requireAdmin() is false when the request does not have the admin role', function () use ($authorizer): void {
    assertFalse($authorizer->requireAdmin(operatorRequest()));
});

// -----------------------------------------------------------------------
// authorizeDelete()
// -----------------------------------------------------------------------

TestSuite::run('authorizeDelete() denies with ADMIN_REQUIRED when the requester is not an admin', function () use ($authorizer): void {
    $reason = $authorizer->authorizeDelete(operatorRequest(), TARGET_USER_ID, ['sub' => 'operator-1']);
    assertEquals(UserAuthorizer::ADMIN_REQUIRED, $reason);
});

TestSuite::run('authorizeDelete() denies with ID_REQUIRED when the target id is empty', function () use ($authorizer): void {
    $reason = $authorizer->authorizeDelete(adminRequest(), '', ['sub' => 'admin-1']);
    assertEquals(UserAuthorizer::ID_REQUIRED, $reason);
});

TestSuite::run('authorizeDelete() denies with SELF_DELETE when the admin targets their own account', function () use ($authorizer): void {
    $reason = $authorizer->authorizeDelete(adminRequest(), 'admin-1', ['sub' => 'admin-1']);
    assertEquals(UserAuthorizer::SELF_DELETE, $reason);
});

TestSuite::run('authorizeDelete() allows (null) when an admin targets a different user', function () use ($authorizer): void {
    $reason = $authorizer->authorizeDelete(adminRequest(), TARGET_USER_ID, ['sub' => 'admin-1']);
    assertNull($reason);
});

TestSuite::run('authorizeDelete() allows (null) when there is no currently authenticated user to compare against', function () use ($authorizer): void {
    $reason = $authorizer->authorizeDelete(adminRequest(), TARGET_USER_ID, null);
    assertNull($reason);
});

// -----------------------------------------------------------------------
// Admin check takes priority over the id check (same order as the original
// $shouldStop chain in UserController::destroy(), preserved intentionally)
// -----------------------------------------------------------------------

TestSuite::run('authorizeDelete() reports ADMIN_REQUIRED even when the id is also empty', function () use ($authorizer): void {
    $reason = $authorizer->authorizeDelete(operatorRequest(), '', ['sub' => 'operator-1']);
    assertEquals(UserAuthorizer::ADMIN_REQUIRED, $reason);
});

// -----------------------------------------------------------------------
// authorizeDelete() — STORY 10.1 seed-user protection
// -----------------------------------------------------------------------

TestSuite::run('authorizeDelete() denies with PROTECTED_SEED when the target user is a seed account', function () use ($authorizer): void {
    $reason = $authorizer->authorizeDelete(adminRequest(), TARGET_USER_ID, ['sub' => 'admin-1'], ['is_seed' => true]);
    assertEquals(UserAuthorizer::PROTECTED_SEED, $reason);
});

TestSuite::run('authorizeDelete() allows (null) when the target user exists but is not a seed account', function () use ($authorizer): void {
    $reason = $authorizer->authorizeDelete(adminRequest(), TARGET_USER_ID, ['sub' => 'admin-1'], ['is_seed' => false]);
    assertNull($reason);
});

TestSuite::run('authorizeDelete() reports PROTECTED_SEED even when the target is also the requester (self-delete)', function () use ($authorizer): void {
    $reason = $authorizer->authorizeDelete(adminRequest(), 'admin-1', ['sub' => 'admin-1'], ['is_seed' => true]);
    assertEquals(UserAuthorizer::PROTECTED_SEED, $reason);
});

// -----------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
