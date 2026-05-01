<?php

declare(strict_types=1);

namespace Xestify\Middleware;

use Xestify\Core\Request;
use Xestify\Core\Response;
use Xestify\Exceptions\AuthException;
use Xestify\Services\JwtService;

/**
 * Validates the Bearer token present in the Authorization header.
 * On success, attaches the decoded payload to the Request via setUser().
 * On failure, immediately returns a 401 response.
 */
class AuthMiddleware
{
    private JwtService $jwt;

    public function __construct(JwtService $jwt)
    {
        $this->jwt = $jwt;
    }

    /**
     * @param callable(Request): void $next
     */
    public function handle(Request $request, callable $next): void
    {
        $token = $request->bearerToken();

        if ($token === null) {
            Response::make()->unauthorized('Missing authorization token.');
            return;
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (AuthException $e) {
            Response::make()->unauthorized($e->getMessage());
            return;
        }

        $request->setUser($payload);
        $next($request);
    }
}
