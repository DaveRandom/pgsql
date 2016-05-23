<?php declare(strict_types = 1);

namespace Amp\Pgsql\pgsql;

use Amp\Pgsql\Connection as PgsqlConnection;
use Amp\Pgsql\Cursor as PgsqlCursor;
use Amp\Promise;

class Cursor implements PgsqlCursor
{
    public function getConnection(): PgsqlConnection
    {
        // TODO: Implement getConnection() method.
    }

    public function fetchAll(int $fetchStyle = PgsqlCursor::FETCH_ASSOC): Promise
    {
        // TODO: Implement fetchAll() method.
    }

    public function fetchRow(int $fetchStyle = PgsqlCursor::FETCH_ASSOC): Promise
    {
        // TODO: Implement fetchRow() method.
    }

    public function close()/*: void*/
    {
        // TODO: Implement close() method.
    }
}
