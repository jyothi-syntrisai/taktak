<?php

declare(strict_types=1);

namespace App\Models;

class RolePermissionModel extends BaseModel
{
    protected $table         = 'role_permissions';
    protected $allowedFields = [
        'role_id',
        'permission_id',
        'created_by',
        'updated_by',
    ];

    /**
     * The permission rows granted to a role, joined out to their slugs.
     *
     * @return list<array<string, mixed>>
     */
    public function permissionsForRole(int $roleId, bool $activeOnly = true): array
    {
        $builder = $this->db->table('role_permissions rp')
            ->select('p.id, p.slug, p.module, p.action, p.name')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->orderBy('p.module', 'ASC')
            ->orderBy('p.id', 'ASC');

        if ($activeOnly) {
            $builder->where('p.status', 'active');
        }

        return $builder->get()->getResultArray();
    }
}
