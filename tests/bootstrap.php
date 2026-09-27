<?php

/**
 * Test bootstrap: Enforce SQLite in-memory for testing environment.
 *
 * This runs before any test and ensures the actual Laravel config is safe:
 * 1. Forces testing env vars
 * 2. Bootstraps Laravel
 * 3. Checks actual config() values (not just env vars)
 * 4. Fails fast if config points to MySQL 'car' in testing
 */

// Force testing environment variables BEFORE bootstrapping
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

$_ENV['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_CONNECTION'] = 'sqlite';

$_ENV['DB_DATABASE'] = ':memory:';
$_SERVER['DB_DATABASE'] = ':memory:';

$_ENV['SQLITE_DATABASE'] = ':memory:';
$_SERVER['SQLITE_DATABASE'] = ':memory:';

// Load autoloader and bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// NOW check the actual resolved config (respects config cache)
$defaultConn = config('database.default');
$mysqlDb = config('database.connections.mysql.database');
$sqliteDb = config('database.connections.sqlite.database');

$isTesting = app()->environment('testing');

if ($isTesting) {
    $errors = [];

    if ($defaultConn === 'mysql') {
        $errors[] = "database.default = mysql (should be sqlite)";
    }

    if ($mysqlDb === 'car') {
        $errors[] = "database.connections.mysql.database = car (should not be 'car' in testing)";
    }

    if ($sqliteDb === 'car') {
        $errors[] = "database.connections.sqlite.database = car (should be :memory:)";
    }

    if (!empty($errors)) {
        fwrite(STDERR, "\n\033[31m[FATAL] Testing environment but database config is unsafe:\033[0m\n");
        foreach ($errors as $err) {
            fwrite(STDERR, "  - $err\n");
        }
        fwrite(STDERR, "\nThis would run migrate:fresh on your development database!\n");
        fwrite(STDERR, "Fix: php artisan config:clear && php artisan test\n\n");
        exit(1);
    }
}