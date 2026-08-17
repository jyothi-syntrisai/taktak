<?php

declare(strict_types=1);

namespace Config;

use App\Libraries\Auth;
use App\Libraries\JwtService;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Application-specific services. `service('auth')` and `service('jwt')` are
 * shared for the length of one request.
 */
class Services extends BaseService
{
    /**
     * Identity of the caller for the current request, populated by the auth filter.
     */
    public static function auth(bool $getShared = true): Auth
    {
        if ($getShared) {
            return static::getSharedInstance('auth');
        }

        return new Auth();
    }

    /**
     * Signing and verification of the access token.
     */
    public static function jwt(bool $getShared = true): JwtService
    {
        if ($getShared) {
            return static::getSharedInstance('jwt');
        }

        return new JwtService(config('Taktak'));
    }
}
