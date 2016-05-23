<?php

namespace Amp\Pgsql;

use Amp\Promise;

interface Connection
{
    const OPTION_ENCODING           = 1;
    const OPTION_DEFAULT_FETCH_TYPE = 2;

    const STATE_CLOSED     = 0;
    const STATE_CONNECTING = 1;
    const STATE_CONNECTED  = 2;

    /**
     * Indicates whether the connection to the server is currently open
     *
     * @return int
     */
    public function getConnectionState(): int;

    /**
     * Returns a list of the notification channels for which active subscriptions exist
     *
     * @return string[]
     */
    public function getSubscribedChannels(): array;

    /**
     * Connect to the server
     *
     * @return Promise<void>
     */
    public function connect(): Promise;

    /**
     * Set an option on the connection
     *
     * @param int $option
     * @param mixed $value
     * @throws UnknownOptionException
     * @throws OptionDefinitionFailureException
     */
    public function setOption(int $option, /* mixed */ $value) /* : void */;

    /**
     * Get the current value of a connection option
     *
     * @param int $option
     * @throws UnknownOptionException
     * @throws OptionDefinitionFailureException
     * @return mixed
     */
    public function getOption(int $option) /* : mixed */;

    /**
     * Execute an SQL query (one that returns a result set) and return a cursor
     *
     * If a parameter array is passed, prepare the statement and bind the parameters before execution
     *
     * @param string $sql
     * @param array $params
     * @param array $types
     * @return Promise<Cursor>
     */
    public function executeQuery(string $sql, array $params = null, array $types = null): Promise;

    /**
     * Execute an SQL command (one that does not return a result set) and return the number of effected rows
     *
     * If a parameter array is passed, prepare the statement and bind the parameters before execution
     *
     * @param string $sql
     * @param array $params
     * @param array $types
     * @return Promise<int>
     */
    public function executeCommand(string $sql, array $params = null, array $types = null): Promise;

    /**
     * Register a callback to be invoked when a notification is received on the named channel
     *
     * @param string $channel
     * @param callable $callback function(string $message, int $pid): void
     * @return Promise<string> An identifier for the listener
     */
    public function listen(string $channel, callable $callback): Promise;

    /**
     * Send a notification message to the named channel
     *
     * @param string $channel
     * @param string $message
     * @return Promise<void>
     */
    public function notify(string $channel, string $message): Promise;

    /**
     * Prepare a statement for execution
     *
     * @param string $name
     * @param string $sql
     * @param array $types
     * @return Promise<Statement>
     */
    public function prepare(string $name, string $sql, array $types = null): Promise;

    /**
     * Attempt to reset a possibly broken connection to a working state
     *
     * (pg_ping())
     *
     * @return Promise<void>
     * @throws ResetFailedException
     */
    public function reset(): Promise;

    /**
     * @param int $flags
     * @return Promise<void>
     */
    public function beginTransaction(int $flags = 0): Promise;

    /**
     * @param string $subscriptionId The ID returned by listen()
     * @return Promise<void>
     */
    public function unlisten(string $subscriptionId): Promise;

    /**
     * Close the connection and free the underlying resources
     */
    public function close() /* : void */;
}
