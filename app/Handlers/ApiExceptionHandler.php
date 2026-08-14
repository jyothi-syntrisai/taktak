<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Exceptions\ApiException;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Renders every uncaught exception as the same JSON envelope the rest of the
 * API uses, so a client never has to parse an HTML error page:
 *
 *     { "success": false, "message": "...", "errors": [...] }
 */
class ApiExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        $details = null;

        if ($exception instanceof ApiException) {
            $message = $exception->getMessage();
            $details = $exception->getDetails();
        } elseif ($exception instanceof PageNotFoundException) {
            $statusCode = 404;
            $message    = 'Route ' . $request->getMethod() . ' /' . ltrim($request->getUri()->getPath(), '/') . ' not found';
        } elseif ($exception instanceof DatabaseException) {
            [$statusCode, $message] = $this->translateDatabaseError($exception);
        } else {
            $message = $exception->getMessage();
        }

        if ($statusCode < 400 || $statusCode > 599) {
            $statusCode = 500;
        }

        // Never leak an internal failure's wording to the caller in production.
        if ($statusCode >= 500 && ENVIRONMENT === 'production') {
            $message = 'Something went wrong';
        }

        $payload = [
            'success' => false,
            'message' => $message !== '' ? $message : 'Something went wrong',
        ];

        if ($details !== null && $details !== []) {
            $payload['errors'] = $details;
        }

        if (ENVIRONMENT !== 'production' && $statusCode >= 500) {
            $payload['exception'] = $exception::class;
            $payload['file']      = clean_path($exception->getFile()) . ':' . $exception->getLine();
        }

        $response
            ->setStatusCode($statusCode)
            ->setContentType('application/json')
            ->setBody(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->send();

        exit($exitCode);
    }

    /**
     * MySQL constraint violations are user errors, not server errors - a
     * duplicate SKU should read as 409, not 500.
     *
     * @return array{0: int, 1: string}
     */
    private function translateDatabaseError(DatabaseException $exception): array
    {
        $text = $exception->getMessage();

        if (str_contains($text, '1062') || stripos($text, 'Duplicate entry') !== false) {
            return [409, 'A record with these details already exists'];
        }

        if (str_contains($text, '1451') || str_contains($text, '1452') || stripos($text, 'foreign key') !== false) {
            return [409, 'This record is referenced by other records, or points at a record that does not exist'];
        }

        return [500, ENVIRONMENT === 'production' ? 'Database error' : $text];
    }
}
