<?php

// Standalone, destructive only to an isolated in-memory SQLite connection.
// Run from the project root: php database/tests/verify-seeders.php
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\VehicleReferenceSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => 'seed_verification',
    'database.connections' => [
        'seed_verification' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ],
    'cache.default' => 'array',
    'session.driver' => 'array',
    'queue.default' => 'sync',
    'hashing.bcrypt.rounds' => 4,
]);
Env::getRepository()->set('CAR_ADMIN_EMAIL', '');
Env::getRepository()->set('CAR_ADMIN_PASSWORD', '');
$db = DB::connection();
check($db->getDriverName() === 'sqlite' && $db->getDatabaseName() === ':memory:', 'Unsafe test connection.');
check((int) $db->selectOne('PRAGMA foreign_keys')->foreign_keys === 1, 'Foreign keys must be enabled.');
check(Artisan::call('migrate', ['--database' => 'seed_verification', '--force' => true]) === 0, 'Migrations failed.');

// Different sequences prove that relationships do not rely on local IDs.
$tables = $db->getSchemaBuilder()->getTableListing();
foreach ($tables as $index => $qualifiedTable) {
    $table = basename(str_replace('.', '/', $qualifiedTable));
    if ($table !== 'migrations' && $db->getSchemaBuilder()->hasColumn($table, 'id')) {
        $db->table('sqlite_sequence')->updateOrInsert(['name' => $table], ['seq' => 100 + $index * 10]);
    }
}

$seed = function (string $class): void {
    check(Artisan::call('db:seed', [
        '--database' => 'seed_verification', '--class' => $class, '--force' => true,
    ]) === 0, 'Seeder failed: '.$class);
};
$expected = [
    'categories' => 46, 'attributes' => 16, 'attribute_values' => 47,
    'category_attributes' => 26, 'products' => 25, 'product_categories' => 25,
    'product_attribute_values' => 117, 'product_custom_attribute_values' => 69,
    'product_variants' => 4, 'variant_attribute_values' => 6, 'product_images' => 0,
    'vehicle_brands' => 1, 'vehicle_models' => 1, 'vehicle_generations' => 1,
    'vehicle_trims' => 1, 'vehicle_engines' => 1, 'product_vehicle_compat' => 1,
    'users' => 0, 'orders' => 0, 'order_items' => 0, 'payment_attempts' => 0,
    'personal_access_tokens' => 0, 'sessions' => 0, 'phone_verifications' => 0,
    'wishlist_items' => 0,
];
$snapshot = function () use ($db, $expected): array {
    $result = [];
    foreach ($expected as $table => $count) {
        check($db->table($table)->count() === $count, 'Unexpected count: '.$table);
        $result[$table] = $db->table($table)->orderBy('id')->get()->map(function ($row) {
            $values = (array) $row;
            unset($values['created_at'], $values['updated_at'], $values['published_at']);

            return $values;
        })->all();
    }

    return $result;
};
$seed(DatabaseSeeder::class);
$first = $snapshot();
$seed(DatabaseSeeder::class);
check($first === $snapshot(), 'Reseeding changed catalog identities, values, or counts.');
check($db->select('PRAGMA foreign_key_check') === [], 'Foreign key violation.');

$compatibility = $db->table('product_vehicle_compat as c')
    ->join('products as p', 'p.id', '=', 'c.product_id')
    ->join('vehicle_engines as e', 'e.id', '=', 'c.vehicle_engine_id')
    ->join('vehicle_trims as t', 't.id', '=', 'e.vehicle_trim_id')
    ->join('vehicle_generations as g', 'g.id', '=', 't.vehicle_generation_id')
    ->join('vehicle_models as m', 'm.id', '=', 'g.vehicle_model_id')
    ->join('vehicle_brands as b', 'b.id', '=', 'm.vehicle_brand_id')
    ->where('p.slug', 'air-filter-peugeot-pars')->where('e.slug', 'سیبل')
    ->where('t.slug', 'تیپ3')->where('g.slug', 'نسل 4')
    ->where('m.slug', 'می باخ')->where('b.slug', 'بنز')->count();
check($compatibility === 1, 'Incorrect vehicle dependency chain.');
foreach ($db->table('product_variants')->get() as $variant) {
    $ids = $db->table('variant_attribute_values')->where('variant_id', $variant->id)
        ->orderBy('attribute_value_id')->pluck('attribute_value_id')->all();
    check($variant->combination_key === implode('-', $ids), 'Invalid variant combination key.');
}
$db->table('vehicle_engines')->where('slug', 'سیبل')->update(['horsepower' => 120]);
$seed(VehicleReferenceSeeder::class);
check((int) $db->table('vehicle_engines')->where('slug', 'سیبل')->value('horsepower') === 120, 'Reseeding overwrote a catalog correction.');

Env::getRepository()->set('CAR_ADMIN_EMAIL', 'admin@example.test');
Env::getRepository()->set('CAR_ADMIN_PASSWORD', '');
$rejected = false;
try {
    $seed(UserSeeder::class);
} catch (InvalidArgumentException $exception) {
    $rejected = true;
}
check($rejected && User::count() === 0, 'Missing password must not create an account.');
// This generated credential exists only in this process and is never output.
$password = bin2hex(random_bytes(24));
Env::getRepository()->set('CAR_ADMIN_PASSWORD', $password);
$seed(UserSeeder::class);
$admin = User::where('email', 'admin@example.test')->firstOrFail();
check($admin->role === 'admin' && Hash::check($password, $admin->password), 'Administrator provisioning failed.');
$originalPassword = $admin->password;
$db->table('users')->where('id', $admin->id)->update(['role' => 'customer', 'is_active' => false]);
Env::getRepository()->set('CAR_ADMIN_PASSWORD', bin2hex(random_bytes(24)));
$seed(UserSeeder::class);
$admin->refresh();
check(User::count() === 1 && $admin->password === $originalPassword, 'Reseeding changed credentials or duplicated the account.');
check($admin->role === 'customer' && ! $admin->is_active, 'Reseeding promoted or activated an existing account.');
check($db->select('PRAGMA foreign_key_check') === [], 'Final foreign key violation.');

echo "PASS: migrations, repeated seeding, shifted IDs, catalog relationships, foreign keys, preserved corrections and safe admin provisioning.\n";
echo json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
