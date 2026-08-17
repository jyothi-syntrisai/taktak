<?php

declare(strict_types=1);

/**
 * The OpenAPI 3.0 description of the Tak Tak API.
 *
 * Returned as JSON by GET /api/v1/docs/openapi.json and rendered by the Swagger
 * UI page at GET /api/v1/docs. Keeping it as PHP (rather than a static .json)
 * lets the server URL follow the deployment and lets the repeated pieces -
 * pagination parameters, error responses - be written once.
 */

$prefix = '/' . trim(config('Taktak')->apiPrefix, '/');

// --- reusable fragments ------------------------------------------------------

/** @var callable(string): array<string, mixed> $ref */
$ref = static fn (string $name): array => ['$ref' => "#/components/schemas/{$name}"];

/** @var callable(string, string, string=): array<string, mixed> $queryParam */
$queryParam = static fn (string $name, string $description, string $type = 'string', mixed $extra = null): array => array_merge([
    'name'        => $name,
    'in'          => 'query',
    'required'    => false,
    'description' => $description,
    'schema'      => array_merge(['type' => $type], is_array($extra) ? $extra : []),
], []);

$listParams = [
    $queryParam('page', 'Page number, from 1.', 'integer', ['minimum' => 1, 'default' => 1]),
    $queryParam('limit', 'Rows per page.', 'integer', ['minimum' => 1, 'maximum' => 200, 'default' => 20]),
    $queryParam('search', 'Free-text filter. Which columns it covers is noted per endpoint.'),
    $queryParam('status', 'Restrict to active or inactive rows.', 'string', ['enum' => ['active', 'inactive']]),
    $queryParam('sort_by', 'Column to sort by. Unknown columns fall back to the endpoint default.'),
    $queryParam('sort_dir', 'Sort direction.', 'string', ['enum' => ['asc', 'desc'], 'default' => 'desc']),
];

$idParam = [
    'name'     => 'id',
    'in'       => 'path',
    'required' => true,
    'schema'   => ['type' => 'integer', 'minimum' => 1],
];

/** @var callable(string, array<string, mixed>): array<string, mixed> $okResponse */
$okResponse = static fn (string $description, array $dataSchema): array => [
    'description' => $description,
    'content'     => [
        'application/json' => [
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data'    => $dataSchema,
                ],
            ],
        ],
    ],
];

/** @var callable(string, array<string, mixed>): array<string, mixed> $pagedResponse */
$pagedResponse = static fn (string $description, array $itemSchema): array => [
    'description' => $description,
    'content'     => [
        'application/json' => [
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => true],
                    'message' => ['type' => 'string'],
                    'data'    => ['type' => 'array', 'items' => $itemSchema],
                    'meta'    => ['$ref' => '#/components/schemas/PageMeta'],
                ],
            ],
        ],
    ],
];

$errorResponses = [
    '400' => ['$ref' => '#/components/responses/BadRequest'],
    '401' => ['$ref' => '#/components/responses/Unauthorized'],
    '403' => ['$ref' => '#/components/responses/Forbidden'],
    '404' => ['$ref' => '#/components/responses/NotFound'],
    '409' => ['$ref' => '#/components/responses/Conflict'],
    '422' => ['$ref' => '#/components/responses/Unprocessable'],
];

/** @var callable(array<string, mixed>): array<string, mixed> $jsonBody */
$jsonBody = static fn (array $schema, bool $required = true): array => [
    'required' => $required,
    'content'  => ['application/json' => ['schema' => $schema]],
];

$auditFields = [
    'created_at' => ['type' => 'string', 'format' => 'date-time'],
    'created_by' => ['type' => 'integer', 'nullable' => true],
    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
    'updated_by' => ['type' => 'integer', 'nullable' => true],
];

$statusField = ['type' => 'string', 'enum' => ['active', 'inactive']];

/** Repeated on every endpoint that accepts `role_id` together with `region_id` / `state_id`. */
$roleScopeNote = <<<'TEXT'
    The role decides which of `region_id` and `state_id` apply:

    * `RSM` - `region_id` is required, `state_id` is refused.
    * `SO` - `region_id` and `state_id` are both required, and the state must be one of the states mapped to that region.
    * every other role, `ADMIN` included - both are refused.
    TEXT;

// --- document ----------------------------------------------------------------

