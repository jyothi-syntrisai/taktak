<?php

declare(strict_types=1);

namespace App\Filters;

use App\Exceptions\ApiException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Per-IP request budget, backed by the framework throttler.
 *
 *     ['filter' => 'throttle']         general limit
 *     ['filter' => 'throttle:auth']    the tighter limit for credential routes
 *
 * Credential endpoints get their own bucket so a burst of ordinary reads can
 * never use up the login allowance, and vice versa.
 */
class ThrottleFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $config = config('Taktak');
        $bucket = $arguments[0] ?? 'general';

        $capacity = $bucket === 'auth' ? $config->rateLimitAuth : $config->rateLimitGeneral;

        // Cache keys may not contain {}()/\@: - an IPv6 address would.
        $ip  = preg_replace('/[^A-Za-z0-9]/', '_', $request->getIPAddress() ?: 'unknown');
        $key = 'throttle_' . $bucket . '_' . $ip;

        // check($key, $capacity, $seconds) refills `capacity` tokens per window.
        if (! service('throttler')->check($key, $capacity, $config->rateLimitWindow)) {
            throw new ApiException(429, 'Too many attempts. Please try again later.');
        }

        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
