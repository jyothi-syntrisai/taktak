<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\DistributorRegionModel;
use App\Models\RegionModel;
use App\Models\RegionStateModel;
use App\Support\HandlesTransactions;
use App\Support\Pagination;

class RegionService
{
    use HandlesTransactions;

    private const SORTABLE = ['id', 'name', 'status', 'created_at'];

    private RegionModel $regions;
    private RegionStateModel $regionStates;

    public function __construct()
    {
        $this->regions      = model(RegionModel::class);
        $this->regionStates = model(RegionStateModel::class);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(array $query): array
    {
        $page    = Pagination::fromQuery($query);
        $builder = db_connect()->table('regions');

        if ($page['status'] !== null) {
            $builder->where('status', $page['status']);
        }

        if ($page['search'] !== null) {
            $builder->like('name', $page['search']);
        }

        // Filtering by state must not truncate the state list shown on each row,
        // so the filter runs as a separate id lookup rather than a join.
        if (isset($query['state_id']) && (int) $query['state_id'] > 0) {
            $regionIds = array_column(
                db_connect()->table('region_states')
                    ->select('region_id')
                    ->where('state_id', (int) $query['state_id'])
                    ->get()
                    ->getResultArray(),
                'region_id',
            );

            if ($regionIds === []) {
                return ['items' => [], 'meta' => Pagination::meta($page['page'], $page['limit'], 0)];
            }

            $builder->whereIn('id', array_map('intval', $regionIds));
        }

        $total = $builder->countAllResults(false);

        [$column, $direction] = Pagination::safeOrder($page['sort_by'], $page['sort_dir'], self::SORTABLE, 'name');

        $rows = $builder->orderBy($column, $direction)
            ->limit($page['limit'], $page['offset'])
            ->get()
            ->getResultArray();

        $states = $this->regionStates->statesForRegions(array_map(static fn (array $r): int => (int) $r['id'], $rows));

        $items = array_map(
            static function (array $region) use ($states): array {
                $region['id']     = (int) $region['id'];
                $region['states'] = $states[$region['id']] ?? [];

                return $region;
            },
            $rows,
        );

        return ['items' => $items, 'meta' => Pagination::meta($page['page'], $page['limit'], $total)];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $id): array
    {
        $region = $this->regions->findRow($id);

        if ($region === null) {
            throw ApiException::notFound('Region not found');
        }

        $region['id']     = (int) $region['id'];
        $region['states'] = $this->regionStates->statesForRegions([$id])[$id] ?? [];

        return $region;
    }

    /**
     * @param array<string, mixed> $input
     * @param list<int>            $stateIds
     *
     * @return array<string, mixed>
     */
    public function create(array $input, array $stateIds, int $actorId): array
    {
        if ($this->regions->existsWith('name', $input['name'])) {
            throw ApiException::conflict('A region with this name already exists');
        }

        $this->assertStatesExist($stateIds);

        $regionId = $this->transaction(function () use ($input, $stateIds, $actorId): int {
            $id = (int) $this->regions->insert([
                'name'       => $input['name'],
                'status'     => $input['status'] ?? 'active',
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->replaceStates($id, $stateIds, $actorId);

            return $id;
        });

        return $this->get($regionId);
    }

    /**
     * @param array<string, mixed> $input
     * @param list<int>|null       $stateIds
     *
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, ?array $stateIds, int $actorId): array
    {
        $region = $this->regions->findRow($id);

        if ($region === null) {
            throw ApiException::notFound('Region not found');
        }

        if (isset($input['name']) && $input['name'] !== $region['name'] && $this->regions->existsWith('name', $input['name'], $id)) {
            throw ApiException::conflict('A region with this name already exists');
        }

        if ($stateIds !== null) {
            $this->assertStatesExist($stateIds);
        }

        $this->transaction(function () use ($id, $input, $stateIds, $actorId): void {
            $changes = array_intersect_key($input, array_flip(['name', 'status']));
            $changes['updated_by'] = $actorId;

            $this->regions->update($id, $changes);

            if ($stateIds !== null) {
                $this->replaceStates($id, $stateIds, $actorId);
            }
        });

        return $this->get($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(int $id, int $actorId): array
    {
        $region = $this->regions->findRow($id);

        if ($region === null) {
            throw ApiException::notFound('Region not found');
        }

        if ($region['status'] === 'inactive') {
            return $this->get($id);
        }

        $distributors = model(DistributorRegionModel::class)->countActiveForRegion($id);

        if ($distributors > 0) {
            throw ApiException::badRequest(
                "{$distributors} active distributor(s) still cover this region. Update them first.",
            );
        }

        $this->regions->update($id, ['status' => 'inactive', 'updated_by' => $actorId]);

        return $this->get($id);
    }

    // -----------------------------------------------------------------------

    /**
     * @param list<int> $stateIds
     */
    private function assertStatesExist(array $stateIds): void
    {
        $found = db_connect()->table('states')
            ->whereIn('id', $stateIds)
            ->where('status', 'active')
            ->countAllResults();

        if ($found !== count($stateIds)) {
            throw ApiException::badRequest('One or more selected states do not exist');
        }
    }

    /**
     * @param list<int> $stateIds
     */
    private function replaceStates(int $regionId, array $stateIds, int $actorId): void
    {
        db_connect()->table('region_states')->where('region_id', $regionId)->delete();

        if ($stateIds === []) {
            return;
        }

        db_connect()->table('region_states')->insertBatch(array_map(
            static fn (int $stateId): array => [
                'region_id'  => $regionId,
                'state_id'   => $stateId,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ],
            $stateIds,
        ));
    }
}
