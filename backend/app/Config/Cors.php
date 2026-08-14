<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cross-Origin Resource Sharing settings.
 *
 * The API is token based - the browser sends `Authorization`, not a cookie - so
 * credentials are off and any origin may call it. Narrow `allowedOrigins` to
 * your own front-end hosts before going to production.
 */
class Cors extends BaseConfig
{
    /**
     * @var array<string, mixed>
     */
    public array $default = [
        'allowedOrigins'         => ['*'],
        'allowedOriginsPatterns' => [],
        'supportsCredentials'    => false,
        'allowedHeaders'         => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],
        'exposedHeaders'         => ['Content-Disposition'],
        'allowedMethods'         => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'maxAge'                 => 7200,
    ];
}
