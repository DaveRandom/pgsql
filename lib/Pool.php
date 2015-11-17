<?php

namespace \Amp\Pgsql;

class Pool implements Db {
    use \Amp\Struct;

    private $connectionString;
    private $poolSize;
    private $maxConnections = 100;
    private $maxOutstandingQueries = 256;

    public function __construct(string $connectionString, int $poolSize) {
        $this->connectionString = $connectionString;
        $this->poolSize = $poolSize;
    }

    public function setOption(string $option, $value) {
        switch ($option) {
            case "maxPoolSize":
                $this->maxPoolSize = (int) $value;
                break;
            case "maxOutstandingQueries":
                // @todo iterate over all existing connections
                // and set the option on each
                break;
            default:
                throw new \DomainException(
                    sprintf("Unknown option key: %s",$option)
                );
        }
    }

    /**
     * Dispatch an unprepared query asynchronously
     *
     * @param string $query
     * @return \Amp\Promise
     */
    public function query(string $query): \Amp\Promise {
        // @todo delegate to pool member
    }

    /**
     * Dispatch a prepared query asynchronously
     *
     * @param string $query
     * @param array $params
     * @return \Amp\Promise
     */
    public function queryParams(string $query, array $params): \Amp\Promise {
        // @todo delegate to pool member
    }

    /**
     * Prepare a query asynchronously
     *
     * @param string $statementName
     * @param string $query
     * @return \Amp\Promise
     */
    public function prepare(string $statementName, string $query): \Amp\Promise {
        // @todo delegate to pool member
    }

    /**
     * Execute a previously prepared statement asynchronously
     *
     * @param string $statementName
     * @param array $params
     * @return \Amp\Promise
     */
    public function execute(string $statementName, array $params): \Amp\Promise {
        // @todo delegate to pool member
    }
}

