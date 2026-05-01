<?php

declare(strict_types=1);

namespace Xestify\Controllers;

use Xestify\Core\Database;
use Xestify\Core\Request;
use Xestify\Core\Response;
use Xestify\Services\JwtService;

class AuthController
{
    private JwtService $jwt;

    public function __construct(JwtService $jwt)
    {
        $this->jwt = $jwt;
    }

    /**
     * POST /api/auth/login
     * Body: { "email": "...", "password": "..." }
     *
     * @param array   $params  Route params (none for this route)
     * @param Request|null $request Injected in tests; built from globals in production
     */
    public function login(array $params, ?Request $request = null): void
    {
        $request ??= Request::fromGlobals($params);

        $email    = (string) $request->body('email', '');
        $password = (string) $request->body('password', '');

        if ($email === '' || $password === '') {
            Response::make()->unprocessable('Email and password are required.', [
                'email'    => $email === '' ? ['Required.'] : [],
                'password' => $password === '' ? ['Required.'] : [],
            ]);
            return;
        }

        $pdo  = Database::connection();
        $stmt = $pdo->prepare('SELECT id, email, password_hash, roles FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user === false || !password_verify($password, (string) $user['password_hash'])) {
            Response::make()->unauthorized('Invalid credentials.');
            return;
        }

        $roles = json_decode((string) $user['roles'], true);

        $token = $this->jwt->encode([
            'sub'   => (string) $user['id'],
            'email' => (string) $user['email'],
            'roles' => is_array($roles) ? $roles : [],
        ]);

        Response::make()->json(['access_token' => $token]);
    }
}
