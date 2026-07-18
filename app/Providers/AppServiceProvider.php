<?php

namespace App\Providers;

use App\Core\Spokes\SpokeRegistry;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SpokeRegistry::class);
        $this->app->singleton(StripeClient::class, fn (): StripeClient => Cashier::stripe());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureCashier();
    }

    /**
     * Configure Cashier to use Tenant as the billable model.
     *
     * LikePlatform cobra a la organización (Tenant), no al usuario individual,
     * por eso las migraciones de Cashier apuntan a tenants/subscriptions.tenant_id
     * en vez de la pareja users/subscriptions.user_id por defecto del paquete.
     */
    protected function configureCashier(): void
    {
        Cashier::useCustomerModel(Tenant::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
