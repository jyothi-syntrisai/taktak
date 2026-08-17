<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\RoleModel;
use App\Models\UserModel;
use Config\Taktak;

class AuthService
{
    private UserModel $users;
    private RoleModel $roles;
    private PermissionService $permissions;
    private Taktak $config;

    public function __construct()
    {
        $this->users       = model(UserModel::class);
        $this->roles       = model(RoleModel::class);
        $this->permissions = new PermissionService();
        $this->config      = config('Taktak');
    }

    /**
     * @return array{access_token: string, user: array<string, mixed>}
     */
    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email, true);

        // Same message for "no such email" and "wrong password" - do not confirm
        // which email addresses exist.
        $invalid = ApiException::unauthorized('Invalid email or password');

        if ($user === null || ! password_verify($password, (string) $user['password_hash'])) {
            throw $invalid;
        }

        if ($user['status'] !== 'active') {
            throw ApiException::forbidden('This account has been deactivated. Contact your administrator.');
        }

        $role = $this->roles->findRow((int) $user['role_id']);

        if ($role === null || $role['status'] !== 'active') {
            throw ApiException::forbidden('The role assigned to this account is inactive.');
        }

        $this->users->update((int) $user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        return $this->issueTokens($user, $role);
    }

    /**
     * There is no server-side session to end - the access token is a
     * stateless JWT valid for 24 hours with no revocation list. This exists
     * so the client has an endpoint to call as it discards its token.
     */
    public function logout(int $userId): void
    {
    }

    public function changePassword(int $userId, string $oldPassword, string $newPassword): void
    {
        $user = $this->userRowWithPassword($userId);

        if ($user === null) {
            throw ApiException::notFound('User not found');
        }

        if (! password_verify($oldPassword, (string) $user['password_hash'])) {
            throw ApiException::badRequest('Current password is incorrect');
        }

        $this->users->update($userId, [
            'password_hash' => self::hashPassword($newPassword),
            'updated_by'    => $userId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(int $userId): array
    {
        $user = $this->users->findWithRole($userId);

        if ($user === null) {
            throw ApiException::notFound('User not found');
        }

        $user['permissions'] = $this->permissions->routeSummaryForRole((int) $user['role_id']);

        return $user;
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => config('Taktak')->bcryptCost]);
    }

    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $role
     *
     * @return array{access_token: string, user: array<string, mixed>}
     */
    private function issueTokens(array $user, array $role): array
    {
        $jwt = service('jwt');

        // The access token itself still carries the flat slug list - that is
        // what `Auth::can()` checks on every request - while the login
        // response below breaks the same grants into page routes / actions
        // for the client to build its navigation and screen permissions.
        $flatSlugs = $this->permissions->slugsForRole((int) $role['id']);

        $accessToken = $jwt->signAccessToken([
            'sub'         => (string) $user['id'],
            'email'       => (string) $user['email'],
            'role'        => (string) $role['name'],
            'role_id'     => (int) $role['id'],
            'permissions' => $flatSlugs,
        ]);

        return [
            'access_token' => $accessToken,
            'user'         => [
                'id'          => (int) $user['id'],
                'full_name'   => $user['full_name'],
                'email'       => $user['email'],
                'role'        => $role['name'],
                'role_id'     => (int) $role['id'],
                'permissions' => $this->permissions->routeSummaryForRole((int) $role['id']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userRowWithPassword(int $userId): ?array
    {
        return db_connect()->table('users')->where('id', $userId)->get()->getRowArray();
    }
}
