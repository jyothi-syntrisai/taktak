<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ApiException;
use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

class Auth extends BaseApiController
{
    private AuthService $service;

    public function __construct()
    {
        $this->service = new AuthService();
    }

    public function login(): ResponseInterface
    {
        $input = $this->validateBody(
            [
                'email'    => ['required', 'valid_email', 'max_length[255]'],
                'password' => ['required'],
            ],
            [
                'email'    => ['required' => 'A valid email address is required', 'valid_email' => 'A valid email address is required'],
                'password' => ['required' => 'Password is required'],
            ],
        );

        $result = $this->service->login(
            strtolower((string) $input['email']),
            (string) $input['password'],
        );

        return $this->ok($result, 'Logged in successfully');
    }

    public function logout(): ResponseInterface
    {
        $this->service->logout($this->actorId());

        return $this->noContentOk('Logged out successfully');
    }

    public function me(): ResponseInterface
    {
        return $this->ok($this->service->profile($this->actorId()));
    }

    public function changePassword(): ResponseInterface
    {
        $input = $this->validateBody(
            [
                'old_password' => ['required'],
                'new_password' => ['required', 'min_length[8]', 'max_length[100]', 'regex_match[/[A-Za-z]/]', 'regex_match[/[0-9]/]'],
            ],
            [
                'old_password' => ['required' => 'Current password is required'],
                'new_password' => [
                    'required'   => 'New password is required',
                    'min_length' => 'New password must be at least 8 characters',
                    'regex_match' => 'New password must contain a letter and a number',
                ],
            ],
        );

        if ($input['old_password'] === $input['new_password']) {
            throw ApiException::unprocessable('Validation failed', [
                ['field' => 'new_password', 'message' => 'New password must be different from the current password'],
            ]);
        }

        $this->service->changePassword(
            $this->actorId(),
            (string) $input['old_password'],
            (string) $input['new_password'],
        );

        return $this->noContentOk('Password changed.');
    }
}
