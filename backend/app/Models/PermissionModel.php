<?php

declare(strict_types=1);

namespace App\Models;

class PermissionModel extends BaseModel
{
    protected $table         = 'permissions';
    protected $allowedFields = [
        'slug',
        'module',
        'action',
        'name',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * The whole active master list, ordered the way the role screen draws it.
     *
     * @return list<array<string, mixed>>
     */
    public function activeList(): array
    {
        return $this->select('id, slug, module, action, name')
            ->where('status', 'active')
            ->orderBy('module', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}
