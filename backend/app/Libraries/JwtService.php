<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\ApiException;
use Config\Taktak;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;
use UnexpectedValueException;

/**
 * Issues and verifies the access token.
 *
 * The token carries the flattened permission list resolved at login, so a
 * per-request permission check never hits the database. It is valid for 24
 * hours and there is no server-side revocation - signing out is purely a
 * client-side action of discarding the token.
 */
class JwtService
{
    private const ALGORITHM = 'HS256';

    private Taktak $config;

    public function __construct(?Taktak $config = null)
    {
        $this->config = $config ?? config('Taktak');
    }

    /**
     * @param array{sub: string, email: string, role: string, role_id: int, permissions: list<string>} $payload
     */
    public function signAccessToken(array $payload): string
    {
        return $this->sign($payload, $this->config->jwtAccessSecret, $this->config->jwtAccessTtl);
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyAccessToken(string $token): array
    {
        return $this->verify($token, $this->config->jwtAccessSecret, 'Access token');
    }

    /**
     * Turns '15m' / '7d' / '30s' / '12h' into seconds. A plain number is
     * already seconds.
     */
    public function ttlToSeconds(string $ttl): int
    {
        if (preg_match('/^(\d+)\s*([smhd])?$/i', trim($ttl), $matches) !== 1) {
            throw new UnexpectedValueException("Cannot read the duration \"{$ttl}\"");
        }

        $amount = (int) $matches[1];

        return match (strtolower($matches[2] ?? 's')) {
            'm'     => $amount * 60,
            'h'     => $amount * 3600,
            'd'     => $amount * 86400,
            default => $amount,
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sign(array $payload, string $secret, string $ttl): string
    {
        $now = time();

        return JWT::encode(
            $payload + ['iat' => $now, 'exp' => $now + $this->ttlToSeconds($ttl)],
            $secret,
            self::ALGORITHM,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function verify(string $token, string $secret, string $label): array
    {
        try {
            $decoded = JWT::decode($token, new Key($secret, self::ALGORITHM));
        } catch (ExpiredException) {
            throw ApiException::unauthorized("{$label} has expired");
        } catch (Throwable) {
            // Anything else - bad signature, wrong segment count, malformed
            // base64 - is the same answer to the caller: the token is no good.
            throw ApiException::unauthorized("Invalid {$label}");
        }

        return json_decode(json_encode($decoded), true);
    }
}
