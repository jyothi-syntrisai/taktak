<?php

declare(strict_types=1);

namespace App\Models;

class ProductModel extends BaseModel
{
    protected $table         = 'products';
    protected $allowedFields = ['brand_id', 'sku', 'product_name', 'status', 'created_by', 'updated_by'];

    /**
     * @return array<string, mixed>|null
     */
    public function findBySku(string $sku): ?array
    {
        return $this->where('sku', $sku)->first();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function withBrand(array $row): array
    {
        $brand = [
            'id'   => isset($row['brand_id']) ? (int) $row['brand_id'] : null,
            'name' => $row['brand_name'] ?? null,
            'code' => $row['brand_code'] ?? null,
        ];

        unset($row['brand_name'], $row['brand_code']);

        $row['id']       = (int) $row['id'];
        $row['brand_id'] = (int) $row['brand_id'];
        $row['brand']    = $brand['id'] === null ? null : $brand;

        return $row;
    }
}
