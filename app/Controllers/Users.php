<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserService;
use CodeIgniter\HTTP\ResponseInterface;

class Users extends BaseApiController
{
    private const PASSWORD_RULES = ['required', 'min_length[8]', 'max_length[100]', 'regex_match[/[A-Za-z]/]', 'regex_match[/[0-9]/]'];

    private const PASSWORD_MESSAGES = [
        'required'    => 'Password is required',
        'min_length'  => 'Password must be at least 8 characters',
        'regex_match' => 'Password must contain a letter and a number',
    ];

    private UserService $service;

    public function __construct()
    {
        $this->service = new UserService();
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
                'full_name' => ['required', 'min_length[2]', 'max_length[150]'],
                'email'     => ['required', 'valid_email', 'max_length[255]'],
                'password'  => self::PASSWORD_RULES,
                'role_id'   => ['required', 'is_natural_no_zero'],
                // Required or refused depending on the role - an RSM needs a
                // region, an SO a region and a state, anyone else neither. The
                // service settles that once it knows which role was picked.
                'region_id' => ['permit_empty', 'is_natural_no_zero'],
                'state_id'  => ['permit_empty', 'is_natural_no_zero'],
                'status'    => ['permit_empty', 'in_list[active,inactive]'],
            ],
            [
                'full_name' => ['required' => 'Full name is required', 'min_length' => 'Full name is required'],
                'email'     => ['required' => 'A valid email address is required', 'valid_email' => 'A valid email address is required'],
                'password'  => self::PASSWORD_MESSAGES,
                'role_id'   => ['required' => 'A role must be selected', 'is_natural_no_zero' => 'A role must be selected'],
                'region_id' => ['is_natural_no_zero' => 'A valid region must be selected'],
                'state_id'  => ['is_natural_no_zero' => 'A valid state must be selected'],
            ],
        );

        return $this->created($this->service->create($input, $this->actorId()), 'User created successfully');
    }

    public function update(string $id): ResponseInterface
    {
        $input = $this->validateBody(
            [
                'full_name' => ['permit_empty', 'min_length[2]', 'max_length[150]'],
                'email'     => ['permit_empty', 'valid_email', 'max_length[255]'],
                'role_id'   => ['permit_empty', 'is_natural_no_zero'],
                'region_id' => ['permit_empty', 'is_natural_no_zero'],
                'state_id'  => ['permit_empty', 'is_natural_no_zero'],
                'status'    => ['permit_empty', 'in_list[active,inactive]'],
            ],
            [
                'region_id' => ['is_natural_no_zero' => 'A valid region must be selected'],
                'state_id'  => ['is_natural_no_zero' => 'A valid state must be selected'],
            ],
        );

        $this->requireAnyOf($input, ['full_name', 'email', 'role_id', 'region_id', 'state_id', 'status']);

        return $this->ok(
            $this->service->update($this->routeId($id), $input, $this->actorId()),
            'User updated successfully',
        );
    }

    public function activate(string $id): ResponseInterface
    {
        return $this->ok(
            $this->service->activate($this->routeId($id), $this->actorId()),
            'User activated successfully',
        );
    }

    public function resetPassword(string $id): ResponseInterface
    {
        $input = $this->validateBody(
            ['new_password' => self::PASSWORD_RULES],
            ['new_password' => self::PASSWORD_MESSAGES],
        );

        $this->service->resetPassword($this->routeId($id), (string) $input['new_password'], $this->actorId());

        return $this->noContentOk('Password reset successfully');
    }

    public function delete(string $id): ResponseInterface
    {
        return $this->ok(
            $this->service->deactivate($this->routeId($id), $this->actorId()),
            'User deactivated successfully',
        );
    }
}
