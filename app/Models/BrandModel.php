<?php

declare(strict_types=1);

namespace App\Models;

class BrandModel extends BaseModel
{
    protected $table         = 'brands';
    protected $allowedFields = ['name', 'code', 'status', 'created_by', 'updated_by'];

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $name): ?array
    {
        return $this->where('name', $name)->first();
    }
}
