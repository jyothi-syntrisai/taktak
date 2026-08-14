<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class Health extends BaseApiController
{
    /** Liveness probe: 200 while the database answers, 503 when it does not. */
    public function index(): ResponseInterface
    {
        $database = 'up';

        try {
            db_connect()->query('SELECT 1');
        } catch (Throwable) {
            $database = 'down';
        }

        return $this->json($database === 'up' ? 200 : 503, [
            'success'        => $database === 'up',
            'status'         => $database === 'up' ? 'ok' : 'degraded',
            'database'       => $database,
            'uptime_seconds' => (int) (microtime(true) - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))),
            'timestamp'      => gmdate('c'),
        ]);
    }
}
