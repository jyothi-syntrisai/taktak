<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\ApiException;

/**
 * Holds the caller identity for the current request.
 *
 * The auth filter fills this in from the access token; controllers and services
 * read it back through `service('auth')`. Request objects cannot carry a `user`
 * property of their own on PHP 8.2+, so a request-scoped service is used.
 */
class Auth
{
    public const ROLE_SUPER_ADMIN = 'SUPER_ADMIN';

    /** @var array{id: int, email: string, role: string, role_id: int, permissions: list<string>}|null */
    private ?array $user = null;

    /**
     * @param array{id: int, email: string, role: string, role_id: int, permissions: list<string>} $user
     */
    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    /**
     * @return array{id: int, email: string, role: string, role_id: int, permissions: list<string>}|null
     */
    public function user(): ?array
    {
        return $this->user;
    }

    public function check(): bool
    {
        return $this->user !== null;
    }

    /** The signed-in user's id, or a 401 if the route was reached unauthenticated. */
    public function id(): int
    {
        if ($this->user === null) {
            throw ApiException::unauthorized();
        }

        return $this->user['id'];
    }

    public function role(): string
    {
        return $this->user['role'] ?? '';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role() === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Super Admin passes everything, so a newly added permission never locks
     * the owner out of their own system.
     */
    public function can(string $slug): bool
    {
        if ($this->user === null) {
            return false;
        }

        return $this->isSuperAdmin() || in_array($slug, $this->user['permissions'], true);
    }
}
