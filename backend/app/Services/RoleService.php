<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use App\Models\RolePermissionModel;
use App\Support\HandlesTransactions;
use App\Support\Pagination;
use App\Support\Permissions;

class RoleService
{
    use HandlesTransactions;

    private const SORTABLE = ['id', 'name', 'status', 'created_at'];

    private RoleModel $roles;
    private RolePermissionModel $rolePermissions;
    private PermissionModel $permissions;

    public function __construct()
    {
        $this->roles           = model(RoleModel::class);
        $this->rolePermissions = model(RolePermissionModel::class);
        $this->permissions     = model(PermissionModel::class);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(array $query): array
    {
        $page = Pagination::fromQuery($query);

        $builder = db_connect()->table('roles');

        if ($page['status'] !== null) {
            $builder->where('status', $page['status']);
        }

        if ($page['search'] !== null) {
            $builder->like('name', $page['search']);
        }

        $total = $builder->countAllResults(false);

        [$column, $direction] = Pagination::safeOrder($page['sort_by'], $page['sort_dir'], self::SORTABLE, 'id');

        $rows = $builder->orderBy($column, $direction)
            ->limit($page['limit'], $page['offset'])
            ->get()
            ->getResultArray();

        // Show how many users sit on each role - the admin screen needs it
        // before anyone tries to deactivate a role.
        $counts = $this->userCountsByRole(array_map(static fn (array $r): int => (int) $r['id'], $rows));

        $items = array_map(
            static function (array $role) use ($counts): array {
                $role = self::cast($role);
                $role['user_count'] = $counts[$role['id']] ?? 0;

                return $role;
            },
            $rows,
        );

        return ['items' => $items, 'meta' => Pagination::meta($page['page'], $page['limit'], $total)];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $id): array
    {
        $role = $this->roles->findRow($id);

        if ($role === null) {
            throw ApiException::notFound('Role not found');
        }

        $role                = self::cast($role);
        $role['permissions'] = array_map(
            static fn (array $p): array => ['id' => (int) $p['id']] + $p,
            $this->rolePermissions->permissionsForRole($id),
        );

        return $role;
    }

    /**
     * @param array<string, mixed> $input
     * @param list<int>|null       $permissionIds
     *
     * @return array<string, mixed>
     */
    public function create(array $input, ?array $permissionIds, int $actorId): array
    {
        if ($this->roles->existsWith('name', $input['name'])) {
            throw ApiException::conflict('A role with this name already exists');
        }

        if ($permissionIds !== null && $permissionIds !== []) {
            $this->assertPermissionsExist($permissionIds);
        }

        $roleId = $this->transaction(function () use ($input, $permissionIds, $actorId): int {
            $id = (int) $this->roles->insert([
                'name'        => $input['name'],
                'description' => $input['description'] ?? null,
                'is_system'   => 0, // only the seeded roles are system roles
                'status'      => $input['status'] ?? 'active',
                'created_by'  => $actorId,
                'updated_by'  => $actorId,
            ]);

            if ($permissionIds !== null && $permissionIds !== []) {
                $this->replacePermissions($id, $permissionIds, $actorId);
            }

            return $id;
        });

        return $this->get($roleId);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, int $actorId): array
    {
        $role = $this->roles->findRow($id);

        if ($role === null) {
            throw ApiException::notFound('Role not found');
        }

        $isSystem = (bool) $role['is_system'];

        if ($isSystem && isset($input['name']) && $input['name'] !== $role['name']) {
            throw ApiException::badRequest('A built-in role cannot be renamed');
        }

        if ($isSystem && ($input['status'] ?? null) === 'inactive') {
            throw ApiException::badRequest('A built-in role cannot be deactivated');
        }

        if (isset($input['name']) && $input['name'] !== $role['name'] && $this->roles->existsWith('name', $input['name'], $id)) {
            throw ApiException::conflict('A role with this name already exists');
        }

        $changes = array_intersect_key($input, array_flip(['name', 'description', 'status']));
        $changes['updated_by'] = $actorId;

        $this->roles->update($id, $changes);

        return $this->get($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(int $id, int $actorId): array
    {
        $role = $this->roles->findRow($id);

        if ($role === null) {
            throw ApiException::notFound('Role not found');
        }

        if ((bool) $role['is_system']) {
            throw ApiException::badRequest('A built-in role cannot be deleted');
        }

        $activeUsers = db_connect()->table('users')
            ->where('role_id', $id)
            ->where('status', 'active')
            ->countAllResults();

        if ($activeUsers > 0) {
            throw ApiException::badRequest(
                "{$activeUsers} active user(s) still use this role. Move them to another role first.",
            );
        }

        $this->roles->update($id, ['status' => 'inactive', 'updated_by' => $actorId]);

        return $this->get($id);
    }

    /**
     * The role screen sends the full tick-box state, so the simplest correct
     * thing is to replace the set inside one transaction rather than diffing.
     *
     * @param list<int> $permissionIds
     *
     * @return array<string, mixed>
     */
    public function assignPermissions(int $id, array $permissionIds, int $actorId): array
    {
        $role = $this->roles->findRow($id);

        if ($role === null) {
            throw ApiException::notFound('Role not found');
        }

        if ($role['name'] === Permissions::ROLE_SUPER_ADMIN) {
            throw ApiException::badRequest('Super Admin always holds every permission and cannot be edited');
        }

        if ($permissionIds !== []) {
            $this->assertPermissionsExist($permissionIds);
        }

        $this->transaction(fn () => $this->replacePermissions($id, $permissionIds, $actorId));

        return $this->get($id);
    }

    // -----------------------------------------------------------------------

    /**
     * @param list<int> $permissionIds
     */
    private function assertPermissionsExist(array $permissionIds): void
    {
        $found = db_connect()->table('permissions')
            ->whereIn('id', $permissionIds)
            ->where('status', 'active')
            ->countAllResults();

        if ($found !== count($permissionIds)) {
            throw ApiException::badRequest('One or more selected permissions do not exist');
        }
    }

    /**
     * @param list<int> $permissionIds
     */
    private function replacePermissions(int $roleId, array $permissionIds, int $actorId): void
    {
        db_connect()->table('role_permissions')->where('role_id', $roleId)->delete();

        if ($permissionIds === []) {
            return;
        }

        $rows = array_map(
            static fn (int $permissionId): array => [
                'role_id'       => $roleId,
                'permission_id' => $permissionId,
                'created_by'    => $actorId,
                'updated_by'    => $actorId,
            ],
            $permissionIds,
        );

        db_connect()->table('role_permissions')->insertBatch($rows);
    }

    /**
     * @param list<int> $roleIds
     *
     * @return array<int, int>
     */
    private function userCountsByRole(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $rows = db_connect()->table('users')
            ->select('role_id, COUNT(id) AS user_count')
            ->whereIn('role_id', $roleIds)
            ->groupBy('role_id')
            ->get()
            ->getResultArray();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row['role_id']] = (int) $row['user_count'];
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $role
     *
     * @return array<string, mixed>
     */
    private static function cast(array $role): array
    {
        $role['id']        = (int) $role['id'];
        $role['is_system'] = (bool) $role['is_system'];

        return $role;
    }
}
