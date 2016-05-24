<?php

namespace Amp\Pgsql\pgsql;

use Amp\Pgsql\CommandStatement as PgsqlCommandStatement;
use Amp\Pgsql\Exceptions\NotImplementedException;
use Amp\Promise;
use function Amp\resolve;

final class CommandStatement extends Statement implements PgsqlCommandStatement
{
    public function execute(array $params = null): Promise
    {
        throw new NotImplementedException; // todo
    }
}
