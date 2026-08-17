<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ApiException;
use App\Models\TargetModel;
use App\Services\TargetService;
use CodeIgniter\HTTP\ResponseInterface;

class Targets extends BaseApiController
{
    private TargetService $service;

    public function __construct()
    {
        $this->service = new TargetService();
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
        $input = $this->validateBody(self::rules(true), self::messages());
        $lines = $this->productLines($this->body(), true) ?? [];

        return $this->created($this->service->create($input, $lines, $this->actorId()), 'Target created successfully');
    }

    public function update(string $id): ResponseInterface
    {
        $input = $this->validateBody(self::rules(false), self::messages());
        $lines = $this->productLines($this->body(), false);

        if ($lines === null) {
            $this->requireAnyOf($input, [
                'name',
                'target_type',
                'target_ref_id',
                'target_month',
                'target_year',
                'status',
            ]);
        }

        return $this->ok(
            $this->service->update($this->routeId($id), $input, $lines, $this->actorId()),
            'Target updated successfully',
        );
    }

    public function delete(string $id): ResponseInterface
    {
        return $this->ok(
            $this->service->deactivate($this->routeId($id), $this->actorId()),
            'Target deactivated successfully',
        );
    }

    // -----------------------------------------------------------------------
    // Drop-downs
    // -----------------------------------------------------------------------

    /** The first drop-down: Region / Distributors / Sales Person. */
    public function types(): ResponseInterface
    {
        return $this->ok($this->service->types());
    }

    /** The second drop-down: the values that can be chosen for `?type=`. */
    public function options(): ResponseInterface
    {
        $query = $this->queryParams();
        $type  = (string) ($query['type'] ?? '');

        if ($type === '') {
            throw ApiException::unprocessable('Validation failed', [
                ['field' => 'type', 'message' => 'type is required'],
            ]);
        }

        $search = isset($query['search']) ? (string) $query['search'] : null;

        return $this->ok($this->service->options($type, $search));
    }

    // -----------------------------------------------------------------------

    /**
     * @return array<string, list<string>>
     */
    private static function rules(bool $forCreate): array
    {
        $required = $forCreate ? 'required' : 'permit_empty';
        $types    = implode(',', TargetModel::typeValues());

        return [
            'name'          => [$required, 'min_length[2]', 'max_length[200]'],
            'target_type'   => [$required, "in_list[{$types}]"],
            'target_ref_id' => [$required, 'is_natural_no_zero'],
            'target_month'  => [$required, 'is_natural_no_zero', 'less_than_equal_to[12]'],
            'target_year'   => [$required, 'greater_than_equal_to[2000]', 'less_than_equal_to[2100]'],
            'status'        => ['permit_empty', 'in_list[active,inactive]'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function messages(): array
    {
        return [
            'name' => [
                'required'   => 'Target name is required',
                'min_length' => 'Target name is required',
            ],
            'target_type' => [
                'required' => 'A target type must be selected',
                'in_list'  => 'target_type must be one of: ' . implode(', ', TargetModel::typeValues()),
            ],
            'target_ref_id' => [
                'required'           => 'A value must be selected for the chosen target type',
                'is_natural_no_zero' => 'A value must be selected for the chosen target type',
            ],
            'target_month' => [
                'required'              => 'A month must be selected',
                'is_natural_no_zero'    => 'Month must be a number from 1 to 12',
                'less_than_equal_to'    => 'Month must be a number from 1 to 12',
            ],
            'target_year' => [
                'required'              => 'A year must be selected',
                'greater_than_equal_to' => 'Year must be between 2000 and 2100',
                'less_than_equal_to'    => 'Year must be between 2000 and 2100',
            ],
        ];
    }

    /**
     * Reads the product table the target screen posts:
     *
     *     "products": [{ "product_id": 7, "target_units": 500 }, ...]
     *
     * The validation library has no rule for a list of objects, so - as with
     * `idList` - it is checked here.
     *
     * @param array<string, mixed> $input
     *
     * @return list<array{product_id: int, target_units: int}>|null null when the field was not sent at all
     */
    private function productLines(array $input, bool $required): ?array
    {
        if (! array_key_exists('products', $input)) {
            if ($required) {
                throw self::lineError('products', 'products is required');
            }

            return null;
        }

        $value = $input['products'];

        if (! is_array($value)) {
            throw self::lineError('products', 'products must be an array of { product_id, target_units }');
        }

        $lines = [];
        $seen  = [];

        foreach (array_values($value) as $index => $item) {
            if (! is_array($item)) {
                throw self::lineError("products[{$index}]", 'Each row must be an object of { product_id, target_units }');
            }

            $productId = $item['product_id'] ?? null;
            $units     = $item['target_units'] ?? null;

            if (! self::isWholeNumber($productId) || (int) $productId < 1) {
                throw self::lineError("products[{$index}].product_id", 'product_id must be a positive whole number');
            }

            // Zero is allowed: a product may sit in the table with no target on it.
            if (! self::isWholeNumber($units) || (int) $units < 0) {
                throw self::lineError("products[{$index}].target_units", 'target_units must be a whole number of 0 or more');
            }

            $productId = (int) $productId;

            if (isset($seen[$productId])) {
                throw self::lineError(
                    "products[{$index}].product_id",
                    "Product {$productId} appears more than once",
                );
            }

            $seen[$productId] = true;
            $lines[]          = ['product_id' => $productId, 'target_units' => (int) $units];
        }

        if ($lines === []) {
            throw self::lineError('products', 'Enter a target for at least one product');
        }

        return $lines;
    }

    private static function isWholeNumber(mixed $value): bool
    {
        return is_numeric($value) && (float) $value == (int) $value;
    }

    private static function lineError(string $field, string $message): ApiException
    {
        return ApiException::unprocessable('Validation failed', [
            ['field' => $field, 'message' => $message],
        ]);
    }
}
