<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DistributorService;
use CodeIgniter\HTTP\ResponseInterface;

class Distributors extends BaseApiController
{
    private DistributorService $service;

    public function __construct()
    {
        $this->service = new DistributorService();
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
                'name'   => ['required', 'min_length[2]', 'max_length[200]'],
                'code'   => ['permit_empty', 'max_length[50]'],
                'status' => ['permit_empty', 'in_list[active,inactive]'],
            ],
            ['name' => ['required' => 'Distributor name is required', 'min_length' => 'Distributor name is required']],
        );

        $regionIds = $this->idList($this->body(), 'region_ids', true, 1) ?? [];

        return $this->created(
            $this->service->create($input, $regionIds, $this->actorId()),
            'Distributor created successfully',
        );
    }

    public function update(string $id): ResponseInterface
    {
        $input = $this->validateBody([
            'name'   => ['permit_empty', 'min_length[2]', 'max_length[200]'],
            'code'   => ['permit_empty', 'max_length[50]'],
            'status' => ['permit_empty', 'in_list[active,inactive]'],
        ]);

        $regionIds = $this->idList($this->body(), 'region_ids', false, 1);

        if ($regionIds === null) {
            $this->requireAnyOf($input, ['name', 'code', 'status']);
        }

        return $this->ok(
            $this->service->update($this->routeId($id), $input, $regionIds, $this->actorId()),
            'Distributor updated successfully',
        );
    }

    public function delete(string $id): ResponseInterface
    {
        return $this->ok(
            $this->service->deactivate($this->routeId($id), $this->actorId()),
            'Distributor deactivated successfully',
        );
    }
}
