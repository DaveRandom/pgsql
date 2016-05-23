<?php declare(strict_types = 1);

namespace Amp\Pgsql\pq;

use Amp\Mutex\Lock;
use Amp\Pgsql\Connection as PgsqlConnection;
use Amp\Pgsql\Cursor as PgsqlCursor;
use Amp\Pgsql\Exception;
use Amp\Promise;
use Amp\Success;
use pq\Connection as pqConnection;
use pq\Result as pqResult;
use function Amp\resolve;

class Cursor implements PgsqlCursor
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
        if ($result->status === pqResult::SINGLE_TUPLE) {
            return $result->fetchRow(self::$fetchStyleMap[$fetchStyle]);
        } else if ($result->status === pqResult::TUPLES_OK) {
            return null;
        }

        throw new Exception($result->errorMessage, $result->status); //todo ex class
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
            throw new Exception('Invalid fetch style number ' . $fetchStyle); //todo ex class
        }

        if ($this->firstResult !== null) {
            $success = new Success($this->getReturnValueFromResult($this->firstResult, $fetchStyle));
            $this->firstResult = null;

            return $success;
        }

        return resolve(function() use($fetchStyle) {
            try {
                yield from $this->connection->pollUntilNotBusy();

                if (!$result = $this->pqConnection->getResult()) {
                    throw new Exception('Failed to get a result'); //todo ex class
                }

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
            throw new \LogicException('Cannot close cursor: already closed'); // todo ex class?
        }

        $this->closed = true;
        $this->releaseLock();
    }
}
