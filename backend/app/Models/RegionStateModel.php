<?php

declare(strict_types=1);

namespace App\Models;

class RegionStateModel extends BaseModel
{
    protected $table         = 'region_states';
    protected $allowedFields = ['region_id', 'state_id', 'created_by', 'updated_by'];

    /**
     * The states of one region, and of many regions at once - the list screen
     * needs every row's states without running a query per row.
     *
     * @param list<int> $regionIds
     *
     * @return array<int, list<array<string, mixed>>> keyed by region id
     */
    public function statesForRegions(array $regionIds): array
    {
        if ($regionIds === []) {
            return [];
        }

        $rows = $this->db->table('region_states rs')
            ->select('rs.region_id, s.id, s.name, s.code')
            ->join('states s', 's.id = rs.state_id')
            ->whereIn('rs.region_id', $regionIds)
            ->orderBy('s.name', 'ASC')
            ->get()
            ->getResultArray();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['region_id']][] = [
                'id'   => (int) $row['id'],
                'name' => $row['name'],
                'code' => $row['code'],
            ];
        }

        return $grouped;
    }
}
