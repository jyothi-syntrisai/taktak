<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\BrandModel;
use App\Models\ImportBatchModel;
use App\Models\ProductImportStagingModel;
use App\Models\ProductModel;
use App\Models\ProductMrpModel;
use App\Support\HandlesTransactions;
use App\Support\Pagination;
use CodeIgniter\HTTP\Files\UploadedFile;
use League\Csv\Reader;
use RuntimeException;
use Throwable;

/**
 * The full import pipeline from the schema document:
 *
 *   CSV file -> product_import_staging -> check each row -> products / product_mrp
 *
 * Every CSV line lands in staging first, as plain text, because a bad file may
 * carry words where a number was expected. Rows that fail a check stay in
 * staging with the reason in `error_message`, so the user can be shown exactly
 * which line failed and why.
 */
class ImportService
{
    use HandlesTransactions;

    public const MODULES = ['products', 'product_mrp'];

    /** Header aliases, so a file exported from Excel does not have to match exactly. */
    private const HEADER_MAP = [
        'brand'        => 'brand_name',
        'brand_name'   => 'brand_name',
        'brand name'   => 'brand_name',
        'sku'          => 'sku',
        'sku code'     => 'sku',
        'product'      => 'product_name',
        'product_name' => 'product_name',
        'product name' => 'product_name',
        'mrp'          => 'mrp',
        'price'        => 'mrp',
        'mrp price'    => 'mrp',
    ];

    private ImportBatchModel $batches;
    private ProductImportStagingModel $staging;
    private ProductModel $products;
    private ProductMrpModel $mrps;
    private BrandModel $brands;
    private MrpService $mrpService;

    public function __construct()
    {
        $this->batches    = model(ImportBatchModel::class);
        $this->staging    = model(ProductImportStagingModel::class);
        $this->products   = model(ProductModel::class);
        $this->mrps       = model(ProductMrpModel::class);
        $this->brands     = model(BrandModel::class);
        $this->mrpService = new MrpService();
    }

