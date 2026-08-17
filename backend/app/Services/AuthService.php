<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\RefreshTokenModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use Config\Taktak;

class AuthService
{
    private UserModel $users;
    private RoleModel $roles;
    private RefreshTokenModel $tokens;
    private PermissionService $permissions;
    private Taktak $config;

    public function __construct()
    {
        $this->users       = model(UserModel::class);
        $this->roles       = model(RoleModel::class);
        $this->tokens      = model(RefreshTokenModel::class);
        $this->permissions = new PermissionService();
        $this->config      = config('Taktak');
    }

    /**
     * @param array{user_agent?: string|null, ip_address?: string|null} $context
     *
     * @return array{access_token: string, refresh_token: string, user: array<string, mixed>}
     */
    public function login(string $email, string $password, array $context): array
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

        return $this->issueTokens($user, $role, $context);
    }

    /**
     * Rotation: the presented token is burned as soon as a new pair is issued,
     * so a stolen refresh token is usable at most once.
     *
     * @param array{user_agent?: string|null, ip_address?: string|null} $context
     *
     * @return array{access_token: string, refresh_token: string, user: array<string, mixed>}
     */
    public function refresh(string $token, array $context): array
    {
        $jwt     = service('jwt');
        $payload = $jwt->verifyRefreshToken($token);

        $stored = $this->tokens->findByHash($jwt->hashToken($token));

        if (
            $stored === null
            || $stored['revoked_at'] !== null
            || strtotime((string) $stored['expires_at']) < time()
        ) {
            throw ApiException::unauthorized('Invalid or expired refresh token');
        }

        $user = $this->userRowWithPassword((int) $payload['sub']);

        if ($user === null || $user['status'] !== 'active') {
            throw ApiException::unauthorized('Account is no longer active');
        }

        $role = $this->roles->findRow((int) $user['role_id']);

        if ($role === null || $role['status'] !== 'active') {
            throw ApiException::forbidden('The role assigned to this account is inactive.');
        }

        $this->tokens->update((int) $stored['id'], [
            'revoked_at' => date('Y-m-d H:i:s'),
            'status'     => 'inactive',
            'updated_by' => (int) $user['id'],
        ]);

        return $this->issueTokens($user, $role, $context);
    }

    /** With a token: end that one session. Without: end every session this user has. */
    public function logout(?string $token, int $userId): void
    {
        if ($token !== null && $token !== '') {
            $this->tokens->revokeByHash(service('jwt')->hashToken($token), $userId);

            return;
        }

        $this->tokens->revokeAllForUser($userId, $userId);
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

        // Force every other device to log in again with the new password.
        $this->tokens->revokeAllForUser($userId, $userId);
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

        $user['permissions'] = $this->permissions->slugsForRole((int) $user['role_id']);

        return $user;
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => config('Taktak')->bcryptCost]);
    }

    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed>                                      $user
     * @param array<string, mixed>                                      $role
     * @param array{user_agent?: string|null, ip_address?: string|null} $context
     *
     * @return array{access_token: string, refresh_token: string, user: array<string, mixed>}
     */
    private function issueTokens(array $user, array $role, array $context): array
    {
        $jwt         = service('jwt');
        $permissions = $this->permissions->slugsForRole((int) $role['id']);

        $accessToken = $jwt->signAccessToken([
            'sub'         => (string) $user['id'],
            'email'       => (string) $user['email'],
            'role'        => (string) $role['name'],
            'role_id'     => (int) $role['id'],
            'permissions' => $permissions,
        ]);

        $jti = $user['id'] . '.' . microtime(true) . '.' . bin2hex(random_bytes(8));

        $refreshToken = $jwt->signRefreshToken([
            'sub' => (string) $user['id'],
            'jti' => $jti,
        ]);

        $this->tokens->insert([
            'user_id'    => (int) $user['id'],
            'token_hash' => $jwt->hashToken($refreshToken),
            'expires_at' => date('Y-m-d H:i:s', time() + $jwt->refreshTtlSeconds()),
            'user_agent' => $context['user_agent'] === null ? null : mb_substr((string) $context['user_agent'], 0, 255),
            'ip_address' => $context['ip_address'] === null ? null : mb_substr((string) $context['ip_address'], 0, 64),
            'created_by' => (int) $user['id'],
            'updated_by' => (int) $user['id'],
        ]);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'user'          => [
                'id'          => (int) $user['id'],
                'full_name'   => $user['full_name'],
                'email'       => $user['email'],
                'role'        => $role['name'],
                'role_id'     => (int) $role['id'],
                'permissions' => $permissions,
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
