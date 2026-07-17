<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the demo accounts used for development, QA, and the screenshots in
 * the project documentation.
 *
 *   - root@demo.com  : platform admin, NOT attached to any tenant
 *   - admin@demo.com : owner of the demo tenant (ACME)
 *   - user@demo.com  : member of the demo tenant (ACME)
 *
 * All three share the same password (`password`) for convenience.
 *
 * Idempotent: re-running will not duplicate users nor duplicate pivot rows.
 */
class DemoUserSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $tenant = Tenant::where('slug', 'acme')->firstOrFail();

        $this->createPlatformAdmin();
        $this->createTenantOwner($tenant);
        $this->createTenantMember($tenant);
    }

    private function createPlatformAdmin(): User
    {
        return $this->createUserIfMissing([
            'name' => 'Root Platform Admin',
            'email' => 'root@demo.com',
            'is_platform_admin' => true,
        ]);
    }

    private function createTenantOwner(Tenant $tenant): User
    {
        $user = $this->createUserIfMissing([
            'name' => 'Demo Admin',
            'email' => 'admin@demo.com',
            'is_platform_admin' => false,
        ]);

        $this->attachToTenant($user, $tenant);
        $this->assignRoleInTenant($user, $tenant, 'owner');

        return $user;
    }

    private function createTenantMember(Tenant $tenant): User
    {
        $user = $this->createUserIfMissing([
            'name' => 'Demo User',
            'email' => 'user@demo.com',
            'is_platform_admin' => false,
        ]);

        $this->attachToTenant($user, $tenant);
        $this->assignRoleInTenant($user, $tenant, 'member');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUserIfMissing(array $attributes): User
    {
        $existing = User::where('email', $attributes['email'])->first();

        if ($existing !== null) {
            return $existing;
        }

        return User::factory()->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => self::DEMO_PASSWORD,
            'is_platform_admin' => $attributes['is_platform_admin'],
            'email_verified_at' => now(),
        ]);
    }

    private function attachToTenant(User $user, Tenant $tenant): void
    {
        $tenant->users()->syncWithoutDetaching([
            $user->id => [
                'joined_at' => now(),
            ],
        ]);
    }

    private function assignRoleInTenant(User $user, Tenant $tenant, string $role): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->id);
        $registrar->forgetCachedPermissions();

        $user->assignRole($role);
    }
}
