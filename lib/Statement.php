<?php

namespace Amp\Pgsql;

use Amp\Promise;

interface Statement
{
    /**
     * Get the Connection object associated with this statement
     *
     * @return Connection
     */
    public function getConnection(): Connection;

    /**
     * Get the name of this statement
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the SQL string used to create this statement
     *
     * @return string
     */
    public function getSQL(): string;

    /**
     * Get the types of the parameters for this statement that were specified when it was created
     *
     * @return array
     */
    public function getTypes(): array;

    /**
     * Bind a value to a parameter
     *
     * @param int $paramNo
     * @param mixed $value
     */
    public function bind(int $paramNo, $value) /* : void */;

    /**
     * Deallocate a statement on the server. This allows the server to free the resources associated with the statement,
     * and enables the statement name to be reused.
     *
     * @return Promise<void>
     */
    public function deallocate(): Promise;

    /**
     * Execute a command statement and return the number of affected rows
     *
     * @param array|null $params
     * @return Promise<int>
     */
    public function executeCommand(array $params = null): Promise;

    /**
     * @param array|null $params
     * @return Promise
     */
    public function executeQuery(array $params = null): Promise;
}
