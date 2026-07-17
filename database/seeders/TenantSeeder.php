<?php

namespace Database\Seeders;

use App\Models\Spoke;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeds the demo tenants used by the development and QA environments.
 *
 * Idempotent: re-running will not duplicate tenants.
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $acme = Tenant::firstOrCreate(
            ['slug' => 'acme'],
            [
                'name' => 'ACME Corp',
                'is_active' => true,
                'is_suspended' => false,
            ]
        );

        // Grant both MVP spokes with their Free plans to the demo tenant.
        Spoke::active()->each(function (Spoke $spoke) use ($acme): void {
            $freePlan = $spoke->plans()->where('slug', 'free')->first();

            $acme->grantSpoke($spoke, $freePlan);
        });
    }
}
