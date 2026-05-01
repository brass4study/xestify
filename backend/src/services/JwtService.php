<?php

declare(strict_types=1);

namespace Xestify\services;

use Xestify\exceptions\AuthException;

/**
 * JWT implementation using HS256 (HMAC-SHA256).
 * Zero external dependencies ÔÇö pure PHP.
 */
class JwtService
{
    private const ALGORITHM = 'HS256';
    private const HASH_ALGO = 'sha256';

    private string $secret;
    private int $ttl;

    public function __construct(string $secret, int $ttl = 3600)
    {
        $this->secret = $secret;
        $this->ttl    = $ttl;
    }

    /**
     * Creates a signed JWT from the given payload.
     * Automatically adds 'iat' and 'exp' claims.
     */
    public function encode(array $payload): string
    {
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $this->ttl;

        $header = $this->base64UrlEncode(
            (string) json_encode(['typ' => 'JWT', 'alg' => self::ALGORITHM])
        );
        $body = $this->base64UrlEncode(
            (string) json_encode($payload)
        );

        $signature = $this->sign("{$header}.{$body}");

        return "{$header}.{$body}.{$signature}";
    }

    /**
     * Validates and decodes a JWT. Returns the payload array.
     *
     * @throws AuthException If the token is malformed, has invalid signature, or is expired.
     */
    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new AuthException('Malformed token.');
        }

        [$header, $body, $signature] = $parts;

        $expected = $this->sign("{$header}.{$body}");
        if (!hash_equals($expected, $signature)) {
            throw new AuthException('Invalid token signature.');
        }

        $payload = json_decode($this->base64UrlDecode($body), true);
        if (!is_array($payload)) {
            throw new AuthException('Could not decode token payload.');
        }

        if (isset($payload['exp']) && time() > (int) $payload['exp']) {
            throw new AuthException('Token has expired.');
        }

        return $payload;
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(
            hash_hmac(self::HASH_ALGO, $data, $this->secret, true)
        );
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT);
        return (string) base64_decode($padded);
    }
}
