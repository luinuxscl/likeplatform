<?php

use App\Http\Middleware\EnsureSpokeAccess;
use App\Models\Spoke;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Route::middleware(['web', EnsureSpokeAccess::class.':docs'])
        ->get('/_test/spoke/docs', fn () => response()->json(['ok' => true]));

    Route::middleware(['web', EnsureSpokeAccess::class.':nonexistent'])
        ->get('/_test/spoke/ghost', fn () => response()->noContent());
});

test('access granted when tenant has active spoke subscription', function () {
    $docs = Spoke::factory()->create(['slug' => 'docs', 'is_active' => true]);
    $tenant = Tenant::factory()->create();
    $tenant->grantSpoke($docs);

    app()->instance('current.tenant', $tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

    $response = $this->get('/_test/spoke/docs');

    $response->assertOk()->assertJson(['ok' => true]);
});

test('access denied with 403 when tenant has no subscription', function () {
    Spoke::factory()->create(['slug' => 'docs', 'is_active' => true]);
    $tenant = Tenant::factory()->create();

    app()->instance('current.tenant', $tenant);

    $response = $this->get('/_test/spoke/docs');

    $response->assertForbidden();
});

test('access denied with 404 when spoke does not exist', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('current.tenant', $tenant);

    $response = $this->get('/_test/spoke/ghost');

    $response->assertNotFound();
});

test('access denied when no tenant context is set', function () {
    $response = $this->get('/_test/spoke/docs');

    $response->assertForbidden();
});
