<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;

class UserModel extends BaseModel
{
    protected $table         = 'users';
    protected $allowedFields = [
        'full_name',
        'email',
        'password_hash',
        'role_id',
        'region_id',
        'state_id',
        'status',
        'last_login_at',
        'created_by',
        'updated_by',
    ];

    /** Columns that may leave the API. `password_hash` is never one of them. */
    public const PUBLIC_FIELDS = 'users.id, users.full_name, users.email, users.role_id, users.region_id,'
        . ' users.state_id, users.status, users.last_login_at, users.created_at, users.created_by,'
        . ' users.updated_at, users.updated_by';

    /** The public columns plus the names behind role_id, region_id and state_id. */
    public const JOINED_FIELDS = self::PUBLIC_FIELDS
        . ', r.name AS role_name, r.description AS role_description'
        . ', rg.name AS region_name'
        . ', st.name AS state_name, st.code AS state_code';

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
     * A fresh builder over users with role, region and state joined in - the
     * shape every user-facing endpoint returns. Fresh, because the model's own
     * builder is shared for the whole request and would carry conditions over
     * between calls.
     */
    public static function joinedQuery(): BaseBuilder
    {
        return db_connect()->table('users')
            ->select(self::JOINED_FIELDS)
            ->join('roles r', 'r.id = users.role_id', 'left')
            ->join('regions rg', 'rg.id = users.region_id', 'left')
            ->join('states st', 'st.id = users.state_id', 'left');
    }

    /**
     * A user with their role, region and state folded in as nested objects.
     *
     * @return array<string, mixed>|null
     */
    public function findWithRole(int $id): ?array
    {
        $row = self::joinedQuery()
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

        // Only an RSM or an SO carries a region, and only an SO a state, so both
        // are null for most users.
        $regionId = isset($row['region_id']) ? (int) $row['region_id'] : null;
        $stateId  = isset($row['state_id']) ? (int) $row['state_id'] : null;

        $region = $regionId === null ? null : [
            'id'   => $regionId,
            'name' => $row['region_name'] ?? null,
        ];

        $state = $stateId === null ? null : [
            'id'   => $stateId,
            'name' => $row['state_name'] ?? null,
            'code' => $row['state_code'] ?? null,
        ];

        unset(
            $row['role_name'],
            $row['role_description'],
            $row['region_name'],
            $row['state_name'],
            $row['state_code'],
            $row['password_hash'],
        );

        $row['id']        = (int) $row['id'];
        $row['role_id']   = (int) $row['role_id'];
        $row['region_id'] = $regionId;
        $row['state_id']  = $stateId;
        $row['role']      = $role['id'] === null ? null : $role;
        $row['region']    = $region;
        $row['state']     = $state;

        return $row;
    }
}
