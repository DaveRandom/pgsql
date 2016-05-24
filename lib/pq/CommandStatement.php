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
        // fixme: redundant check is redundant?
        if ($this->connection->getConnectionState() !== PgsqlConnection::STATE_CONNECTED) {
            throw new InvalidOperationException('Cannot execute commands before connection');
        }

        return $this->mutex->withLock(function() use($params) {
            yield from $this->connection->callAsyncPqMethodAndAwaitResult([$this->pqStatement, 'execAsync'], [$params]);

            $result = $this->connection->getResultAndThrowIfNotOK();

            return $result->affectedRows;
        });
    }
}