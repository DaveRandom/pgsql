<?php

namespace Amp\Pgsql\pq;

use Amp\Mutex\Lock;
use Amp\Mutex\Mutex;
use Amp\Pgsql\Connection as PostgreSQLConnection;
use Amp\Pgsql\Statement as PostgreSQLStatement;
use Amp\Promise;
use function Amp\resolve;
use pq\Connection as pqConnection;
use pq\Statement as pqStatement;
use pq\Result as pqResult;

/**
 * Class Statement
 *
 * @package pqAsync
 */
class Statement implements PostgreSQLStatement
{
    private $connection;
    private $pqConnection;
    private $pqStatement;
    private $mutex;
    private $sql;
    private $types;

    public function __construct(
        Connection $connection,
        pqConnection $pqConnection,
        pqStatement $pqStatement,
        Mutex $mutex,
        string $sql,
        array $types = null
    ) {
        $this->connection = $connection;
        $this->pqConnection = $pqConnection;
        $this->pqStatement = $pqStatement;
        $this->mutex = $mutex;
        $this->sql = $sql;
        $this->types = $types ?? [];
    }

    public function getConnection(): PostgreSQLConnection
    {
        return $this->connection;
    }

    public function getName(): string
    {
        return $this->pqStatement->name;
    }

    public function getSQL(): string
    {
        return $this->sql;
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function bind(int $paramNo, $value)
    {
        $this->pqStatement->bind($paramNo, $value);
    }

    public function deallocate(): Promise
    {
        throw new \Exception('todo'); //todo
    }

    public function executeCommand(array $params = null): Promise
    {
        if ($this->connection->getConnectionState() !== PostgreSQLConnection::STATE_CONNECTED) {
            throw new \LogicException('Cannot execute commands before connection');
        }

        return $this->mutex->withLock(function() use($params) {
            yield from $this->connection->callAsyncPqMethodAndAwaitResult([$this->pqStatement, 'execAsync'], [$params]);

            if (!$result = $this->pqConnection->getResult()) {
                throw new \RuntimeException('Failed to get a result'); //todo ex class
            }

            if ($result->status !== pqResult::COMMAND_OK) {
                throw new \RuntimeException($result->errorMessage, $result->status); // todo ex class, handle warnings somehow
            }

            return $result->affectedRows;
        });
    }

    public function executeQuery(array $params = null): Promise
    {
        if ($this->connection->getConnectionState() !== PostgreSQLConnection::STATE_CONNECTED) {
            throw new \LogicException('Cannot execute commands before connection');
        }

        return resolve(function() use($params) {
            /** @var Lock $lock */
            $lock = yield $this->mutex->getLock();

            try {
                yield from $this->connection->callAsyncPqMethodAndAwaitResult([$this->pqStatement, 'execAsync'], [$params]);

                if (!$result = $this->pqConnection->getResult()) {
                    throw new \RuntimeException('Failed to get a result'); //todo ex class
                }

                if ($result->status !== pqResult::SINGLE_TUPLE && $result->status !== pqResult::TUPLES_OK) {
                    throw new \RuntimeException($result->errorMessage, $result->status); //todo ex class, handle warnings somehow
                }

                return new Cursor($this->connection, $this->pqConnection, $result, $lock);
            } catch (\Throwable $e) {
                $lock->release();
                throw $e;
            }
        });
    }
}
