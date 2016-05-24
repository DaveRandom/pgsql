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
     * @throws InvalidOperationException
     * @throws ConnectFailureException
     */
    public function connect(): Promise;

    /**
     * Close the connection and free the underlying resources
     *
     * @throws InvalidOperationException
     */
    public function close() /* : void */;

    /**
     * Attempt to reset a possibly broken connection to a working state
     *
     * @return Promise<void>
     * @throws ResetFailedException
     * @throws InvalidOperationException
     * @throws CommandDispatchFailureException
     */
    public function reset(): Promise;

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
     * @return mixed
     * @throws UnknownOptionException
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
     * @throws InvalidOperationException
     * @throws CommandDispatchFailureException
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
     * @throws InvalidOperationException
     * @throws CommandDispatchFailureException
     */
    public function executeCommand(string $sql, array $params = null, array $types = null): Promise;

    /**
     * Prepare a statement for execution
     *
     * @param string $name
     * @param string $sql
     * @param array $types
     * @return Promise<Statement>
     * @throws InvalidOperationException
     * @throws CommandDispatchFailureException
     */
    public function prepareQuery(string $name, string $sql, array $types = null): Promise;

    /**
     * Prepare a statement for execution
     *
     * @param string $name
     * @param string $sql
     * @param array $types
     * @return Promise<Statement>
     * @throws InvalidOperationException
     * @throws CommandDispatchFailureException
     */
    public function prepareCommand(string $name, string $sql, array $types = null): Promise;

    /**
     * @param int $flags
     * @return Promise<void>
     * @throws InvalidOperationException
     * @throws CommandDispatchFailureException
     */
    public function beginTransaction(int $flags = 0): Promise;

    /**
     * Register a callback to be invoked when a notification is received on the named channel
     *
     * @param string $channel
     * @param callable $callback function(string $message, int $pid): void
     * @return Promise<string> An identifier for the listener
     * @throws InvalidOperationException
     * @throws CommandDispatchFailureException
     */
    public function listen(string $channel, callable $callback): Promise;

    /**
     * @param string $subscriptionId The ID returned by listen()
     * @return Promise<void>
     * @throws InvalidOperationException
     * @throws CommandDispatchFailureException
     */
    public function unlisten(string $subscriptionId): Promise;

    /**
     * Send a notification message to the named channel
     *
     * @param string $channel
     * @param string $message
     * @return Promise<void>
     * @throws InvalidOperationException
     * @throws CommandDispatchFailureException
     */
    public function notify(string $channel, string $message): Promise;
}
