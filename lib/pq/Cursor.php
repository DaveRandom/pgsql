<?php declare(strict_types = 1);

namespace Amp\Pgsql\pq;

use Amp\Mutex\Lock;
use Amp\Pgsql\Connection as PgsqlConnection;
use Amp\Pgsql\Cursor as PgsqlCursor;
use Amp\Pgsql\Exceptions\InvalidOperationException;
use Amp\Promise;
use Amp\Success;
use pq\Connection as pqConnection;
use pq\Result as pqResult;
use function Amp\resolve;

final class Cursor implements PgsqlCursor
{
    private $connection;
    private $pqConnection;
    private $firstResult;
    private $lock;

    private $lockReleased = false;
    private $closed = false;

    private static $fetchStyleMap = [
        self::FETCH_ARRAY => pqResult::FETCH_ARRAY,
        self::FETCH_ASSOC => pqResult::FETCH_ASSOC,
        self::FETCH_OBJECT => pqResult::FETCH_OBJECT,
    ];

    public function __construct(Connection $connection, pqConnection $pqConnection, pqResult $firstResult, Lock $lock)
    {
        $this->connection = $connection;
        $this->pqConnection = $pqConnection;
        $this->lock = $lock;
        $this->firstResult = $firstResult;
    }

    public function __destruct()
    {
        $this->releaseLock();
    }

    private function releaseLock()
    {
        if (!$this->lockReleased) {
            $this->lock->release();
            $this->lockReleased = true;
        }
    }

    private function getReturnValueFromResult(pqResult $result, int $fetchStyle)
    {
        if ($result->status === pqResult::TUPLES_OK) {
            return null;
        }

        return $result->fetchRow(self::$fetchStyleMap[$fetchStyle]);
    }

    public function getConnection(): PgsqlConnection
    {
        return $this->connection;
    }

    public function fetchAll(int $fetchStyle = self::FETCH_DEFAULT): Promise
    {
        if ($fetchStyle === self::FETCH_DEFAULT) {
            $fetchStyle = $this->connection->getOption(PgsqlConnection::OPTION_DEFAULT_FETCH_TYPE);
        }

        return resolve(function() use($fetchStyle) {
            $rows = [];

            while ($row = yield $this->fetchRow($fetchStyle)) {
                $rows[] = $row;
            }

            $this->releaseLock();

            return $rows;
        });
    }

    public function fetchRow(int $fetchStyle = self::FETCH_DEFAULT): Promise
    {
        if ($fetchStyle === self::FETCH_DEFAULT) {
            $fetchStyle = $this->connection->getOption(PgsqlConnection::OPTION_DEFAULT_FETCH_TYPE);
        }

        if (!isset(self::$fetchStyleMap[$fetchStyle])) {
            throw new InvalidOperationException('Invalid fetch style number ' . $fetchStyle);
        }

        /* This looks like the wrong thing to do,  but the method that created the cursor needs to fetch the first
           result from the wire so that it can throw when a the operation fails in some way.   It makes more sense
           to me if we throw there than if we return what appears to be a valid cursor, only to have it throw when
           attempting to get data from it. */
        if ($this->firstResult !== null) {
            $success = new Success($this->getReturnValueFromResult($this->firstResult, $fetchStyle));
            $this->firstResult = null;

            return $success;
        }

        return resolve(function() use($fetchStyle) {
            try {
                yield from $this->connection->pollUntilNotBusy();

                $result = $this->connection->getResultAndThrowIfNotOK();

                return $this->getReturnValueFromResult($result, $fetchStyle);
            } catch (\Throwable $e) {
                $this->releaseLock();
                throw $e;
            }
        });
    }

    public function close()
    {
        if ($this->closed) {
            throw new InvalidOperationException('Cannot close cursor: already closed');
        }

        $this->closed = true;
        $this->releaseLock();
    }
}
