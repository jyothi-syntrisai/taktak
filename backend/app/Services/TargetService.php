<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\TargetModel;
use App\Models\TargetProductModel;
use App\Support\HandlesTransactions;
use App\Support\Pagination;
use App\Support\Permissions;

/**
 * Monthly targets. A target names an owner - a region, a distributor or a sales
 * person - a month and a year, and carries one row per product holding the
 * number of units expected.
 *
 * The owner is polymorphic: `target_type` decides which table `target_ref_id`
 * points at, so every lookup goes through {@see resolveOwners()} rather than a
 * join. Everything the drop-downs on the target screen need is served by
 * {@see types()} and {@see options()}.
 */
class TargetService
{
    use HandlesTransactions;

    private const SORTABLE = [
        'id',
        'name',
        'target_type',
        'target_month',
        'target_year',
        'status',
        'created_at',
    ];

    /** A single line may not ask for more units than this - a typo guard, nothing more. */
    private const MAX_UNITS = 999999999;

    /** How many rows a value drop-down returns. */
    private const OPTIONS_LIMIT = 500;

    private TargetModel $targets;
    private TargetProductModel $targetProducts;

    public function __construct()
    {
        $this->targets        = model(TargetModel::class);
        $this->targetProducts = model(TargetProductModel::class);
    }

    // -----------------------------------------------------------------------
    // Drop-downs
    // -----------------------------------------------------------------------

    /**
     * The first drop-down: what a target can be set against.
     *
     * @return list<array{value: string, label: string}>
     */
    public function types(): array
    {
        $types = [];

        foreach (TargetModel::TYPES as $value => $label) {
            $types[] = ['value' => $value, 'label' => $label];
        }

        return $types;
    }

    /**
     * The second drop-down: the values that can be chosen for a type. Only rows
     * that could actually carry a target are returned - active regions, active
     * distributors, active users on a sales role.
     *
     * @return list<array<string, mixed>>
     */
    public function options(string $type, ?string $search = null): array
    {
        // Named for the query parameter that carried it, not for the column.
        $this->assertTypeKnown($type, 'type');

        $builder = match ($type) {
            TargetModel::TYPE_REGION => db_connect()->table('regions')
                ->select('id, name')
                ->where('status', 'active')
                ->orderBy('name', 'ASC'),

            TargetModel::TYPE_DISTRIBUTOR => db_connect()->table('distributors')
                ->select('id, name, code')
                ->where('status', 'active')
                ->orderBy('name', 'ASC'),

            TargetModel::TYPE_SALES_PERSON => db_connect()->table('users u')
                ->select('u.id, u.full_name AS name, u.email, r.name AS role_name, rg.name AS region_name')
                ->join('roles r', 'r.id = u.role_id')
                ->join('regions rg', 'rg.id = u.region_id', 'left')
                ->whereIn('r.name', Permissions::SALES_ROLES)
                ->where('u.status', 'active')
                ->orderBy('u.full_name', 'ASC'),
        };

        if ($search !== null && $search !== '') {
            $column = $type === TargetModel::TYPE_SALES_PERSON ? 'u.full_name' : 'name';
            $builder->like($column, $search);
        }

        $rows = $builder->limit(self::OPTIONS_LIMIT)->get()->getResultArray();

        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];

