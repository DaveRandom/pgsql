<?php

namespace Amp\Pgsql\pq;

use Amp\Mutex\Lock;
use Amp\Pgsql\Connection as PgsqlConnection;
use Amp\Pgsql\Exceptions\InvalidOperationException;
use Amp\Pgsql\QueryStatement as PgsqlQueryStatement;
use Amp\Promise;
use function Amp\resolve;

class QueryStatement extends Statement implements PgsqlQueryStatement
{
    public function execute(array $params = null): Promise
    {
        return resolve(function() use($params) {
            /** @var Lock $lock */
            $lock = yield $this->mutex->getLock();

            if ($this->connection->getConnectionState() < PgsqlConnection::STATE_OK) {
                throw new InvalidOperationException('Cannot execute statement: invalid connection state');
            }

            try {
                yield from $this->connection->callAsyncPqMethodAndAwaitResult([$this->pqStatement, 'execAsync'], [$params]);

                $result = $this->connection->getResultAndThrowIfNotOK();

                return new Cursor($this->connection, $this->pqConnection, $result, $lock);
            } catch (\Throwable $e) {
                $lock->release();
                throw $e;
            }
        });
    }
}