return [
    'openapi' => '3.0.3',
    'info'    => [
        'title'       => 'Tak Tak API',
        'version'     => '1.0.0',
        'description' => <<<'TEXT'
            Distribution back office: users and roles, geography (states, regions,
            distributors), the product catalogue with dated MRP history, and CSV
            imports.

            **Envelope.** Every response is `{ success, message, data }`; list
            endpoints add `meta` with the page numbers. Errors are
            `{ success: false, message, errors? }`, where `errors` is a list of
            `{ field, message }`.

            **Auth.** `POST /auth/login` returns an access token, sent as
            `Authorization: Bearer <token>`. It is valid for 24 hours and there
            is no refresh token - once it expires the user signs in again.

            **Permissions.** Each endpoint below names the `page_route:page_action`
            slug it requires. `SUPER_ADMIN` passes every check.

            **Deletes are soft.** `DELETE` flips `status` to `inactive` and
            returns the row; nothing is removed, so old records keep resolving
            the names behind `created_by` / `updated_by`.
            TEXT,
    ],
    'servers' => [
        ['url' => rtrim((string) config('App')->baseURL, '/') . $prefix, 'description' => 'This server'],
    ],
    'tags' => [
        ['name' => 'Health', 'description' => 'Service probe'],
        ['name' => 'Auth', 'description' => 'Login, profile, password'],
        ['name' => 'Users', 'description' => 'User accounts'],
        ['name' => 'Roles', 'description' => 'Roles and their permission sets'],
        ['name' => 'Permissions', 'description' => 'The permission master list'],
        ['name' => 'States', 'description' => 'Reference data'],
        ['name' => 'Regions', 'description' => 'Regions and the states they contain'],
        ['name' => 'Distributors', 'description' => 'Distributors and the regions they cover'],
        ['name' => 'Brands', 'description' => 'Product brands'],
        ['name' => 'Products', 'description' => 'Catalogue and dated MRP history'],
        ['name' => 'Targets', 'description' => 'Monthly unit targets per region, distributor or sales person'],
        ['name' => 'Imports', 'description' => 'CSV imports and their staged rows'],
    ],
    'security' => [['bearerAuth' => []]],

    'paths' => [
        // -------------------------------------------------------------- health
        '/health' => [
            'get' => [
                'tags'     => ['Health'],
                'summary'  => 'Service and database probe',
                'security' => [],
                'responses' => [
                    '200' => [
                        'description' => 'The API and its database are up.',
                        'content'     => ['application/json' => ['schema' => $ref('Health')]],
                    ],
                    '503' => [
                        'description' => 'The database is unreachable.',
                        'content'     => ['application/json' => ['schema' => $ref('Health')]],
                    ],
                ],
            ],
        ],

        // ---------------------------------------------------------------- auth
        '/auth/login' => [
            'post' => [
                'tags'        => ['Auth'],
                'summary'     => 'Exchange credentials for an access token',
                'description' => 'Rate limited. A wrong password and an unknown email return the same message on purpose. The token is valid for 24 hours.',
                'security'    => [],
                'requestBody' => $jsonBody([
                    'type'     => 'object',
                    'required' => ['email', 'password'],
                    'properties' => [
                        'email'    => ['type' => 'string', 'format' => 'email', 'example' => 'superadmin@taktak.com'],
                        'password' => ['type' => 'string', 'format' => 'password', 'example' => 'SuperAdmin@123'],
                    ],
                ]),
                'responses' => [
                    '200' => $okResponse('Signed in.', $ref('AuthResult')),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                    '422' => ['$ref' => '#/components/responses/Unprocessable'],
                    '429' => ['$ref' => '#/components/responses/TooManyRequests'],
                ],
            ],
        ],
        '/auth/logout' => [
            'post' => [
                'tags'        => ['Auth'],
                'summary'     => 'End a session',
                'description' => 'There is no server-side session to revoke - the client discards its access token. This endpoint exists for API completeness.',
                'responses' => [
                    '200' => $okResponse('Signed out.', ['type' => 'null']),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                ],
            ],
        ],
        '/auth/me' => [
            'get' => [
                'tags'      => ['Auth'],
                'summary'   => 'The signed-in user, their role and permission slugs',
                'responses' => [
                    '200' => $okResponse('The caller profile.', $ref('Profile')),
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                ],
            ],
        ],
        '/auth/change-password' => [
            'post' => [
                'tags'        => ['Auth'],
                'summary'     => 'Change your own password',
                'description' => 'Access tokens already issued stay valid until they expire (up to 24 hours) - there is no server-side revocation.',
                'requestBody' => $jsonBody([
                    'type'     => 'object',
                    'required' => ['old_password', 'new_password'],
                    'properties' => [
                        'old_password' => ['type' => 'string', 'format' => 'password'],
                        'new_password' => ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'description' => 'At least 8 characters, with a letter and a number.'],
                    ],
                ]),
                'responses' => [
                    '200' => $okResponse('Password changed.', ['type' => 'null']),
                    '400' => ['$ref' => '#/components/responses/BadRequest'],
                    '401' => ['$ref' => '#/components/responses/Unauthorized'],
                    '422' => ['$ref' => '#/components/responses/Unprocessable'],
                ],
            ],
        ],

        // --------------------------------------------------------------- users
        '/users' => [
            'get' => [
                'tags'        => ['Users'],
                'summary'     => 'List users',
                'description' => "Requires `users:view`. `search` covers full name and email.\n\nSortable: id, full_name, email, status, created_at, last_login_at.",
                'parameters'  => array_merge($listParams, [
                    $queryParam('role_id', 'Only users on this role.', 'integer'),
                    $queryParam('region_id', 'Only users tied to this region.', 'integer'),
                    $queryParam('state_id', 'Only users tied to this state.', 'integer'),
                ]),
                'responses' => ['200' => $pagedResponse('A page of users.', $ref('User'))] + $errorResponses,
            ],
            'post' => [
                'tags'        => ['Users'],
                'summary'     => 'Create a user',
                'description' => "Requires `users:create`.\n\n" . $roleScopeNote,
                'requestBody' => $jsonBody($ref('UserCreate')),
                'responses'   => ['201' => $okResponse('User created.', $ref('User'))] + $errorResponses,
            ],
        ],
        '/users/{id}' => [
            'parameters' => [$idParam],
            'get'        => [
                'tags'      => ['Users'],
                'summary'   => 'Fetch one user',
                'description' => 'Requires `users:view`.',
                'responses' => ['200' => $okResponse('The user.', $ref('User'))] + $errorResponses,
            ],
            'put' => [
                'tags'        => ['Users'],
                'summary'     => 'Update a user',
                'description' => "Requires `users:edit`. Send only the fields you are changing.\n\nChanging the role or deactivating the account takes effect the next time that user's access token is issued - there is no server-side revocation, so an already-issued token stays valid until it expires (up to 24 hours). The last active Super Admin cannot be moved or deactivated.\n\n"
                    . $roleScopeNote
                    . "\n\nMoving a user onto a role that reaches less far clears whatever no longer applies: an RSM moved to ADMIN loses its region, an SO moved to RSM its state.",
                'requestBody' => $jsonBody($ref('UserUpdate')),
                'responses'   => ['200' => $okResponse('User updated.', $ref('User'))] + $errorResponses,
            ],
            'delete' => [
                'tags'        => ['Users'],
                'summary'     => 'Deactivate a user (soft delete)',
                'description' => 'Requires `users:delete`. Flips status to inactive. There is no server-side session revocation, so an access token already issued to that user stays valid until it expires (up to 24 hours).',
                'responses'   => ['200' => $okResponse('User deactivated.', $ref('User'))] + $errorResponses,
            ],
        ],
        '/users/{id}/activate' => [
            'parameters' => [$idParam],
            'patch'      => [
                'tags'        => ['Users'],
                'summary'     => 'Reactivate a user',
                'description' => 'Requires `users:edit`. Fails when the role the user sits on is inactive.',
                'responses'   => ['200' => $okResponse('User activated.', $ref('User'))] + $errorResponses,
            ],
        ],
        '/users/{id}/reset-password' => [
            'parameters' => [$idParam],
            'patch'      => [
                'tags'        => ['Users'],
                'summary'     => 'Set a new password for a user',
                'description' => 'Requires `users:edit`. There is no server-side session revocation, so an access token already issued to that user stays valid until it expires (up to 24 hours).',
                'requestBody' => $jsonBody([
                    'type'       => 'object',
                    'required'   => ['new_password'],
                    'properties' => ['new_password' => ['type' => 'string', 'format' => 'password', 'minLength' => 8]],
                ]),
                'responses' => ['200' => $okResponse('Password reset.', ['type' => 'null'])] + $errorResponses,
            ],
        ],

        // --------------------------------------------------------------- roles
        '/roles' => [
            'get' => [
                'tags'        => ['Roles'],
                'summary'     => 'List roles',
                'description' => 'Requires `roles:view`. Each row carries `user_count`, so the screen can warn before a role is deactivated.',
                'parameters'  => $listParams,
                'responses'   => ['200' => $pagedResponse('A page of roles.', $ref('RoleListItem'))] + $errorResponses,
            ],
            'post' => [
                'tags'        => ['Roles'],
                'summary'     => 'Create a role',
                'description' => 'Requires `roles:create`. `permission_ids` may be sent to grant permissions in the same call.',
                'requestBody' => $jsonBody($ref('RoleCreate')),
                'responses'   => ['201' => $okResponse('Role created.', $ref('Role'))] + $errorResponses,
            ],
        ],
        '/roles/{id}' => [
            'parameters' => [$idParam],
            'get'        => [
                'tags'        => ['Roles'],
                'summary'     => 'Fetch one role with its permissions',
                'description' => 'Requires `roles:view`.',
                'responses'   => ['200' => $okResponse('The role.', $ref('Role'))] + $errorResponses,
            ],
            'put' => [
                'tags'        => ['Roles'],
                'summary'     => 'Update a role',
                'description' => 'Requires `roles:edit`. Built-in roles cannot be renamed or deactivated.',
                'requestBody' => $jsonBody($ref('RoleUpdate')),
                'responses'   => ['200' => $okResponse('Role updated.', $ref('Role'))] + $errorResponses,
            ],
            'delete' => [
                'tags'        => ['Roles'],
                'summary'     => 'Deactivate a role (soft delete)',
                'description' => 'Requires `roles:delete`. Refused while active users still sit on the role, and for built-in roles.',
                'responses'   => ['200' => $okResponse('Role deactivated.', $ref('Role'))] + $errorResponses,
            ],
        ],
        '/roles/{id}/permissions' => [
            'parameters' => [$idParam],
            'put'        => [
                'tags'        => ['Roles'],
                'summary'     => 'Replace a role\'s permission set',
                'description' => "Requires `roles:assign` - kept separate from `roles:edit` so renaming a role does not also grant powers.\n\nThe screen sends the full tick-box state; the set is replaced, not merged. Super Admin cannot be edited.",
                'requestBody' => $jsonBody([
                    'type'       => 'object',
                    'required'   => ['permission_ids'],
                    'properties' => ['permission_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 2, 3]]],
                ]),
                'responses' => ['200' => $okResponse('Permissions updated.', $ref('Role'))] + $errorResponses,
            ],
        ],

        // --------------------------------------------------------- permissions
        '/permissions' => [
            'get' => [
                'tags'        => ['Permissions'],
                'summary'     => 'The permission master list, grouped by page route',
                'description' => 'Requires `roles:view`. This is the shape the role screen needs to draw its tick boxes.',
                'responses'   => ['200' => $okResponse('Grouped permissions.', $ref('PermissionGroups'))] + $errorResponses,
            ],
        ],

        // -------------------------------------------------------------- states
        '/states' => [
            'get' => [
                'tags'        => ['States'],
                'summary'     => 'List states',
                'description' => "Reference data - readable by any signed-in user, which is why there is no `states` permission module. There is no create / update / delete endpoint.\n\n`search` covers name and code. Default page size is 100.",
                'parameters'  => [
                    $queryParam('page', 'Page number, from 1.', 'integer', ['minimum' => 1, 'default' => 1]),
                    $queryParam('limit', 'Rows per page.', 'integer', ['minimum' => 1, 'maximum' => 500, 'default' => 100]),
                    $queryParam('search', 'Matches name or code.'),
                    $queryParam('status', 'Restrict to active or inactive rows.', 'string', ['enum' => ['active', 'inactive']]),
                ],
                'responses' => ['200' => $pagedResponse('A page of states.', $ref('State'))] + $errorResponses,
            ],
        ],

        // ------------------------------------------------------------- regions
        '/regions' => [
            'get' => [
                'tags'        => ['Regions'],
                'summary'     => 'List regions with their states',
                'description' => "Requires `regions:view`. `search` covers the name.\n\nSortable: id, name, status, created_at.",
                'parameters'  => array_merge($listParams, [
                    $queryParam('state_id', 'Only regions that contain this state.', 'integer'),
                ]),
                'responses' => ['200' => $pagedResponse('A page of regions.', $ref('Region'))] + $errorResponses,
            ],
            'post' => [
                'tags'        => ['Regions'],
                'summary'     => 'Create a region',
                'description' => 'Requires `regions:create`. At least one state must be selected.',
                'requestBody' => $jsonBody($ref('RegionCreate')),
                'responses'   => ['201' => $okResponse('Region created.', $ref('Region'))] + $errorResponses,
            ],
        ],
        '/regions/{id}' => [
            'parameters' => [$idParam],
            'get'        => [
                'tags'        => ['Regions'],
                'summary'     => 'Fetch one region',
                'description' => 'Requires `regions:view`.',
                'responses'   => ['200' => $okResponse('The region.', $ref('Region'))] + $errorResponses,
            ],
            'put' => [
                'tags'        => ['Regions'],
                'summary'     => 'Update a region',
                'description' => 'Requires `regions:edit`. Sending `state_ids` replaces the whole set.',
                'requestBody' => $jsonBody($ref('RegionUpdate')),
                'responses'   => ['200' => $okResponse('Region updated.', $ref('Region'))] + $errorResponses,
            ],
            'delete' => [
                'tags'        => ['Regions'],
                'summary'     => 'Deactivate a region (soft delete)',
                'description' => 'Requires `regions:delete`. Refused while active distributors still cover the region.',
                'responses'   => ['200' => $okResponse('Region deactivated.', $ref('Region'))] + $errorResponses,
            ],
        ],

        // -------------------------------------------------------- distributors
        '/distributors' => [
            'get' => [
                'tags'        => ['Distributors'],
                'summary'     => 'List distributors with their regions',
                'description' => "Requires `distributors:view`. `search` covers name and code.\n\nSortable: id, name, code, status, created_at.",
                'parameters'  => array_merge($listParams, [
                    $queryParam('region_id', 'Only distributors covering this region.', 'integer'),
                ]),
                'responses' => ['200' => $pagedResponse('A page of distributors.', $ref('Distributor'))] + $errorResponses,
            ],
            'post' => [
                'tags'        => ['Distributors'],
                'summary'     => 'Create a distributor',
                'description' => 'Requires `distributors:create`. At least one region must be selected.',
                'requestBody' => $jsonBody($ref('DistributorCreate')),
                'responses'   => ['201' => $okResponse('Distributor created.', $ref('Distributor'))] + $errorResponses,
            ],
        ],
        '/distributors/{id}' => [
            'parameters' => [$idParam],
            'get'        => [
                'tags'        => ['Distributors'],
                'summary'     => 'Fetch one distributor',
                'description' => 'Requires `distributors:view`.',
                'responses'   => ['200' => $okResponse('The distributor.', $ref('Distributor'))] + $errorResponses,
            ],
            'put' => [
                'tags'        => ['Distributors'],
                'summary'     => 'Update a distributor',
                'description' => 'Requires `distributors:edit`. Sending `region_ids` replaces the whole set.',
                'requestBody' => $jsonBody($ref('DistributorUpdate')),
                'responses'   => ['200' => $okResponse('Distributor updated.', $ref('Distributor'))] + $errorResponses,
            ],
            'delete' => [
                'tags'        => ['Distributors'],
                'summary'     => 'Deactivate a distributor (soft delete)',
                'description' => 'Requires `distributors:delete`.',
                'responses'   => ['200' => $okResponse('Distributor deactivated.', $ref('Distributor'))] + $errorResponses,
            ],
        ],

        // -------------------------------------------------------------- brands
        '/brands' => [
            'get' => [
                'tags'        => ['Brands'],
                'summary'     => 'List brands',
                'description' => "Requires `brands:view`. `search` covers name and code.\n\nSortable: id, name, code, status, created_at.",
                'parameters'  => $listParams,
                'responses'   => ['200' => $pagedResponse('A page of brands.', $ref('Brand'))] + $errorResponses,
            ],
            'post' => [
                'tags'        => ['Brands'],
                'summary'     => 'Create a brand',
                'description' => 'Requires `brands:create`.',
                'requestBody' => $jsonBody($ref('BrandCreate')),
                'responses'   => ['201' => $okResponse('Brand created.', $ref('Brand'))] + $errorResponses,
            ],
        ],
        '/brands/{id}' => [
            'parameters' => [$idParam],
            'get'        => [
                'tags'        => ['Brands'],
                'summary'     => 'Fetch one brand',
                'description' => 'Requires `brands:view`.',
                'responses'   => ['200' => $okResponse('The brand.', $ref('Brand'))] + $errorResponses,
            ],
            'put' => [
                'tags'        => ['Brands'],
                'summary'     => 'Update a brand',
                'description' => 'Requires `brands:edit`.',
                'requestBody' => $jsonBody($ref('BrandUpdate')),
                'responses'   => ['200' => $okResponse('Brand updated.', $ref('Brand'))] + $errorResponses,
            ],
            'delete' => [
                'tags'        => ['Brands'],
                'summary'     => 'Deactivate a brand (soft delete)',
                'description' => 'Requires `brands:delete`. Refused while active products still belong to the brand.',
                'responses'   => ['200' => $okResponse('Brand deactivated.', $ref('Brand'))] + $errorResponses,
            ],
        ],

        // ------------------------------------------------------------ products
        '/products' => [
            'get' => [
                'tags'        => ['Products'],
                'summary'     => 'List products with the price in force',
                'description' => "Requires `products:view`. `search` covers SKU and product name. Each row carries `current_mrp`, the row whose `effective_to` is still open.\n\nSortable: id, sku, product_name, status, created_at.",
                'parameters'  => array_merge($listParams, [
                    $queryParam('brand_id', 'Only products of this brand.', 'integer'),
                ]),
                'responses' => ['200' => $pagedResponse('A page of products.', $ref('Product'))] + $errorResponses,
            ],
            'post' => [
                'tags'        => ['Products'],
                'summary'     => 'Create a product',
                'description' => 'Requires `products:create`. An opening `mrp` may be sent, in which case the first price row is written in the same transaction.',
                'requestBody' => $jsonBody($ref('ProductCreate')),
                'responses'   => ['201' => $okResponse('Product created.', $ref('ProductDetail'))] + $errorResponses,
            ],
        ],
        '/products/price-list' => [
            'get' => [
                'tags'        => ['Products'],
                'summary'     => 'Current price list',
                'description' => 'Requires `product_mrp:view`. Reads the `v_current_price_list` view: every active product with the price in force today.',
                'parameters'  => [$queryParam('search', 'Matches SKU, product name or brand name.')],
                'responses'   => [
                    '200' => $okResponse('The price list.', ['type' => 'array', 'items' => $ref('PriceListRow')]),
                ] + $errorResponses,
            ],
        ],
        '/products/{id}' => [
            'parameters' => [$idParam],
            'get'        => [
                'tags'        => ['Products'],
                'summary'     => 'Fetch one product with its full price history',
                'description' => 'Requires `products:view`.',
                'responses'   => ['200' => $okResponse('The product.', $ref('ProductDetail'))] + $errorResponses,
            ],
            'put' => [
                'tags'        => ['Products'],
                'summary'     => 'Update a product',
                'description' => 'Requires `products:edit`. Prices are not edited here - use the MRP endpoints.',
                'requestBody' => $jsonBody($ref('ProductUpdate')),
                'responses'   => ['200' => $okResponse('Product updated.', $ref('ProductDetail'))] + $errorResponses,
            ],
            'delete' => [
                'tags'        => ['Products'],
                'summary'     => 'Deactivate a product (soft delete)',
                'description' => 'Requires `products:delete`. The MRP history is left untouched so old reports keep pricing correctly.',
                'responses'   => ['200' => $okResponse('Product deactivated.', $ref('ProductDetail'))] + $errorResponses,
            ],
        ],
        '/products/{id}/mrp' => [
            'parameters' => [$idParam],
            'get'        => [
                'tags'        => ['Products'],
                'summary'     => 'Price history of a product',
                'description' => 'Requires `product_mrp:view`. Newest first; the open row (`effective_to: null`) is the price in force.',
                'responses'   => [
                    '200' => $okResponse('The history.', ['type' => 'array', 'items' => $ref('ProductMrp')]),
                ] + $errorResponses,
            ],
            'post' => [
                'tags'        => ['Products'],
                'summary'     => 'Record a new MRP',
                'description' => "Requires `product_mrp:create`.\n\nNothing is overwritten: the row in force is closed on the day before `effective_from`, and a new open row is inserted. `effective_from` must be later than the current row's start date, and no two rows may start on the same day.",
                'requestBody' => $jsonBody([
                    'type'     => 'object',
                    'required' => ['mrp', 'effective_from'],
                    'properties' => [
                        'mrp'            => ['type' => 'number', 'format' => 'double', 'example' => 129.5],
                        'effective_from' => ['type' => 'string', 'format' => 'date', 'example' => '2026-04-01'],
                    ],
                ]),
                'responses' => ['201' => $okResponse('New price recorded.', $ref('ProductDetail'))] + $errorResponses,
            ],
        ],
        '/products/{id}/mrp/on-date' => [
            'parameters' => [$idParam],
            'get'        => [
                'tags'        => ['Products'],
                'summary'     => 'The price in force on a given date',
                'description' => 'Requires `product_mrp:view`. The reporting lookup: returns the row whose date range covers `on_date`.',
                'parameters'  => [[
                    'name'     => 'on_date',
                    'in'       => 'query',
                    'required' => true,
                    'schema'   => ['type' => 'string', 'format' => 'date', 'example' => '2026-02-15'],
                ]],
                'responses' => ['200' => $okResponse('The price row.', $ref('ProductMrp'))] + $errorResponses,
            ],
        ],

        // ------------------------------------------------------------- targets
        '/targets' => [
            'get' => [
                'tags'        => ['Targets'],
                'summary'     => 'List targets',
                'description' => "Requires `targets:view`. `search` covers the target name. Each row carries the resolved owner plus `product_count` and `total_units`; the product rows themselves come back from `GET /targets/{id}`.\n\nSortable: id, name, target_type, target_month, target_year, status, created_at.",
                'parameters'  => array_merge($listParams, [
                    $queryParam('target_type', 'Only targets of this type.', 'string', ['enum' => ['region', 'distributor', 'sales_person']]),
                    $queryParam('target_ref_id', 'Only targets belonging to this region / distributor / user. Pair it with `target_type` - ids are not unique across the three.', 'integer'),
                    $queryParam('target_month', 'Month, 1-12.', 'integer'),
                    $queryParam('target_year', 'Four-digit year.', 'integer'),
                ]),
                'responses' => ['200' => $pagedResponse('A page of targets.', $ref('Target'))] + $errorResponses,
            ],
            'post' => [
                'tags'        => ['Targets'],
                'summary'     => 'Create a target',
                'description' => "Requires `targets:create`.\n\nThe owner must exist and be active, and for `sales_person` must sit on a sales role (`RSM`, `SO`, `SALES_PERSON`). Every product must exist and be active, and may appear only once. One **active** target is allowed per owner per month - a second one is a 409, while a deactivated target steps aside so the period can be planned again.",
                'requestBody' => $jsonBody($ref('TargetCreate')),
                'responses'   => ['201' => $okResponse('Target created.', $ref('TargetDetail'))] + $errorResponses,
            ],
        ],
        '/targets/types' => [
            'get' => [
                'tags'        => ['Targets'],
                'summary'     => 'The target type drop-down',
                'description' => 'Requires `targets:view`. The fixed list behind the first drop-down on the target screen.',
                'responses'   => [
                    '200' => $okResponse('The types.', ['type' => 'array', 'items' => $ref('TargetType')]),
                ] + $errorResponses,
            ],
        ],
        '/targets/options' => [
            'get' => [
                'tags'        => ['Targets'],
                'summary'     => 'The value drop-down for a target type',
                'description' => "Requires `targets:view`. The second drop-down: what can be chosen once a type is picked. Only rows that could carry a target are returned - active regions, active distributors, active users on a sales role. Capped at 500 rows; narrow it with `search`.\n\nThe shape follows the type: a region is `{ id, name }`, a distributor adds `code`, a sales person adds `email`, `role_name` and `region_name`.",
                'parameters'  => [
                    [
                        'name'     => 'type',
                        'in'       => 'query',
                        'required' => true,
                        'schema'   => ['type' => 'string', 'enum' => ['region', 'distributor', 'sales_person']],
                    ],
                    $queryParam('search', 'Matches the name.'),
                ],
                'responses' => [
                    '200' => $okResponse('The values for that type.', ['type' => 'array', 'items' => $ref('TargetOption')]),
                ] + $errorResponses,
            ],
        ],
        '/targets/{id}' => [
            'parameters' => [$idParam],
            'get'        => [
                'tags'        => ['Targets'],
                'summary'     => 'Fetch one target with its product rows',
                'description' => 'Requires `targets:view`.',
                'responses'   => ['200' => $okResponse('The target.', $ref('TargetDetail'))] + $errorResponses,
            ],
            'put' => [
                'tags'        => ['Targets'],
                'summary'     => 'Update a target',
                'description' => 'Requires `targets:edit`. Sending `products` replaces the whole table of product rows; leaving it out keeps them as they are.',
                'requestBody' => $jsonBody($ref('TargetUpdate')),
                'responses'   => ['200' => $okResponse('Target updated.', $ref('TargetDetail'))] + $errorResponses,
            ],
            'delete' => [
                'tags'        => ['Targets'],
                'summary'     => 'Deactivate a target (soft delete)',
                'description' => 'Requires `targets:delete`. The product rows are kept, so the target can still be read back and reported on, and the month is freed for a new target.',
                'responses'   => ['200' => $okResponse('Target deactivated.', $ref('TargetDetail'))] + $errorResponses,
            ],
        ],

        // ------------------------------------------------------------- imports
        '/imports' => [
            'get' => [
                'tags'        => ['Imports'],
                'summary'     => 'List import batches',
                'description' => 'Requires `imports:view`. `search` covers the file name. Newest first.',
                'parameters'  => array_merge($listParams, [
                    $queryParam('module', 'Filter by import type.', 'string', ['enum' => ['products', 'product_mrp']]),
                    $queryParam('batch_status', 'Filter by batch outcome.', 'string', ['enum' => ['pending', 'processing', 'completed', 'failed']]),
                ]),
                'responses' => ['200' => $pagedResponse('A page of batches.', $ref('ImportBatch'))] + $errorResponses,
            ],
        ],
        '/imports/template' => [
            'get' => [
                'tags'        => ['Imports'],
                'summary'     => 'Download a CSV template',
                'description' => 'Requires `imports:view`.',
                'parameters'  => [$queryParam('module', 'Which template to download.', 'string', ['enum' => ['products', 'product_mrp'], 'default' => 'products'])],
                'responses'   => [
                    '200' => [
                        'description' => 'The template file.',
                        'content'     => ['text/csv' => ['schema' => ['type' => 'string']]],
                    ],
                ] + $errorResponses,
            ],
        ],
        '/imports/{id}' => [
            'parameters' => [$idParam],
            'get'        => [
                'tags'        => ['Imports'],
                'summary'     => 'Fetch one import batch',
                'description' => 'Requires `imports:view`.',
                'responses'   => ['200' => $okResponse('The batch.', $ref('ImportBatch'))] + $errorResponses,
            ],
        ],
        '/imports/{id}/rows' => [
            'parameters' => [$idParam],
            'get'        => [
                'tags'        => ['Imports'],
                'summary'     => 'The staged rows of a batch',
                'description' => 'Requires `imports:view`. Rows that failed keep the reason in `error_message`, so the user can be shown exactly which line failed and why.',
                'parameters'  => array_merge($listParams, [
                    $queryParam('row_status', 'Filter by row outcome.', 'string', ['enum' => ['pending', 'valid', 'error', 'processed']]),
                ]),
                'responses' => ['200' => $pagedResponse('A page of staged rows.', $ref('ImportRow'))] + $errorResponses,
            ],
        ],
        '/imports/products' => [
            'post' => [
                'tags'        => ['Imports'],
                'summary'     => 'Import products from a CSV',
                'description' => "Requires `products:import`.\n\nColumns: `brand_name`, `sku`, `product_name`, `mrp` (optional). Common header spellings are accepted (`brand`, `product`, `price`, ...). An existing SKU is updated rather than duplicated; an unchanged price does not create a new history row.\n\nEach row is applied in its own transaction, so one bad line never rolls back the file.",
                'requestBody' => [
                    'required' => true,
                    'content'  => [
                        'multipart/form-data' => [
                            'schema' => [
                                'type'     => 'object',
                                'required' => ['file'],
                                'properties' => [
                                    'file'                  => ['type' => 'string', 'format' => 'binary', 'description' => 'The .csv file.'],
                                    'effective_from'        => ['type' => 'string', 'format' => 'date', 'description' => 'Start date for any prices in the file. Defaults to today.'],
                                    'create_missing_brands' => ['type' => 'boolean', 'description' => 'Create brands that do not exist yet instead of failing those rows.'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => ['201' => $okResponse('Import finished - check the counts.', $ref('ImportBatch'))] + $errorResponses,
            ],
        ],
        '/imports/product-mrp' => [
            'post' => [
                'tags'        => ['Imports'],
                'summary'     => 'Import prices from a CSV',
                'description' => "Requires `product_mrp:import`.\n\nColumns: `sku`, `mrp`. Every SKU must already exist. A row whose price equals the current one is reported as an error rather than silently skipped, so the file can be corrected.",
                'requestBody' => [
                    'required' => true,
                    'content'  => [
                        'multipart/form-data' => [
                            'schema' => [
                                'type'     => 'object',
                                'required' => ['file'],
                                'properties' => [
                                    'file'           => ['type' => 'string', 'format' => 'binary', 'description' => 'The .csv file.'],
                                    'effective_from' => ['type' => 'string', 'format' => 'date', 'description' => 'Start date for the new prices. Defaults to today.'],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => ['201' => $okResponse('Import finished - check the counts.', $ref('ImportBatch'))] + $errorResponses,
            ],
        ],
    ],

    // -------------------------------------------------------------- components
    'components' => [
        'securitySchemes' => [
            'bearerAuth' => [
                'type'         => 'http',
                'scheme'       => 'bearer',
                'bearerFormat' => 'JWT',
                'description'  => 'The `access_token` from /auth/login, sent as `Authorization: Bearer <token>`.',
            ],
        ],

        'responses' => [
            'BadRequest'      => ['description' => 'A business rule rejected the request.', 'content' => ['application/json' => ['schema' => $ref('Error')]]],
            'Unauthorized'    => ['description' => 'Missing, invalid or expired access token.', 'content' => ['application/json' => ['schema' => $ref('Error')]]],
            'Forbidden'       => ['description' => 'The role lacks the permission this endpoint needs.', 'content' => ['application/json' => ['schema' => $ref('Error')]]],
            'NotFound'        => ['description' => 'No such record.', 'content' => ['application/json' => ['schema' => $ref('Error')]]],
            'Conflict'        => ['description' => 'A unique value is already taken.', 'content' => ['application/json' => ['schema' => $ref('Error')]]],
            'Unprocessable'   => ['description' => 'Validation failed - see `errors`.', 'content' => ['application/json' => ['schema' => $ref('Error')]]],
            'TooManyRequests' => ['description' => 'Rate limit exceeded.', 'content' => ['application/json' => ['schema' => $ref('Error')]]],
        ],

        'schemas' => [
            'Error' => [
                'type'       => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'example' => false],
                    'message' => ['type' => 'string', 'example' => 'Validation failed'],
                    'errors'  => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'field'   => ['type' => 'string', 'example' => 'email'],
                                'message' => ['type' => 'string', 'example' => 'A valid email address is required'],
                            ],
                        ],
                    ],
                ],
            ],

            'PageMeta' => [
                'type'       => 'object',
                'properties' => [
                    'page'        => ['type' => 'integer', 'example' => 1],
                    'limit'       => ['type' => 'integer', 'example' => 20],
                    'total'       => ['type' => 'integer', 'example' => 137],
                    'total_pages' => ['type' => 'integer', 'example' => 7],
                ],
            ],

            'Health' => [
                'type'       => 'object',
                'properties' => [
                    'success'        => ['type' => 'boolean'],
                    'status'         => ['type' => 'string', 'enum' => ['ok', 'degraded']],
                    'database'       => ['type' => 'string', 'enum' => ['up', 'down']],
                    'uptime_seconds' => ['type' => 'integer'],
                    'timestamp'      => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],

            'PermissionSummary' => [
                'type'        => 'object',
                'description' => 'A role\'s permissions broken into the page routes it can reach and, per route, which actions it may perform there.',
                'properties'  => [
                    'page_routes'  => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['products', 'product_mrp']],
                    'page_actions' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'route'       => ['type' => 'string', 'example' => 'products'],
                                'permissions' => ['type' => 'array', 'items' => ['type' => 'string'], 'example' => ['view', 'create']],
                            ],
                        ],
                    ],
                ],
            ],

            'AuthResult' => [
                'type'       => 'object',
                'properties' => [
                    'access_token' => ['type' => 'string'],
                    'user'         => [
                        'type'       => 'object',
                        'properties' => [
                            'id'          => ['type' => 'integer'],
                            'full_name'   => ['type' => 'string'],
                            'email'       => ['type' => 'string', 'format' => 'email'],
                            'role'        => ['type' => 'string', 'example' => 'SUPER_ADMIN'],
                            'role_id'     => ['type' => 'integer'],
                            'permissions' => $ref('PermissionSummary'),
                        ],
                    ],
                ],
            ],

            'Profile' => [
                'allOf' => [
                    $ref('User'),
                    [
                        'type'       => 'object',
                        'properties' => ['permissions' => $ref('PermissionSummary')],
                    ],
                ],
            ],

            'RoleSummary' => [
                'type'       => 'object',
                'nullable'   => true,
                'properties' => [
                    'id'          => ['type' => 'integer'],
                    'name'        => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                ],
            ],

            'RegionSummary' => [
                'type'       => 'object',
                'nullable'   => true,
                'description' => 'Set only for a user on a region-scoped role (RSM, SO).',
                'properties' => [
                    'id'   => ['type' => 'integer'],
                    'name' => ['type' => 'string', 'example' => 'South'],
                ],
            ],

            'StateSummary' => [
                'type'       => 'object',
                'nullable'   => true,
                'description' => 'Set only for a user on a state-scoped role (SO).',
                'properties' => [
                    'id'   => ['type' => 'integer'],
                    'name' => ['type' => 'string', 'example' => 'Karnataka'],
                    'code' => ['type' => 'string', 'nullable' => true, 'example' => 'KA'],
                ],
            ],

            'User' => [
                'type'       => 'object',
                'properties' => array_merge([
                    'id'            => ['type' => 'integer'],
                    'full_name'     => ['type' => 'string'],
                    'email'         => ['type' => 'string', 'format' => 'email'],
                    'role_id'       => ['type' => 'integer'],
                    'role'          => $ref('RoleSummary'),
                    'region_id'     => ['type' => 'integer', 'nullable' => true],
                    'region'        => $ref('RegionSummary'),
                    'state_id'      => ['type' => 'integer', 'nullable' => true],
                    'state'         => $ref('StateSummary'),
                    'status'        => $statusField,
                    'last_login_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ], $auditFields),
            ],

            'UserCreate' => [
                'type'     => 'object',
                'required' => ['full_name', 'email', 'password', 'role_id'],
                'properties' => [
                    'full_name' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 150],
                    'email'     => ['type' => 'string', 'format' => 'email'],
                    'password'  => ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'description' => 'At least 8 characters, with a letter and a number.'],
                    'role_id'   => ['type' => 'integer'],
                    'region_id' => ['type' => 'integer', 'nullable' => true, 'description' => 'Required for RSM and SO, refused for any other role.'],
                    'state_id'  => ['type' => 'integer', 'nullable' => true, 'description' => 'Required for SO, refused for any other role. Must be a state of `region_id`.'],
                    'status'    => array_merge($statusField, ['default' => 'active']),
                ],
            ],

            'UserUpdate' => [
                'type'        => 'object',
                'minProperties' => 1,
                'properties'  => [
                    'full_name' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 150],
                    'email'     => ['type' => 'string', 'format' => 'email'],
                    'role_id'   => ['type' => 'integer'],
                    'region_id' => ['type' => 'integer', 'nullable' => true, 'description' => 'Follows the same rule as on create, against the role the user ends up on.'],
                    'state_id'  => ['type' => 'integer', 'nullable' => true, 'description' => 'Follows the same rule as on create, against the role the user ends up on.'],
                    'status'    => $statusField,
                ],
            ],

            'Permission' => [
                'type'       => 'object',
                'properties' => [
                    'id'          => ['type' => 'integer'],
                    'slug'        => ['type' => 'string', 'example' => 'products:view'],
                    'page_route'  => ['type' => 'string', 'example' => 'products'],
                    'page_action' => ['type' => 'string', 'example' => 'view'],
                    'name'        => ['type' => 'string', 'example' => 'View Products'],
                ],
            ],

            'PermissionGroups' => [
                'type'       => 'object',
                'properties' => [
                    'total'       => ['type' => 'integer'],
                    'page_routes' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'route'       => ['type' => 'string'],
                                'permissions' => ['type' => 'array', 'items' => $ref('Permission')],
                            ],
                        ],
                    ],
                ],
            ],

            'Role' => [
                'type'       => 'object',
                'properties' => array_merge([
                    'id'          => ['type' => 'integer'],
                    'name'        => ['type' => 'string', 'example' => 'ADMIN'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'is_system'   => ['type' => 'boolean', 'description' => 'Built-in roles cannot be renamed, deactivated or deleted.'],
                    'status'      => $statusField,
                    'permissions' => ['type' => 'array', 'items' => $ref('Permission')],
                ], $auditFields),
            ],

            'RoleListItem' => [
                'type'       => 'object',
                'properties' => array_merge([
                    'id'          => ['type' => 'integer'],
                    'name'        => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'is_system'   => ['type' => 'boolean'],
                    'status'      => $statusField,
                    'user_count'  => ['type' => 'integer', 'description' => 'How many users sit on this role.'],
                ], $auditFields),
            ],

            'RoleCreate' => [
                'type'     => 'object',
                'required' => ['name'],
                'properties' => [
                    'name'           => ['type' => 'string', 'minLength' => 2, 'maxLength' => 50, 'pattern' => '^[A-Za-z0-9_ -]+$'],
                    'description'    => ['type' => 'string', 'nullable' => true, 'maxLength' => 255],
                    'status'         => array_merge($statusField, ['default' => 'active']),
                    'permission_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                ],
            ],

            'RoleUpdate' => [
                'type'          => 'object',
                'minProperties' => 1,
                'properties'    => [
                    'name'        => ['type' => 'string', 'minLength' => 2, 'maxLength' => 50],
                    'description' => ['type' => 'string', 'nullable' => true, 'maxLength' => 255],
                    'status'      => $statusField,
                ],
            ],

            'State' => [
                'type'       => 'object',
                'properties' => array_merge([
                    'id'     => ['type' => 'integer'],
                    'name'   => ['type' => 'string', 'example' => 'Karnataka'],
                    'code'   => ['type' => 'string', 'nullable' => true, 'example' => 'KA'],
                    'status' => $statusField,
                ], $auditFields),
            ],

            'Region' => [
                'type'       => 'object',
                'properties' => array_merge([
                    'id'     => ['type' => 'integer'],
                    'name'   => ['type' => 'string', 'example' => 'South'],
                    'status' => $statusField,
                    'states' => ['type' => 'array', 'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'id'   => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'code' => ['type' => 'string', 'nullable' => true],
                        ],
                    ]],
                ], $auditFields),
            ],

            'RegionCreate' => [
                'type'     => 'object',
                'required' => ['name', 'state_ids'],
                'properties' => [
                    'name'      => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100],
                    'status'    => array_merge($statusField, ['default' => 'active']),
                    'state_ids' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'integer']],
                ],
            ],

            'RegionUpdate' => [
                'type'          => 'object',
                'minProperties' => 1,
                'properties'    => [
                    'name'      => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100],
                    'status'    => $statusField,
                    'state_ids' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'integer'], 'description' => 'Replaces the whole set.'],
                ],
            ],

            'Distributor' => [
                'type'       => 'object',
                'properties' => array_merge([
                    'id'      => ['type' => 'integer'],
                    'name'    => ['type' => 'string'],
                    'code'    => ['type' => 'string', 'nullable' => true],
                    'status'  => $statusField,
                    'regions' => ['type' => 'array', 'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'id'     => ['type' => 'integer'],
                            'name'   => ['type' => 'string'],
                            'states' => ['type' => 'array', 'items' => $ref('State')],
                        ],
                    ]],
                ], $auditFields),
            ],

            'DistributorCreate' => [
                'type'     => 'object',
                'required' => ['name', 'region_ids'],
                'properties' => [
                    'name'       => ['type' => 'string', 'minLength' => 2, 'maxLength' => 200],
                    'code'       => ['type' => 'string', 'nullable' => true, 'maxLength' => 50],
                    'status'     => array_merge($statusField, ['default' => 'active']),
                    'region_ids' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'integer']],
                ],
            ],

            'DistributorUpdate' => [
                'type'          => 'object',
                'minProperties' => 1,
                'properties'    => [
                    'name'       => ['type' => 'string', 'minLength' => 2, 'maxLength' => 200],
                    'code'       => ['type' => 'string', 'nullable' => true, 'maxLength' => 50],
                    'status'     => $statusField,
                    'region_ids' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'integer'], 'description' => 'Replaces the whole set.'],
                ],
            ],

            'Brand' => [
                'type'       => 'object',
                'properties' => array_merge([
                    'id'     => ['type' => 'integer'],
                    'name'   => ['type' => 'string'],
                    'code'   => ['type' => 'string', 'nullable' => true],
                    'status' => $statusField,
                ], $auditFields),
            ],

            'BrandCreate' => [
                'type'     => 'object',
                'required' => ['name'],
                'properties' => [
                    'name'   => ['type' => 'string', 'minLength' => 2, 'maxLength' => 150],
                    'code'   => ['type' => 'string', 'nullable' => true, 'maxLength' => 50],
                    'status' => array_merge($statusField, ['default' => 'active']),
                ],
            ],

            'BrandUpdate' => [
                'type'          => 'object',
                'minProperties' => 1,
                'properties'    => [
                    'name'   => ['type' => 'string', 'minLength' => 2, 'maxLength' => 150],
                    'code'   => ['type' => 'string', 'nullable' => true, 'maxLength' => 50],
                    'status' => $statusField,
                ],
            ],

            'ProductMrp' => [
                'type'       => 'object',
                'properties' => [
                    'id'             => ['type' => 'integer'],
                    'product_id'     => ['type' => 'integer'],
                    'mrp'            => ['type' => 'number', 'format' => 'double', 'example' => 120.0],
                    'effective_from' => ['type' => 'string', 'format' => 'date'],
                    'effective_to'   => ['type' => 'string', 'format' => 'date', 'nullable' => true, 'description' => 'Null means this is the price in force.'],
                ],
            ],

            'Product' => [
                'type'       => 'object',
                'properties' => array_merge([
                    'id'           => ['type' => 'integer'],
                    'brand_id'     => ['type' => 'integer'],
                    'brand'        => [
                        'type'       => 'object',
                        'nullable'   => true,
                        'properties' => [
                            'id'   => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'code' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                    'sku'          => ['type' => 'string'],
                    'product_name' => ['type' => 'string'],
                    'status'       => $statusField,
                    'current_mrp'  => array_merge($ref('ProductMrp'), []),
                ], $auditFields),
            ],

            'ProductDetail' => [
                'allOf' => [
                    $ref('Product'),
                    [
                        'type'       => 'object',
                        'properties' => [
                            'mrps' => ['type' => 'array', 'items' => $ref('ProductMrp'), 'description' => 'Full price history, newest first.'],
                        ],
                    ],
                ],
            ],

            'ProductCreate' => [
                'type'     => 'object',
                'required' => ['brand_id', 'sku', 'product_name'],
                'properties' => [
                    'brand_id'       => ['type' => 'integer'],
                    'sku'            => ['type' => 'string', 'maxLength' => 100],
                    'product_name'   => ['type' => 'string', 'minLength' => 2, 'maxLength' => 255],
                    'status'         => array_merge($statusField, ['default' => 'active']),
                    'mrp'            => ['type' => 'number', 'format' => 'double', 'description' => 'Optional opening price.'],
                    'effective_from' => ['type' => 'string', 'format' => 'date', 'description' => 'Start date for the opening price. Defaults to today.'],
                ],
            ],

            'ProductUpdate' => [
                'type'          => 'object',
                'minProperties' => 1,
                'properties'    => [
                    'brand_id'     => ['type' => 'integer'],
                    'sku'          => ['type' => 'string', 'maxLength' => 100],
                    'product_name' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 255],
                    'status'       => $statusField,
                ],
            ],

            'PriceListRow' => [
                'type'       => 'object',
                'properties' => [
                    'product_id'     => ['type' => 'integer'],
                    'sku'            => ['type' => 'string'],
                    'product_name'   => ['type' => 'string'],
                    'brand_name'     => ['type' => 'string'],
                    'mrp'            => ['type' => 'number', 'format' => 'double', 'nullable' => true],
                    'effective_from' => ['type' => 'string', 'format' => 'date', 'nullable' => true],
                ],
            ],

            'TargetType' => [
                'type'       => 'object',
                'properties' => [
                    'value' => ['type' => 'string', 'enum' => ['region', 'distributor', 'sales_person']],
                    'label' => ['type' => 'string', 'example' => 'Sales Person'],
                ],
            ],

            'TargetOption' => [
                'type'        => 'object',
                'description' => 'One value drop-down row. `id` and `name` are always present; the rest depend on the type.',
                'properties'  => [
                    'id'          => ['type' => 'integer'],
                    'name'        => ['type' => 'string'],
                    'code'        => ['type' => 'string', 'nullable' => true, 'description' => 'Distributors only.'],
                    'email'       => ['type' => 'string', 'description' => 'Sales people only.'],
                    'role_name'   => ['type' => 'string', 'description' => 'Sales people only.'],
                    'region_name' => ['type' => 'string', 'nullable' => true, 'description' => 'Sales people only.'],
                ],
            ],

            'TargetOwner' => [
                'type'        => 'object',
                'nullable'    => true,
                'description' => 'The region, distributor or user behind `target_ref_id`, resolved. Null if that record has since been removed - the target itself is still a real record.',
                'properties'  => [
                    'id'        => ['type' => 'integer'],
                    'type'      => ['type' => 'string', 'enum' => ['region', 'distributor', 'sales_person']],
                    'name'      => ['type' => 'string'],
                    'status'    => $statusField,
                    'code'      => ['type' => 'string', 'nullable' => true, 'description' => 'Distributors only.'],
                    'email'     => ['type' => 'string', 'description' => 'Sales people only.'],
                    'role_name' => ['type' => 'string', 'description' => 'Sales people only.'],
                ],
            ],

            'TargetLine' => [
                'type'       => 'object',
                'properties' => [
                    'id'             => ['type' => 'integer', 'description' => 'Id of the target row itself, not of the product.'],
                    'product_id'     => ['type' => 'integer'],
                    'sku'            => ['type' => 'string'],
                    'product_name'   => ['type' => 'string'],
                    'product_status' => $statusField,
                    'brand_id'       => ['type' => 'integer', 'nullable' => true],
                    'brand_name'     => ['type' => 'string', 'nullable' => true],
                    'target_units'   => ['type' => 'integer', 'example' => 500],
                ],
            ],

            'Target' => [
                'type'       => 'object',
                'properties' => array_merge([
                    'id'            => ['type' => 'integer'],
                    'name'          => ['type' => 'string', 'example' => 'South April push'],
                    'target_type'   => ['type' => 'string', 'enum' => ['region', 'distributor', 'sales_person']],
                    'type_label'    => ['type' => 'string', 'example' => 'Region'],
                    'target_ref_id' => ['type' => 'integer', 'description' => 'Id inside the table `target_type` names.'],
                    'target'        => $ref('TargetOwner'),
                    'target_month'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
                    'target_year'   => ['type' => 'integer', 'example' => 2026],
                    'period'        => ['type' => 'string', 'example' => '2026-04', 'description' => 'Year and month together, for display and sorting.'],
                    'status'        => $statusField,
                    'product_count' => ['type' => 'integer'],
                    'total_units'   => ['type' => 'integer'],
                ], $auditFields),
            ],

            'TargetDetail' => [
                'allOf' => [
                    $ref('Target'),
                    [
                        'type'       => 'object',
                        'properties' => [
                            'products' => ['type' => 'array', 'items' => $ref('TargetLine'), 'description' => 'The product table, by brand then product name.'],
                        ],
                    ],
                ],
            ],

            'TargetProductInput' => [
                'type'     => 'object',
                'required' => ['product_id', 'target_units'],
                'properties' => [
                    'product_id'   => ['type' => 'integer', 'minimum' => 1],
                    'target_units' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 999999999, 'description' => 'Zero is allowed - a product may sit in the table with no target on it.'],
                ],
            ],

            'TargetCreate' => [
                'type'     => 'object',
                'required' => ['name', 'target_type', 'target_ref_id', 'target_month', 'target_year', 'products'],
                'properties' => [
                    'name'          => ['type' => 'string', 'minLength' => 2, 'maxLength' => 200],
                    'target_type'   => ['type' => 'string', 'enum' => ['region', 'distributor', 'sales_person']],
                    'target_ref_id' => ['type' => 'integer', 'description' => 'The value chosen in the second drop-down.'],
                    'target_month'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
                    'target_year'   => ['type' => 'integer', 'minimum' => 2000, 'maximum' => 2100],
                    'status'        => array_merge($statusField, ['default' => 'active']),
                    'products'      => ['type' => 'array', 'minItems' => 1, 'items' => $ref('TargetProductInput')],
                ],
            ],

            'TargetUpdate' => [
                'type'          => 'object',
                'minProperties' => 1,
                'properties'    => [
                    'name'          => ['type' => 'string', 'minLength' => 2, 'maxLength' => 200],
                    'target_type'   => ['type' => 'string', 'enum' => ['region', 'distributor', 'sales_person']],
                    'target_ref_id' => ['type' => 'integer'],
                    'target_month'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
                    'target_year'   => ['type' => 'integer', 'minimum' => 2000, 'maximum' => 2100],
                    'status'        => $statusField,
                    'products'      => ['type' => 'array', 'minItems' => 1, 'items' => $ref('TargetProductInput'), 'description' => 'Replaces the whole table.'],
                ],
            ],

            'ImportBatch' => [
                'type'       => 'object',
                'properties' => array_merge([
                    'id'              => ['type' => 'integer'],
                    'file_name'       => ['type' => 'string'],
                    'module'          => ['type' => 'string', 'enum' => ['products', 'product_mrp']],
                    'total_records'   => ['type' => 'integer'],
                    'success_records' => ['type' => 'integer'],
                    'failed_records'  => ['type' => 'integer'],
                    'status'          => ['type' => 'string', 'enum' => ['pending', 'processing', 'completed', 'failed']],
                ], $auditFields),
            ],

            'ImportRow' => [
                'type'       => 'object',
                'properties' => array_merge([
                    'id'              => ['type' => 'integer'],
                    'import_batch_id' => ['type' => 'integer'],
                    'row_number'      => ['type' => 'integer', 'description' => 'Line number in the uploaded file, counting the header as line 1.'],
                    'brand_name'      => ['type' => 'string', 'nullable' => true],
                    'sku'             => ['type' => 'string', 'nullable' => true],
                    'product_name'    => ['type' => 'string', 'nullable' => true],
                    'mrp'             => ['type' => 'string', 'nullable' => true, 'description' => 'Kept as text, exactly as the file had it.'],
                    'status'          => ['type' => 'string', 'enum' => ['pending', 'valid', 'error', 'processed']],
                    'error_message'   => ['type' => 'string', 'nullable' => true],
                ], $auditFields),
            ],
        ],
    ],
];
