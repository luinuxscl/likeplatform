<?php

namespace Database\Seeders;

use App\Models\Spoke;
use Illuminate\Database\Seeder;

/**
 * Seeds the Spoke catalogue with the two MVP spokes (Docs and Invoicing) and
 * their basic plans. This is the catalogue that every tenant will see in the
 * App Switcher once their subscriptions are granted.
 *
 * Idempotent: re-running will not duplicate spokes or plans.
 */
class SpokeSeeder extends Seeder
{
    /**
     * Spoke catalogue definition for the MVP.
     *
     * @var array<int, array{name: string, slug: string, description: string, icon: string, sort_order: int, plans: array<int, array{name: string, slug: string, price_cents: int|null, features: array<int, string>}>}>
     */
    private const SPOKES = [
        [
            'name' => 'Docs',
            'slug' => 'docs',
            'description' => 'Centraliza y comparte la documentación de tu organización.',
            'icon' => 'document-text',
            'sort_order' => 10,
            'plans' => [
                [
                    'name' => 'Free',
                    'slug' => 'free',
                    'price_cents' => 0,
                    'features' => [
                        'Hasta 50 documentos',
                        'Búsqueda básica',
                    ],
                ],
                [
                    'name' => 'Pro',
                    'slug' => 'pro',
                    'price_cents' => 1999,
                    'features' => [
                        'Documentos ilimitados',
                        'Búsqueda avanzada',
                        'Control de versiones',
                    ],
                ],
            ],
        ],
        [
            'name' => 'Invoicing',
            'slug' => 'invoicing',
            'description' => 'Crea, envía y cobra facturas a tus clientes.',
            'icon' => 'banknotes',
            'sort_order' => 20,
            'plans' => [
                [
                    'name' => 'Free',
                    'slug' => 'free',
                    'price_cents' => 0,
                    'features' => [
                        'Hasta 10 facturas/mes',
                        '1 usuario',
                    ],
                ],
                [
                    'name' => 'Pro',
                    'slug' => 'pro',
                    'price_cents' => 4999,
                    'features' => [
                        'Facturas ilimitadas',
                        'Equipo completo',
                        'Exportación CSV',
                    ],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::SPOKES as $spokeData) {
            $plans = $spokeData['plans'];
            unset($spokeData['plans']);

            $spoke = Spoke::firstOrCreate(
                ['slug' => $spokeData['slug']],
                $spokeData,
            );

            foreach ($plans as $planData) {
                $spoke->plans()->firstOrCreate(
                    ['slug' => $planData['slug']],
                    $planData,
                );
            }
        }
    }
}
