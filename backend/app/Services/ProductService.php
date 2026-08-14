<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\BrandModel;
use App\Models\ProductModel;
use App\Models\ProductMrpModel;
use App\Support\HandlesTransactions;
use App\Support\Pagination;

class ProductService
{
    use HandlesTransactions;

    private const SORTABLE = ['id', 'sku', 'product_name', 'status', 'created_at'];

    private ProductModel $products;
    private ProductMrpModel $mrps;
    private BrandModel $brands;
    private MrpService $mrpService;

    public function __construct()
    {
        $this->products   = model(ProductModel::class);
        $this->mrps       = model(ProductMrpModel::class);
        $this->brands     = model(BrandModel::class);
        $this->mrpService = new MrpService();
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(array $query): array
    {
        $page = Pagination::fromQuery($query);

        $builder = db_connect()->table('products')
            ->select('products.*, b.name AS brand_name, b.code AS brand_code')
            ->join('brands b', 'b.id = products.brand_id', 'left');

        if ($page['status'] !== null) {
            $builder->where('products.status', $page['status']);
        }

        if (isset($query['brand_id']) && (int) $query['brand_id'] > 0) {
            $builder->where('products.brand_id', (int) $query['brand_id']);
        }

        if ($page['search'] !== null) {
            $builder->groupStart()
                ->like('products.sku', $page['search'])
                ->orLike('products.product_name', $page['search'])
                ->groupEnd();
        }

        $total = $builder->countAllResults(false);

        [$column, $direction] = Pagination::safeOrder($page['sort_by'], $page['sort_dir'], self::SORTABLE, 'created_at');

        $rows = $builder->orderBy('products.' . $column, $direction)
            ->limit($page['limit'], $page['offset'])
            ->get()
            ->getResultArray();

        // Only the row still in force - what a list screen wants next to each product.
        $current = $this->mrps->currentForMany(array_map(static fn (array $r): int => (int) $r['id'], $rows));

        $items = array_map(
            static function (array $row) use ($current): array {
                $product                = ProductModel::withBrand($row);
                $product['current_mrp'] = $current[$product['id']] ?? null;

                return $product;
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
        $row = db_connect()->table('products')
            ->select('products.*, b.name AS brand_name, b.code AS brand_code')
            ->join('brands b', 'b.id = products.brand_id', 'left')
            ->where('products.id', $id)
            ->get()
            ->getRowArray();

        if ($row === null) {
            throw ApiException::notFound('Product not found');
        }

        $current = $this->mrps->currentFor($id);

        $product                = ProductModel::withBrand($row);
        $product['mrps']        = $this->mrps->historyFor($id);
        $product['current_mrp'] = $current === null ? null : ProductMrpModel::cast($current);

        return $product;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function create(array $input, int $actorId): array
    {
        if ($this->products->existsWith('sku', $input['sku'])) {
            throw ApiException::conflict("A product with SKU \"{$input['sku']}\" already exists");
        }

        $this->assertBrandUsable((int) $input['brand_id']);

        $productId = $this->transaction(function () use ($input, $actorId): int {
            $id = (int) $this->products->insert([
                'brand_id'     => (int) $input['brand_id'],
                'sku'          => $input['sku'],
                'product_name' => $input['product_name'],
                'status'       => $input['status'] ?? 'active',
                'created_by'   => $actorId,
                'updated_by'   => $actorId,
            ]);

            // Optional opening price, so a product and its first MRP can be
            // created in one call.
            if (isset($input['mrp']) && $input['mrp'] !== '') {
                $this->mrpService->setMrp(
                    $id,
                    (float) $input['mrp'],
                    $input['effective_from'] ?? MrpService::today(),
                    $actorId,
                );
            }

            return $id;
        });

        return $this->get($productId);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, int $actorId): array
    {
        $product = $this->products->findRow($id);

        if ($product === null) {
            throw ApiException::notFound('Product not found');
        }

        $changes = [];

        if (array_key_exists('sku', $input)) {
            if ($input['sku'] !== $product['sku'] && $this->products->existsWith('sku', $input['sku'], $id)) {
                throw ApiException::conflict("A product with SKU \"{$input['sku']}\" already exists");
            }

            $changes['sku'] = $input['sku'];
        }

        if (array_key_exists('brand_id', $input) && (int) $input['brand_id'] !== (int) $product['brand_id']) {
            $this->assertBrandUsable((int) $input['brand_id']);
            $changes['brand_id'] = (int) $input['brand_id'];
        }

        if (array_key_exists('product_name', $input)) {
            $changes['product_name'] = $input['product_name'];
        }

        if (array_key_exists('status', $input)) {
            $changes['status'] = $input['status'];
        }

        $changes['updated_by'] = $actorId;

        $this->products->update($id, $changes);

        return $this->get($id);
    }

    /**
     * The MRP history stays untouched - old reports must keep pricing correctly.
     *
     * @return array<string, mixed>
     */
    public function deactivate(int $id, int $actorId): array
    {
        $product = $this->products->findRow($id);

        if ($product === null) {
            throw ApiException::notFound('Product not found');
        }

        if ($product['status'] === 'inactive') {
            return $this->get($id);
        }

        $this->products->update($id, ['status' => 'inactive', 'updated_by' => $actorId]);

        return $this->get($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function setMrp(int $productId, float $mrp, string $effectiveFrom, int $actorId): array
    {
        if ($this->products->findRow($productId) === null) {
            throw ApiException::notFound('Product not found');
        }

        $this->transaction(fn () => $this->mrpService->setMrp($productId, $mrp, $effectiveFrom, $actorId));

        return $this->get($productId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mrpHistory(int $productId): array
    {
        if ($this->products->findRow($productId) === null) {
            throw ApiException::notFound('Product not found');
        }

        return $this->mrps->historyFor($productId);
    }

    /**
     * @return array<string, mixed>
     */
    public function mrpOnDate(int $productId, string $onDate): array
    {
        if ($this->products->findRow($productId) === null) {
            throw ApiException::notFound('Product not found');
        }

        $row = $this->mrpService->mrpOnDate($productId, $onDate);

        if ($row === null) {
            throw ApiException::notFound("No MRP was in force for this product on {$onDate}");
        }

        return $row;
    }

    /**
     * Reads the `v_current_price_list` view from the schema document rather
     * than rebuilding the same join in the application.
     *
     * @return list<array<string, mixed>>
     */
    public function currentPriceList(?string $search): array
    {
        $db = db_connect();

        if ($search === null || $search === '') {
            $rows = $db->query('SELECT * FROM v_current_price_list ORDER BY brand_name, product_name')->getResultArray();
        } else {
            $like = '%' . $search . '%';
            $rows = $db->query(
                'SELECT * FROM v_current_price_list
                  WHERE sku LIKE ? OR product_name LIKE ? OR brand_name LIKE ?
                  ORDER BY brand_name, product_name',
                [$like, $like, $like],
            )->getResultArray();
        }

        return array_map(static function (array $row): array {
            $row['product_id'] = (int) $row['product_id'];
            $row['mrp']        = $row['mrp'] === null ? null : (float) $row['mrp'];

            return $row;
        }, $rows);
    }

    // -----------------------------------------------------------------------

    private function assertBrandUsable(int $brandId): void
    {
        $brand = $this->brands->findRow($brandId);

        if ($brand === null) {
            throw ApiException::badRequest('The selected brand does not exist');
        }

        if ($brand['status'] !== 'active') {
            throw ApiException::badRequest('The selected brand is inactive');
        }
    }
}
