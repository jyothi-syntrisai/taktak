<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The master permission list. `php spark db:seed InitialSeeder` inserts exactly
 * these rows into the `permissions` table, and the role screen reads them back
 * to draw its tick boxes. A permission slug is always `page_route:page_action`.
 */
final class Permissions
{
    public const ROLE_SUPER_ADMIN = 'SUPER_ADMIN';
    public const ROLE_ADMIN       = 'ADMIN';
    public const ROLE_RSM         = 'RSM';
    public const ROLE_SO          = 'SO';

    /** @var list<array{name: string, description: string}> */
    public const SYSTEM_ROLES = [
        ['name' => self::ROLE_SUPER_ADMIN, 'description' => 'Full access to everything, including roles and permissions'],
        ['name' => self::ROLE_ADMIN, 'description' => 'Manages masters, products and imports'],
        ['name' => self::ROLE_RSM, 'description' => 'Regional Sales Manager - works within one region'],
        ['name' => self::ROLE_SO, 'description' => 'Sales Officer - works within one state of one region'],
    ];

    /**
     * How far a user on a role reaches. A user is scoped by the role they sit
     * on: an RSM belongs to a region, an SO to one state inside that region, and
     * everybody else is unscoped.
     */
    public const SCOPE_NONE   = 'none';
    public const SCOPE_REGION = 'region';
    public const SCOPE_STATE  = 'state';

    /** @var array<string, string> role name => scope */
    private const ROLE_SCOPES = [
        self::ROLE_RSM => self::SCOPE_REGION,
        self::ROLE_SO  => self::SCOPE_STATE,
    ];

    /**
     * Roles whose users can carry a sales target of their own - the "Sales
     * Person" drop-down on the target screen lists exactly these. The retired
     * SALES_PERSON role is included because users still sitting on it are meant
     * to keep working until they are moved to RSM or SO.
     *
     * @var list<string>
     */
    public const SALES_ROLES = [self::ROLE_RSM, self::ROLE_SO, 'SALES_PERSON'];

    /** @var array<string, list<string>> page route => the actions available on it */
    public const PAGE_ACTIONS = [
        'users'        => ['view', 'create', 'edit', 'delete'],
        'roles'        => ['view', 'create', 'edit', 'delete', 'assign'],
        'regions'      => ['view', 'create', 'edit', 'delete'],
        'distributors' => ['view', 'create', 'edit', 'delete'],
        'brands'       => ['view', 'create', 'edit', 'delete'],
        'products'     => ['view', 'create', 'edit', 'delete', 'import'],
        'product_mrp'  => ['view', 'create', 'import'],
        'targets'      => ['view', 'create', 'edit', 'delete'],
        'imports'      => ['view'],
    ];

    /** @var array<string, string> */
    private const ACTION_LABELS = [
        'view'   => 'View',
        'create' => 'Create',
        'edit'   => 'Edit',
        'delete' => 'Delete',
        'assign' => 'Assign',
        'import' => 'Import',
    ];

    /** @var array<string, string> */
    private const PAGE_LABELS = [
        'users'        => 'Users',
        'roles'        => 'Roles & Permissions',
        'regions'      => 'Regions',
        'distributors' => 'Distributors',
        'brands'       => 'Brands',
        'products'     => 'Products',
        'product_mrp'  => 'Product MRP',
        'targets'      => 'Targets',
        'imports'      => 'CSV Imports',
    ];

    /**
     * Every permission as it is stored: slug, page route, page action, display name.
     *
     * @return list<array{slug: string, page_route: string, page_action: string, name: string}>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::PAGE_ACTIONS as $route => $actions) {
            foreach ($actions as $action) {
                $permissions[] = [
                    'slug'        => "{$route}:{$action}",
                    'page_route'  => $route,
                    'page_action' => $action,
                    'name'        => self::ACTION_LABELS[$action] . ' ' . self::PAGE_LABELS[$route],
                ];
            }
        }

        return $permissions;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_map(static fn (array $p): string => $p['slug'], self::all());
    }

    /**
     * The scope a role carries. Anything not listed - including roles an
     * administrator adds later - is unscoped.
     */
    public static function scopeFor(string $roleName): string
    {
        return self::ROLE_SCOPES[$roleName] ?? self::SCOPE_NONE;
    }

    /** True when a user on this role must be given a region. */
    public static function requiresRegion(string $roleName): bool
    {
        return self::scopeFor($roleName) !== self::SCOPE_NONE;
    }

    /** True when a user on this role must be given a state as well as a region. */
    public static function requiresState(string $roleName): bool
    {
        return self::scopeFor($roleName) === self::SCOPE_STATE;
    }

    /**
     * Starting access for the built-in roles, straight out of the "Default
     * Access For Each Role" table in the schema document. This is only the seed
     * state - it can be changed from the admin screen afterwards.
     *
     * The older SALES_PERSON role is no longer seeded. Any row that already
     * exists is left exactly as it is, so users still sitting on it keep working
     * until they are moved to RSM or SO.
     *
     * @return array<string, list<string>>
     */
    public static function defaultRolePermissions(): array
    {
        $readOnly = [
            'regions:view',
            'distributors:view',
            'brands:view',
            'products:view',
            'product_mrp:view',
            'targets:view',
            'imports:view',
        ];

        return [
            self::ROLE_SUPER_ADMIN => self::slugs(),

            self::ROLE_ADMIN => array_merge(
                ['users:view', 'users:create', 'users:edit'],
                self::expand('regions'),
                self::expand('distributors'),
                self::expand('brands'),
                self::expand('products'),
                self::expand('product_mrp'),
                self::expand('targets'),
                self::expand('imports'),
            ),

            self::ROLE_RSM => $readOnly,

            self::ROLE_SO => $readOnly,
        ];
    }

    /**
     * @return list<string>
     */
    private static function expand(string $route): array
    {
        return array_map(
            static fn (string $action): string => "{$route}:{$action}",
            self::PAGE_ACTIONS[$route] ?? [],
        );
    }
}
