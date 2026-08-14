<?php

declare(strict_types=1);

namespace App\Models;

class RegionModel extends BaseModel
{
    protected $table         = 'regions';
    protected $allowedFields = ['name', 'status', 'created_by', 'updated_by'];
}
