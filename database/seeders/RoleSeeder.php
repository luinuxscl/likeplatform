<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Creates the tenant-wide roles and permissions that every tenant in the
 * platform will share. Roles are stored as templates (team_id = NULL) and
 * assigned to a user within a specific tenant via `setPermissionsTeamId()`.
 *
 * Idempotent: re-running will not duplicate roles or permissions.
 */
class RoleSeeder extends Seeder
{
    /**
     * Permission matrix: which base permissions each tenant-wide role grants.
     *
     * @var array<string, array<int, string>>
     */
    private const ROLE_PERMISSIONS = [
        'admin' => [
            'tenant.manage_members',
            'tenant.manage_settings',
            'tenant.view_billing',
        ],
        'billing' => [
            'tenant.view_billing',
            'tenant.manage_billing',
        ],
    ];

    /**
     * Roles that have NO base permissions by default (the `owner` role uses
     * Spatie's wildcard feature; `member` is the default role with empty
     * permissions until spoke-level permissions are granted).
     */
    private const ROLES_WITHOUT_PERMISSIONS = ['owner', 'member'];

    /**
     * All tenant-wide permissions the platform supports today.
     *
     * @var array<int, string>
     */
    private const PERMISSIONS = [
        'tenant.manage_members',
        'tenant.manage_settings',
        'tenant.view_billing',
        'tenant.manage_billing',
        'tenant.delete',
    ];

    public function run(): void
    {
        $this->seedPermissions();
        $this->seedRoles();
    }

    private function seedPermissions(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }
    }

    private function seedRoles(): void
    {
        foreach (self::ROLES_WITHOUT_PERMISSIONS as $name) {
            Role::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
                'team_id' => null,
            ]);
        }

        foreach (self::ROLE_PERMISSIONS as $name => $permissions) {
            $role = Role::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
                'team_id' => null,
            ]);

            $role->syncPermissions($permissions);
        }
    }
}
