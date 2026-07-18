<?php

use App\Core\Stripe\SpokePlanSyncer;
use App\Models\Spoke;
use App\Models\SpokePlan;
use Illuminate\Support\Facades\Cache;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;
use Stripe\Util\Util;

function makeStripeProduct(array $attrs = []): Product
{
    return Util::convertToStripeObject(array_merge([
        'id' => 'prod_test_'.uniqid(),
        'object' => 'product',
        'name' => 'Spoke — Plan',
    ], $attrs), ['api_key' => 'sk_test']);
}

function makeStripePrice(array $attrs = []): Price
{
    return Util::convertToStripeObject(array_merge([
        'id' => 'price_test_'.uniqid(),
        'object' => 'price',
        'product' => 'prod_test',
        'currency' => 'clp',
        'unit_amount' => 1999,
        'recurring' => ['interval' => 'month'],
    ], $attrs), ['api_key' => 'sk_test']);
}

beforeEach(function () {
    $this->stripe = Mockery::mock(StripeClient::class);
    $this->app->instance(StripeClient::class, $this->stripe);
    $this->syncer = $this->app->make(SpokePlanSyncer::class);
});

test('sync creates product and price for a paid plan', function () {
    $spoke = Spoke::factory()->create(['slug' => 'docs', 'name' => 'Docs']);
    $plan = SpokePlan::factory()->create([
        'spoke_id' => $spoke->id,
        'name' => 'Pro',
        'slug' => 'pro',
        'price_cents' => 1999,
        'stripe_product_id' => null,
        'stripe_price_id' => null,
    ]);

    $product = makeStripeProduct(['id' => 'prod_test_docs_pro']);
    $price = makeStripePrice(['id' => 'price_test_docs_pro', 'unit_amount' => 1999]);

    $this->stripe->products = Mockery::mock();
    $this->stripe->products->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn ($args) => $args['name'] === 'Docs — Pro'))
        ->andReturn($product);
    $this->stripe->products->shouldNotReceive('update');

    $this->stripe->prices = Mockery::mock();
    $this->stripe->prices->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn ($args) => $args['unit_amount'] === 1999 && $args['currency'] === 'clp'))
        ->andReturn($price);
    $this->stripe->prices->shouldNotReceive('retrieve');
    $this->stripe->prices->shouldNotReceive('update');

    $synced = $this->syncer->sync($plan);

    expect($synced->stripe_product_id)->toBe('prod_test_docs_pro')
        ->and($synced->stripe_price_id)->toBe('price_test_docs_pro');
});

test('sync reuses existing product and creates a new price when amount changes', function () {
    $spoke = Spoke::factory()->create(['slug' => 'invoicing', 'name' => 'Invoicing']);
    $plan = SpokePlan::factory()->create([
        'spoke_id' => $spoke->id,
        'name' => 'Pro',
        'slug' => 'pro',
        'price_cents' => 6990,
        'stripe_product_id' => 'prod_existing',
        'stripe_price_id' => 'price_old',
    ]);

    $oldPrice = makeStripePrice(['id' => 'price_old', 'unit_amount' => 4999, 'currency' => 'clp']);
    $newPrice = makeStripePrice(['id' => 'price_new', 'unit_amount' => 6990, 'currency' => 'clp']);
    $product = makeStripeProduct(['id' => 'prod_existing']);

    $this->stripe->products = Mockery::mock();
    $this->stripe->products->shouldReceive('update')
        ->once()
        ->with('prod_existing', Mockery::any())
        ->andReturn($product);

    $this->stripe->prices = Mockery::mock();
    $this->stripe->prices->shouldReceive('retrieve')
        ->once()
        ->with('price_old')
        ->andReturn($oldPrice);
    $this->stripe->prices->shouldReceive('update')
        ->once()
        ->with('price_old', ['active' => false])
        ->andReturn($oldPrice);
    $this->stripe->prices->shouldReceive('create')
        ->once()
        ->andReturn($newPrice);

    $synced = $this->syncer->sync($plan);

    expect($synced->stripe_price_id)->toBe('price_new');
});

