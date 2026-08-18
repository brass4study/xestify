<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Xestify\core\Database;
use Xestify\database\seeders\BusinessDataSeeder;
use Xestify\exceptions\SeederException;

$pdo = Database::connection();
$seeder = new BusinessDataSeeder($pdo);

try {
    $result = $seeder->run();
} catch (SeederException $e) {
    fwrite(STDERR, "Business data seeder abortado: {$e->getMessage()}\n");
    exit(1);
}

echo "=== Business data seeder (STORY 10.6) ===\n";
echo "Admin: {$result['admin_email']} ({$result['admin_id']})\n\n";

$totalSeeded = 0;
$totalSkipped = 0;
$groupsSeeded = 0;
$groupsSkipped = 0;

const REPORT_ROW_FORMAT = "%-18s %-10s %6s\n";

printf(REPORT_ROW_FORMAT, 'Grupo', 'Estado', 'Filas');
printf(REPORT_ROW_FORMAT, str_repeat('-', 18), str_repeat('-', 10), str_repeat('-', 6));

foreach ($result['groups'] as $group) {
    printf("%-18s %-10s %6d\n", $group['group'], $group['status'], $group['count']);

    if ($group['status'] === 'seeded') {
        $totalSeeded += $group['count'];
        $groupsSeeded++;
    } else {
        $totalSkipped += $group['count'];
        $groupsSkipped++;
    }
}

printf(REPORT_ROW_FORMAT, str_repeat('-', 18), str_repeat('-', 10), str_repeat('-', 6));
echo "\nGrupos sembrados: {$groupsSeeded} | saltados: {$groupsSkipped}\n";
echo "Filas insertadas en esta ejecucion: {$totalSeeded} | filas ya existentes (saltadas): {$totalSkipped}\n";
echo "\nDone.\n";
