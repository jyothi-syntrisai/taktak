<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * Tak Tak API routes.
 *
 * Every route is declared here - auto-routing is off, so an endpoint exists
 * only if it appears below. Filters merge from the group down to the route, so
 * `auth` always runs before `permission`.
 *
 *     ['filter' => 'permission:users,view']   ->   requires the users:view slug
 *
 * @var RouteCollection $routes
 */
$apiPrefix = config('Taktak')->apiPrefix;

$routes->get('/', static function () {
    $prefix = config('Taktak')->apiPrefix;

    return service('response')->setJSON([
        'success' => true,
        'message' => 'Tak Tak API',
        'api'     => '/' . $prefix,
        'docs'    => '/' . $prefix . '/docs',
    ]);
});

$routes->group($apiPrefix, static function (RouteCollection $routes): void {
    // --- open endpoints ----------------------------------------------------
    $routes->get('health', 'Health::index');
    $routes->get('docs', 'Docs::index');
    $routes->get('docs/openapi.json', 'Docs::openapi');

    // --- auth --------------------------------------------------------------
    $routes->group('auth', static function (RouteCollection $routes): void {
        $routes->post('login', 'Auth::login', ['filter' => 'throttle:auth']);
        $routes->post('logout', 'Auth::logout', ['filter' => 'auth']);
        $routes->get('me', 'Auth::me', ['filter' => 'auth']);
        $routes->post('change-password', 'Auth::changePassword', ['filter' => 'auth']);
    });

    // --- users -------------------------------------------------------------
    $routes->group('users', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Users::index', ['filter' => 'permission:users,view']);
        $routes->get('(:num)', 'Users::show/$1', ['filter' => 'permission:users,view']);
        $routes->post('/', 'Users::create', ['filter' => 'permission:users,create']);
        $routes->put('(:num)', 'Users::update/$1', ['filter' => 'permission:users,edit']);
        $routes->patch('(:num)/activate', 'Users::activate/$1', ['filter' => 'permission:users,edit']);
        $routes->patch('(:num)/reset-password', 'Users::resetPassword/$1', ['filter' => 'permission:users,edit']);
        // Soft delete - flips status to inactive, the row is kept forever.
        $routes->delete('(:num)', 'Users::delete/$1', ['filter' => 'permission:users,delete']);
    });

    // --- roles and permissions ---------------------------------------------
    $routes->group('roles', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Roles::index', ['filter' => 'permission:roles,view']);
        $routes->get('(:num)', 'Roles::show/$1', ['filter' => 'permission:roles,view']);
        $routes->post('/', 'Roles::create', ['filter' => 'permission:roles,create']);
        $routes->put('(:num)', 'Roles::update/$1', ['filter' => 'permission:roles,edit']);
        // Kept behind roles:assign rather than roles:edit, so a person who can
        // rename a role cannot also hand that role extra powers.
        $routes->put('(:num)/permissions', 'Roles::assignPermissions/$1', ['filter' => 'permission:roles,assign']);
        $routes->delete('(:num)', 'Roles::delete/$1', ['filter' => 'permission:roles,delete']);
    });

    $routes->get('permissions', 'Permissions::index', ['filter' => ['auth', 'permission:roles,view']]);

    // --- geography ---------------------------------------------------------
    // States are reference data with no add / edit / delete screen, which is why
    // the permission master list has no `states` module. Any signed-in user may
    // read them, because the region screen needs the dropdown.
    $routes->get('states', 'States::index', ['filter' => 'auth']);

    $routes->group('regions', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Regions::index', ['filter' => 'permission:regions,view']);
        $routes->get('(:num)', 'Regions::show/$1', ['filter' => 'permission:regions,view']);
        $routes->post('/', 'Regions::create', ['filter' => 'permission:regions,create']);
        $routes->put('(:num)', 'Regions::update/$1', ['filter' => 'permission:regions,edit']);
        $routes->delete('(:num)', 'Regions::delete/$1', ['filter' => 'permission:regions,delete']);
    });

    $routes->group('distributors', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Distributors::index', ['filter' => 'permission:distributors,view']);
        $routes->get('(:num)', 'Distributors::show/$1', ['filter' => 'permission:distributors,view']);
        $routes->post('/', 'Distributors::create', ['filter' => 'permission:distributors,create']);
        $routes->put('(:num)', 'Distributors::update/$1', ['filter' => 'permission:distributors,edit']);
        $routes->delete('(:num)', 'Distributors::delete/$1', ['filter' => 'permission:distributors,delete']);
    });

    // --- catalogue ---------------------------------------------------------
    $routes->group('brands', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->get('/', 'Brands::index', ['filter' => 'permission:brands,view']);
        $routes->get('(:num)', 'Brands::show/$1', ['filter' => 'permission:brands,view']);
        $routes->post('/', 'Brands::create', ['filter' => 'permission:brands,create']);
        $routes->put('(:num)', 'Brands::update/$1', ['filter' => 'permission:brands,edit']);
        $routes->delete('(:num)', 'Brands::delete/$1', ['filter' => 'permission:brands,delete']);
    });

    $routes->group('products', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        // Declared before (:num) so "price-list" is never read as an id.
        $routes->get('price-list', 'Products::priceList', ['filter' => 'permission:product_mrp,view']);

        $routes->get('/', 'Products::index', ['filter' => 'permission:products,view']);
        $routes->get('(:num)', 'Products::show/$1', ['filter' => 'permission:products,view']);
        $routes->post('/', 'Products::create', ['filter' => 'permission:products,create']);
        $routes->put('(:num)', 'Products::update/$1', ['filter' => 'permission:products,edit']);
        $routes->delete('(:num)', 'Products::delete/$1', ['filter' => 'permission:products,delete']);

        // MRP history
        $routes->get('(:num)/mrp', 'Products::mrpHistory/$1', ['filter' => 'permission:product_mrp,view']);
        $routes->post('(:num)/mrp', 'Products::setMrp/$1', ['filter' => 'permission:product_mrp,create']);
        $routes->get('(:num)/mrp/on-date', 'Products::mrpOnDate/$1', ['filter' => 'permission:product_mrp,view']);
    });

    // --- targets -----------------------------------------------------------
    $routes->group('targets', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        // Declared before (:num) so "types" and "options" are never read as ids.
        // Both feed the two drop-downs at the top of the target screen.
        $routes->get('types', 'Targets::types', ['filter' => 'permission:targets,view']);
        $routes->get('options', 'Targets::options', ['filter' => 'permission:targets,view']);

        $routes->get('/', 'Targets::index', ['filter' => 'permission:targets,view']);
        $routes->get('(:num)', 'Targets::show/$1', ['filter' => 'permission:targets,view']);
        $routes->post('/', 'Targets::create', ['filter' => 'permission:targets,create']);
        $routes->put('(:num)', 'Targets::update/$1', ['filter' => 'permission:targets,edit']);
        $routes->delete('(:num)', 'Targets::delete/$1', ['filter' => 'permission:targets,delete']);
    });

    // --- CSV imports -------------------------------------------------------
    $routes->group('imports', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->get('template', 'Imports::template', ['filter' => 'permission:imports,view']);
        $routes->get('/', 'Imports::index', ['filter' => 'permission:imports,view']);
        $routes->get('(:num)', 'Imports::show/$1', ['filter' => 'permission:imports,view']);
        $routes->get('(:num)/rows', 'Imports::rows/$1', ['filter' => 'permission:imports,view']);

        // multipart/form-data: field "file" holds the CSV, plus optional
        // "effective_from" and "create_missing_brands" text fields.
        $routes->post('products', 'Imports::importProducts', ['filter' => 'permission:products,import']);
        $routes->post('product-mrp', 'Imports::importMrp', ['filter' => 'permission:product_mrp,import']);
    });
});
