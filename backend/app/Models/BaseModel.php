<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Shared settings for every table in the schema.
 *
 * `created_at` / `updated_at` are maintained by MySQL itself (DEFAULT
 * CURRENT_TIMESTAMP / ON UPDATE CURRENT_TIMESTAMP), which is why the
 * framework's own timestamp handling is switched off. Rows are never hard
 * deleted either - "delete" flips `status` to inactive so old reports keep
 * resolving the name behind created_by / updated_by.
 */
abstract class BaseModel extends Model
{
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = false;
    protected $useSoftDeletes = false;
    protected $allowCallbacks = false;
    protected $useAutoIncrement = true;

    /**
     * Find a row by id or return null - the services turn that into a 404 with
     * a message that names the resource.
     *
     * @return array<string, mixed>|null
     */
    public function findRow(int $id): ?array
    {
        /** @var array<string, mixed>|null $row */
        $row = $this->find($id);

        return $row;
    }

    /**
     * True when another row (any row but `$exceptId`) already holds this value.
     */
    public function existsWith(string $column, mixed $value, ?int $exceptId = null): bool
    {
        $builder = db_connect()->table($this->table)->where($column, $value);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }
}
