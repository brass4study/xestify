<?php

declare(strict_types=1);

namespace Xestify\database\seeders;

use DateTimeImmutable;
use PDO;
use Xestify\exceptions\SeederException;

/**
 * Facade for STORY 10.6's business data seeder: resolves the demo admin
 * user, verifies every required plugin is active (aborts the whole run
 * otherwise), then orchestrates CoreEntitySeeder (8 `plugin_entity_data`
 * groups) followed by ExtensionDataSeeder (3 `plugin_extension_data`
 * groups) and merges their per-group summaries.
 *
 * Not invoked on server boot (same precedent as UserSeeder/PluginLoader,
 * see docs/10-productivity/sesion.md sesion 2026-05-07) — run manually via
 * `php tools/setup/seed-business-data.php`.
 */
final class BusinessDataSeeder
{
    private const ADMIN_EMAIL = 'admin@xestify.local';

    /** @var list<string> */
    private const REQUIRED_ACTIVE_SLUGS = [
        'clients', 'distributors', 'ophthalmologists', 'brands', 'manufacturers',
        'orders', 'sales', 'invoices', 'optometries', 'contact_lenses', 'comments',
    ];

    private const DATE_RANGE_YEARS = 2;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{
     *     admin_email: string,
     *     admin_id: string,
     *     groups: list<array{group: string, status: string, count: int}>
     * }
     */
    public function run(): array
    {
        $adminId = $this->resolveAdminId();
        $this->assertRequiredPluginsActive();

        $now = new DateTimeImmutable('today');
        $rangeStart = $now->modify('-' . self::DATE_RANGE_YEARS . ' years');

        $core = new CoreEntitySeeder($this->pdo, $adminId);
        $core->run($now, $rangeStart);

        $extension = new ExtensionDataSeeder($this->pdo, $adminId, [
            'clients' => $core->clientIds,
            'ophthalmologists' => $core->ophthalmologistIds,
            'brands' => $core->brandIds,
            'manufacturers' => $core->manufacturerIds,
            'distributors' => $core->distributorIds,
        ]);
        $extension->run($now, $rangeStart);

        return [
            'admin_email' => self::ADMIN_EMAIL,
            'admin_id' => $adminId,
            'groups' => [...$core->summary(), ...$extension->summary()],
        ];
    }

    private function resolveAdminId(): string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute([':email' => self::ADMIN_EMAIL]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new SeederException(
                'No existe el usuario admin (' . self::ADMIN_EMAIL . '). '
                . 'Ejecuta antes: php tools/setup/seed-admin-user.php'
            );
        }

        return (string) $id;
    }

    private function assertRequiredPluginsActive(): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT slug FROM plugins WHERE slug = ANY(:slugs::text[]) AND status = 'active'"
        );
        $stmt->execute([':slugs' => '{' . implode(',', self::REQUIRED_ACTIVE_SLUGS) . '}']);
        $activeSlugs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $missing = array_values(array_diff(self::REQUIRED_ACTIVE_SLUGS, $activeSlugs));

        if ($missing !== []) {
            throw new SeederException(
                'Plugins requeridos no activos, abortando sin sembrar nada: ' . implode(', ', $missing)
            );
        }
    }
}
