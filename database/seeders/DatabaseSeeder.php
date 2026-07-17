<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order is important:
     *   1. RoleSeeder     — creates the Spatie role/permission templates
     *   2. SpokeSeeder    — creates the Spoke catalogue (Docs, Invoicing)
     *   3. TenantSeeder   — creates the demo tenants and grants them Spokes
     *   4. DemoUserSeeder — creates users and assigns them to tenants/roles
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SpokeSeeder::class,
            TenantSeeder::class,
            DemoUserSeeder::class,
        ]);
    }
}
