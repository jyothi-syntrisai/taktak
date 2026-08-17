<?php

declare(strict_types=1);

namespace App\Models;

class DistributorRegionModel extends BaseModel
{
    protected $table         = 'distributor_regions';
    protected $allowedFields = ['distributor_id', 'region_id', 'created_by', 'updated_by'];

    /**
     * Regions covered by each distributor, with the states of those regions
     * folded in - one query for the whole page of results.
     *
     * @param list<int> $distributorIds
     *
     * @return array<int, list<array<string, mixed>>> keyed by distributor id
     */
    public function regionsForDistributors(array $distributorIds): array
    {
        if ($distributorIds === []) {
            return [];
        }

        $rows = $this->db->table('distributor_regions dr')
            ->select('dr.distributor_id, r.id, r.name')
            ->join('regions r', 'r.id = dr.region_id')
            ->whereIn('dr.distributor_id', $distributorIds)
            ->orderBy('r.name', 'ASC')
            ->get()
            ->getResultArray();

        $regionIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['id'], $rows)));
        $states    = model(RegionStateModel::class)->statesForRegions($regionIds);

        $grouped = [];

        foreach ($rows as $row) {
            $regionId = (int) $row['id'];

            $grouped[(int) $row['distributor_id']][] = [
                'id'     => $regionId,
                'name'   => $row['name'],
                'states' => $states[$regionId] ?? [],
            ];
        }

        return $grouped;
    }

    /** Active distributors that still cover a region - blocks its deactivation. */
    public function countActiveForRegion(int $regionId): int
    {
        return $this->db->table('distributor_regions dr')
            ->join('distributors d', 'd.id = dr.distributor_id')
            ->where('dr.region_id', $regionId)
            ->where('d.status', 'active')
            ->countAllResults();
    }
}
