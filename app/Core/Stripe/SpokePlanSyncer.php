<?php

namespace App\Core\Stripe;

use App\Models\SpokePlan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Price;
use Stripe\Product;
use Stripe\StripeClient;

/**
 * Sincroniza SpokePlans con productos y precios en Stripe.
 *
 * Patrón de uso: ejecutar `php artisan stripe:sync-prices` después de cambiar
 * `price_cents` o agregar un nuevo plan. Crea un Product por SpokePlan y un
 * Price recurrente (mensual) en CLP. Los IDs quedan guardados en la fila del
 * plan para usarlos al crear suscripciones con `newSubscription($spoke->slug, $priceId)`.
 */
class SpokePlanSyncer
{
    public function __construct(protected StripeClient $stripe) {}

    /**
     * Sincroniza un único SpokePlan. Devuelve el plan actualizado.
     *
     * Se protege con un lock por plan para evitar ejecuciones concurrentes que
     * crearían productos/precios duplicados en Stripe (race entre dos
     * invocaciones de `sync()` para el mismo SpokePlan).
     */
    public function sync(SpokePlan $plan): SpokePlan
    {
        $this->assertSyncable($plan);

        return Cache::lock("spoke-plan-sync:{$plan->id}", 30)->block(10, function () use ($plan): SpokePlan {
            $plan->refresh();

            $product = $this->syncProduct($plan);
            $price = $this->syncPrice($plan, $product);

            $plan->forceFill([
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
            ])->save();

            Log::info('SpokePlan synced with Stripe', [
                'spoke_plan_id' => $plan->id,
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
            ]);

            return $plan->refresh();
        });
    }

    /**
     * Sincroniza todos los planes con precio > 0. Los planes Free no se crean
     * en Stripe porque no generan cobro (trial sin tarjeta, manejado local).
     *
     * @return array<int, SpokePlan>
     */
    public function syncAll(): array
    {
        return SpokePlan::query()
            ->where('price_cents', '>', 0)
            ->get()
            ->map(fn (SpokePlan $plan): SpokePlan => $this->sync($plan))
            ->all();
    }

    /**
     * Crea o reutiliza el Product en Stripe.
     */
    protected function syncProduct(SpokePlan $plan): Product
    {
        $name = sprintf('%s — %s', $plan->spoke->name, $plan->name);
        $description = $plan->spoke->description;

        if ($plan->stripe_product_id) {
            return $this->stripe->products->update($plan->stripe_product_id, [
                'name' => $name,
                'description' => $description,
            ]);
        }

        return $this->stripe->products->create([
            'name' => $name,
            'description' => $description,
            'metadata' => [
                'spoke_id' => (string) $plan->spoke_id,
                'spoke_plan_id' => (string) $plan->id,
                'spoke_slug' => $plan->spoke->slug,
                'plan_slug' => $plan->slug,
            ],
        ]);
    }

    /**
     * Crea (o reemplaza) el Price recurrente. Stripe no permite editar el
     * amount de un Price, así que si cambia el monto creamos uno nuevo y
     * archivamos el viejo.
     */
    protected function syncPrice(SpokePlan $plan, Product $product): Price
    {
        $amount = (int) $plan->price_cents;

        if ($plan->stripe_price_id) {
            $existing = $this->stripe->prices->retrieve($plan->stripe_price_id);

            if ((int) $existing->unit_amount === $amount && $existing->currency === config('cashier.currency')) {
                return $existing;
            }

            $this->stripe->prices->update($existing->id, ['active' => false]);
        }

        return $this->stripe->prices->create([
            'product' => $product->id,
            'currency' => config('cashier.currency'),
            'unit_amount' => $amount,
            'recurring' => ['interval' => 'month'],
            'metadata' => [
                'spoke_id' => (string) $plan->spoke_id,
                'spoke_plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
            ],
        ]);
    }

    /**
     * Valida que el plan pueda sincronizarse (precio positivo, spoke relacionado).
     */
    protected function assertSyncable(SpokePlan $plan): void
    {
        if (! $plan->spoke) {
            throw new \InvalidArgumentException("SpokePlan {$plan->id} no tiene un Spoke relacionado.");
        }

        if ($plan->price_cents === null || $plan->price_cents <= 0) {
            throw new \InvalidArgumentException("SpokePlan {$plan->id} no es pago (price_cents={$plan->price_cents}). Los planes Free no se sincronizan con Stripe.");
        }
    }
}
