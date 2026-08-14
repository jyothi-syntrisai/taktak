<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Services\AuthService;
use App\Support\IndianStates;
use App\Support\Permissions;
use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Idempotent. Re-running only fills in what is missing - it never resets a role
 * whose permissions an administrator has since changed, and it never touches an
 * existing Super Admin password.
 *
 * `created_by` / `updated_by` are left NULL for these rows, because they are
 * written before any user exists.
 *
 *     php spark db:seed InitialSeeder
 */
class InitialSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedRoles();
        $this->seedRolePermissions();
        $this->seedStates();
        $this->seedSuperAdmin();
        $this->warnAboutStalePermissions();

        $this->say('Seed complete');
    }

    private function seedPermissions(): void
    {
        $existing = array_column($this->db->table('permissions')->select('slug')->get()->getResultArray(), 'slug');

        $missing = array_values(array_filter(
            Permissions::all(),
            static fn (array $permission): bool => ! in_array($permission['slug'], $existing, true),
        ));

        if ($missing !== []) {
            $this->db->table('permissions')->insertBatch(array_map(
                static fn (array $p): array => $p + ['created_by' => null, 'updated_by' => null],
                $missing,
            ));
        }

        $this->say(sprintf('Permissions: %d added, %d already present', count($missing), count($existing)));
    }

    private function seedRoles(): void
    {
        $added = 0;

        foreach (Permissions::SYSTEM_ROLES as $definition) {
            $exists = $this->db->table('roles')->where('name', $definition['name'])->countAllResults() > 0;

            if ($exists) {
                continue;
            }

            $this->db->table('roles')->insert([
                'name'        => $definition['name'],
                'description' => $definition['description'],
                'is_system'   => 1,
                'status'      => 'active',
                'created_by'  => null,
                'updated_by'  => null,
            ]);
            $added++;
        }

        $this->say(sprintf('Roles: %d added, %d already present', $added, count(Permissions::SYSTEM_ROLES) - $added));
    }

    private function seedRolePermissions(): void
    {
        $permissions = $this->db->table('permissions')->select('id, slug')->get()->getResultArray();
        $idBySlug    = array_column($permissions, 'id', 'slug');

        foreach (Permissions::defaultRolePermissions() as $roleName => $slugs) {
            $role = $this->db->table('roles')->where('name', $roleName)->get()->getRowArray();

            if ($role === null) {
                continue;
            }

            $held = array_map(
                'intval',
                array_column(
                    $this->db->table('role_permissions')
                        ->select('permission_id')
                        ->where('role_id', $role['id'])
                        ->get()
                        ->getResultArray(),
                    'permission_id',
                ),
            );

            $toAdd = [];

            foreach ($slugs as $slug) {
                $permissionId = isset($idBySlug[$slug]) ? (int) $idBySlug[$slug] : null;

                if ($permissionId !== null && ! in_array($permissionId, $held, true)) {
                    $toAdd[] = [
                        'role_id'       => (int) $role['id'],
                        'permission_id' => $permissionId,
                        'created_by'    => null,
                        'updated_by'    => null,
                    ];
                }
            }

            if ($toAdd !== []) {
                $this->db->table('role_permissions')->insertBatch($toAdd);
            }

            $this->say(sprintf(
                'Role %s: %d permission(s) granted, %d already held',
                $roleName,
                count($toAdd),
                count($held),
            ));
        }
    }

    private function seedStates(): void
    {
        $existing = array_column($this->db->table('states')->select('name')->get()->getResultArray(), 'name');

        $missing = array_values(array_filter(
            IndianStates::ALL,
            static fn (array $state): bool => ! in_array($state['name'], $existing, true),
        ));

        if ($missing !== []) {
            $this->db->table('states')->insertBatch(array_map(
                static fn (array $s): array => $s + ['created_by' => null, 'updated_by' => null],
                $missing,
            ));
        }

        $this->say(sprintf('States: %d added, %d already present', count($missing), count($existing)));
    }

    private function seedSuperAdmin(): void
    {
        $config = config('Taktak');

        $role = $this->db->table('roles')->where('name', Permissions::ROLE_SUPER_ADMIN)->get()->getRowArray();

        if ($role === null) {
            throw new RuntimeException('SUPER_ADMIN role is missing - seedRoles must run first');
        }

        $exists = $this->db->table('users')->where('email', $config->superAdminEmail)->countAllResults() > 0;

        if ($exists) {
            $this->say("Super Admin already exists ({$config->superAdminEmail}) - password left unchanged");

            return;
        }

        $isFirst = $this->db->table('users')->where('role_id', $role['id'])->countAllResults() === 0;

        $this->db->table('users')->insert([
            'full_name'     => $config->superAdminName,
            'email'         => $config->superAdminEmail,
            'password_hash' => AuthService::hashPassword($config->superAdminPassword),
            'role_id'       => (int) $role['id'],
            'status'        => 'active',
            'created_by'    => null,
            'updated_by'    => null,
        ]);

        // The first user has no creator, so it points at itself once it exists.
        $userId = (int) $this->db->insertID();
        $this->db->table('users')->where('id', $userId)->update(['created_by' => $userId, 'updated_by' => $userId]);

        $this->say($isFirst
            ? "Super Admin created: {$config->superAdminEmail} / {$config->superAdminPassword} - change this password after first login"
            : "Additional Super Admin created: {$config->superAdminEmail}");
    }

    private function warnAboutStalePermissions(): void
    {
        $stale = $this->db->table('permissions')->whereNotIn('slug', Permissions::slugs())->countAllResults();

        if ($stale > 0) {
            $this->say(
                "WARNING: {$stale} permission row(s) in the database are not in the current master list. "
                . 'They were left alone - remove them by hand if they are no longer wanted.',
            );
        }
    }

    private function say(string $message): void
    {
        if (is_cli()) {
            echo $message . PHP_EOL;
        }
    }
}
