<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class Docs extends BaseApiController
{
    /** Swagger UI, pointed at the spec below it. */
    public function index(): ResponseInterface
    {
        return $this->response
            ->setStatusCode(200)
            ->setContentType('text/html; charset=utf-8')
            ->setBody(view('docs/swagger', [
                'specUrl' => rtrim(base_url(config('Taktak')->apiPrefix . '/docs/openapi.json'), '/'),
            ]));
    }

    /** The machine-readable description - import this into Postman or Insomnia. */
    public function openapi(): ResponseInterface
    {
        return $this->response
            ->setStatusCode(200)
            ->setContentType('application/json')
            ->setBody(json_encode(
                require APPPATH . 'Docs/openapi.php',
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
            ));
    }
}
