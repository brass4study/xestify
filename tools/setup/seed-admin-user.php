<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Xestify\database\seeders\UserSeeder;

UserSeeder::seedIfEmpty();

echo "Admin seed executed.\n";