    /**
     * @param array{effective_from?: string|null, create_missing_brands?: bool} $options
     *
     * @return array<string, mixed> the finished batch
     */
    public function run(UploadedFile $file, string $module, array $options, int $actorId): array
    {
        $config    = config('Taktak');
        $originalName = $file->getClientName();

        $batchId = (int) $this->batches->insert([
            'file_name'  => mb_substr($originalName, 0, 255),
            'module'     => $module,
            'status'     => 'processing',
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        // Move the upload out of the PHP temp directory so the parser can stream
        // it, then always remove it again.
        $storedName = date('Y-m-d_His') . '__' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        $file->move($config->uploadDir, $storedName, true);
        $path = rtrim($config->uploadDir, '/\\') . DIRECTORY_SEPARATOR . $file->getName();

        try {
            $rows = $this->readCsv($path, $config->maxImportRows);

            if ($rows === []) {
                $this->batches->update($batchId, ['status' => 'failed', 'updated_by' => $actorId]);

                throw ApiException::badRequest('The uploaded file has no data rows');
            }

            // Step 1 - land every row in staging, untouched.
            $this->staging->insertBatch(array_map(
                static fn (array $row, int $index): array => [
                    'import_batch_id' => $batchId,
                    // +2: row 1 is the header, and users count from 1
                    'row_number'      => $index + 2,
                    'brand_name'      => $row['brand_name'],
                    'sku'             => $row['sku'],
                    'product_name'    => $row['product_name'],
                    'mrp'             => $row['mrp'],
                    'status'          => 'pending',
                    'created_by'      => $actorId,
                    'updated_by'      => $actorId,
                ],
                $rows,
                array_keys($rows),
            ));

            $this->batches->update($batchId, ['total_records' => count($rows), 'updated_by' => $actorId]);

            // Steps 2 and 3 - check each staged row, then promote the good ones.
            ['success' => $success, 'failed' => $failed] = $this->processStagedRows($batchId, $module, $options, $actorId);

            $this->batches->update($batchId, [
                'success_records' => $success,
                'failed_records'  => $failed,
                'status'          => $failed === count($rows) ? 'failed' : 'completed',
                'updated_by'      => $actorId,
            ]);

            return $this->getBatch($batchId);
        } catch (Throwable $e) {
            $this->batches->update($batchId, ['status' => 'failed', 'updated_by' => $actorId]);

            throw $e;
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function listBatches(array $query): array
    {
        $page    = Pagination::fromQuery($query);
        $builder = db_connect()->table('import_batches');

        if (isset($query['module']) && in_array($query['module'], self::MODULES, true)) {
            $builder->where('module', $query['module']);
        }

        if (isset($query['batch_status']) && in_array($query['batch_status'], ['pending', 'processing', 'completed', 'failed'], true)) {
            $builder->where('status', $query['batch_status']);
        }

        if ($page['search'] !== null) {
            $builder->like('file_name', $page['search']);
        }

        $total = $builder->countAllResults(false);

        $rows = $builder->orderBy('id', 'DESC')
            ->limit($page['limit'], $page['offset'])
            ->get()
            ->getResultArray();

        return [
            'items' => array_map(ImportBatchModel::cast(...), $rows),
            'meta'  => Pagination::meta($page['page'], $page['limit'], $total),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getBatch(int $id): array
    {
        $batch = $this->batches->findRow($id);

        if ($batch === null) {
            throw ApiException::notFound('Import batch not found');
        }

        return ImportBatchModel::cast($batch);
    }

    /**
     * Every staged row of a batch, including the ones that failed and why.
     *
     * @param array<string, mixed> $query
     *
     * @return array{items: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function listBatchRows(int $batchId, array $query): array
    {
        $this->getBatch($batchId);

        $page    = Pagination::fromQuery($query);
        $builder = db_connect()->table('product_import_staging')->where('import_batch_id', $batchId);

        if (isset($query['row_status']) && in_array($query['row_status'], ['pending', 'valid', 'error', 'processed'], true)) {
            $builder->where('status', $query['row_status']);
        }

        $total = $builder->countAllResults(false);

        $rows = $builder->orderBy('row_number', 'ASC')
            ->limit($page['limit'], $page['offset'])
            ->get()
            ->getResultArray();

        return [
            'items' => array_map(ProductImportStagingModel::cast(...), $rows),
            'meta'  => Pagination::meta($page['page'], $page['limit'], $total),
        ];
    }

    public function csvTemplate(string $module): string
    {
        return $module === 'products'
            ? "brand_name,sku,product_name,mrp\nAcme,SKU-001,Acme Widget 500g,120.00\nAcme,SKU-002,Acme Widget 1kg,220.00\n"
            : "sku,mrp\nSKU-001,130.00\nSKU-002,240.00\n";
    }

    // -----------------------------------------------------------------------
    // Parsing
    // -----------------------------------------------------------------------

    /**
     * @return list<array{brand_name: string|null, sku: string|null, product_name: string|null, mrp: string|null}>
     */
    private function readCsv(string $path, int $maxRows): array
    {
        $reader = Reader::createFromPath($path, 'r');
        $reader->setHeaderOffset(0);

        $headers = array_map(
            static fn (string $header): string => self::HEADER_MAP[self::normaliseHeader($header)] ?? self::normaliseHeader($header),
            $reader->getHeader(),
        );

        $rows = [];

        foreach ($reader->getRecords($headers) as $record) {
            $row = [
                'brand_name'   => self::cell($record['brand_name'] ?? null),
                'sku'          => self::cell($record['sku'] ?? null),
                'product_name' => self::cell($record['product_name'] ?? null),
                'mrp'          => self::cell($record['mrp'] ?? null),
            ];

            // Skip a line that is entirely blank - trailing newlines are common.
            if ($row === ['brand_name' => null, 'sku' => null, 'product_name' => null, 'mrp' => null]) {
                continue;
            }

            $rows[] = $row;

            if (count($rows) > $maxRows) {
                throw ApiException::badRequest("This file has more than {$maxRows} rows. Please split it.");
            }
        }

        return $rows;
    }

    private static function normaliseHeader(string $header): string
    {
        return strtolower(trim(str_replace("\u{FEFF}", '', $header)));
    }

    private static function cell(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function parseMrp(string $value): float
    {
        return (float) str_replace([',', ' '], '', $value);
    }

    // -----------------------------------------------------------------------
    // Checking and promoting
    // -----------------------------------------------------------------------

    /**
     * @param array{effective_from?: string|null, create_missing_brands?: bool} $options
     *
     * @return array{success: int, failed: int}
     */
    private function processStagedRows(int $batchId, string $module, array $options, int $actorId): array
    {
        $effectiveFrom = $options['effective_from'] ?? MrpService::today();
        $staged        = $this->staging->pendingRows($batchId);

        // Catch duplicates inside the file itself, which no per-row check sees.
        $seenSkus = [];

        $success = 0;
        $failed  = 0;

        foreach ($staged as $row) {
            $error = $this->validateRow($row, $module, $seenSkus);

            if ($error !== null) {
                $this->staging->update((int) $row['id'], [
                    'status'        => 'error',
                    'error_message' => mb_substr($error, 0, 500),
                    'updated_by'    => $actorId,
                ]);
                $failed++;

                continue;
            }

            $seenSkus[strtolower((string) $row['sku'])] = (int) $row['row_number'];

            try {
                // One transaction per row: a single bad row must not roll back
                // the whole file.
                $this->transaction(function () use ($row, $module, $options, $effectiveFrom, $actorId): void {
                    if ($module === 'products') {
                        $this->promoteProductRow($row, $options, $effectiveFrom, $actorId);
                    } else {
                        $this->promoteMrpRow($row, $effectiveFrom, $actorId);
                    }
                });

                $this->staging->update((int) $row['id'], [
                    'status'        => 'processed',
                    'error_message' => null,
                    'updated_by'    => $actorId,
                ]);
                $success++;
            } catch (Throwable $e) {
                $this->staging->update((int) $row['id'], [
                    'status'        => 'error',
                    'error_message' => mb_substr($e->getMessage(), 0, 500),
                    'updated_by'    => $actorId,
                ]);
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, int>   $seenSkus
     */
    private function validateRow(array $row, string $module, array $seenSkus): ?string
    {
        $sku = $row['sku'];

        if ($sku === null) {
            return 'SKU is missing';
        }

        if (mb_strlen($sku) > 100) {
            return 'SKU is longer than 100 characters';
        }

        $duplicateAt = $seenSkus[strtolower($sku)] ?? null;

        if ($duplicateAt !== null) {
            return "SKU \"{$sku}\" already appeared on row {$duplicateAt} of this file";
        }

        if ($module === 'products') {
            if ($row['product_name'] === null) {
                return 'Product name is missing';
            }

            if (mb_strlen($row['product_name']) > 255) {
                return 'Product name is longer than 255 characters';
            }

            if ($row['brand_name'] === null) {
                return 'Brand name is missing';
            }

            if (mb_strlen($row['brand_name']) > 150) {
                return 'Brand name is longer than 150 characters';
            }
        }

        if ($row['mrp'] !== null) {
            $cleaned = str_replace([',', ' '], '', (string) $row['mrp']);

            if (! is_numeric($cleaned)) {
                return "MRP \"{$row['mrp']}\" is not a number";
            }

            $value = (float) $cleaned;

            if ($value <= 0) {
                return 'MRP must be greater than zero';
            }

            if ($value > 9999999999) {
                return 'MRP is too large';
            }
        } elseif ($module === 'product_mrp') {
            return 'MRP is missing';
        }

        if ($module === 'product_mrp' && $this->products->findBySku($sku) === null) {
            return "No product exists with SKU \"{$sku}\"";
        }

        return null;
    }

    /**
     * @param array<string, mixed>                                              $row
     * @param array{effective_from?: string|null, create_missing_brands?: bool} $options
     */
    private function promoteProductRow(array $row, array $options, string $effectiveFrom, int $actorId): void
    {
        $brand = $this->brands->findByName((string) $row['brand_name']);

        if ($brand === null) {
            if (($options['create_missing_brands'] ?? false) !== true) {
                throw new RuntimeException(
                    "Brand \"{$row['brand_name']}\" does not exist. Create it first, or re-upload with create_missing_brands=true.",
                );
            }

            $brandId = (int) $this->brands->insert([
                'name'       => $row['brand_name'],
                'code'       => null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        } elseif ($brand['status'] !== 'active') {
            throw new RuntimeException("Brand \"{$row['brand_name']}\" is inactive");
        } else {
            $brandId = (int) $brand['id'];
        }

        $existing = $this->products->findBySku((string) $row['sku']);

        if ($existing !== null) {
            $productId = (int) $existing['id'];
            $this->products->update($productId, [
                'brand_id'     => $brandId,
                'product_name' => $row['product_name'],
                'updated_by'   => $actorId,
            ]);
        } else {
            $productId = (int) $this->products->insert([
                'brand_id'     => $brandId,
                'sku'          => $row['sku'],
                'product_name' => $row['product_name'],
                'created_by'   => $actorId,
                'updated_by'   => $actorId,
            ]);
        }

        if ($row['mrp'] === null) {
            return;
        }

        $newMrp  = self::parseMrp((string) $row['mrp']);
        $current = $this->mrps->currentFor($productId);

        // Re-importing an unchanged price should not spawn a duplicate history row.
        if ($current !== null && (float) $current['mrp'] === $newMrp) {
            return;
        }

        $this->mrpService->setMrp($productId, $newMrp, $effectiveFrom, $actorId);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function promoteMrpRow(array $row, string $effectiveFrom, int $actorId): void
    {
        $product = $this->products->findBySku((string) $row['sku']);

        if ($product === null) {
            throw new RuntimeException("No product exists with SKU \"{$row['sku']}\"");
        }

        $productId = (int) $product['id'];
        $newMrp    = self::parseMrp((string) $row['mrp']);
        $current   = $this->mrps->currentFor($productId);

        if ($current !== null && (float) $current['mrp'] === $newMrp) {
            throw new RuntimeException("MRP is already {$newMrp} for SKU \"{$row['sku']}\" - nothing to change");
        }

        $this->mrpService->setMrp($productId, $newMrp, $effectiveFrom, $actorId);
    }
}
