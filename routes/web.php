<?php

use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Stripe webhooks — excluidas de CSRF en bootstrap/app.php
Route::post('stripe/webhook', StripeWebhookController::class)
    ->name('cashier.webhook');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/docs.php';
require __DIR__.'/invoicing.php';
