<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\RefreshTokenModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Support\Pagination;
use App\Support\Permissions;

class UserService
{
    private const SORTABLE = ['id', 'full_name', 'email', 'status', 'created_at', 'last_login_at'];

    private UserModel $users;
    private RoleModel $roles;
    private RefreshTokenModel $tokens;

    public function __construct()
    {
        $this->users  = model(UserModel::class);
        $this->roles  = model(RoleModel::class);
        $this->tokens = model(RefreshTokenModel::class);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(array $query): array
    {
        $page = Pagination::fromQuery($query);

        // A fresh builder per query - the model's own builder is shared for the
        // whole request and would carry conditions over between calls.
        $builder = db_connect()->table('users')
            ->select(UserModel::PUBLIC_FIELDS . ', r.name AS role_name, r.description AS role_description')
            ->join('roles r', 'r.id = users.role_id', 'left');

        if ($page['status'] !== null) {
            $builder->where('users.status', $page['status']);
        }

        if (isset($query['role_id']) && (int) $query['role_id'] > 0) {
            $builder->where('users.role_id', (int) $query['role_id']);
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

        $this->assertRoleUsable((int) $input['role_id']);

        $id = $this->users->insert([
            'full_name'     => $input['full_name'],
            'email'         => $email,
            'password_hash' => AuthService::hashPassword((string) $input['password']),
            'role_id'       => (int) $input['role_id'],
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

        if ($roleChanged) {
            $this->assertRoleUsable((int) $input['role_id']);
            // Moving the only Super Admin onto a lesser role locks everyone out
            // of the roles screen, which is the one screen no other role reaches.
            $this->guardLastActiveSuperAdmin($user);
            $changes['role_id'] = (int) $input['role_id'];
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

        // Role or status changed - existing sessions must not keep the old access.
        if ($roleChanged || $deactivating) {
            $this->tokens->revokeAllForUser($id, $actorId);
        }

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
        $this->tokens->revokeAllForUser($id, $actorId);

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

        $this->tokens->revokeAllForUser($id, $actorId);
    }

    // -----------------------------------------------------------------------

    private function assertRoleUsable(int $roleId): void
    {
        $role = $this->roles->findRow($roleId);

        if ($role === null) {
            throw ApiException::badRequest('The selected role does not exist');
        }

        if ($role['status'] !== 'active') {
            throw ApiException::badRequest('The selected role is inactive');
        }
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
