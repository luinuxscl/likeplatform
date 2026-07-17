<?php

use App\Http\Middleware\EnsureTenantContext;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Route::middleware(['web', EnsureTenantContext::class])->get('/_test/tenant-context', function () {
        return response()->json([
            'tenant_id' => app()->bound('current.tenant') ? app('current.tenant')->id : null,
            'team_id' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
        ]);
    });
});

test('middleware resolves tenant from session and sets permission team id', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'acme',
        'is_active' => true,
        'is_suspended' => false,
    ]);

    $response = $this->withSession(['current_tenant_id' => $tenant->id])
        ->get('/_test/tenant-context');

    $response->assertOk()
        ->assertJson([
            'tenant_id' => $tenant->id,
            'team_id' => $tenant->id,
        ]);
});

test('middleware resolves tenant from route parameter when session is empty', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'globex',
        'is_active' => true,
        'is_suspended' => false,
    ]);

    Route::middleware(['web', EnsureTenantContext::class])
        ->get('/_test/by-slug/{tenant}', function () {
            return response()->json([
                'tenant_id' => app()->bound('current.tenant') ? app('current.tenant')->id : null,
            ]);
        });

    $response = $this->get('/_test/by-slug/globex');

    $response->assertOk()->assertJson(['tenant_id' => $tenant->id]);
});

test('middleware ignores suspended tenants', function () {
    $tenant = Tenant::factory()->create([
        'slug' => 'suspended-co',
        'is_active' => true,
        'is_suspended' => true,
    ]);

    $response = $this->withSession(['current_tenant_id' => $tenant->id])
        ->get('/_test/tenant-context');

    $response->assertOk()->assertJson([
        'tenant_id' => null,
        'team_id' => null,
    ]);
});

test('roles assigned in one tenant are not visible in another tenant', function () {
    $acme = Tenant::factory()->create(['slug' => 'acme', 'is_active' => true, 'is_suspended' => false]);
    $globex = Tenant::factory()->create(['slug' => 'globex', 'is_active' => true, 'is_suspended' => false]);
    $user = User::factory()->create();

    $acme->users()->attach($user->id, ['joined_at' => now()]);
    $globex->users()->attach($user->id, ['joined_at' => now()]);

    // Role templates live with team_id = null (matches RoleSeeder convention) so they
    // can be reused by every tenant via setPermissionsTeamId().
    Role::firstOrCreate([
        'name' => 'owner',
        'guard_name' => 'web',
        'team_id' => null,
    ]);

    $registrar = app(PermissionRegistrar::class);

    $registrar->setPermissionsTeamId($acme->id);
    $registrar->forgetCachedPermissions();
    $user->assignRole('owner');

    $registrar->setPermissionsTeamId($globex->id);
    $registrar->forgetCachedPermissions();
    $globexUser = User::find($user->id);
    expect($globexUser->hasRole('owner'))->toBeFalse()
        ->and($globexUser->getRoleNames())->toBeEmpty();

    $registrar->setPermissionsTeamId($acme->id);
    $registrar->forgetCachedPermissions();
    $acmeUser = User::find($user->id);
    expect($acmeUser->hasRole('owner'))->toBeTrue();
});
