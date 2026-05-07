<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Xestify\database\seeders\EntitySeeder;

EntitySeeder::migrateLegacyClientRecords();

echo "Legacy client records migration executed.\n";
