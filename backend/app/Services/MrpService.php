<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\ProductMrpModel;

/**
 * Price history rules.
 *
 * An MRP row is never overwritten. The row currently in force (the one with an
 * empty `effective_to`) is closed on the day before the new price starts, and a
 * fresh row is inserted. That is what lets a report priced for any past month
 * pick the row whose date range covers it.
 *
 *   | mrp | effective_from | effective_to |
 *   | 100 | 2026-01-01     | 2026-03-31   |
 *   | 110 | 2026-04-01     | NULL         |  <- current
 */
class MrpService
{
    private ProductMrpModel $mrps;

    public function __construct()
    {
        $this->mrps = model(ProductMrpModel::class);
    }

    public static function today(): string
    {
        return gmdate('Y-m-d');
    }

    /** 'YYYY-MM-DD' arithmetic in UTC, so a server timezone can never shift a price date. */
    public static function addDays(string $isoDate, int $days): string
    {
        return gmdate('Y-m-d', strtotime($isoDate . ' UTC') + ($days * 86400));
    }

    /**
     * Records a new MRP for a product. Callers that need it to be atomic with
     * other writes should already be inside a transaction.
     *
     * @return array<string, mixed> the newly created row
     */
    public function setMrp(int $productId, float $mrp, string $effectiveFrom, int $actorId): array
    {
        $duplicate = $this->mrps
            ->where('product_id', $productId)
            ->where('effective_from', $effectiveFrom)
            ->first();

        if ($duplicate !== null) {
            throw ApiException::conflict(
                "This product already has a price starting on {$effectiveFrom}. Edit that entry or pick another date.",
            );
        }

        $current = $this->mrps->currentFor($productId);

        if ($current !== null) {
            if ($effectiveFrom <= $current['effective_from']) {
                throw ApiException::badRequest(
                    "The new price must start after the current price, which began on {$current['effective_from']}.",
                );
            }

            $this->mrps->update((int) $current['id'], [
                'effective_to' => self::addDays($effectiveFrom, -1),
                'updated_by'   => $actorId,
            ]);
        }

        $id = (int) $this->mrps->insert([
            'product_id'     => $productId,
            'mrp'            => $mrp,
            'effective_from' => $effectiveFrom,
            'effective_to'   => null,
            'created_by'     => $actorId,
            'updated_by'     => $actorId,
        ]);

        return ProductMrpModel::cast($this->mrps->findRow($id) ?? []);
    }

    /**
     * The price in force for a product on a given date - the reporting lookup.
     *
     * @return array<string, mixed>|null
     */
    public function mrpOnDate(int $productId, string $onDate): ?array
    {
        $row = db_connect()->table('product_mrp')
            ->where('product_id', $productId)
            ->where('effective_from <=', $onDate)
            ->groupStart()
            ->where('effective_to', null)
            ->orWhere('effective_to >=', $onDate)
            ->groupEnd()
            ->orderBy('effective_from', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        return $row === null ? null : ProductMrpModel::cast($row);
    }
}
