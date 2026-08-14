<?php

declare(strict_types=1);

namespace Xestify\repositories;

use PDO;
use PDOException;
use Xestify\exceptions\RepositoryException;

final class UserRepository
{
    private const USER_NOT_FOUND_MESSAGE = 'User not found or already deleted: ';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, roles, name, avatar, is_seed, created_at
             FROM users
             WHERE id = :id AND deleted_at IS NULL'
        );
        $this->execute($stmt, [':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->normalizeRow($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, roles, name, avatar, is_seed, created_at
             FROM users
             WHERE email = :email AND deleted_at IS NULL'
        );
        $this->execute($stmt, [':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->normalizeRow($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, email, password_hash, roles, name, avatar, is_seed, created_at
             FROM users
             WHERE deleted_at IS NULL
             ORDER BY created_at ASC'
        );

        if ($stmt === false) {
            return [];
        }

        return array_map(fn(array $row): array => $this->normalizeRow($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(string $id, array $data): array
    {
        $allowed = ['name' => 'name', 'email' => 'email', 'avatar' => 'avatar', 'roles' => 'roles'];
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            if (!isset($allowed[$key])) {
                continue;
            }

            $fields[] = $allowed[$key] . ' = :' . $key;
            $params[':' . $key] = $this->prepareValue($value, $key);
        }

        if ($fields === []) {
            return $this->find($id) ?? [];
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id AND deleted_at IS NULL RETURNING id, email, password_hash, roles, name, avatar, is_seed, created_at';
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $placeholder => $value) {
            $type = PDO::PARAM_STR;
            if ($placeholder === ':avatar' && is_resource($value)) {
                $type = PDO::PARAM_LOB;
            }

            $stmt->bindValue($placeholder, $value, $type);
        }

        $this->execute($stmt);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RepositoryException(self::USER_NOT_FOUND_MESSAGE . $id);
        }

        return $this->normalizeRow($row);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL'
        );
        $this->execute($stmt, [':id' => $id]);

        if ($stmt->rowCount() === 0) {
            throw new RepositoryException(self::USER_NOT_FOUND_MESSAGE . $id);
        }
    }

    public function updatePassword(string $id, string $hash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = :hash WHERE id = :id AND deleted_at IS NULL'
        );
        $this->execute($stmt, [':id' => $id, ':hash' => $hash]);

        if ($stmt->rowCount() === 0) {
            throw new RepositoryException(self::USER_NOT_FOUND_MESSAGE . $id);
        }
    }

    /**
     * @param array<string, mixed>|null $params Omit when the statement's values were already bound via bindValue().
     */
    private function execute(\PDOStatement $stmt, ?array $params = null): void
    {
        try {
            if ($params === null) {
                $stmt->execute();
            } else {
                $stmt->execute($params);
            }
        } catch (PDOException $e) {
            throw new RepositoryException('Query failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        if (array_key_exists('avatar', $row)) {
            $row['avatar'] = $this->normalizeBinaryValue($row['avatar']);
        }

        if (array_key_exists('roles', $row)) {
            $row['roles'] = $this->normalizeRolesValue($row['roles']);
        }

        if (array_key_exists('is_seed', $row)) {
            $row['is_seed'] = $this->normalizeBooleanValue($row['is_seed']);
        }

        return $row;
    }

    private function normalizeBooleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $value === 't' || $value === 'true' || $value === '1';
        }

        return (bool) $value;
    }

    private function normalizeRolesValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $value;
    }

    private function normalizeBinaryValue(mixed $value): mixed
    {
        if (is_resource($value)) {
            $content = stream_get_contents($value);
            return $content === false ? '' : $content;
        }

        if (is_string($value) && str_starts_with($value, 'Resource id #')) {
            return '';
        }

        return $value;
    }

    private function prepareValue(mixed $value, string $fieldName): mixed
    {
        $result = $value;

        if ($fieldName === 'roles') {
            if (is_array($value)) {
                $json = json_encode($value);
                $result = $json === false ? '[]' : $json;
            } elseif (is_string($value)) {
                $result = $value;
            } else {
                $result = '[]';
            }
        }

        if ($fieldName === 'avatar' && is_string($value)) {
            $stream = fopen('php://temp', 'r+');
            if ($stream !== false) {
                fwrite($stream, $value);
                rewind($stream);
                $result = $stream;
            }
        }

        return $result;
    }
}
