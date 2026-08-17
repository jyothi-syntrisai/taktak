<?php

declare(strict_types=1);

namespace App\Models;

class TargetProductModel extends BaseModel
{
    protected $table         = 'target_products';
    protected $allowedFields = ['target_id', 'product_id', 'target_units', 'created_by', 'updated_by'];

    /**
     * The product rows behind each target, with the SKU, product name and brand
     * folded in - one query for the whole page.
     *
     * @param list<int> $targetIds
     *
     * @return array<int, list<array<string, mixed>>> keyed by target id
     */
    public function forTargets(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        $rows = $this->db->table('target_products tp')
            ->select('tp.id, tp.target_id, tp.product_id, tp.target_units,'
                . ' p.sku, p.product_name, p.status AS product_status,'
                . ' p.brand_id, b.name AS brand_name')
            ->join('products p', 'p.id = tp.product_id', 'left')
            ->join('brands b', 'b.id = p.brand_id', 'left')
            ->whereIn('tp.target_id', $targetIds)
            ->orderBy('b.name', 'ASC')
            ->orderBy('p.product_name', 'ASC')
            ->get()
            ->getResultArray();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['target_id']][] = [
                'id'             => (int) $row['id'],
                'product_id'     => (int) $row['product_id'],
                'sku'            => $row['sku'],
                'product_name'   => $row['product_name'],
                'product_status' => $row['product_status'],
                'brand_id'       => isset($row['brand_id']) ? (int) $row['brand_id'] : null,
                'brand_name'     => $row['brand_name'],
                'target_units'   => (int) $row['target_units'],
            ];
        }

        return $grouped;
    }

    /**
     * Line count and unit total per target - what a list screen shows without
     * having to send every line down with it.
     *
     * @param list<int> $targetIds
     *
     * @return array<int, array{product_count: int, total_units: int}> keyed by target id
     */
    public function totalsForTargets(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        $rows = $this->db->table('target_products')
            ->select('target_id, COUNT(*) AS product_count, COALESCE(SUM(target_units), 0) AS total_units')
            ->whereIn('target_id', $targetIds)
            ->groupBy('target_id')
            ->get()
            ->getResultArray();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row['target_id']] = [
                'product_count' => (int) $row['product_count'],
                'total_units'   => (int) $row['total_units'],
            ];
        }

        return $totals;
    }

    /**
     * Swaps the whole product table of a target for the one that was sent. The
     * caller runs this inside a transaction.
     *
     * @param list<array{product_id: int, target_units: int}> $lines
     */
    public function replaceForTarget(int $targetId, array $lines, int $actorId): void
    {
        $this->db->table('target_products')->where('target_id', $targetId)->delete();

        if ($lines === []) {
            return;
        }

        $this->db->table('target_products')->insertBatch(array_map(
            static fn (array $line): array => [
                'target_id'    => $targetId,
                'product_id'   => $line['product_id'],
                'target_units' => $line['target_units'],
                'created_by'   => $actorId,
                'updated_by'   => $actorId,
            ],
            $lines,
        ));
    }
}
