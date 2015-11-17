<?php

namespace Amp\Pgsql;

interface Db {
    /**
     * Dispatch an unprepared query asynchronously
     *
     * @param string $query
     * @return \Amp\Promise
     */
    public function query(string $query): \Amp\Promise;

    /**
     * Dispatch a prepared query asynchronously
     *
     * @param string $query
     * @param array $params
     * @return \Amp\Promise
     */
    public function queryParams(string $query, array $params): \Amp\Promise;

    /**
     * Prepare a query asynchronously
     *
     * @param string $statementName
     * @param string $query
     * @return \Amp\Promise
     */
    public function prepare(string $statementName, string $query): \Amp\Promise;

    /**
     * Execute a previously prepared statement asynchronously
     *
     * @param string $statementName
     * @param array $params
     * @return \Amp\Promise
     */
    public function execute(string $statementName, array $params): \Amp\Promise;
}

