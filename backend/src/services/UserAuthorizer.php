<?php

declare(strict_types=1);

namespace Xestify\services;

use Xestify\core\Request;

/**
 * Decisiones de autorización sobre usuarios. Hoy solo cubre el borrado
 * (UserController::destroy()); pensada para poder añadir más acciones o
 * ser absorbida por un AuthorizationService más amplio cuando llegue
 * EPIC A6 (matriz de permisos por recurso/acción, post-MVP). Sin I/O,
 * sin HTTP — el llamador traduce el resultado a una respuesta concreta.
 */
final class UserAuthorizer
{
    public const ADMIN_REQUIRED = 'admin_required';
    public const ID_REQUIRED = 'id_required';
    public const SELF_DELETE = 'self_delete';

    public function requireAdmin(Request $request): bool
    {
        return $request->hasRole('admin');
    }

    /**
     * @param array<string, mixed>|null $currentUser
     */
    public function authorizeDelete(Request $request, string $targetUserId, ?array $currentUser): ?string
    {
        return match (true) {
            !$this->requireAdmin($request) => self::ADMIN_REQUIRED,
            $targetUserId === '' => self::ID_REQUIRED,
            $currentUser !== null && (string) ($currentUser['sub'] ?? '') === $targetUserId => self::SELF_DELETE,
            default => null,
        };
    }
}
