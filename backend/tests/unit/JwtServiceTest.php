<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../src/Exceptions/AuthException.php';
require_once __DIR__ . '/../../src/Services/JwtService.php';

use Xestify\Services\JwtService;
use Xestify\Exceptions\AuthException;

const JWT_TEST_SECRET = 'test-secret-key-for-unit-tests';
const JWT_TEST_TTL    = 3600;

// -----------------------------------------------------------------------
// encode → decode roundtrip
// -----------------------------------------------------------------------

TestSuite::run('encode/decode roundtrip preserves payload', function (): void {
    $jwt   = new JwtService(JWT_TEST_SECRET, JWT_TEST_TTL);
    $token = $jwt->encode(['sub' => '42', 'email' => 'a@b.com', 'roles' => ['admin']]);
    $data  = $jwt->decode($token);

    assertEquals('42', $data['sub'], 'sub mismatch');
    assertEquals('a@b.com', $data['email'], 'email mismatch');
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

TestSuite::summary();
exit(TestSuite::exitCode());
