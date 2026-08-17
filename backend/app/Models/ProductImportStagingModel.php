<?php

declare(strict_types=1);

namespace App\Models;

class ProductImportStagingModel extends BaseModel
{
    protected $table         = 'product_import_staging';
    protected $allowedFields = [
        'import_batch_id',
        'row_number',
        'brand_name',
        'sku',
        'product_name',
        'mrp',
        'status',
        'error_message',
        'created_by',
        'updated_by',
    ];

    /**
     * Rows of a batch waiting to be checked, in file order.
     *
     * @return list<array<string, mixed>>
     */
    public function pendingRows(int $batchId): array
    {
        return $this->where('import_batch_id', $batchId)
            ->where('status', 'pending')
            ->orderBy('row_number', 'ASC')
            ->findAll();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function cast(array $row): array
    {
        $row['id']              = (int) $row['id'];
        $row['import_batch_id'] = (int) $row['import_batch_id'];
        $row['row_number']      = (int) $row['row_number'];

        return $row;
    }
}
