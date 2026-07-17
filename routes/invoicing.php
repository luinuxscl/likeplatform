<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tenant.context', 'spoke.access:invoicing'])
    ->prefix('app/invoicing')
    ->name('invoicing.')
    ->group(function () {
        Route::livewire('/', 'invoicing.index')->name('index');
        Route::livewire('/create', 'invoicing.create')->name('create');
        Route::livewire('/{invoice}', 'invoicing.show')->name('show');
        Route::livewire('/{invoice}/edit', 'invoicing.edit')->name('edit');
    });
