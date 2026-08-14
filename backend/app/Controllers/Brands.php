<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\BrandService;
use CodeIgniter\HTTP\ResponseInterface;

class Brands extends BaseApiController
{
    private BrandService $service;

    public function __construct()
    {
        $this->service = new BrandService();
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
                'name'   => ['required', 'min_length[2]', 'max_length[150]'],
                'code'   => ['permit_empty', 'max_length[50]'],
                'status' => ['permit_empty', 'in_list[active,inactive]'],
            ],
            ['name' => ['required' => 'Brand name is required', 'min_length' => 'Brand name is required']],
        );

        return $this->created($this->service->create($input, $this->actorId()), 'Brand created successfully');
    }

    public function update(string $id): ResponseInterface
    {
        $input = $this->validateBody([
            'name'   => ['permit_empty', 'min_length[2]', 'max_length[150]'],
            'code'   => ['permit_empty', 'max_length[50]'],
            'status' => ['permit_empty', 'in_list[active,inactive]'],
        ]);

        $this->requireAnyOf($input, ['name', 'code', 'status']);

        return $this->ok(
            $this->service->update($this->routeId($id), $input, $this->actorId()),
            'Brand updated successfully',
        );
    }

    public function delete(string $id): ResponseInterface
    {
        return $this->ok(
            $this->service->deactivate($this->routeId($id), $this->actorId()),
            'Brand deactivated successfully',
        );
    }
}
