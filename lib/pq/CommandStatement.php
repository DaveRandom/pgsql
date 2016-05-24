<?php

namespace Amp\Pgsql\pq;

use Amp\Pgsql\CommandStatement as PgsqlCommandStatement;
use Amp\Pgsql\Connection as PgsqlConnection;
use Amp\Pgsql\Exceptions\InvalidOperationException;
use Amp\Promise;
use function Amp\resolve;

final class CommandStatement extends Statement implements PgsqlCommandStatement
{
    public function execute(array $params = null): Promise
    {
        return $this->mutex->withLock(function() use($params) {
            if ($this->connection->getConnectionState() < PgsqlConnection::STATE_OK) {
                throw new InvalidOperationException('Cannot execute statement: invalid connection state');
            }

            yield from $this->connection->callAsyncPqMethodAndAwaitResult([$this->pqStatement, 'execAsync'], [$params]);

            $result = $this->connection->getResultAndThrowIfNotOK();

            return $result->affectedRows;
        });
    }
}
