<?php

declare(strict_types=1);

namespace App\Models;

class StateModel extends BaseModel
{
    protected $table         = 'states';
    protected $allowedFields = ['name', 'code', 'status', 'created_by', 'updated_by'];
}
