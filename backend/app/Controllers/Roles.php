<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\RoleService;
use CodeIgniter\HTTP\ResponseInterface;

class Roles extends BaseApiController
{
    private RoleService $service;

    public function __construct()
    {
        $this->service = new RoleService();
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
                'name'        => ['required', 'min_length[2]', 'max_length[50]', 'regex_match[/^[A-Za-z0-9_ -]+$/]'],
                'description' => ['permit_empty', 'max_length[255]'],
                'status'      => ['permit_empty', 'in_list[active,inactive]'],
            ],
            [
                'name' => [
                    'required'    => 'Role name is required',
                    'min_length'  => 'Role name is required',
                    'regex_match' => 'Role name may only contain letters, numbers, spaces, _ and -',
                ],
            ],
        );

        $permissionIds = $this->idList($this->body(), 'permission_ids', false);

        return $this->created(
            $this->service->create($input, $permissionIds, $this->actorId()),
            'Role created successfully',
        );
    }

    public function update(string $id): ResponseInterface
    {
        $input = $this->validateBody([
            'name'        => ['permit_empty', 'min_length[2]', 'max_length[50]', 'regex_match[/^[A-Za-z0-9_ -]+$/]'],
            'description' => ['permit_empty', 'max_length[255]'],
            'status'      => ['permit_empty', 'in_list[active,inactive]'],
        ]);

        $this->requireAnyOf($input, ['name', 'description', 'status']);

        return $this->ok(
            $this->service->update($this->routeId($id), $input, $this->actorId()),
            'Role updated successfully',
        );
    }

    /** The screen sends the full tick-box state, so the whole set is replaced. */
    public function assignPermissions(string $id): ResponseInterface
    {
        $permissionIds = $this->idList($this->body(), 'permission_ids', true) ?? [];

        return $this->ok(
            $this->service->assignPermissions($this->routeId($id), $permissionIds, $this->actorId()),
            'Permissions updated successfully',
        );
    }

    public function delete(string $id): ResponseInterface
    {
        return $this->ok(
            $this->service->deactivate($this->routeId($id), $this->actorId()),
            'Role deactivated successfully',
        );
    }
}
