<?php

declare(strict_types=1);

namespace App\Models;

class UserModel extends BaseModel
{
    protected $table         = 'users';
    protected $allowedFields = [
        'full_name',
        'email',
        'password_hash',
        'role_id',
        'status',
        'last_login_at',
        'created_by',
        'updated_by',
    ];

    /** Columns that may leave the API. `password_hash` is never one of them. */
    public const PUBLIC_FIELDS = 'users.id, users.full_name, users.email, users.role_id, users.status,'
        . ' users.last_login_at, users.created_at, users.created_by, users.updated_at, users.updated_by';

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email, bool $withPassword = false): ?array
    {
        $builder = $this->db->table('users')
            ->select($withPassword ? 'users.*' : self::PUBLIC_FIELDS)
            ->where('users.email', $email);

        return $builder->get()->getRowArray();
    }

    /**
     * A user with their role folded in as a nested object - the shape every
     * user-facing endpoint returns.
     *
     * @return array<string, mixed>|null
     */
    public function findWithRole(int $id): ?array
    {
        $row = $this->db->table('users')
            ->select(self::PUBLIC_FIELDS . ', r.name AS role_name, r.description AS role_description')
            ->join('roles r', 'r.id = users.role_id', 'left')
            ->where('users.id', $id)
            ->get()
            ->getRowArray();

        return $row === null ? null : self::withRole($row);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function withRole(array $row): array
    {
        $role = [
            'id'          => isset($row['role_id']) ? (int) $row['role_id'] : null,
            'name'        => $row['role_name'] ?? null,
            'description' => $row['role_description'] ?? null,
        ];

        unset($row['role_name'], $row['role_description'], $row['password_hash']);

        $row['id']      = (int) $row['id'];
        $row['role_id'] = (int) $row['role_id'];
        $row['role']    = $role['id'] === null ? null : $role;

        return $row;
    }
}
