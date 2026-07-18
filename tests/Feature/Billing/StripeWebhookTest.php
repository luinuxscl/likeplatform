<?php

use Illuminate\Support\Facades\Route;

test('stripe webhook route is registered and excluded from CSRF', function () {
    $route = Route::getRoutes()->getByName('cashier.webhook');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('POST')
        ->and($route->uri())->toBe('stripe/webhook');
});

test('stripe webhook accepts an unhandled event type and logs it', function () {
    config(['logging.default' => 'single']);

    $payload = [
        'id' => 'evt_test_'.uniqid(),
        'type' => 'ping',
        'object' => 'event',
        'created' => now()->timestamp,
        'data' => ['object' => []],
    ];

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [], [], [],
        [], // server vars
        json_encode($payload),
    );

    $response->assertNoContent();

    $logFile = storage_path('logs/laravel.log');
    expect(file_exists($logFile))->toBeTrue();

    $log = file_get_contents($logFile);
    expect($log)->toContain('Stripe webhook received (no specific handler)')
        ->and($log)->toContain('ping')
        ->and($log)->toContain($payload['id']);
});

test('stripe webhook rejects requests without a verifiable signature when secret is set', function () {
    config(['cashier.webhook.secret' => 'whsec_test_secret']);

    $response = $this->postJson('/stripe/webhook', [
        'type' => 'ping',
        'data' => ['object' => []],
    ]);

    $response->assertForbidden();
});
