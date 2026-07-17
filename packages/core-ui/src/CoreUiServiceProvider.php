<?php

namespace CoreUi;

use CoreUi\View\Components\AppSwitcher;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class CoreUiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Blade::anonymousComponentPath(
            __DIR__.'/../resources/views/components',
            'core',
        );

        Blade::component('app-switcher', AppSwitcher::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'core-ui');
    }
}
