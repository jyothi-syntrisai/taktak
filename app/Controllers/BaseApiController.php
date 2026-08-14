<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\ApiException;
use App\Libraries\Auth;
use App\Support\Pagination;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Every controller answers with the same envelope:
 *
 *     { "success": true,  "message": "...", "data": ... , "meta": {...} }
 *     { "success": false, "message": "...", "errors": [{ "field", "message" }] }
 */
abstract class BaseApiController extends Controller
{
    protected Auth $auth;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);

        $this->auth = service('auth');
    }

    // -----------------------------------------------------------------------
    // Responses
    // -----------------------------------------------------------------------

    protected function ok(mixed $data, string $message = 'Success'): ResponseInterface
    {
        return $this->json(200, ['success' => true, 'message' => $message, 'data' => $data]);
    }

    protected function created(mixed $data, string $message = 'Created successfully'): ResponseInterface
    {
        return $this->json(201, ['success' => true, 'message' => $message, 'data' => $data]);
    }

    /**
     * @param list<mixed>                                                     $items
     * @param array{page: int, limit: int, total: int, total_pages: int}      $meta
     */
    protected function paginated(array $items, array $meta, string $message = 'Success'): ResponseInterface
    {
        return $this->json(200, [
            'success' => true,
            'message' => $message,
            'data'    => $items,
            'meta'    => $meta,
        ]);
    }

    /** A 200 that carries no resource - password changes, logout, and so on. */
    protected function noContentOk(string $message = 'Success'): ResponseInterface
    {
        return $this->json(200, ['success' => true, 'message' => $message, 'data' => null]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function json(int $status, array $payload): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($payload);
    }

    // -----------------------------------------------------------------------
    // Input
    // -----------------------------------------------------------------------

    /**
     * The request body as an array, whether it arrived as JSON or as form
     * fields, with every string trimmed.
     *
     * @return array<string, mixed>
     */
    protected function body(): array
    {
        $raw = $this->request->getBody();

        if (is_string($raw) && trim($raw) !== '' && str_contains($this->request->getHeaderLine('Content-Type'), 'json')) {
            $decoded = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ApiException::badRequest('Request body is not valid JSON');
            }

            return self::trimStrings(is_array($decoded) ? $decoded : []);
        }

        $post = $this->request->getPost();

        return self::trimStrings(is_array($post) ? $post : []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function queryParams(): array
    {
        return self::trimStrings($this->request->getGet() ?? []);
    }

    /**
     * Runs the body through the validation service and hands back only the
     * fields that were actually sent, so a partial update stays partial.
     *
     * @param array<string, string|array<string, mixed>> $rules
     * @param array<string, array<string, string>>       $messages
     *
     * @return array<string, mixed>
     */
    protected function validateBody(array $rules, array $messages = []): array
    {
        $data = $this->body();

        $validation = service('validation');
        $validation->reset();
        $validation->setRules($rules, $messages);

        if (! $validation->run($data)) {
            throw ApiException::unprocessable('Validation failed', self::formatErrors($validation->getErrors()));
        }

        return array_intersect_key($data, $rules);
    }

    /**
     * Refuses an update request that carries nothing to update.
     *
     * @param array<string, mixed> $input
     * @param list<string>         $fields
     */
    protected function requireAnyOf(array $input, array $fields): void
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $input)) {
                return;
            }
        }

        throw ApiException::unprocessable('Validation failed', [
            ['field' => '(root)', 'message' => 'No fields to update'],
        ]);
    }

    /**
     * Reads a list of positive integers - `state_ids`, `region_ids`,
     * `permission_ids`. CodeIgniter's validation library has no rule for a JSON
     * array of ids, so it is checked here.
     *
     * @param array<string, mixed> $input
     *
     * @return list<int>|null null when the field was not sent at all
     */
    protected function idList(array $input, string $field, bool $required, int $minItems = 0): ?array
    {
        if (! array_key_exists($field, $input)) {
            if ($required) {
                throw ApiException::unprocessable('Validation failed', [
                    ['field' => $field, 'message' => "{$field} is required"],
                ]);
            }

            return null;
        }

        $value = $input[$field];

        if (! is_array($value)) {
            throw ApiException::unprocessable('Validation failed', [
                ['field' => $field, 'message' => "{$field} must be an array of ids"],
            ]);
        }

        $ids = [];

        foreach ($value as $item) {
            if (! is_numeric($item) || (int) $item < 1 || (float) $item != (int) $item) {
                throw ApiException::unprocessable('Validation failed', [
                    ['field' => $field, 'message' => "{$field} must contain positive whole numbers only"],
                ]);
            }

            $ids[] = (int) $item;
        }

        $ids = array_values(array_unique($ids));

        if (count($ids) < $minItems) {
            throw ApiException::unprocessable('Validation failed', [
                ['field' => $field, 'message' => "Select at least {$minItems} item(s)"],
            ]);
        }

        return $ids;
    }

    /**
     * @return array{page: int, limit: int, offset: int, search: string|null, status: string|null, sort_by: string|null, sort_dir: string}
     */
    protected function pagination(int $defaultLimit = Pagination::DEFAULT_LIMIT, int $maxLimit = Pagination::MAX_LIMIT): array
    {
        return Pagination::fromQuery($this->queryParams(), $defaultLimit, $maxLimit);
    }

    /** A positive integer route parameter, or a 404 if it is anything else. */
    protected function routeId(int|string $id): int
    {
        if (! is_numeric($id) || (int) $id < 1) {
            throw ApiException::unprocessable('Validation failed', [
                ['field' => 'id', 'message' => 'id must be a positive whole number'],
            ]);
        }

        return (int) $id;
    }

    /** The id of the signed-in user, for created_by / updated_by. */
    protected function actorId(): int
    {
        return $this->auth->id();
    }

    /** A YYYY-MM-DD value from the query string, validated. */
    protected function requireIsoDateQuery(string $field): string
    {
        $value = (string) ($this->queryParams()[$field] ?? '');

        if (! self::isIsoDate($value)) {
            throw ApiException::unprocessable('Validation failed', [
                ['field' => $field, 'message' => "{$field} must be a real date in YYYY-MM-DD format"],
            ]);
        }

        return $value;
    }

    public static function isIsoDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @param array<string, string> $errors field => message
     *
     * @return list<array{field: string, message: string}>
     */
    private static function formatErrors(array $errors): array
    {
        $formatted = [];

        foreach ($errors as $field => $message) {
            $formatted[] = ['field' => $field, 'message' => $message];
        }

        return $formatted;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function trimStrings(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = trim($value);
            } elseif (is_array($value)) {
                $data[$key] = self::trimStrings($value);
            }
        }

        return $data;
    }
}
