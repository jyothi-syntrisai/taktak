<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\RegionService;
use CodeIgniter\HTTP\ResponseInterface;

class Regions extends BaseApiController
{
    private RegionService $service;

    public function __construct()
    {
        $this->service = new RegionService();
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
                'name'   => ['required', 'min_length[2]', 'max_length[100]'],
                'status' => ['permit_empty', 'in_list[active,inactive]'],
            ],
            ['name' => ['required' => 'Region name is required', 'min_length' => 'Region name is required']],
        );

        $stateIds = $this->idList($this->body(), 'state_ids', true, 1) ?? [];

        return $this->created(
            $this->service->create($input, $stateIds, $this->actorId()),
            'Region created successfully',
        );
    }

    public function update(string $id): ResponseInterface
    {
        $input = $this->validateBody([
            'name'   => ['permit_empty', 'min_length[2]', 'max_length[100]'],
            'status' => ['permit_empty', 'in_list[active,inactive]'],
        ]);

        $body     = $this->body();
        $stateIds = $this->idList($body, 'state_ids', false, 1);

        if ($stateIds === null) {
            $this->requireAnyOf($input, ['name', 'status']);
        }

        return $this->ok(
            $this->service->update($this->routeId($id), $input, $stateIds, $this->actorId()),
            'Region updated successfully',
        );
    }

    public function delete(string $id): ResponseInterface
    {
        return $this->ok(
            $this->service->deactivate($this->routeId($id), $this->actorId()),
            'Region deactivated successfully',
        );
    }
}
