<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Xestify\core\AppDebug;
use Xestify\database\seeders\UserSeeder;

// Seed accounts have well-known passwords and cannot log in unless APP_DEBUG=true
// (AuthController). They exist for development, demos and the test suites; a
// production install gets its real administrator from install.php /
// create-admin-user.php instead.
if (!AppDebug::enabled()) {
    fwrite(
        STDERR,
        "AVISO: APP_DEBUG=false. Los usuarios seed (admin@xestify.local / usuario@xestify.local) tienen\n"
        . "       contrasena conocida y no podran iniciar sesion en este entorno. Para un administrador real\n"
        . "       usa: php tools/setup/create-admin-user.php\n"
    );
}

UserSeeder::seedIfEmpty();

echo "Admin seed executed.\n";
