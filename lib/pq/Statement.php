<?php

namespace Amp\Pgsql\pq;

use Amp\Mutex\Mutex;
use Amp\Pgsql\Connection as PgsqlConnection;
use Amp\Pgsql\Exceptions\NotImplementedException;
use Amp\Pgsql\Statement as PgsqlStatement;
use Amp\Promise;
use pq\Connection as pqConnection;
use pq\Statement as pqStatement;
use function Amp\resolve;

abstract class Statement implements PgsqlStatement
{
    protected $connection;
    protected $pqConnection;
    protected $pqStatement;
    protected $mutex;

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

    public function getConnection(): PgsqlConnection
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
        throw new NotImplementedException; //todo
    }
}
