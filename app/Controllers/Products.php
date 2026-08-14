<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ApiException;
use App\Services\ProductService;
use CodeIgniter\HTTP\ResponseInterface;

class Products extends BaseApiController
{
    private ProductService $service;

    public function __construct()
    {
        $this->service = new ProductService();
    }

    public function index(): ResponseInterface
    {
        $result = $this->service->list($this->queryParams());

        return $this->paginated($result['items'], $result['meta']);
    }

    public function show(string $id): ResponseInterface
    {
        return $this->ok($this->service->get($this->routeId($id)));
    }

    public function create(): ResponseInterface
    {
        $input = $this->validateBody(
            [
                'brand_id'       => ['required', 'is_natural_no_zero'],
                'sku'            => ['required', 'max_length[100]'],
                'product_name'   => ['required', 'min_length[2]', 'max_length[255]'],
                'status'         => ['permit_empty', 'in_list[active,inactive]'],
                // Optional opening price, so a product and its first MRP can be
                // created in one call.
                'mrp'            => ['permit_empty', 'numeric', 'greater_than[0]'],
                'effective_from' => ['permit_empty', 'valid_date[Y-m-d]'],
            ],
            [
                'brand_id'     => ['required' => 'A brand must be selected', 'is_natural_no_zero' => 'A brand must be selected'],
                'sku'          => ['required' => 'SKU is required'],
                'product_name' => ['required' => 'Product name is required', 'min_length' => 'Product name is required'],
                'mrp'          => ['greater_than' => 'MRP must be greater than zero', 'numeric' => 'MRP must be a number'],
                'effective_from' => ['valid_date' => 'Date must be in YYYY-MM-DD format'],
            ],
        );

        return $this->created($this->service->create($input, $this->actorId()), 'Product created successfully');
    }

    public function update(string $id): ResponseInterface
    {
        $input = $this->validateBody([
            'brand_id'     => ['permit_empty', 'is_natural_no_zero'],
            'sku'          => ['permit_empty', 'max_length[100]'],
            'product_name' => ['permit_empty', 'min_length[2]', 'max_length[255]'],
            'status'       => ['permit_empty', 'in_list[active,inactive]'],
        ]);

        $this->requireAnyOf($input, ['brand_id', 'sku', 'product_name', 'status']);

        return $this->ok(
            $this->service->update($this->routeId($id), $input, $this->actorId()),
            'Product updated successfully',
        );
    }

    public function delete(string $id): ResponseInterface
    {
        return $this->ok(
            $this->service->deactivate($this->routeId($id), $this->actorId()),
            'Product deactivated successfully',
        );
    }

    // -----------------------------------------------------------------------
    // MRP
    // -----------------------------------------------------------------------

    public function mrpHistory(string $id): ResponseInterface
    {
        return $this->ok($this->service->mrpHistory($this->routeId($id)));
    }

    public function setMrp(string $id): ResponseInterface
    {
        $input = $this->validateBody(
            [
                'mrp'            => ['required', 'numeric', 'greater_than[0]'],
                'effective_from' => ['required', 'valid_date[Y-m-d]'],
            ],
            [
                'mrp'            => ['required' => 'MRP is required', 'greater_than' => 'MRP must be greater than zero'],
                'effective_from' => ['required' => 'effective_from is required', 'valid_date' => 'Date must be in YYYY-MM-DD format'],
            ],
        );

        if (! self::isIsoDate((string) $input['effective_from'])) {
            throw ApiException::unprocessable('Validation failed', [
                ['field' => 'effective_from', 'message' => 'Date must be in YYYY-MM-DD format'],
            ]);
        }

        $product = $this->service->setMrp(
            $this->routeId($id),
            (float) $input['mrp'],
            (string) $input['effective_from'],
            $this->actorId(),
        );

        return $this->created($product, 'New MRP recorded. The previous price has been closed, not replaced.');
    }

    public function mrpOnDate(string $id): ResponseInterface
    {
        $onDate = $this->requireIsoDateQuery('on_date');

        return $this->ok($this->service->mrpOnDate($this->routeId($id), $onDate));
    }

    /** The `v_current_price_list` view: every active product with the price in force today. */
    public function priceList(): ResponseInterface
    {
        $search = $this->queryParams()['search'] ?? null;

        return $this->ok($this->service->currentPriceList(is_string($search) ? $search : null));
    }
}
