<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PermissionModel;
use App\Models\RolePermissionModel;

class PermissionService
{
    /**
     * Flattens a role into the list of `module:action` slugs it grants. This
     * runs at login and is baked into the access token, so per-request
     * permission checks cost nothing. Because the access token is short-lived,
     * a role change takes effect on the next refresh.
     *
     * @return list<string>
     */
    public function slugsForRole(int $roleId): array
    {
        $rows  = model(RolePermissionModel::class)->permissionsForRole($roleId);
        $slugs = array_map(static fn (array $row): string => $row['slug'], $rows);

        sort($slugs);

        return array_values(array_unique($slugs));
    }

    /**
     * The active master list grouped by module - exactly the shape the role
     * screen needs to draw its tick boxes.
     *
     * @return array{total: int, modules: list<array{module: string, permissions: list<array<string, mixed>>}>}
     */
    public function groupedMasterList(): array
    {
        $permissions = model(PermissionModel::class)->activeList();

        $grouped = [];

        foreach ($permissions as $permission) {
            $permission['id']                       = (int) $permission['id'];
            $grouped[$permission['module']][]       = $permission;
        }

        $modules = [];

        foreach ($grouped as $module => $items) {
            $modules[] = ['module' => $module, 'permissions' => $items];
        }

        return ['total' => count($permissions), 'modules' => $modules];
    }
}
