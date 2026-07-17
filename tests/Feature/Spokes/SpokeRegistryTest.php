<?php

use App\Core\Spokes\SpokeRegistry;
use App\Models\Spoke;
use App\Models\Tenant;

beforeEach(function () {
    $this->registry = app(SpokeRegistry::class);
});

test('all returns active spokes ordered by sort order', function () {
    Spoke::factory()->create(['slug' => 'alpha', 'is_active' => true, 'sort_order' => 20]);
    Spoke::factory()->create(['slug' => 'beta', 'is_active' => true, 'sort_order' => 10]);
    Spoke::factory()->create(['slug' => 'gamma', 'is_active' => false, 'sort_order' => 0]);

    $this->registry->flush();

    $spokes = $this->registry->all();

    expect($spokes)->toHaveCount(2)
        ->and($spokes->first()->slug)->toBe('beta')
        ->and($spokes->last()->slug)->toBe('alpha');
});

test('findBySlug resolves the correct spoke', function () {
    $docs = Spoke::factory()->create(['slug' => 'docs', 'is_active' => true]);

    $found = $this->registry->findBySlug('docs');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($docs->id);
});

test('findBySlug returns null for unknown slug', function () {
    expect($this->registry->findBySlug('nonexistent'))->toBeNull();
});

test('forTenant returns only subscribed spokes', function () {
    $docs = Spoke::factory()->create(['slug' => 'docs', 'is_active' => true]);
    $invoicing = Spoke::factory()->create(['slug' => 'invoicing', 'is_active' => true]);
    $crm = Spoke::factory()->create(['slug' => 'crm', 'is_active' => true]);

    $tenant = Tenant::factory()->create();
    $tenant->grantSpoke($docs);
    $tenant->grantSpoke($invoicing);

    $available = $this->registry->forTenant($tenant);

    expect($available)->toHaveCount(2)
        ->and($available->pluck('slug')->toArray())->toEqualCanonicalizing(['docs', 'invoicing']);
});

test('tenantHasAccess returns true when subscribed', function () {
    $docs = Spoke::factory()->create(['slug' => 'docs', 'is_active' => true]);
    $tenant = Tenant::factory()->create();
    $tenant->grantSpoke($docs);

    expect($this->registry->tenantHasAccess($tenant, 'docs'))->toBeTrue();
});

test('tenantHasAccess returns false when not subscribed', function () {
    Spoke::factory()->create(['slug' => 'docs', 'is_active' => true]);
    $tenant = Tenant::factory()->create();

    expect($this->registry->tenantHasAccess($tenant, 'docs'))->toBeFalse();
});