            return $row;
        }, $rows);
    }

    // -----------------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------------

    /**
     * @param array<string, mixed> $query
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(array $query): array
    {
        $page    = Pagination::fromQuery($query);
        $builder = db_connect()->table('targets');

        if ($page['status'] !== null) {
            $builder->where('status', $page['status']);
        }

        if (isset($query['target_type']) && $query['target_type'] !== '') {
            $this->assertTypeKnown((string) $query['target_type']);
            $builder->where('target_type', (string) $query['target_type']);
        }

        foreach (['target_ref_id', 'target_month', 'target_year'] as $filter) {
            if (isset($query[$filter]) && (int) $query[$filter] > 0) {
                $builder->where($filter, (int) $query[$filter]);
            }
        }

        if ($page['search'] !== null) {
            $builder->like('name', $page['search']);
        }

        $total = $builder->countAllResults(false);

        [$column, $direction] = Pagination::safeOrder($page['sort_by'], $page['sort_dir'], self::SORTABLE, 'created_at');

        $rows = $builder->orderBy($column, $direction)
            ->limit($page['limit'], $page['offset'])
            ->get()
            ->getResultArray();

        $ids     = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $totals  = $this->targetProducts->totalsForTargets($ids);
        $owners  = $this->resolveOwners($rows);

        $items = array_map(
            static function (array $row) use ($totals, $owners): array {
                $target  = TargetModel::cast($row);
                $rolled  = $totals[$target['id']] ?? ['product_count' => 0, 'total_units' => 0];

                $target['type_label']    = TargetModel::TYPES[$target['target_type']] ?? $target['target_type'];
                $target['target']        = $owners[$target['target_type']][$target['target_ref_id']] ?? null;
                $target['product_count'] = $rolled['product_count'];
                $target['total_units']   = $rolled['total_units'];

                return $target;
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
        $row = $this->targets->findRow($id);

        if ($row === null) {
            throw ApiException::notFound('Target not found');
        }

        $target = TargetModel::cast($row);
        $owners = $this->resolveOwners([$row]);
        $lines  = $this->targetProducts->forTargets([$id])[$id] ?? [];

        $target['type_label']    = TargetModel::TYPES[$target['target_type']] ?? $target['target_type'];
        $target['target']        = $owners[$target['target_type']][$target['target_ref_id']] ?? null;
        $target['products']      = $lines;
        $target['product_count'] = count($lines);
        $target['total_units']   = array_sum(array_column($lines, 'target_units'));

        return $target;
    }

    /**
     * @param array<string, mixed>                            $input
     * @param list<array{product_id: int, target_units: int}> $lines
     *
     * @return array<string, mixed>
     */
    public function create(array $input, array $lines, int $actorId): array
    {
        $type  = (string) $input['target_type'];
        $refId = (int) $input['target_ref_id'];
        $month = (int) $input['target_month'];
        $year  = (int) $input['target_year'];

        $this->assertOwnerUsable($type, $refId);
        $this->assertProductsUsable($lines);

        // Only a target that is still live reserves its month. A deactivated one
        // steps aside so the period can be planned again.
        if ((string) ($input['status'] ?? 'active') === 'active') {
            $this->assertPeriodFree($type, $refId, $month, $year);
        }

        $targetId = $this->transaction(function () use ($input, $type, $refId, $month, $year, $lines, $actorId): int {
            $id = (int) $this->targets->insert([
                'name'          => $input['name'],
                'target_type'   => $type,
                'target_ref_id' => $refId,
                'target_month'  => $month,
                'target_year'   => $year,
                'status'        => $input['status'] ?? 'active',
                'created_by'    => $actorId,
                'updated_by'    => $actorId,
            ]);

            $this->targetProducts->replaceForTarget($id, $lines, $actorId);

            return $id;
        });

        return $this->get($targetId);
    }

    /**
     * @param array<string, mixed>                                 $input
     * @param list<array{product_id: int, target_units: int}>|null $lines null leaves the product table alone
     *
     * @return array<string, mixed>
     */
    public function update(int $id, array $input, ?array $lines, int $actorId): array
    {
        $target = $this->targets->findRow($id);

        if ($target === null) {
            throw ApiException::notFound('Target not found');
        }

        // The owner and the period are settled together: changing any one of the
        // four has to be checked against the other three as they will end up.
        $type  = (string) ($input['target_type'] ?? $target['target_type']);
        $refId = (int) ($input['target_ref_id'] ?? $target['target_ref_id']);
        $month = (int) ($input['target_month'] ?? $target['target_month']);
        $year  = (int) ($input['target_year'] ?? $target['target_year']);

        $ownerChanged = $type !== $target['target_type'] || $refId !== (int) $target['target_ref_id'];

        if ($ownerChanged) {
            $this->assertOwnerUsable($type, $refId);
        }

        if ($lines !== null) {
            $this->assertProductsUsable($lines);
        }

        $status = (string) ($input['status'] ?? $target['status']);

        if ($status === 'active') {
            $this->assertPeriodFree($type, $refId, $month, $year, $id);
        }

        $this->transaction(function () use ($id, $input, $type, $refId, $month, $year, $lines, $actorId): void {
            $changes = [];

            if (array_key_exists('name', $input)) {
                $changes['name'] = $input['name'];
            }

            if (array_key_exists('target_type', $input)) {
                $changes['target_type'] = $type;
            }

            if (array_key_exists('target_ref_id', $input)) {
                $changes['target_ref_id'] = $refId;
            }

            if (array_key_exists('target_month', $input)) {
                $changes['target_month'] = $month;
            }

            if (array_key_exists('target_year', $input)) {
                $changes['target_year'] = $year;
            }

            if (array_key_exists('status', $input)) {
                $changes['status'] = $input['status'];
            }

            $changes['updated_by'] = $actorId;

            $this->targets->update($id, $changes);

            if ($lines !== null) {
                $this->targetProducts->replaceForTarget($id, $lines, $actorId);
            }
        });

        return $this->get($id);
    }

    /**
     * The product rows are kept, so a deactivated target can still be read back
     * and reported on.
     *
     * @return array<string, mixed>
     */
    public function deactivate(int $id, int $actorId): array
    {
        $target = $this->targets->findRow($id);

        if ($target === null) {
            throw ApiException::notFound('Target not found');
        }

        if ($target['status'] === 'inactive') {
            return $this->get($id);
        }

        $this->targets->update($id, ['status' => 'inactive', 'updated_by' => $actorId]);

        return $this->get($id);
    }

    // -----------------------------------------------------------------------
    // Rules
    // -----------------------------------------------------------------------

    private function assertTypeKnown(string $type, string $field = 'target_type'): void
    {
        if (! array_key_exists($type, TargetModel::TYPES)) {
            throw ApiException::unprocessable('Validation failed', [
                [
                    'field'   => $field,
                    'message' => $field . ' must be one of: ' . implode(', ', TargetModel::typeValues()),
                ],
            ]);
        }
    }

    /**
     * The chosen value has to exist, be active, and - for a sales person - sit
     * on a role that actually sells.
     */
    private function assertOwnerUsable(string $type, int $refId): void
    {
        $this->assertTypeKnown($type);

        $noun = TargetModel::noun($type);
        $row  = $this->resolveOwners([['target_type' => $type, 'target_ref_id' => $refId]])[$type][$refId] ?? null;

        if ($row === null) {
            throw ApiException::badRequest("The selected {$noun} does not exist");
        }

        if (($row['status'] ?? 'active') !== 'active') {
            throw ApiException::badRequest("The selected {$noun} is inactive");
        }

        if ($type === TargetModel::TYPE_SALES_PERSON
            && ! in_array((string) ($row['role_name'] ?? ''), Permissions::SALES_ROLES, true)) {
            throw ApiException::badRequest(
                'The selected user is not on a sales role (' . implode(', ', Permissions::SALES_ROLES) . ')',
            );
        }
    }

    private function assertPeriodFree(string $type, int $refId, int $month, int $year, ?int $exceptId = null): void
    {
        $clash = $this->targets->findActiveForPeriod($type, $refId, $month, $year, $exceptId);

        if ($clash !== null) {
            throw ApiException::conflict(sprintf(
                'An active target already covers this %s for %04d-%02d ("%s")',
                TargetModel::noun($type),
                $year,
                $month,
                $clash['name'],
            ));
        }
    }

    /**
     * @param list<array{product_id: int, target_units: int}> $lines
     */
    private function assertProductsUsable(array $lines): void
    {
        foreach ($lines as $index => $line) {
            if ($line['target_units'] > self::MAX_UNITS) {
                throw ApiException::unprocessable('Validation failed', [
                    [
                        'field'   => "products[{$index}].target_units",
                        'message' => 'target_units is too large',
                    ],
                ]);
            }
        }

        $productIds = array_values(array_unique(array_column($lines, 'product_id')));

        if ($productIds === []) {
            return;
        }

        $found = array_column(
            db_connect()->table('products')
                ->select('id, status')
                ->whereIn('id', $productIds)
                ->get()
                ->getResultArray(),
            'status',
            'id',
        );

        foreach ($productIds as $productId) {
            if (! isset($found[$productId])) {
                throw ApiException::badRequest("Product {$productId} does not exist");
            }

            if ($found[$productId] !== 'active') {
                throw ApiException::badRequest("Product {$productId} is inactive and cannot be given a target");
            }
        }
    }

    // -----------------------------------------------------------------------
    // Owner lookup
    // -----------------------------------------------------------------------

    /**
     * Resolves the owner behind every row in one query per type - three at
     * worst, whatever the page size.
     *
     * A row whose owner has since been hard deleted resolves to null rather than
     * failing: the target itself is still a real record.
     *
     * @param list<array<string, mixed>> $rows anything carrying target_type and target_ref_id
     *
     * @return array<string, array<int, array<string, mixed>>> [type][id] => owner
     */
    private function resolveOwners(array $rows): array
    {
        $idsByType = [];

        foreach ($rows as $row) {
            $idsByType[(string) $row['target_type']][] = (int) $row['target_ref_id'];
        }

        $resolved = [];

        foreach ($idsByType as $type => $ids) {
            $ids = array_values(array_unique($ids));

            $found = match ($type) {
                TargetModel::TYPE_REGION => db_connect()->table('regions')
                    ->select('id, name, status')
                    ->whereIn('id', $ids)
                    ->get()
                    ->getResultArray(),

                TargetModel::TYPE_DISTRIBUTOR => db_connect()->table('distributors')
                    ->select('id, name, code, status')
                    ->whereIn('id', $ids)
                    ->get()
                    ->getResultArray(),

                TargetModel::TYPE_SALES_PERSON => db_connect()->table('users u')
                    ->select('u.id, u.full_name AS name, u.email, u.status, r.name AS role_name')
                    ->join('roles r', 'r.id = u.role_id', 'left')
                    ->whereIn('u.id', $ids)
                    ->get()
                    ->getResultArray(),

                default => [],
            };

            foreach ($found as $row) {
                $row['id']   = (int) $row['id'];
                $row['type'] = $type;

                $resolved[$type][$row['id']] = $row;
            }
        }

        return $resolved;
    }
}
