<?php declare(strict_types = 1);

namespace Amp\Pgsql\pgsql;

use Amp\Pgsql\Connection as PgsqlConnection;
use Amp\Pgsql\Cursor as PgsqlCursor;
use Amp\Pgsql\Exceptions\NotImplementedException;
use Amp\Promise;

class Cursor implements PgsqlCursor
{
    public function getConnection(): PgsqlConnection
    {
        throw new NotImplementedException; // todo
    }

    public function fetchAll(int $fetchStyle = PgsqlCursor::FETCH_ASSOC): Promise
    {
        throw new NotImplementedException; // todo
    }

    public function fetchRow(int $fetchStyle = PgsqlCursor::FETCH_ASSOC): Promise
    {
        throw new NotImplementedException; // todo
    }

    public function close()/*: void*/
    {
        throw new NotImplementedException; // todo
    }
}
