<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../src/exceptions/AuthException.php';
require_once __DIR__ . '/../../src/services/JwtService.php';

use Xestify\services\JwtService;
use Xestify\exceptions\AuthException;

const JWT_TEST_SECRET = 'test-secret-key-for-unit-tests';
const JWT_TEST_TTL    = 3600;
const JWT_TEST_EMAIL  = 'a@b.com';

// -----------------------------------------------------------------------
// encode → decode roundtrip
// -----------------------------------------------------------------------

TestSuite::run('encode/decode roundtrip preserves payload', function (): void {
    $jwt   = new JwtService(JWT_TEST_SECRET, JWT_TEST_TTL);
    $token = $jwt->encode(['sub' => '42', 'email' => JWT_TEST_EMAIL, 'roles' => ['admin']]);
    $data  = $jwt->decode($token);

    assertEquals('42', $data['sub'], 'sub mismatch');
    assertEquals(JWT_TEST_EMAIL, $data['email'], 'email mismatch');
    assertEquals(['admin'], $data['roles'], 'roles mismatch');
});

TestSuite::run('encode adds iat and exp claims', function (): void {
    $jwt   = new JwtService(JWT_TEST_SECRET, JWT_TEST_TTL);
    $before = time();
    $token = $jwt->encode(['sub' => '1']);
    $after  = time();
    $data  = $jwt->decode($token);

    assertTrue(isset($data['iat']), 'iat not set');
    assertTrue(isset($data['exp']), 'exp not set');
    assertTrue($data['iat'] >= $before && $data['iat'] <= $after, 'iat out of range');
    assertEquals(JWT_TEST_TTL, $data['exp'] - $data['iat'], 'exp offset incorrect');
});

TestSuite::run('token is 3-segment string', function (): void {
    $jwt   = new JwtService(JWT_TEST_SECRET, JWT_TEST_TTL);
    $token = $jwt->encode(['sub' => '1']);
    $parts = explode('.', $token);

    assertEquals(3, count($parts), 'Token should have 3 segments');
});

// -----------------------------------------------------------------------
// base64UrlDecode padding (hallazgo 01.06)
// -----------------------------------------------------------------------

TestSuite::run('base64UrlDecode pads correctly for every base64 remainder length', function (): void {
    $jwt = new JwtService(JWT_TEST_SECRET, JWT_TEST_TTL);
    $method = new ReflectionMethod(JwtService::class, 'base64UrlDecode');

    foreach (['f', 'fo', 'foo', 'foob', 'fooba', 'foobar', str_repeat('x', 83)] as $original) {
        $encoded = rtrim(strtr(base64_encode($original), '+/', '-_'), '=');
        $decoded = $method->invoke($jwt, $encoded);
        assertEquals($original, $decoded, "base64UrlDecode should recover the original value for input of length " . strlen($encoded));
    }
});

// -----------------------------------------------------------------------
// Invalid signature
// -----------------------------------------------------------------------

TestSuite::run('decode throws AuthException on wrong signature', function (): void {
    $jwt1  = new JwtService(JWT_TEST_SECRET, JWT_TEST_TTL);
    $jwt2  = new JwtService('other-secret', JWT_TEST_TTL);
    $token = $jwt1->encode(['sub' => '1']);

    $threw = false;
    try {
        $jwt2->decode($token);
    } catch (AuthException) {
        $threw = true;
    }
    assertTrue($threw, 'Should have thrown AuthException');
});

TestSuite::run('decode throws AuthException when signature is tampered', function (): void {
    $jwt   = new JwtService(JWT_TEST_SECRET, JWT_TEST_TTL);
    $token = $jwt->encode(['sub' => '1']);
    $parts = explode('.', $token);
    // Flip last char of signature
    $parts[2] = $parts[2] === 'A' ? 'B' : 'A';
    $tampered = implode('.', $parts);

    $threw = false;
    try {
        $jwt->decode($tampered);
    } catch (AuthException) {
        $threw = true;
    }
    assertTrue($threw, 'Should have thrown AuthException on tampered signature');
});

