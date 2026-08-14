<?php

declare(strict_types=1);

namespace App\Models;

class DistributorModel extends BaseModel
{
    protected $table         = 'distributors';
    protected $allowedFields = ['name', 'code', 'status', 'created_by', 'updated_by'];
}
