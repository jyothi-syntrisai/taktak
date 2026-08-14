<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ApiException;
use App\Services\ImportService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;

class Imports extends BaseApiController
{
    private ImportService $service;

    public function __construct()
    {
        $this->service = new ImportService();
    }

    public function index(): ResponseInterface
    {
        $result = $this->service->listBatches($this->queryParams());

        return $this->paginated($result['items'], $result['meta']);
    }

    public function show(string $id): ResponseInterface
    {
        return $this->ok($this->service->getBatch($this->routeId($id)));
    }

    /** Every staged row of a batch, including the ones that failed and why. */
    public function rows(string $id): ResponseInterface
    {
        $result = $this->service->listBatchRows($this->routeId($id), $this->queryParams());

        return $this->paginated($result['items'], $result['meta']);
    }

    public function template(): ResponseInterface
    {
        $module = (string) ($this->queryParams()['module'] ?? 'products');

        if (! in_array($module, ImportService::MODULES, true)) {
            throw ApiException::unprocessable('Validation failed', [
                ['field' => 'module', 'message' => 'module must be one of: ' . implode(', ', ImportService::MODULES)],
            ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setContentType('text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', "attachment; filename=\"{$module}_import_template.csv\"")
            ->setBody($this->service->csvTemplate($module));
    }

    public function importProducts(): ResponseInterface
    {
        return $this->runImport('products');
    }

    public function importMrp(): ResponseInterface
    {
        return $this->runImport('product_mrp');
    }

    // -----------------------------------------------------------------------

    private function runImport(string $module): ResponseInterface
    {
        $file = $this->csvFile();

        // Multipart fields arrive as strings, so the options are read here
        // rather than through the JSON body helper.
        $options = [
            'effective_from'        => $this->effectiveFromOption(),
            'create_missing_brands' => in_array(
                strtolower((string) ($this->request->getPost('create_missing_brands') ?? '')),
                ['1', 'true', 'yes', 'on'],
                true,
            ),
        ];

        $batch = $this->service->run($file, $module, $options, $this->actorId());

        return $this->created(
            $batch,
            "Import finished: {$batch['success_records']} of {$batch['total_records']} rows applied",
        );
    }

    private function csvFile(): UploadedFile
    {
        $file = $this->request->getFile('file');

        if ($file === null) {
            throw ApiException::badRequest('No CSV file was uploaded (field name must be "file")');
        }

        if (! $file->isValid()) {
            throw ApiException::badRequest($file->getErrorString());
        }

        if (strtolower($file->getClientExtension()) !== 'csv') {
            throw ApiException::badRequest('Only .csv files are accepted');
        }

        $maxBytes = config('Taktak')->maxUploadSizeMb * 1024 * 1024;

        if ($file->getSize() > $maxBytes) {
            throw ApiException::badRequest('Uploaded file is too large');
        }

        return $file;
    }

    /**
     * `effective_from` is a batch-level setting because the staging table has no
     * per-row date column - it carries brand_name, sku, product_name and mrp only.
     */
    private function effectiveFromOption(): ?string
    {
        $value = $this->request->getPost('effective_from');

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);

        if (! self::isIsoDate($value)) {
            throw ApiException::unprocessable('Validation failed', [
                ['field' => 'effective_from', 'message' => 'Date must be in YYYY-MM-DD format'],
            ]);
        }

        return $value;
    }
}
