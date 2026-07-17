<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

test('demo seeders create the three demo accounts', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'root@demo.com')->exists())->toBeTrue()
        ->and(User::where('email', 'admin@demo.com')->exists())->toBeTrue()
        ->and(User::where('email', 'user@demo.com')->exists())->toBeTrue();
});

test('root demo account is a platform admin and belongs to no tenant', function () {
    $this->seed(DatabaseSeeder::class);

    $root = User::where('email', 'root@demo.com')->firstOrFail();

    expect($root->isPlatformAdmin())->toBeTrue()
        ->and($root->tenants)->toBeEmpty();
});

test('admin demo account is owner of the ACME tenant', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@demo.com')->firstOrFail();
    $acme = Tenant::where('slug', 'acme')->firstOrFail();

    expect($admin->tenants->pluck('id')->contains($acme->id))->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId($acme->id);
    expect($admin->hasRole('owner'))->toBeTrue();
});

test('user demo account is member of the ACME tenant', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::where('email', 'user@demo.com')->firstOrFail();
    $acme = Tenant::where('slug', 'acme')->firstOrFail();

    expect($user->tenants->pluck('id')->contains($acme->id))->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId($acme->id);
    expect($user->hasRole('member'))->toBeTrue();
});

test('demo accounts share the same known password', function () {
    $this->seed(DatabaseSeeder::class);

    foreach (['root@demo.com', 'admin@demo.com', 'user@demo.com'] as $email) {
        $user = User::where('email', $email)->firstOrFail();
        expect(Hash::check('password', $user->password))->toBeTrue();
    }
});

test('demo seeders are idempotent', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'root@demo.com')->count())->toBe(1)
        ->and(User::where('email', 'admin@demo.com')->count())->toBe(1)
        ->and(User::where('email', 'user@demo.com')->count())->toBe(1);

    $acme = Tenant::where('slug', 'acme')->firstOrFail();
    expect($acme->users()->count())->toBe(2);
});
