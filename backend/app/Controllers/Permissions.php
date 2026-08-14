<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PermissionService;
use CodeIgniter\HTTP\ResponseInterface;

class Permissions extends BaseApiController
{
    /**
     * The whole permission master list, grouped by module - exactly the shape
     * the role screen needs to draw its tick boxes.
     */
    public function index(): ResponseInterface
    {
        return $this->ok((new PermissionService())->groupedMasterList());
    }
}
