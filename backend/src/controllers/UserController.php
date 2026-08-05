<?php

declare(strict_types=1);

namespace Xestify\controllers;

use Xestify\core\Request;
use Xestify\core\Response;
use Xestify\exceptions\RepositoryException;
use Xestify\repositories\UserRepository;

class UserController
{
    private const MSG_AUTH_REQUIRED = 'Authentication required.';
    private const MSG_USER_NOT_FOUND = 'User not found.';
    private const MSG_ADMIN_REQUIRED = 'Admin role is required.';
    private const MSG_USER_ID_REQUIRED = 'User id is required.';
    private const MSG_EMAIL_CHANGE_PASSWORD_REQUIRED = 'Current password is required when changing email.';
    private const MSG_SELF_DELETE_FORBIDDEN = 'You cannot delete your own account.';
    private const MSG_SELF_DELETE_DETAIL = 'Self-deletion is not allowed.';

    public function __construct(private UserRepository $repository)
    {
    }

    public function me(array $params, ?Request $request = null): void
    {
        $this->ignoreParams($params);
        $request ??= new Request();
        $user = $this->authenticatedUser($request);

        if ($user === null) {
            Response::make()->unauthorized(self::MSG_AUTH_REQUIRED);
            return;
        }

        $profile = $this->repository->find((string) $user['sub']);
        if ($profile === null) {
            Response::make()->notFound(self::MSG_USER_NOT_FOUND);
            return;
        }

        Response::make()->json($profile);
    }

    public function updateMe(array $params, ?Request $request = null): void
    {
        $this->ignoreParams($params);
        $request ??= new Request();
        $user = $this->authenticatedUser($request);

        if ($user === null) {
            Response::make()->unauthorized('Authentication required.');
            return;
        }

        $payload = $request->allBody();
        $id = (string) $user['sub'];

        if (isset($payload['email']) && (string) $payload['email'] !== '') {
            $currentPassword = (string) ($payload['current_password'] ?? '');
            if ($currentPassword === '') {
                Response::make()->unprocessable(self::MSG_EMAIL_CHANGE_PASSWORD_REQUIRED, [
                    'current_password' => ['Required.'],
                ]);
                return;
            }
        }

        $updated = $this->repository->update($id, [
            'name' => $payload['name'] ?? null,
            'email' => $payload['email'] ?? null,
            'avatar' => $payload['avatar'] ?? null,
        ]);

        Response::make()->json($updated);
    }

    public function listUsers(array $params, ?Request $request = null): void
    {
        $this->ignoreParams($params);
        $request ??= new Request();
        if (!$this->isAdmin($request)) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        Response::make()->json($this->repository->all());
    }

    public function show(array $params, ?Request $request = null): void
    {
        $this->ignoreParams($params);
        $request ??= new Request();
        if (!$this->isAdmin($request)) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        $id = (string) ($params['id'] ?? '');
        if ($id === '') {
            Response::make()->notFound(self::MSG_USER_ID_REQUIRED);
            return;
        }

        $user = $this->repository->find($id);
        if ($user === null) {
            Response::make()->notFound(self::MSG_USER_NOT_FOUND);
            return;
        }

        Response::make()->json($user);
    }

    public function update(array $params, ?Request $request = null): void
    {
        $this->ignoreParams($params);
        $request ??= new Request();
        if (!$this->isAdmin($request)) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        $id = (string) ($params['id'] ?? '');
        if ($id === '') {
            Response::make()->notFound(self::MSG_USER_ID_REQUIRED);
            return;
        }

        $payload = $request->allBody();
        $data = [];
        if (array_key_exists('name', $payload)) {
            $data['name'] = $payload['name'];
        }
        if (array_key_exists('email', $payload)) {
            $data['email'] = $payload['email'];
        }
        if (array_key_exists('avatar', $payload)) {
            $data['avatar'] = $payload['avatar'];
        }

        $updated = $this->repository->update($id, $data);
        Response::make()->json($updated);
    }

    public function destroy(array $params, ?Request $request = null): void
    {
        $this->ignoreParams($params);
        $request ??= new Request();

        $targetId = (string) ($params['id'] ?? '');
        $currentUser = $this->authenticatedUser($request);
        $shouldStop = false;

        if (!$this->isAdmin($request)) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            $shouldStop = true;
        }

        if (!$shouldStop && $targetId === '') {
            Response::make()->notFound(self::MSG_USER_ID_REQUIRED);
            $shouldStop = true;
        }

        if (!$shouldStop && $currentUser !== null && (string) ($currentUser['sub'] ?? '') === $targetId) {
            Response::make()->unprocessable(self::MSG_SELF_DELETE_FORBIDDEN, [
                'id' => [self::MSG_SELF_DELETE_DETAIL],
            ]);
            $shouldStop = true;
        }

        if ($shouldStop) {
            return;
        }

        try {
            $this->repository->delete($targetId);
        } catch (RepositoryException $e) {
            Response::make()->notFound($e->getMessage());
            return;
        }

        Response::make()->json(['deleted' => true, 'id' => $targetId]);
    }

    private function ignoreParams(array $params): void
    {
        unset($params);
    }

    private function isAdmin(?Request $request): bool
    {
        if ($request === null) {
            return false;
        }

        $user = $request->user();
        if (!is_array($user)) {
            return false;
        }

        $roles = $user['roles'] ?? [];
        return is_array($roles) && in_array('admin', $roles, true);
    }

    private function authenticatedUser(?Request $request): ?array
    {
        if ($request === null) {
            return null;
        }

        $user = $request->user();
        return is_array($user) ? $user : null;
    }
}
