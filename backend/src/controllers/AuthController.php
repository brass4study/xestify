<?php

declare(strict_types=1);

namespace Xestify\controllers;

use Xestify\core\AppDebug;
use Xestify\core\Request;
use Xestify\core\RequestFactory;
use Xestify\core\Response;
use Xestify\core\RuntimePathNormalizer;
use Xestify\repositories\UserRepository;
use Xestify\services\JwtService;

class AuthController
{
    public function __construct(
        private JwtService $jwt,
        private UserRepository $users,
        private ?RequestFactory $requestFactory = null
    ) {
    }

    /**
     * POST /api/v1/auth/login
     * Body: { "email": "...", "password": "..." }
     *
     * @param array   $params  Route params (none for this route)
     * @param Request|null $request Injected in tests; built from globals in production
     */
    public function login(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);

        $email    = (string) $request->body('email', '');
        $password = (string) $request->body('password', '');

        if ($email === '' || $password === '') {
            Response::make()->unprocessable('Email and password are required.', [
                'email'    => $email === '' ? ['Required.'] : [],
                'password' => $password === '' ? ['Required.'] : [],
            ]);
            return;
        }

        $user = $this->users->findByEmail($email);
        $isBlockedSeedUser = $user !== null && ($user['is_seed'] ?? false) === true && !AppDebug::enabled();

        if ($user === null || $isBlockedSeedUser || !password_verify($password, (string) $user['password_hash'])) {
            Response::make()->unauthorized('Invalid credentials.');
            return;
        }

        $roles = $user['roles'];

        $token = $this->jwt->encode([
            'sub'   => (string) $user['id'],
            'email' => (string) $user['email'],
            'roles' => is_array($roles) ? $roles : [],
        ]);

        Response::make()->json([
            'access_token' => $token,
            'email'        => (string) $user['email'],
        ]);
    }

    private function requestFactory(): RequestFactory
    {
        if ($this->requestFactory === null) {
            $this->requestFactory = new RequestFactory(new RuntimePathNormalizer());
        }

        return $this->requestFactory;
    }
}
