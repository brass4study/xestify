<?php

declare(strict_types=1);

namespace Xestify\database\seeders;

use PDO;
use Xestify\exceptions\SeederException;

/**
 * Creates the first *real* administrator of an installation — as opposed to
 * the fixed seed accounts of UserSeeder (`is_seed = TRUE`), which are blocked
 * from logging in whenever APP_DEBUG is off (AuthController) and are never
 * meant for production. The application itself has no user-creation endpoint
 * (see STORY A1.8 in the backlog), so until it does this is the only way to
 * bootstrap access on a fresh install: run by `tools/setup/install.php` and by
 * `tools/setup/create-admin-user.php`.
 */
final class AdminUserCreator
{
    public const MIN_PASSWORD_LENGTH = 12;

    private const ADMIN_ROLES_JSON = '["admin"]';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * True when at least one non-seed, non-deleted user with the admin role
     * exists — i.e. someone can already log in and administer the install.
     */
    public function hasRealAdmin(): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM users
              WHERE is_seed = FALSE AND deleted_at IS NULL AND roles @> CAST(:roles AS jsonb)
              LIMIT 1'
        );
        $stmt->execute([':roles' => self::ADMIN_ROLES_JSON]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Validates and inserts a real admin user. Throws SeederException with a
     * user-facing message on invalid input or duplicate email.
     *
     * @return array{id: string, email: string, name: string}
     */
    public function create(string $email, string $name, string $password): array
    {
        $email = trim($email);
        $name = trim($name);

        $this->assertValidInput($email, $name, $password);
        $this->assertEmailAvailable($email);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, password_hash, roles, name, is_seed)
             VALUES (:email, :hash, :roles, :name, FALSE)
             RETURNING id, email, name'
        );
        $stmt->execute([
            ':email' => $email,
            ':hash' => password_hash($password, PASSWORD_BCRYPT),
            ':roles' => self::ADMIN_ROLES_JSON,
            ':name' => $name,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new SeederException('No se pudo crear el usuario administrador.');
        }

        return [
            'id' => (string) $row['id'],
            'email' => (string) $row['email'],
            'name' => (string) $row['name'],
        ];
    }

    private function assertValidInput(string $email, string $name, string $password): void
    {
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new SeederException("Email de administrador no valido: '{$email}'.");
        }

        if ($name === '') {
            throw new SeederException('El nombre del administrador no puede estar vacio.');
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new SeederException(
                'La contrasena del administrador debe tener al menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres.'
            );
        }
    }

    private function assertEmailAvailable(string $email): void
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);

        if ($stmt->fetchColumn() !== false) {
            throw new SeederException("Ya existe un usuario con el email '{$email}'.");
        }
    }
}
