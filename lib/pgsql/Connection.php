<?php declare(strict_types = 1);

namespace Amp\Pgsql\pgsql;

use Amp\Pgsql\Connection as PgsqlConnection;
use Amp\Pgsql\NotImplementedException;
use Amp\Pgsql\OptionDefinitionFailureException;
use Amp\Pgsql\ResetFailedException;
use Amp\Pgsql\UnknownOptionException;
use Amp\Promise;

class Connection implements PgsqlConnection
{
    /**
     * Indicates whether the connection to the server is currently open
     *
     * @return int
     */
    public function getConnectionState(): int
    {
        throw new NotImplementedException; // todo
    }

    /**
     * Returns a list of the notification channels for which active subscriptions exist
     *
     * @return string[]
     */
    public function getSubscribedChannels(): array
    {
        throw new NotImplementedException; // todo
    }

    /**
     * Connect to the server
     *
     * @param string $dsn todo check pq/pgsql compat
     * @param array $options An array of options to apply before connecting
     * @return Promise<void>
     */
    public function connect(string $dsn, array $options = []): Promise
    {
        throw new NotImplementedException; // todo
    }

    /**
     * Set an option on the connection
     *
     * @param int $option
     * @param mixed $value
     * @throws UnknownOptionException
     * @throws OptionDefinitionFailureException
     */
    public function setOption(int $option, /* mixed */ $value) /* : void */
    {
        throw new NotImplementedException; // todo
    }

    /**
     * Get the current value of a connection option
     *
     * @param int $option
     * @throws UnknownOptionException
     * @throws OptionDefinitionFailureException
     * @return mixed
     */
    public function getOption(int $option) /* : mixed */
    {
        throw new NotImplementedException; // todo
    }

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
    public function executeQuery(string $sql, array $params = null, array $types = null): Promise
    {
        throw new NotImplementedException; // todo
    }

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
    public function executeCommand(string $sql, array $params = null, array $types = null): Promise
    {
        throw new NotImplementedException; // todo
    }

    /**
     * Register a callback to be invoked when a notification is received on the named channel
     *
     * @param string $channel
     * @param callable $callback function(string $message, int $pid): void
     * @return Promise<string> An identifier for the listener
     */
    public function listen(string $channel, callable $callback): Promise
    {
        throw new NotImplementedException; // todo
    }

    /**
     * Send a notification message to the named channel
     *
     * @param string $channel
     * @param string $message
     * @return Promise<void>
     */
    public function notify(string $channel, string $message): Promise
    {
        throw new NotImplementedException; // todo
    }

    /**
     * Prepare a statement for execution
     *
     * @param string $name
     * @param string $sql
     * @param array $types
     * @return Promise<Statement>
     */
    public function prepare(string $name, string $sql, array $types = null): Promise
    {
        throw new NotImplementedException; // todo
    }

    /**
     * Attempt to reset a possibly broken connection to a working state
     *
     * (pg_ping())
     *
     * @return Promise<void>
     * @throws ResetFailedException
     */
    public function reset(): Promise
    {
        throw new NotImplementedException; // todo
    }

    /**
     * @param int $flags
     * @return Promise<void>
     */
    public function beginTransaction(int $flags = 0): Promise
    {
        throw new NotImplementedException; // todo
    }

    /**
     * @param string $subscriptionId The ID returned by listen()
     * @return Promise<void>
     */
    public function unlisten(string $subscriptionId): Promise
    {
        throw new NotImplementedException; // todo
    }

    /**
     * Close the connection and free the underlying resources
     */
    public function close() /* : void */
    {
        throw new NotImplementedException; // todo
    }
}
