<?php

use Cachet\Database\Seeders\DatabaseSeeder;
use Cachet\Enums\DefaultGroupEnum;
use Cachet\Models\Group;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Workbench\App\User;

function groupsMigration(): object
{
    return require __DIR__.'/../../../database/migrations/2026_11_26_000001_create_groups_tables.php';
}

it('replaces the admin flag with the default groups', function () {
    groupsMigration()->down();

    $now = now();
    DB::table('users')->insert([
        ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x', 'is_admin' => true, 'created_at' => $now, 'updated_at' => $now],
        ['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'x', 'is_admin' => false, 'created_at' => $now, 'updated_at' => $now],
    ]);

    groupsMigration()->up();

    $keysOf = fn (string $email): array => User::query()->where('email', $email)->sole()
        ->groups->map(fn (Group $group): ?DefaultGroupEnum => $group->key)->all();

    expect(Schema::hasColumn('users', 'is_admin'))->toBeFalse()
        ->and($keysOf('admin@example.com'))->toBe([DefaultGroupEnum::administrators])
        ->and($keysOf('staff@example.com'))->toBe([DefaultGroupEnum::read_only]);
});

it('restores the admin flag when rolled back', function () {
    $admin = User::factory()->administrator()->create();
    $staff = User::factory()->create();

    groupsMigration()->down();

    expect(DB::table('users')->where('id', $admin->getKey())->value('is_admin'))->toBeTruthy()
        ->and(DB::table('users')->where('id', $staff->getKey())->value('is_admin'))->toBeFalsy()
        ->and(Schema::hasTable('groups'))->toBeFalse();

    groupsMigration()->up();
});

it('seeds the demo user as an administrator', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'test@test.com')->sole()->isAdministrator())->toBeTrue();
});