test('sync keeps the existing price when amount and currency are unchanged', function () {
    $spoke = Spoke::factory()->create();
    $plan = SpokePlan::factory()->create([
        'spoke_id' => $spoke->id,
        'price_cents' => 1999,
        'stripe_product_id' => 'prod_existing',
        'stripe_price_id' => 'price_existing',
    ]);

    $existingPrice = makeStripePrice(['id' => 'price_existing', 'unit_amount' => 1999, 'currency' => 'clp']);
    $product = makeStripeProduct(['id' => 'prod_existing']);

    $this->stripe->products = Mockery::mock();
    $this->stripe->products->shouldReceive('update')->once()->andReturn($product);

    $this->stripe->prices = Mockery::mock();
    $this->stripe->prices->shouldReceive('retrieve')
        ->once()
        ->with('price_existing')
        ->andReturn($existingPrice);
    $this->stripe->prices->shouldNotReceive('create');
    $this->stripe->prices->shouldNotReceive('update');

    $synced = $this->syncer->sync($plan);

    expect($synced->stripe_price_id)->toBe('price_existing');
});

test('sync rejects plans without a related spoke', function () {
    $spoke = Spoke::factory()->create();
    $plan = SpokePlan::factory()->create(['spoke_id' => $spoke->id, 'price_cents' => 1999]);
    $plan->setRelation('spoke', null);

    $this->stripe->products = Mockery::mock();
    $this->stripe->products->shouldNotReceive('create');

    expect(fn () => $this->syncer->sync($plan))->toThrow(InvalidArgumentException::class);
});

test('sync rejects free plans (price_cents <= 0)', function () {
    $spoke = Spoke::factory()->create();
    $plan = SpokePlan::factory()->create([
        'spoke_id' => $spoke->id,
        'price_cents' => 0,
    ]);

    $this->stripe->products = Mockery::mock();
    $this->stripe->products->shouldNotReceive('create');

    expect(fn () => $this->syncer->sync($plan))->toThrow(InvalidArgumentException::class);
});

test('syncAll only processes paid plans', function () {
    $spoke = Spoke::factory()->create();
    SpokePlan::factory()->create(['spoke_id' => $spoke->id, 'slug' => 'free', 'price_cents' => 0]);
    SpokePlan::factory()->create(['spoke_id' => $spoke->id, 'slug' => 'pro', 'price_cents' => 1999]);

    $product = makeStripeProduct();
    $price = makeStripePrice();

    $this->stripe->products = Mockery::mock();
    $this->stripe->products->shouldReceive('create')->once()->andReturn($product);
    $this->stripe->prices = Mockery::mock();
    $this->stripe->prices->shouldReceive('create')->once()->andReturn($price);

    $synced = $this->syncer->syncAll();

    expect($synced)->toHaveCount(1)
        ->and($synced[0]->slug)->toBe('pro');
});

test('sync acquires a per-plan lock to prevent duplicate Stripe products on concurrent runs', function () {
    $spoke = Spoke::factory()->create();
    $plan = SpokePlan::factory()->create(['spoke_id' => $spoke->id, 'price_cents' => 1999]);

    $executedInsideLock = false;

    $lock = Mockery::mock();
    $lock->shouldReceive('block')
        ->once()
        ->andReturnUsing(function ($seconds, $closure) use (&$executedInsideLock) {
            $executedInsideLock = true;

            return $closure();
        });
    Cache::shouldReceive('lock')
        ->once()
        ->andReturn($lock);

    $product = makeStripeProduct();
    $price = makeStripePrice();

    $this->stripe->products = Mockery::mock();
    $this->stripe->products->shouldReceive('create')->once()->andReturn($product);
    $this->stripe->prices = Mockery::mock();
    $this->stripe->prices->shouldReceive('create')->once()->andReturn($price);

    $synced = $this->syncer->sync($plan);

    expect($executedInsideLock)->toBeTrue()
        ->and($synced->stripe_product_id)->toBe($product->id)
        ->and($synced->stripe_price_id)->toBe($price->id);
});
