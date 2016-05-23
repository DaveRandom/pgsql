<?php

namespace Amp\Pgsql;

use Amp\Promise;

interface Cursor
{
    const FETCH_DEFAULT = 0;
    const FETCH_ARRAY   = 1;
    const FETCH_ASSOC   = 2;
    const FETCH_OBJECT  = 3;

    /**
     * Get the Connection object associated with this cursor
     *
     * @return Connection
     */
    public function getConnection(): Connection;

    /**
     * Buffer all pending data from the server and return it as an array of rows
     *
     * @param int $fetchStyle
     * @return Promise<array>
     */
    public function fetchAll(int $fetchStyle = self::FETCH_DEFAULT): Promise;

    /**
     * Fetch a single pending row from the server and return it
     *
     * @param int $fetchStyle
     * @return Promise
     */
    public function fetchRow(int $fetchStyle = self::FETCH_DEFAULT): Promise;

    /**
     * Close the cursor and free the associated connection for use
     */
    public function close() /* : void */;
}
