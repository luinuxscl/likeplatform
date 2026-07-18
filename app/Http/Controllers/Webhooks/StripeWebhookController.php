<?php

namespace App\Http\Controllers\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint para webhooks de Stripe.
 *
 * Extiende el WebhookController de Cashier para reutilizar:
 *  - Verificación de firma vía `STRIPE_WEBHOOK_SECRET`
 *  - Despacho de eventos `WebhookReceived` y `WebhookHandled`
 *  - Convención de métodos `handle{StudlyType}` (se completan en T1.3)
 *
 * Mientras no exista un handler específico para un tipo de evento, registramos
 * el payload en el log para confirmar recepción y dejar rastro de auditoría.
 */
class StripeWebhookController extends CashierWebhookController
{
    /**
     * Entry point invocable. Reenvía al handler de Cashier.
     */
    public function __invoke(Request $request): Response
    {
        return $this->handleWebhook($request);
    }

    /**
     * Maneja tipos de evento sin handler dedicado. Loguea y responde 200 para
     * que Stripe no reintente indefinidamente.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function missingMethod($parameters = []): Response
    {
        $payload = $parameters;

        Log::info('Stripe webhook received (no specific handler)', [
            'type' => $payload['type'] ?? 'unknown',
            'id' => $payload['id'] ?? null,
            'created' => $payload['created'] ?? null,
        ]);

        return response()->noContent();
    }
}
