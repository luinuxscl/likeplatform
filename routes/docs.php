<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tenant.context', 'spoke.access:docs'])
    ->prefix('app/docs')
    ->name('docs.')
    ->group(function () {
        Route::livewire('/', 'docs.index')->name('index');
        Route::livewire('/create', 'docs.create')->name('create');
        Route::livewire('/{document}', 'docs.show')->name('show');
        Route::livewire('/{document}/edit', 'docs.edit')->name('edit');
    });
