<?php

namespace Amp\Pgsql;

use Amp\Pgsql\Exceptions\InvalidOperationException;
use Amp\Pgsql\Exceptions\ResultFetchFailureException;
use Amp\Pgsql\Exceptions\ServerProtocolViolationException;
use Amp\Pgsql\Exceptions\UnexpectedResultStatusException;
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
     * @throws InvalidOperationException
     * @throws ResultFetchFailureException
     * @throws ServerProtocolViolationException
     * @throws UnexpectedResultStatusException
     */
    public function fetchAll(int $fetchStyle = self::FETCH_DEFAULT): Promise;

    /**
     * Fetch a single pending row from the server and return it
     *
     * @param int $fetchStyle
     * @return Promise
     * @throws InvalidOperationException
     * @throws ResultFetchFailureException
     * @throws ServerProtocolViolationException
     * @throws UnexpectedResultStatusException
     */
    public function fetchRow(int $fetchStyle = self::FETCH_DEFAULT): Promise;

    /**
     * Close the cursor and free the associated connection for use
     *
     * @throws InvalidOperationException
     */
    public function close() /* : void */;
}
