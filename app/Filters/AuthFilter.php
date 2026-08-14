<?php

declare(strict_types=1);

namespace App\Filters;

use App\Exceptions\ApiException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Verifies the `Authorization: Bearer <access token>` header and publishes the
 * caller identity on the `auth` service for the rest of the request.
 */
class AuthFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if ($header === '' || stripos($header, 'Bearer ') !== 0) {
            throw ApiException::unauthorized('Missing or malformed Authorization header');
        }

        $payload = service('jwt')->verifyAccessToken(trim(substr($header, 7)));

        service('auth')->setUser([
            'id'          => (int) ($payload['sub'] ?? 0),
            'email'       => (string) ($payload['email'] ?? ''),
            'role'        => (string) ($payload['role'] ?? ''),
            'role_id'     => (int) ($payload['role_id'] ?? 0),
            'permissions' => array_values((array) ($payload['permissions'] ?? [])),
        ]);

        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
