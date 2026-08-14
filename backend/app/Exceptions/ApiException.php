<?php

declare(strict_types=1);

namespace App\Exceptions;

use CodeIgniter\Exceptions\HTTPExceptionInterface;
use RuntimeException;

/**
 * The one exception type the API throws on purpose.
 *
 * It implements HTTPExceptionInterface so the framework picks the HTTP status
 * off the exception code instead of defaulting everything to 500, and it
 * carries an optional `details` payload for field-level validation errors.
 */
class ApiException extends RuntimeException implements HTTPExceptionInterface
{
    /** @var array<int|string, mixed>|null */
    protected ?array $details;

    /**
     * @param array<int|string, mixed>|null $details
     */
    public function __construct(int $statusCode, string $message, ?array $details = null)
    {
        parent::__construct($message, $statusCode);
        $this->details = $details;
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /**
     * @param array<int|string, mixed>|null $details
     */
    public static function badRequest(string $message = 'Bad request', ?array $details = null): self
    {
        return new self(400, $message, $details);
    }

    public static function unauthorized(string $message = 'Authentication required'): self
    {
        return new self(401, $message);
    }

    public static function forbidden(string $message = 'You do not have permission to perform this action'): self
    {
        return new self(403, $message);
    }

    public static function notFound(string $message = 'Resource not found'): self
    {
        return new self(404, $message);
    }

    /**
     * @param array<int|string, mixed>|null $details
     */
    public static function conflict(string $message = 'Resource already exists', ?array $details = null): self
    {
        return new self(409, $message, $details);
    }

    /**
     * @param array<int|string, mixed>|null $details
     */
    public static function unprocessable(string $message = 'Validation failed', ?array $details = null): self
    {
        return new self(422, $message, $details);
    }

    public static function internal(string $message = 'Something went wrong'): self
    {
        return new self(500, $message);
    }
}
