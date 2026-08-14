<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The list-query contract shared by every collection endpoint:
 *
 *     ?page=1&limit=20&search=acme&status=active&sort_by=name&sort_dir=asc
 */
final class Pagination
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT     = 200;

    /**
     * Reads the standard list parameters, applying defaults and clamps. Values
     * that make no sense (limit=0, sort_dir=sideways) fall back rather than
     * raising an error, so a stale bookmark still returns a page.
     *
     * @param array<string, mixed> $query
     *
     * @return array{page: int, limit: int, offset: int, search: string|null, status: string|null, sort_by: string|null, sort_dir: string}
     */
    public static function fromQuery(array $query, int $defaultLimit = self::DEFAULT_LIMIT, int $maxLimit = self::MAX_LIMIT): array
    {
        $page  = max(1, (int) ($query['page'] ?? 1));
        $limit = (int) ($query['limit'] ?? $defaultLimit);
        $limit = $limit < 1 ? $defaultLimit : min($limit, $maxLimit);

        $search = trim((string) ($query['search'] ?? ''));
        $status = strtolower(trim((string) ($query['status'] ?? '')));
        $sortBy = trim((string) ($query['sort_by'] ?? ''));

        $sortDir = strtolower(trim((string) ($query['sort_dir'] ?? 'desc')));

        return [
            'page'     => $page,
            'limit'    => $limit,
            'offset'   => ($page - 1) * $limit,
            'search'   => $search === '' ? null : mb_substr($search, 0, 200),
            'status'   => in_array($status, ['active', 'inactive'], true) ? $status : null,
            'sort_by'  => $sortBy === '' ? null : $sortBy,
            'sort_dir' => $sortDir === 'asc' ? 'ASC' : 'DESC',
        ];
    }

    /**
     * @return array{page: int, limit: int, total: int, total_pages: int}
     */
    public static function meta(int $page, int $limit, int $total): array
    {
        return [
            'page'        => $page,
            'limit'       => $limit,
            'total'       => $total,
            'total_pages' => max(1, (int) ceil($total / max(1, $limit))),
        ];
    }

    /**
     * Only allow sorting by a column the caller is actually allowed to sort by -
     * the value lands in an ORDER BY clause.
     *
     * @param list<string> $allowed
     *
     * @return array{0: string, 1: string} [column, direction]
     */
    public static function safeOrder(?string $sortBy, string $sortDir, array $allowed, string $fallback): array
    {
        $column = ($sortBy !== null && in_array($sortBy, $allowed, true)) ? $sortBy : $fallback;

        return [$column, strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC'];
    }
}
