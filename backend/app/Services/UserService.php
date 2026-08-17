<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\RegionModel;
use App\Models\RoleModel;
use App\Models\StateModel;
use App\Models\UserModel;
use App\Support\Pagination;
use App\Support\Permissions;

class UserService
{
    private const SORTABLE = ['id', 'full_name', 'email', 'status', 'created_at', 'last_login_at'];

    private UserModel $users;
    private RoleModel $roles;
    private RegionModel $regions;
    private StateModel $states;

    public function __construct()
    {
        $this->users   = model(UserModel::class);
        $this->roles   = model(RoleModel::class);
        $this->regions = model(RegionModel::class);
        $this->states  = model(StateModel::class);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(array $query): array
    {
        $page = Pagination::fromQuery($query);

        $builder = UserModel::joinedQuery();

        if ($page['status'] !== null) {
            $builder->where('users.status', $page['status']);
        }

        foreach (['role_id', 'region_id', 'state_id'] as $filter) {
            if (isset($query[$filter]) && (int) $query[$filter] > 0) {
                $builder->where('users.' . $filter, (int) $query[$filter]);
            }
        }

        if ($page['search'] !== null) {
            $builder->groupStart()
                ->like('users.full_name', $page['search'])
                ->orLike('users.email', $page['search'])
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);

        [$column, $direction] = Pagination::safeOrder($page['sort_by'], $page['sort_dir'], self::SORTABLE, 'created_at');

        $rows = $builder->orderBy('users.' . $column, $direction)
            ->limit($page['limit'], $page['offset'])
            ->get()
            ->getResultArray();

        return [
            'items' => array_map(UserModel::withRole(...), $rows),
            'meta'  => Pagination::meta($page['page'], $page['limit'], $total),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $id): array
    {
        $user = $this->users->findWithRole($id);

        if ($user === null) {
            throw ApiException::notFound('User not found');
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function create(array $input, int $actorId): array
    {
        $email = strtolower((string) $input['email']);

        if ($this->users->existsWith('email', $email)) {
            throw ApiException::conflict('A user with this email address already exists');
        }

        $role  = $this->assertRoleUsable((int) $input['role_id']);
        $scope = $this->resolveScope(
            (string) $role['name'],
            self::optionalId($input, 'region_id'),
            self::optionalId($input, 'state_id'),
        );

        $id = $this->users->insert([
            'full_name'     => $input['full_name'],
            'email'         => $email,
            'password_hash' => AuthService::hashPassword((string) $input['password']),
            'role_id'       => (int) $input['role_id'],
            'region_id'     => $scope['region_id'],
            'state_id'      => $scope['state_id'],
            'status'        => $input['status'] ?? 'active',
            'created_by'    => $actorId,
            'updated_by'    => $actorId,
        ]);

        return $this->get((int) $id);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, int $actorId): array
    {
        $user = $this->users->findRow($id);

        if ($user === null) {
            throw ApiException::notFound('User not found');
        }

        $changes = [];

        if (array_key_exists('full_name', $input)) {
            $changes['full_name'] = $input['full_name'];
        }

        if (array_key_exists('email', $input)) {
            $email = strtolower((string) $input['email']);

            if ($email !== $user['email'] && $this->users->existsWith('email', $email, $id)) {
                throw ApiException::conflict('A user with this email address already exists');
            }

            $changes['email'] = $email;
        }

        $roleChanged = array_key_exists('role_id', $input) && (int) $input['role_id'] !== (int) $user['role_id'];
        $role        = null;

        if ($roleChanged) {
            $role = $this->assertRoleUsable((int) $input['role_id']);
            // Moving the only Super Admin onto a lesser role locks everyone out
            // of the roles screen, which is the one screen no other role reaches.
            $this->guardLastActiveSuperAdmin($user);
            $changes['role_id'] = (int) $input['role_id'];
        }

        // The role decides whether a region and a state belong on this user, so
        // it is settled after the role, filling in from the existing row what
        // the request did not send. An edit that touches neither the role nor
        // the two ids leaves them alone entirely - a name change should not fail
        // because the region behind an older user has since been deactivated.
        $currentRegion = isset($user['region_id']) ? (int) $user['region_id'] : null;
        $currentState  = isset($user['state_id']) ? (int) $user['state_id'] : null;
        $scopeSent     = array_key_exists('region_id', $input) || array_key_exists('state_id', $input);

        if ($roleChanged || $scopeSent) {
            $role ??= $this->roles->findRow((int) $user['role_id']);

            $scope = $this->resolveScope(
                (string) ($role['name'] ?? ''),
                self::optionalId($input, 'region_id', $currentRegion),
                self::optionalId($input, 'state_id', $currentState),
                ! array_key_exists('region_id', $input),
                ! array_key_exists('state_id', $input),
            );

            if ($scope['region_id'] !== $currentRegion) {
                $changes['region_id'] = $scope['region_id'];
            }

            if ($scope['state_id'] !== $currentState) {
                $changes['state_id'] = $scope['state_id'];
            }
        }

        $deactivating = ($input['status'] ?? null) === 'inactive' && $user['status'] === 'active';

        if ($deactivating) {
            $this->guardLastActiveSuperAdmin($user);

            if ($id === $actorId) {
                throw ApiException::badRequest('You cannot deactivate your own account');
            }
        }

        if (array_key_exists('status', $input)) {
            $changes['status'] = $input['status'];
        }

        if ($changes !== []) {
            $changes['updated_by'] = $actorId;
            $this->users->update($id, $changes);
        }

        // There is no server-side session to revoke - a role or status change
        // takes effect the next time this user's access token is issued (on
        // their next login, or within 24 hours as the current one expires).

        return $this->get($id);
    }

    /**
     * Records are never hard deleted - "delete" flips the row to inactive so old
     * reports keep resolving the name behind created_by / updated_by.
     *
     * @return array<string, mixed>
     */
    public function deactivate(int $id, int $actorId): array
    {
        $user = $this->users->findRow($id);

        if ($user === null) {
            throw ApiException::notFound('User not found');
        }

        if ($id === $actorId) {
            throw ApiException::badRequest('You cannot deactivate your own account');
        }

        if ($user['status'] === 'inactive') {
            return $this->get($id);
        }

        $this->guardLastActiveSuperAdmin($user);

        $this->users->update($id, ['status' => 'inactive', 'updated_by' => $actorId]);

        return $this->get($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function activate(int $id, int $actorId): array
    {
        $user = $this->users->findRow($id);

        if ($user === null) {
            throw ApiException::notFound('User not found');
        }

        if ($user['status'] === 'active') {
            return $this->get($id);
        }

        $this->assertRoleUsable((int) $user['role_id']);
        $this->users->update($id, ['status' => 'active', 'updated_by' => $actorId]);

        return $this->get($id);
    }

    public function resetPassword(int $id, string $newPassword, int $actorId): void
    {
        if ($this->users->findRow($id) === null) {
            throw ApiException::notFound('User not found');
        }

        $this->users->update($id, [
            'password_hash' => AuthService::hashPassword($newPassword),
            'updated_by'    => $actorId,
        ]);
    }

    // -----------------------------------------------------------------------

    /**
     * @return array<string, mixed> the role row, which the caller needs for the
     *                              region / state rules that hang off its name
     */
    private function assertRoleUsable(int $roleId): array
    {
        $role = $this->roles->findRow($roleId);

        if ($role === null) {
            throw ApiException::badRequest('The selected role does not exist');
        }

        if ($role['status'] !== 'active') {
            throw ApiException::badRequest('The selected role is inactive');
        }

        return $role;
    }

    /**
     * Where a user sits, decided entirely by the role they are on: an RSM must
     * be given a region, an SO a region and one state inside it, and every other
     * role neither.
     *
     * `$regionInherited` / `$stateInherited` mark values that came off the
     * existing row rather than the request. A role change can leave those
     * stranded - an RSM moving to ADMIN keeps no region - so an inherited value
     * the new role has no use for is dropped quietly, while one the request
     * actually asked for is refused.
     *
     * @return array{region_id: int|null, state_id: int|null}
     */
    private function resolveScope(
        string $roleName,
        ?int $regionId,
        ?int $stateId,
        bool $regionInherited = false,
        bool $stateInherited = false,
    ): array {
        $scope = Permissions::scopeFor($roleName);

        if ($scope === Permissions::SCOPE_NONE) {
            if (($regionId !== null && ! $regionInherited) || ($stateId !== null && ! $stateInherited)) {
                throw ApiException::badRequest("A user on the {$roleName} role is not tied to a region or a state");
            }

            return ['region_id' => null, 'state_id' => null];
        }

        if ($regionId === null) {
            throw self::fieldError('region_id', "A region is required for a user on the {$roleName} role");
        }

        $this->assertRegionUsable($regionId);

        if ($scope === Permissions::SCOPE_REGION) {
            if ($stateId !== null && ! $stateInherited) {
                throw ApiException::badRequest("A user on the {$roleName} role is tied to a region, not to a single state");
            }

            return ['region_id' => $regionId, 'state_id' => null];
        }

        if ($stateId === null) {
            throw self::fieldError('state_id', "A state is required for a user on the {$roleName} role");
        }

        $this->assertStateInRegion($stateId, $regionId);

        return ['region_id' => $regionId, 'state_id' => $stateId];
    }

    private function assertRegionUsable(int $regionId): void
    {
        $region = $this->regions->findRow($regionId);

        if ($region === null) {
            throw ApiException::badRequest('The selected region does not exist');
        }

        if ($region['status'] !== 'active') {
            throw ApiException::badRequest('The selected region is inactive');
        }
    }

    /**
     * An SO works one state of their manager's region, so the state has to be
     * one of the states mapped to that region.
     */
    private function assertStateInRegion(int $stateId, int $regionId): void
    {
        $state = $this->states->findRow($stateId);

        if ($state === null) {
            throw ApiException::badRequest('The selected state does not exist');
        }

        if ($state['status'] !== 'active') {
            throw ApiException::badRequest('The selected state is inactive');
        }

        $mapped = db_connect()->table('region_states')
            ->where('region_id', $regionId)
            ->where('state_id', $stateId)
            ->countAllResults() > 0;

        if (! $mapped) {
            throw ApiException::badRequest('The selected state does not belong to the selected region');
        }
    }

    /**
     * An id sent in the body, or `$current` when the field was left out. An
     * explicit null or empty string clears it.
     *
     * @param array<string, mixed> $input
     */
    private static function optionalId(array $input, string $key, ?int $current = null): ?int
    {
        if (! array_key_exists($key, $input)) {
            return $current;
        }

        $value = $input[$key];

        return $value === null || $value === '' ? null : (int) $value;
    }

    private static function fieldError(string $field, string $message): ApiException
    {
        return ApiException::unprocessable('Validation failed', [
            ['field' => $field, 'message' => $message],
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function guardLastActiveSuperAdmin(array $user): void
    {
        $role = $this->roles->findRow((int) $user['role_id']);

        if ($role === null || $role['name'] !== Permissions::ROLE_SUPER_ADMIN) {
            return;
        }

        $remaining = db_connect()->table('users')
            ->where('role_id', (int) $user['role_id'])
            ->where('status', 'active')
            ->where('id !=', (int) $user['id'])
            ->countAllResults();

        if ($remaining === 0) {
            throw ApiException::badRequest('This is the last active Super Admin and cannot be removed');
        }
    }
}
