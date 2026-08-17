<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\BrandModel;
use App\Support\Pagination;

class BrandService
{
    private const SORTABLE = ['id', 'name', 'code', 'status', 'created_at'];

    private BrandModel $brands;

    public function __construct()
    {
        $this->brands = model(BrandModel::class);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(array $query): array
    {
        $page    = Pagination::fromQuery($query);
        $builder = db_connect()->table('brands');

        if ($page['status'] !== null) {
            $builder->where('status', $page['status']);
        }

        if ($page['search'] !== null) {
            $builder->groupStart()
                ->like('name', $page['search'])
                ->orLike('code', $page['search'])
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);

        [$column, $direction] = Pagination::safeOrder($page['sort_by'], $page['sort_dir'], self::SORTABLE, 'name');

        $rows = $builder->orderBy($column, $direction)
            ->limit($page['limit'], $page['offset'])
            ->get()
            ->getResultArray();

        $items = array_map(static function (array $brand): array {
            $brand['id'] = (int) $brand['id'];

            return $brand;
        }, $rows);

        return ['items' => $items, 'meta' => Pagination::meta($page['page'], $page['limit'], $total)];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(int $id): array
    {
        $brand = $this->brands->findRow($id);

        if ($brand === null) {
            throw ApiException::notFound('Brand not found');
        }

        $brand['id'] = (int) $brand['id'];

        return $brand;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function create(array $input, int $actorId): array
    {
        $code = ($input['code'] ?? null) ?: null;

        if ($this->brands->existsWith('name', $input['name'])) {
            throw ApiException::conflict('A brand with this name already exists');
        }

        if ($code !== null && $this->brands->existsWith('code', $code)) {
            throw ApiException::conflict('A brand with this code already exists');
        }

        $id = (int) $this->brands->insert([
            'name'       => $input['name'],
            'code'       => $code,
            'status'     => $input['status'] ?? 'active',
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return $this->get($id);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, int $actorId): array
    {
        $brand = $this->brands->findRow($id);

        if ($brand === null) {
            throw ApiException::notFound('Brand not found');
        }

        $changes = [];

        if (array_key_exists('name', $input)) {
            if ($input['name'] !== $brand['name'] && $this->brands->existsWith('name', $input['name'], $id)) {
                throw ApiException::conflict('A brand with this name already exists');
            }

            $changes['name'] = $input['name'];
        }

        if (array_key_exists('code', $input)) {
            $code = ($input['code'] ?? null) ?: null;

            if ($code !== null && $code !== $brand['code'] && $this->brands->existsWith('code', $code, $id)) {
                throw ApiException::conflict('A brand with this code already exists');
            }

            $changes['code'] = $code;
        }

        if (array_key_exists('status', $input)) {
            $changes['status'] = $input['status'];
        }

        $changes['updated_by'] = $actorId;

        $this->brands->update($id, $changes);

        return $this->get($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(int $id, int $actorId): array
    {
        $brand = $this->brands->findRow($id);

        if ($brand === null) {
            throw ApiException::notFound('Brand not found');
        }

        $activeProducts = db_connect()->table('products')
            ->where('brand_id', $id)
            ->where('status', 'active')
            ->countAllResults();

        if ($activeProducts > 0) {
            throw ApiException::badRequest(
                "{$activeProducts} active product(s) belong to this brand. Deactivate them first.",
            );
        }

        $this->brands->update($id, ['status' => 'inactive', 'updated_by' => $actorId]);

        return $this->get($id);
    }
}
