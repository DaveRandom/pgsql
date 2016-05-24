<?php declare(strict_types = 1);

namespace Amp\Pgsql\pgsql;

use Amp\Pgsql\Connection;
use Amp\Pgsql\Exceptions\NotImplementedException;
use Amp\Pgsql\Statement as PgsqlStatement;
use Amp\Promise;

abstract class Statement implements PgsqlStatement
{
    public function getConnection(): Connection
    {
        throw new NotImplementedException; // todo
    }

    public function getName(): string
    {
        throw new NotImplementedException; // todo
    }

    public function getSQL(): string
    {
        throw new NotImplementedException; // todo
    }

    public function getTypes(): array
    {
        throw new NotImplementedException; // todo
    }

    public function bind(int $paramNo, $value) /* : void */
    {
        throw new NotImplementedException; // todo
    }

    public function deallocate(): Promise
    {
        throw new NotImplementedException; // todo
    }
}
