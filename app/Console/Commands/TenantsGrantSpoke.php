<?php

namespace App\Console\Commands;

use App\Models\Spoke;
use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantsGrantSpoke extends Command
{
    protected $signature = 'tenants:grant-spoke
                            {tenant : Tenant slug or ID}
                            {spoke : Spoke slug}';

    protected $description = 'Grant a Spoke subscription to a tenant.';

    public function handle(): int
    {
        $tenant = $this->resolveTenant();

        if ($tenant === null) {
            return self::FAILURE;
        }

        $spoke = Spoke::where('slug', $this->argument('spoke'))->first();

        if ($spoke === null) {
            $this->error("Spoke [{$this->argument('spoke')}] not found.");

            return self::FAILURE;
        }

        if ($tenant->hasSpokeAccess($spoke)) {
            $this->info("Tenant [{$tenant->name}] already has access to [{$spoke->name}].");

            return self::SUCCESS;
        }

        $tenant->grantSpoke($spoke);

        $this->info("Spoke [{$spoke->name}] granted to tenant [{$tenant->name}].");

        return self::SUCCESS;
    }

    private function resolveTenant(): ?Tenant
    {
        $identifier = $this->argument('tenant');

        $tenant = Tenant::where('slug', $identifier)
            ->orWhere('id', is_numeric($identifier) ? (int) $identifier : 0)
            ->first();

        if ($tenant === null) {
            $this->error("Tenant [{$identifier}] not found.");
        }

        return $tenant;
    }
}
