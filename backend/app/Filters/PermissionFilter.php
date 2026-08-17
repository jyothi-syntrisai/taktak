<?php

declare(strict_types=1);

namespace App\Filters;

use App\Exceptions\ApiException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Gates a route on one or more `page_route:page_action` permissions.
 *
 * Filter arguments are comma separated and a colon already separates the alias
 * from its arguments, so a route writes the slug with a comma:
 *
 *     ['filter' => 'permission:users,view']   ->   users:view
 *
 * Permissions belong to the role, not the user - the access token carries the
 * flattened list resolved at login, so a role change takes effect the next
 * time the user signs in.
 */
class PermissionFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');

        if (! $auth->check()) {
            throw ApiException::unauthorized();
        }

        if ($auth->isSuperAdmin()) {
            return null;
        }

        // ['users', 'view'] -> 'users:view'; ['users:view'] is accepted too.
        $slugs = $this->toSlugs($arguments ?? []);

        $missing = array_values(array_filter($slugs, static fn (string $slug): bool => ! $auth->can($slug)));

        if ($missing !== []) {
            throw ApiException::forbidden('Missing required permission: ' . implode(', ', $missing));
        }

        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * @param list<string> $arguments
     *
     * @return list<string>
     */
    private function toSlugs(array $arguments): array
    {
        $slugs = [];
        $pair  = [];

        foreach ($arguments as $argument) {
            if (str_contains($argument, ':')) {
                $slugs[] = $argument;

                continue;
            }

            $pair[] = $argument;

            if (count($pair) === 2) {
                $slugs[] = implode(':', $pair);
                $pair    = [];
            }
        }

        return $slugs;
    }
}
