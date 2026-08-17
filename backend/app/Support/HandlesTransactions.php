<?php

declare(strict_types=1);

namespace App\Support;

use CodeIgniter\Database\BaseConnection;
use Throwable;

/**
 * Runs a closure inside one database transaction and rolls back on any
 * exception - including the ApiExceptions the services throw for business
 * rules, so a half-applied change never survives a rejected request.
 */
trait HandlesTransactions
{
    protected function db(): BaseConnection
    {
        return db_connect();
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    protected function transaction(callable $callback): mixed
    {
        $db = $this->db();
        $db->transBegin();

        try {
            $result = $callback();
            $db->transCommit();

            return $result;
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }
    }
}