// -----------------------------------------------------------------------
// Malformed token
// -----------------------------------------------------------------------

TestSuite::run('decode throws on malformed token (1 segment)', function (): void {
    $jwt   = new JwtService(JWT_TEST_SECRET, JWT_TEST_TTL);
    $threw = false;
    try {
        $jwt->decode('notavalidtoken');
    } catch (AuthException) {
        $threw = true;
    }
    assertTrue($threw, 'Should have thrown AuthException for malformed token');
});

TestSuite::run('decode throws on malformed token (2 segments)', function (): void {
    $jwt   = new JwtService(JWT_TEST_SECRET, JWT_TEST_TTL);
    $threw = false;
    try {
        $jwt->decode('header.body');
    } catch (AuthException) {
        $threw = true;
    }
    assertTrue($threw, 'Should have thrown AuthException for 2-segment token');
});

// -----------------------------------------------------------------------
// Expiry
// -----------------------------------------------------------------------

TestSuite::run('decode throws AuthException for expired token', function (): void {
    // TTL = -1 means already expired
    $jwt   = new JwtService(JWT_TEST_SECRET, -1);
    $token = $jwt->encode(['sub' => '99']);

    $threw = false;
    try {
        $jwt->decode($token);
    } catch (AuthException) {
        $threw = true;
    }
    assertTrue($threw, 'Should have thrown AuthException for expired token');
});

// -----------------------------------------------------------------------
// Sliding refresh (shouldRefresh / refresh)
// -----------------------------------------------------------------------

TestSuite::run('shouldRefresh is false for a freshly issued token', function (): void {
    $jwt     = new JwtService(JWT_TEST_SECRET, JWT_TEST_TTL);
    $payload = $jwt->decode($jwt->encode(['sub' => '1']));

    assertFalse($jwt->shouldRefresh($payload), 'A token at full TTL should not need refresh yet');
});

TestSuite::run('shouldRefresh is true once past the TTL midpoint', function (): void {
    $jwt = new JwtService(JWT_TEST_SECRET, 100);
    // Fabricate a payload as if issued 60s ago (40s remaining out of 100s TTL).
    $payload = ['sub' => '1', 'iat' => time() - 60, 'exp' => time() + 40];

    assertTrue($jwt->shouldRefresh($payload), 'A token past its TTL midpoint should be refreshed');
});

TestSuite::run('shouldRefresh is false for an already expired payload', function (): void {
    $jwt     = new JwtService(JWT_TEST_SECRET, 100);
    $payload = ['sub' => '1', 'iat' => time() - 200, 'exp' => time() - 100];

    assertFalse($jwt->shouldRefresh($payload), 'An expired payload is AuthMiddleware\'s job to reject, not refresh');
});

TestSuite::run('shouldRefresh is false when payload has no exp claim', function (): void {
    $jwt = new JwtService(JWT_TEST_SECRET, JWT_TEST_TTL);

    assertFalse($jwt->shouldRefresh(['sub' => '1']), 'Without exp there is nothing to judge refresh against');
});

TestSuite::run('refresh() re-signs the same claims with a renewed exp', function (): void {
    $jwt = new JwtService(JWT_TEST_SECRET, JWT_TEST_TTL);
    // A payload with a stale exp, as if decoded moments before the refresh threshold fired.
    $staleExp = time() - 1;
    $payload  = ['sub' => '7', 'email' => JWT_TEST_EMAIL, 'roles' => ['admin'], 'iat' => time() - 3600, 'exp' => $staleExp];

    $refreshedToken   = $jwt->refresh($payload);
    $refreshedPayload = $jwt->decode($refreshedToken);

    assertEquals('7', $refreshedPayload['sub'], 'sub must survive refresh');
    assertEquals(JWT_TEST_EMAIL, $refreshedPayload['email'], 'email must survive refresh');
    assertEquals(['admin'], $refreshedPayload['roles'], 'roles must survive refresh');
    assertTrue($refreshedPayload['exp'] > $staleExp, 'refreshed token must have a later exp than the stale one it replaces');
});

// -----------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
