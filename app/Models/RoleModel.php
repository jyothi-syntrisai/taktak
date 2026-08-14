<?php

declare(strict_types=1);

namespace App\Models;

class RoleModel extends BaseModel
{
    protected $table         = 'roles';
    protected $allowedFields = [
        'name',
        'description',
        'is_system',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $name): ?array
    {
        return $this->where('name', $name)->first();
    }
}
