<?php

namespace Amp\Pgsql\pgsql;

use Amp\Pgsql\Exceptions\NotImplementedException;
use Amp\Pgsql\QueryStatement as PgsqlQueryStatement;
use Amp\Promise;
use function Amp\resolve;

class QueryStatement extends Statement implements PgsqlQueryStatement
{
    public function execute(array $params = null): Promise
    {
        throw new NotImplementedException; // todo
    }
}
