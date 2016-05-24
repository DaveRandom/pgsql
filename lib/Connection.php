<?php

namespace Amp\Pgsql;

use Amp\Pgsql\Exceptions\CommandDispatchFailureException;
use Amp\Pgsql\Exceptions\CommandErrorException;
use Amp\Pgsql\Exceptions\ConnectFailureException;
use Amp\Pgsql\Exceptions\InvalidOperationException;
use Amp\Pgsql\Exceptions\OptionDefinitionFailureException;
use Amp\Pgsql\Exceptions\ResetFailedException;
use Amp\Pgsql\Exceptions\ResultFetchFailureException;
use Amp\Pgsql\Exceptions\ServerProtocolViolationException;
use Amp\Pgsql\Exceptions\UnexpectedResultStatusException;
use Amp\Pgsql\Exceptions\UnknownOptionException;
use Amp\Promise;

interface Connection
{
    const OPTION_ENCODING           = 1;
    const OPTION_DEFAULT_FETCH_TYPE = 2;

    const STATE_NONE              = 0;
    const STATE_STARTED           = 0b00000001;
    const STATE_MADE              = 0b00000010;
    const STATE_SSL_STARTUP       = 0b00000100;
    const STATE_AUTH_OK           = 0b00001000;
    const STATE_SETENV            = 0b00010000;
    const STATE_AWAITING_RESPONSE = 0b00100000;
    const STATE_OK                = 0b01000000;
    const STATE_BAD               = 0b10000000;

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
     * Get notices generated while processing the last server operation
     *
     * @return string[]
     */
    public function getNotices(): array;

    /**
     * Execute an SQL query (one that returns a result set) and return a cursor
     *
     * If a parameter array is passed, prepare the statement and bind the parameters before execution
     *
     * @param string $sql
     * @param array $params
     * @param array $types
     * @return Promise<Cursor>
     * @throws InvalidOperationException when the connection is not open
     * @throws CommandDispatchFailureException when the command could not be sent to the server
     * @throws ResultFetchFailureException when a server result could not be retrieved
     * @throws CommandErrorException when the server encounters a fatal error while processing the command
     * @throws ServerProtocolViolationException when the server's response violates the pgsql protocol
     * @throws UnexpectedResultStatusException when the server's response status is unexpected for an execute command
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
     * @throws InvalidOperationException when the connection is not open
     * @throws CommandDispatchFailureException when the command could not be sent to the server
     * @throws ResultFetchFailureException when a server result could not be retrieved
     * @throws CommandErrorException when the server encounters a fatal error while processing the command
     * @throws ServerProtocolViolationException when the server's response violates the pgsql protocol
     * @throws UnexpectedResultStatusException when the server's response status is unexpected for an execute command
     */
    public function executeCommand(string $sql, array $params = null, array $types = null): Promise;

    /**
     * Prepare a statement for execution
     *
     * @param string $name
     * @param string $sql
     * @param array $types
     * @return Promise<Statement>
     * @throws InvalidOperationException when the connection is not open
     * @throws CommandDispatchFailureException when the command could not be sent to the server
     * @throws ResultFetchFailureException when a server result could not be retrieved
     * @throws CommandErrorException when the server encounters a fatal error while processing the command
     * @throws ServerProtocolViolationException when the server's response violates the pgsql protocol
     * @throws UnexpectedResultStatusException when the server's response status is unexpected for a prepare command
     */
    public function prepareQuery(string $name, string $sql, array $types = null): Promise;

    /**
     * Prepare a statement for execution
     *
     * @param string $name
     * @param string $sql
     * @param array $types
     * @return Promise<Statement>
     * @throws InvalidOperationException when the connection is not open
     * @throws CommandDispatchFailureException when the command could not be sent to the server
     * @throws ResultFetchFailureException when a server result could not be retrieved
     * @throws CommandErrorException when the server encounters a fatal error while processing the command
     * @throws ServerProtocolViolationException when the server's response violates the pgsql protocol
     * @throws UnexpectedResultStatusException when the server's response status is unexpected for a prepare command
     */
    public function prepareCommand(string $name, string $sql, array $types = null): Promise;

    /**
     * @param int $flags
     * @return Promise<void> A promise the resolves without a value
     * @throws InvalidOperationException when the connection is not open
     * @throws CommandDispatchFailureException when the command could not be sent to the server
     * @throws ResultFetchFailureException when a server result could not be retrieved
     * @throws CommandErrorException when the server encounters a fatal error while processing the command
     * @throws ServerProtocolViolationException when the server's response violates the pgsql protocol
     * @throws UnexpectedResultStatusException when the server's response status is unexpected for a begin transaction command
     */
    public function beginTransaction(int $flags = 0): Promise;

    /**
     * Register a callback to be invoked when a notification is received on the named channel
     *
     * @param string $channel
     * @param callable $callback callable(string $message, int $pid): void
     * @return Promise<string> An identifier for the listener
     * @throws InvalidOperationException when the connection is not open
     * @throws CommandDispatchFailureException when the command could not be sent to the server
     * @throws ResultFetchFailureException when a server result could not be retrieved
     * @throws CommandErrorException when the server encounters a fatal error while processing the command
     * @throws ServerProtocolViolationException when the server's response violates the pgsql protocol
     * @throws UnexpectedResultStatusException when the server's response status is unexpected for a listen command
     */
    public function listen(string $channel, callable $callback): Promise;

    /**
     * @param string $subscriptionId The ID returned by listen()
     * @return Promise<void>
     * @throws InvalidOperationException when the connection is not open
     * @throws CommandDispatchFailureException when the command could not be sent to the server
     * @throws ResultFetchFailureException when a server result could not be retrieved
     * @throws CommandErrorException when the server encounters a fatal error while processing the command
     * @throws ServerProtocolViolationException when the server's response violates the pgsql protocol
     * @throws UnexpectedResultStatusException when the server's response status is unexpected for an unlisten command
     */
    public function unlisten(string $subscriptionId): Promise;

    /**
     * Send a notification message to the named channel
     *
     * @param string $channel
     * @param string $message
     * @return Promise<void>
     * @throws InvalidOperationException when the connection is not open
     * @throws CommandDispatchFailureException when the command could not be sent to the server
     * @throws ResultFetchFailureException when a server result could not be retrieved
     * @throws CommandErrorException when the server encounters a fatal error while processing the command
     * @throws ServerProtocolViolationException when the server's response violates the pgsql protocol
     * @throws UnexpectedResultStatusException when the server's response status is unexpected for a notify command
     */
    public function notify(string $channel, string $message): Promise;
}
