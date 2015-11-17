<?php

namespace \Amp\Pgsql;

/**
 * 
 */
function connect(string $connectionString): \Amp\Promise {
    $deferred = new Deferred;
    
    if (!$db = \pg_connect($connectionString, \PGSQL_CONNECT_ASYNC)) {
        return new \Amp\Failure(new \RuntimeException(
            "Failed creating connection resource"
        ));
    }
    
    if (\pg_connection_status($db) === \PGSQL_CONNECTION_BAD) {
        return new \Amp\Failure(new \RuntimeException(
            \pg_last_error($db)
        ));
    }
    
    if (!$socket = \pg_socket($db)) {
        return new \Amp\Failure(new \RuntimeException(
            "Failed accessing database connection socket"
        ));
    }
    
    \stream_set_blocking($socket, FALSE);
    $cbData = new class {
        use \Amp\Struct;
        public $db;
        public $deferred;
        public $readWatcher;
        public $writeWatcher;
        public $connectionString;
        public $dbResource;
    };
    $watchFunc = __NAMESPACE__ . "\\__watchAsyncConnect";
    $readWatcher = \Amp\onReadable($socket, $watchFunc, ["cb_data" => $cbData]);
    $writeWatcher = \Amp\onWritable($socket, $watchFunc, ["cb_data" => $cbData]);
    
    $cbData->db = $db;
    $cbData->deferred = $deferred;
    $cbData->readWatcher = $readWatcher;
    $cbData->writeWatcher = $writeWatcher;
    $cbData->connectionString = $connectionString;

    return $deferred->promise();
}

/**
 * This function is NOT considered part of the public API and should not be relied upon.
 */
function __watchAsyncConnect($watcherId, $socket, $cbData) {
    switch (\pg_connect_poll($socket)) {
        case \PGSQL_POLLING_READING:
            break;
        case \PGSQL_POLLING_WRITING:
            break;
        case \PGSQL_POLLING_FAILED:
            \Amp\cancel($cbData->readWatcherId);
            \Amp\cancel($cbData->writeWatcherId);
            $cbData->deferred->fail(new \RuntimeException(
                "Async connection failure"
            ));
            break;
        case \PGSQL_POLLING_OK:
            \Amp\cancel($cbData->readWatcherId);
            \Amp\cancel($cbData->writeWatcherId);
            $cbData->deferred->succeed(new Connection(
                $cbData->socket,
                $cbData->db,
                $cbData->connectionString
            ));
    }
}

