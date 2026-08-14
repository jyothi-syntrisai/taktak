<?php

declare(strict_types=1);

namespace App\Models;

class ProductMrpModel extends BaseModel
{
    protected $table         = 'product_mrp';
    protected $allowedFields = [
        'product_id',
        'mrp',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
    ];

    /**
     * The row still in force for a product - the one with an open end date.
     *
     * @return array<string, mixed>|null
     */
    public function currentFor(int $productId): ?array
    {
        return $this->where('product_id', $productId)
            ->where('effective_to', null)
            ->orderBy('effective_from', 'DESC')
            ->first();
    }

    /**
     * Current price for every product in the page, in one query.
     *
     * @param list<int> $productIds
     *
     * @return array<int, array<string, mixed>> keyed by product id
     */
    public function currentForMany(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = $this->db->table('product_mrp')
            ->select('id, product_id, mrp, effective_from, effective_to')
            ->whereIn('product_id', $productIds)
            ->where('effective_to', null)
            ->get()
            ->getResultArray();

        $byProduct = [];

        foreach ($rows as $row) {
            $byProduct[(int) $row['product_id']] = self::cast($row);
        }

        return $byProduct;
    }

    /**
     * Whole price history of a product, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function historyFor(int $productId): array
    {
        $rows = $this->where('product_id', $productId)
            ->orderBy('effective_from', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();

        return array_map(self::cast(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function cast(array $row): array
    {
        $row['id']         = (int) $row['id'];
        $row['product_id'] = (int) $row['product_id'];
        $row['mrp']        = (float) $row['mrp'];

        return $row;
    }
}
