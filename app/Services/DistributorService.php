<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\DistributorModel;
use App\Models\DistributorRegionModel;
use App\Support\HandlesTransactions;
use App\Support\Pagination;

class DistributorService
{
    use HandlesTransactions;

    private const SORTABLE = ['id', 'name', 'code', 'status', 'created_at'];

    private DistributorModel $distributors;
    private DistributorRegionModel $distributorRegions;

    public function __construct()
    {
        $this->distributors       = model(DistributorModel::class);
        $this->distributorRegions = model(DistributorRegionModel::class);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(array $query): array
    {
        $page    = Pagination::fromQuery($query);
        $builder = db_connect()->table('distributors');

        if ($page['status'] !== null) {
            $builder->where('status', $page['status']);
        }

        if ($page['search'] !== null) {
            $builder->groupStart()
                ->like('name', $page['search'])
                ->orLike('code', $page['search'])
                ->groupEnd();
        }

        if (isset($query['region_id']) && (int) $query['region_id'] > 0) {
            $ids = array_column(
                db_connect()->table('distributor_regions')
                    ->select('distributor_id')
                    ->where('region_id', (int) $query['region_id'])
                    ->get()
                    ->getResultArray(),
                'distributor_id',
            );

            if ($ids === []) {
                return ['items' => [], 'meta' => Pagination::meta($page['page'], $page['limit'], 0)];
            }

            $builder->whereIn('id', array_map('intval', $ids));
        }

        $total = $builder->countAllResults(false);

        [$column, $direction] = Pagination::safeOrder($page['sort_by'], $page['sort_dir'], self::SORTABLE, 'name');

        $rows = $builder->orderBy($column, $direction)
            ->limit($page['limit'], $page['offset'])
            ->get()
            ->getResultArray();

        $regions = $this->distributorRegions->regionsForDistributors(
            array_map(static fn (array $r): int => (int) $r['id'], $rows),
        );

        $items = array_map(
            static function (array $distributor) use ($regions): array {
                $distributor['id']      = (int) $distributor['id'];
                $distributor['regions'] = $regions[$distributor['id']] ?? [];

                return $distributor;
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
        $distributor = $this->distributors->findRow($id);

        if ($distributor === null) {
            throw ApiException::notFound('Distributor not found');
        }

        $distributor['id']      = (int) $distributor['id'];
        $distributor['regions'] = $this->distributorRegions->regionsForDistributors([$id])[$id] ?? [];

        return $distributor;
    }

    /**
     * @param array<string, mixed> $input
     * @param list<int>            $regionIds
     *
     * @return array<string, mixed>
     */
    public function create(array $input, array $regionIds, int $actorId): array
    {
        $code = ($input['code'] ?? null) ?: null;

        if ($code !== null && $this->distributors->existsWith('code', $code)) {
            throw ApiException::conflict('A distributor with this code already exists');
        }

        $this->assertRegionsExist($regionIds);

        $distributorId = $this->transaction(function () use ($input, $code, $regionIds, $actorId): int {
            $id = (int) $this->distributors->insert([
                'name'       => $input['name'],
                'code'       => $code,
                'status'     => $input['status'] ?? 'active',
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->replaceRegions($id, $regionIds, $actorId);

            return $id;
        });

        return $this->get($distributorId);
    }

    /**
     * @param array<string, mixed> $input
     * @param list<int>|null       $regionIds
     *
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, ?array $regionIds, int $actorId): array
    {
        $distributor = $this->distributors->findRow($id);

        if ($distributor === null) {
            throw ApiException::notFound('Distributor not found');
        }

        if (array_key_exists('code', $input)) {
            $code = ($input['code'] ?? null) ?: null;

            if ($code !== null && $code !== $distributor['code'] && $this->distributors->existsWith('code', $code, $id)) {
                throw ApiException::conflict('A distributor with this code already exists');
            }
        }

        if ($regionIds !== null) {
            $this->assertRegionsExist($regionIds);
        }

        $this->transaction(function () use ($id, $input, $regionIds, $actorId): void {
            $changes = [];

            if (array_key_exists('name', $input)) {
                $changes['name'] = $input['name'];
            }

            if (array_key_exists('code', $input)) {
                $changes['code'] = ($input['code'] ?? null) ?: null;
            }

            if (array_key_exists('status', $input)) {
                $changes['status'] = $input['status'];
            }

            $changes['updated_by'] = $actorId;

            $this->distributors->update($id, $changes);

            if ($regionIds !== null) {
                $this->replaceRegions($id, $regionIds, $actorId);
            }
        });

        return $this->get($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(int $id, int $actorId): array
    {
        $distributor = $this->distributors->findRow($id);

        if ($distributor === null) {
            throw ApiException::notFound('Distributor not found');
        }

        if ($distributor['status'] === 'inactive') {
            return $this->get($id);
        }

        $this->distributors->update($id, ['status' => 'inactive', 'updated_by' => $actorId]);

        return $this->get($id);
    }

    // -----------------------------------------------------------------------

    /**
     * @param list<int> $regionIds
     */
    private function assertRegionsExist(array $regionIds): void
    {
        $found = db_connect()->table('regions')
            ->whereIn('id', $regionIds)
            ->where('status', 'active')
            ->countAllResults();

        if ($found !== count($regionIds)) {
            throw ApiException::badRequest('One or more selected regions do not exist or are inactive');
        }
    }

    /**
     * @param list<int> $regionIds
     */
    private function replaceRegions(int $distributorId, array $regionIds, int $actorId): void
    {
        db_connect()->table('distributor_regions')->where('distributor_id', $distributorId)->delete();

        if ($regionIds === []) {
            return;
        }

        db_connect()->table('distributor_regions')->insertBatch(array_map(
            static fn (int $regionId): array => [
                'distributor_id' => $distributorId,
                'region_id'      => $regionId,
                'created_by'     => $actorId,
                'updated_by'     => $actorId,
            ],
            $regionIds,
        ));
    }
}
