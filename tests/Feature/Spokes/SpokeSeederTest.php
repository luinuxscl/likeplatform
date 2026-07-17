<?php

use App\Models\Spoke;
use Database\Seeders\SpokeSeeder;

test('spoke seeder creates the Docs and Invoicing spokes', function () {
    $this->seed(SpokeSeeder::class);

    expect(Spoke::count())->toBe(2);

    $docs = Spoke::where('slug', 'docs')->first();
    $invoicing = Spoke::where('slug', 'invoicing')->first();

    expect($docs)->not->toBeNull()
        ->and($docs->name)->toBe('Docs')
        ->and($docs->icon)->toBe('document-text');

    expect($invoicing)->not->toBeNull()
        ->and($invoicing->name)->toBe('Invoicing')
        ->and($invoicing->icon)->toBe('banknotes');
});

test('spoke seeder creates Free and Pro plans for each spoke', function () {
    $this->seed(SpokeSeeder::class);

    $docs = Spoke::where('slug', 'docs')->firstOrFail();
    $invoicing = Spoke::where('slug', 'invoicing')->firstOrFail();

    expect($docs->plans)->toHaveCount(2)
        ->and($docs->plans->first()->slug)->toBe('free')
        ->and($docs->plans->last()->slug)->toBe('pro');

    expect($invoicing->plans)->toHaveCount(2)
        ->and($invoicing->plans->first()->slug)->toBe('free')
        ->and($invoicing->plans->last()->slug)->toBe('pro');
});

test('spoke seeder is idempotent', function () {
    $this->seed(SpokeSeeder::class);
    $this->seed(SpokeSeeder::class);

    expect(Spoke::count())->toBe(2);

    $docs = Spoke::where('slug', 'docs')->firstOrFail();
    expect($docs->plans)->toHaveCount(2);
});
