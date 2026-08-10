<?php

declare(strict_types=1);

namespace Xestify\repositories;

use PDO;
use PDOException;
use PDOStatement;
use Xestify\exceptions\RepositoryException;

class ConfigurationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<int, array{key: string, value: array<string, mixed>, created_at: string, updated_at: string}>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT config_key, value_json, created_at, updated_at
             FROM configuration
             ORDER BY config_key ASC'
        );

        if ($stmt === false) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row): array => $this->mapRow($row), $rows);
    }

    /**
     * @return array{key: string, value: array<string, mixed>, created_at: string, updated_at: string}|null
     */
    public function find(string $key): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT config_key, value_json, created_at, updated_at
             FROM configuration
             WHERE config_key = :config_key'
        );
        $this->execute($stmt, [':config_key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->mapRow($row);
    }

    /**
     * @param array<string, mixed> $value
     * @return array{key: string, value: array<string, mixed>, created_at: string, updated_at: string}
     */
    public function save(string $key, array $value): array
    {
        $encodedValue = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedValue === false) {
            throw new RepositoryException('Configuration payload could not be encoded.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO configuration (config_key, value_json)
             VALUES (:config_key, CAST(:value_json AS JSONB))
             ON CONFLICT (config_key) DO UPDATE
             SET value_json = EXCLUDED.value_json,
                 updated_at = NOW()
             RETURNING config_key, value_json, created_at, updated_at'
        );
        $this->execute($stmt, [
            ':config_key' => $key,
            ':value_json' => $encodedValue,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RepositoryException('Configuration payload could not be saved.');
        }

        return $this->mapRow($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{key: string, value: array<string, mixed>, created_at: string, updated_at: string}
     */
    private function mapRow(array $row): array
    {
        return [
            'key' => (string) ($row['config_key'] ?? ''),
            'value' => $this->decodeValue($row['value_json'] ?? []),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function execute(PDOStatement $stmt, array $params): void
    {
        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            throw new RepositoryException('Configuration query failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

