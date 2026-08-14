<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Application settings for the Tak Tak API.
 *
 * Every property can be overridden from `.env` with a `taktak.` prefix, e.g.
 *
 *     taktak.jwtAccessSecret = 'a-long-random-string'
 *     taktak.jwtAccessTtl    = 15m
 */
class Taktak extends BaseConfig
{
    /** URI prefix every API route is mounted under. */
    public string $apiPrefix = 'api/v1';

    // --- JWT ---------------------------------------------------------------

    public string $jwtAccessSecret = 'change-me-access-secret-at-least-32-chars';
    public string $jwtRefreshSecret = 'change-me-refresh-secret-at-least-32-chars';

    /** Duration strings: 30s, 15m, 12h, 7d. */
    public string $jwtAccessTtl  = '15m';
    public string $jwtRefreshTtl = '7d';

    // --- First-run super admin (used by the seeder only) -------------------

    public string $superAdminName     = 'Super Admin';
    public string $superAdminEmail    = 'superadmin@taktak.com';
    public string $superAdminPassword = 'SuperAdmin@123';

    // --- Passwords ---------------------------------------------------------

    /** bcrypt work factor. 12 matches the Node service this API replaces. */
    public int $bcryptCost = 12;

    // --- CSV uploads -------------------------------------------------------

    public int $maxUploadSizeMb = 10;
    public int $maxImportRows   = 50000;

    /** Absolute path uploaded CSV files are staged in. */
    public string $uploadDir = '';

    // --- Rate limits (requests per window, per IP) -------------------------

    public int $rateLimitGeneral = 300;
    public int $rateLimitAuth    = 20;
    public int $rateLimitWindow  = 900; // seconds

    public function __construct()
    {
        parent::__construct();

        if ($this->uploadDir === '') {
            $this->uploadDir = WRITEPATH . 'uploads';
        }
    }
}
