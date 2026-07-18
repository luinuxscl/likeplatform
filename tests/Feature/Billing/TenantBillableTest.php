<?php

use App\Models\Tenant;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Cashier;

test('tenant model uses the Billable trait', function () {
    $traits = class_uses_recursive(Tenant::class);

    expect($traits)->toContain(Billable::class);
});

test('Cashier resolves billable customers through the Tenant model', function () {
    $tenant = Tenant::factory()->create(['stripe_id' => 'cus_test_abc123']);

    $found = Cashier::findBillable('cus_test_abc123');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($tenant->id);
});

test('tenant exposes the cashier billable columns', function () {
    $tenant = Tenant::factory()->create([
        'stripe_id' => 'cus_test_xyz',
        'pm_type' => 'visa',
        'pm_last_four' => '4242',
        'trial_ends_at' => now()->addDays(14),
    ]);

    $fresh = $tenant->fresh();

    expect($fresh->stripe_id)->toBe('cus_test_xyz')
        ->and($fresh->pm_type)->toBe('visa')
        ->and($fresh->pm_last_four)->toBe('4242')
        ->and($fresh->trial_ends_at->isFuture())->toBeTrue();
});

test('tenant has a subscriptions relation powered by the billable trait', function () {
    $tenant = Tenant::factory()->create();

    expect($tenant->subscriptions()->getQuery()->getModel()->getTable())->toBe('subscriptions');
});
