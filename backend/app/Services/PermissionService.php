<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PermissionModel;
use App\Models\RolePermissionModel;

class PermissionService
{
    /**
     * Flattens a role into the list of `page_route:page_action` slugs it
     * grants. This runs at login and is baked into the access token, so
     * per-request permission checks cost nothing. Because the access token is
     * valid for 24 hours, a role change takes effect the next time the user
     * signs in.
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
     * A role's permissions as the login response needs them: every page route
     * it can reach, and - per route - which actions it may perform there.
     *
     * @return array{page_routes: list<string>, page_actions: list<array{route: string, permissions: list<string>}>}
     */
    public function routeSummaryForRole(int $roleId): array
    {
        $rows = model(RolePermissionModel::class)->permissionsForRole($roleId);

        $actionsByRoute = [];

        foreach ($rows as $row) {
            $actionsByRoute[$row['page_route']][] = $row['page_action'];
        }

        $pageActions = [];

        foreach ($actionsByRoute as $route => $actions) {
            $pageActions[] = ['route' => $route, 'permissions' => array_values(array_unique($actions))];
        }

        return [
            'page_routes'  => array_values(array_keys($actionsByRoute)),
            'page_actions' => $pageActions,
        ];
    }

    /**
     * The active master list grouped by page route - exactly the shape the
     * role screen needs to draw its tick boxes.
     *
     * @return array{total: int, page_routes: list<array{route: string, permissions: list<array<string, mixed>>}>}
     */
    public function groupedMasterList(): array
    {
        $permissions = model(PermissionModel::class)->activeList();

        $grouped = [];

        foreach ($permissions as $permission) {
            $permission['id']                          = (int) $permission['id'];
            $grouped[$permission['page_route']][]       = $permission;
        }

        $pageRoutes = [];

        foreach ($grouped as $route => $items) {
            $pageRoutes[] = ['route' => $route, 'permissions' => $items];
        }

        return ['total' => count($permissions), 'page_routes' => $pageRoutes];
    }
}
