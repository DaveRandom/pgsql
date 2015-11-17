<?php

namespace \Amp\Pgsql;

class Connection implements Db {
    use \Amp\Struct;

    private static $OP_QUERY = 1;
    private static $OP_QUERY_PARAMS = 2;
    private static $OP_PREPARE = 3;
    private static $OP_EXECUTE = 4;

    private $db;
    private $socket;
    private $connectionString;
    private $readWatcher;
    private $writeWatcher;
    private $queryQueue = [];
    private $maxOutstandingQueries = 256;

    public function __construct($db, $socket, string $connectionString) {
        $this->db = $db;
        $this->socket = $socket;
        $this->connectionString = $connectionString;
        $this->readWatcher = \Amp\onReadable($socket, [$this, "onReadable"]);
        $this->writeWatcher = \Amp\onReadable($socket, [$this, "onWritable"], [
            "enable" => FALSE
        ]);
    }

    public function setOption(string $option, $value) {
        switch ($option) {
            case "maxOutstandingQueries":
                $this->maxOutstandingQueries = (int) $value;
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
        if ($this->queryCacheSize > $this->maxOutstandingQueries) {
            return new \Amp\Failure(new \RuntimeException(
                "Too busy"
            ));
        }

        $deferred = new \Amp\Deferred;
        $this->queryCache[] = [self::$OP_QUERY, [$query], $deferred];
        $this->queryCacheSize++;
        if (!$this->queryCacheSize++) {
            $sendResult = \pg_send_query($this->db, $query);
            $this->processSendResult($sendResult);
        }
        return $deferred->promise();
    }

    private function processSendResult($sendResult) {
        if ($sendResult === 0) {
            // Send didn't complete in the initial pg_send_query() call;
            // enable the write watcher.
            \Amp\enable($this->writeWatcher);
            return;
        }
        if ($sendResult === FALSE) {
            $this->failCurrentOperation();
        }
    }

    private function failCurrentOperation() {
        $this->queryCacheSize--;
        $key = \key($this->queryQueue);
        $deferred = \end($this->queryQueue[$key]);
        unset($this->queryQueue[$key]);
        $deferred->fail(new \RuntimeException(
            \pg_last_error($this->db)
        ));
        if ($this->queryQueue) {
            $this->dispatchNextOperation();
        }
    }

    private function onWritable() {
        $flush = \pg_flush($this->db);
        if ($flush) {
            // Write was fully flushed; we're finished and can disable the watcher.
            \Amp\disable($this->writeWatcher);
            return;
        }
        if ($flush === FALSE) {
            $this->failCurrentOperation();
        }
    }

    private function onReadable() {
        if (!\pg_consume_input($this->db)) {
            $this->onInputConsumptionFailure();
            return;
        }
        if (!\pg_connection_busy($this->db)) {
            $this->finalizeCompletedQueryResult();
        }
    }

    private function onInputConsumptionFailure() {
        switch (\pg_connection_status($this->db)) {
            case \PGSQL_CONNECTION_BAD:
                $this->failAllOutstandingOperations();
                break;
            default:
                $this->failCurrentOperation();
                break;
        }
    }

    private function failAllOutstandingOperations() {
        while ($this->queryQueue) {
            $this->failCurrentOperation();
        }
    }

    private function finalizeCompletedQueryResult() {
        $this->queryCacheSize--;
        $key = key($this->queryQueue);
        $deferred = end($this->queryQueue[$key]);
        unset($this->queryQueue[$key]);
        $result = \pg_get_result($this->db);
        $deferred->succeed($result);
        if ($this->queryQueue) {
            $this->dispatchNextOperation();
        }
    }

    private function dispatchNextOperation() {
        list($opcode, $args) = \current($this->queryQueue[$key]);
        switch ($opcode) {
            case self::$OP_QUERY:
                $sendFunc = 'pg_send_query';
                break;
            case self::$OP_QUERY_PARAMS:
                $sendFunc = 'pg_send_query_params';
                break;
            case self::$OP_PREPARE:
                $sendFunc = 'pg_send_prepare';
                break;
            case self::$OP_EXECUTE:
                $sendFunc = 'pg_send_execute';
                break;
            default:
                throw new \UnexpectedValueException(
                    \sprintf('Unknown pg_send_* opcode: %s', $opcode)
                );
        }
        \array_unshift($args, $this->db);
        $sendResult = \call_user_func_array($sendFunc, $args);
        $this->processSendResult($sendResult);
    }

    /**
     * Dispatch an prepared query asynchronously
     *
     * @param string $query
     * @param array $params
     * @return \Amp\Promise
     */
    public function queryParams(string $query, array $params): \Amp\Promise {
        if ($this->queryCacheSize > $this->maxOutstandingQueries) {
            return new \Amp\Failure(new \RuntimeException(
                "Too busy"
            ));
        }

        $deferred = new \Amp\Deferred;
        $this->queryCache[] = [self::$OP_QUERY_PARAMS, [$query, $params], $deferred];
        if (!$this->queryCacheSize++) {
            $sendResult = \pg_send_query_params($this->db, $query, $params);
            $this->processSendResult($sendResult);
        }

        return $deferred->promise();
    }

    /**
     * Prepare a query asynchronously
     *
     * @param string $statementName
     * @param string $query
     * @return \Amp\Promise
     */
    public function prepare(string $statementName, string $query): \Amp\Promise {
        if ($this->queryCacheSize > $this->maxOutstandingQueries) {
            return new \Amp\Failure(new \RuntimeException(
                "Too busy"
            ));
        }

        $deferred = new \Amp\Deferred;
        $this->queryCache[] = [self::$OP_QUERY_PARAMS, [$statementName, $query], $deferred];
        if (!$this->queryCacheSize++) {
            $sendResult = \pg_send_prepare($this->db, $statementName, $query);
            $this->processSendResult($sendResult);
        }

        return $deferred->promise();
    }

    /**
     * Execute a previously prepared statement asynchronously
     *
     * @param string $statementName
     * @param array $params
     * @return \Amp\Promise
     */
    public function execute(string $statementName, array $params) {
        if ($this->queryCacheSize > $this->maxOutstandingQueries) {
            return new \Amp\Failure(new \RuntimeException(
                "Too busy"
            ));
        }

        $deferred = new \Amp\Deferred;
        $this->queryCache[] = [self::$OP_QUERY_PARAMS, [$statementName, $params], $deferred];
        if (!$this->queryCacheSize++) {
            $sendResult = \pg_send_execute($this->db, $statementName, $params);
            $this->processSendResult($sendResult);
        }

        return $deferred->promise();
    }
}

