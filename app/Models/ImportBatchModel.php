<?php

declare(strict_types=1);

namespace App\Models;

class ImportBatchModel extends BaseModel
{
    protected $table         = 'import_batches';
    protected $allowedFields = [
        'file_name',
        'module',
        'total_records',
        'success_records',
        'failed_records',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function cast(array $row): array
    {
        foreach (['id', 'total_records', 'success_records', 'failed_records'] as $column) {
            $row[$column] = (int) $row[$column];
        }

        return $row;
    }
}
