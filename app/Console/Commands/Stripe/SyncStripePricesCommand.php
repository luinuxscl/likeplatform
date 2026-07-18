<?php

namespace App\Console\Commands\Stripe;

use App\Core\Stripe\SpokePlanSyncer;
use App\Models\SpokePlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('stripe:sync-prices {--plan=* : IDs de SpokePlan específicos a sincronizar (opcional)}')]
#[Description('Sincroniza los SpokePlans pagos con productos y precios en Stripe.')]
class SyncStripePricesCommand extends Command
{
    public function handle(SpokePlanSyncer $syncer): int
    {
        $query = SpokePlan::query()->with('spoke');

        if ($ids = $this->option('plan')) {
            $query->whereIn('id', $ids);
        } else {
            $query->where('price_cents', '>', 0);
        }

        $plans = $query->get();

        if ($plans->isEmpty()) {
            $this->warn('No hay SpokePlans pagos para sincronizar.');

            return self::SUCCESS;
        }

        $this->info("Sincronizando {$plans->count()} SpokePlan(s) con Stripe…");

        $rows = [];

        foreach ($plans as $plan) {
            try {
                $synced = $syncer->sync($plan);
                $rows[] = [$plan->id, $plan->spoke->slug, $plan->slug, $synced->stripe_product_id, $synced->stripe_price_id];
            } catch (\Throwable $e) {
                $this->error("Fallo al sincronizar SpokePlan {$plan->id}: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->table(
            ['Plan ID', 'Spoke', 'Plan slug', 'Stripe Product', 'Stripe Price'],
            $rows,
        );

        return self::SUCCESS;
    }
}
