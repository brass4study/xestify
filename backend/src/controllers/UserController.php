<?php

declare(strict_types=1);

namespace Xestify\controllers;

use Xestify\core\Request;
use Xestify\core\Response;
use Xestify\exceptions\RepositoryException;
use Xestify\repositories\UserRepository;

class UserController
{
    private const ADMIN_RESET_PASSWORD_LENGTH = 14;
    private const MSG_AUTH_REQUIRED = 'Se requiere autenticación.';
    private const MSG_USER_NOT_FOUND = 'No se encontró el usuario.';
    private const MSG_ADMIN_REQUIRED = 'Se requiere rol de administrador.';
    private const MSG_USER_ID_REQUIRED = 'Se requiere el id del usuario.';
    private const MSG_ROLES_INVALID = 'El campo roles debe ser una lista de strings no vacíos.';
    private const MSG_EMAIL_CHANGE_SECRET_REQUIRED = 'Se requiere la contraseña actual al cambiar la dirección de correo.';
    private const MSG_SECRET_CHANGE_REQUIRED = 'Se requiere la contraseña actual al cambiar la contraseña.';
    private const MSG_SECRET_MISMATCH = 'La contraseña actual es incorrecta.';
    private const MSG_SELF_DELETE_FORBIDDEN = 'No puedes eliminar tu propia cuenta.';
    private const MSG_SELF_DELETE_DETAIL = 'No está permitido eliminarse a sí mismo.';

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

        Response::make()->json($this->sanitizeUser($profile));
    }

    public function updateMe(array $params, ?Request $request = null): void
    {
        $this->ignoreParams($params);
        $request ??= new Request();
        $user = $this->authenticatedUser($request);
        if ($user === null) {
            Response::make()->unauthorized(self::MSG_AUTH_REQUIRED);
            return;
        }

        $payload = $request->allBody();
        $id = (string) ($user['sub'] ?? '');
        if ($id === '') {
            Response::make()->unauthorized(self::MSG_AUTH_REQUIRED);
            return;
        }

        $isEmailChange = $this->isEmailChange($payload);
        $isPasswordChange = $this->isPasswordChange($payload);
        if (!$this->validateCurrentSecret($payload, $id, $isEmailChange, $isPasswordChange)) {
            return;
        }

        $updated = $this->repository->update($id, $this->buildProfileUpdateData($payload));
        $this->updatePasswordIfNeeded($payload, $id);
        Response::make()->json($this->sanitizeUser($updated));
    }

    public function listUsers(array $params, ?Request $request = null): void
    {
        $this->ignoreParams($params);
        $request ??= new Request();
        if (!$this->isAdmin($request)) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        Response::make()->json(array_map($this->sanitizeUser(...), $this->repository->all()));
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

        Response::make()->json($this->sanitizeUser($user));
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
        if (array_key_exists('roles', $payload)) {
            $roles = $this->normalizeRolesPayload($payload['roles']);
            if ($roles === null) {
                Response::make()->unprocessable(self::MSG_ROLES_INVALID, [
                    'roles' => ['Formato inválido.'],
                ]);
                return;
            }

            $data['roles'] = $roles;
        }

        $updated = $this->repository->update($id, $data);
        Response::make()->json($this->sanitizeUser($updated));
    }

    public function resetPassword(array $params, ?Request $request = null): void
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

        $plainPassword = $this->generateTemporaryPassword(self::ADMIN_RESET_PASSWORD_LENGTH);

        try {
            $this->repository->updatePassword($id, password_hash($plainPassword, PASSWORD_BCRYPT));
        } catch (RepositoryException $e) {
            Response::make()->notFound($e->getMessage());
            return;
        }

        Response::make()->json([
            'id' => $id,
            'temporary_password' => $plainPassword,
        ]);
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

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function sanitizeUser(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }

    private function validateCurrentSecret(array $payload, string $id, bool $isEmailChange, bool $isPasswordChange): bool
    {
        if (!$isEmailChange && !$isPasswordChange) {
            return true;
        }

        $profile = $this->repository->find($id);
        if ($profile === null) {
            Response::make()->notFound(self::MSG_USER_NOT_FOUND);
            return false;
        }

        $requiresVerification = $this->requiresCurrentSecretVerification($payload, $profile, $isEmailChange, $isPasswordChange);
        $currentPassword = (string) ($payload['current_password'] ?? '');
        $isValid = true;

        if ($requiresVerification && $currentPassword === '') {
            $message = $isEmailChange ? self::MSG_EMAIL_CHANGE_SECRET_REQUIRED : self::MSG_SECRET_CHANGE_REQUIRED;
            Response::make()->unprocessable($message, [
                'current_password' => ['Requerido.'],
            ]);
            $isValid = false;
        } elseif ($requiresVerification && !$this->passwordMatches($currentPassword, $profile)) {
            Response::make()->unprocessable(self::MSG_SECRET_MISMATCH, [
                'current_password' => ['Incorrecto.'],
            ]);
            $isValid = false;
        }

        return $isValid;
    }

    private function requiresCurrentSecretVerification(array $payload, array $profile, bool $isEmailChange, bool $isPasswordChange): bool
    {
        if ($isPasswordChange) {
            return true;
        }

        return $isEmailChange && (string) ($payload['email'] ?? '') !== (string) ($profile['email'] ?? '');
    }

    private function isEmailChange(array $payload): bool
    {
        return isset($payload['email']) && (string) $payload['email'] !== '';
    }

    private function isPasswordChange(array $payload): bool
    {
        return isset($payload['password']) && (string) $payload['password'] !== '';
    }

    private function passwordMatches(string $currentPassword, array $profile): bool
    {
        $storedHash = (string) ($profile['password_hash'] ?? '');
        return $storedHash !== '' && password_verify($currentPassword, $storedHash);
    }

    private function updatePasswordIfNeeded(array $payload, string $id): void
    {
        if (!isset($payload['password']) || (string) $payload['password'] === '') {
            return;
        }

        $this->repository->updatePassword($id, password_hash((string) $payload['password'], PASSWORD_BCRYPT));
    }

    private function buildProfileUpdateData(array $payload): array
    {
        $data = [];

        if (array_key_exists('name', $payload)) {
            $data['name'] = $payload['name'];
        }

        if (array_key_exists('email', $payload) && (string) $payload['email'] !== '') {
            $data['email'] = $payload['email'];
        }

        if (array_key_exists('avatar', $payload)) {
            $data['avatar'] = $payload['avatar'];
        }

        return $data;
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeRolesPayload(mixed $roles): ?array
    {
        $cleanRoles = [];
        $isValid = is_array($roles);

        if ($isValid) {
            foreach ($roles as $role) {
                if (!is_string($role)) {
                    $isValid = false;
                    break;
                }

                $normalizedRole = trim($role);
                if ($normalizedRole === '') {
                    $isValid = false;
                    break;
                }

                if (!in_array($normalizedRole, $cleanRoles, true)) {
                    $cleanRoles[] = $normalizedRole;
                }
            }
        }

        if ($cleanRoles === []) {
            $isValid = false;
        }

        return $isValid ? $cleanRoles : null;
    }

    private function generateTemporaryPassword(int $length): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
        $maxIndex = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $maxIndex)];
        }

        return $password;
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
