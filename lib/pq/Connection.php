<?php

namespace Amp\Pgsql\pq;

use Amp\Deferred;
use Amp\Mutex\Lock;
use Amp\Mutex\Mutex;
use Amp\Mutex\QueuedExclusiveMutex;
use Amp\Pgsql\Connection as PgsqlConnection;
use Amp\Pgsql\Cursor as PgsqlCursor;
use Amp\Pgsql\Exceptions\CommandDispatchFailureException;
use Amp\Pgsql\Exceptions\CommandErrorException;
use Amp\Pgsql\Exceptions\ConnectFailureException;
use Amp\Pgsql\Exceptions\InternalLogicErrorException;
use Amp\Pgsql\Exceptions\InvalidOperationException;
use Amp\Pgsql\Exceptions\NotImplementedException;
use Amp\Pgsql\Exceptions\OptionDefinitionFailureException;
use Amp\Pgsql\Exceptions\ResultFetchFailureException;
use Amp\Pgsql\Exceptions\ServerProtocolViolationException;
use Amp\Pgsql\Exceptions\UnexpectedResultStatusException;
use Amp\Pgsql\Exceptions\UnknownOptionException;
use Amp\Promise;
use Amp\Success;
use pq\Connection as pqConnection;
use pq\Exception\RuntimeException as pqRuntimeException;
use pq\Result as pqResult;
use function Amp\any;
use function Amp\cancel;
use function Amp\disable;
use function Amp\enable;
use function Amp\first;
use function Amp\immediately;
use function Amp\onReadable;
use function Amp\onWritable;
use function Amp\pipe;
use function Amp\resolve;

/**
 * Class Connection
 *
 * @package pqAsync
 */
class Connection implements PgsqlConnection
{
    const IS_READABLE = 0b01;
    const IS_WRITABLE = 0b10;

    /**
     * @var pqConnection
    */
    private $pqConnection;

    /**
     * @var Mutex
     */
    private $mutex;

    /**
     * @var callable[][]
     */
    private $subscribedChannels = [];

    /**
     * @var int
     */
    private $channelSubscriptionIdCounter = 0;

    /**
     * @var Deferred
     */
    private $readableDeferred;

    /**
     * @var Deferred
     */
    private $writableDeferred;

    /**
     * @var string
     */
    private $readableWatcherID;

    /**
     * @var string
     */
    private $writableWatcherID;

    /**
     * @var array
     */
    private $options = [
        self::OPTION_DEFAULT_FETCH_TYPE => PgsqlCursor::FETCH_ASSOC,
        self::OPTION_ENCODING => 'UTF8',
    ];

    /**
     * @var int[]
     */
    private static $statusMap = [
        pqConnection::STARTED           => self::STATE_STARTED,
        pqConnection::MADE              => self::STATE_MADE,
        pqConnection::SSL_STARTUP       => self::STATE_SSL_STARTUP,
        pqConnection::AUTH_OK           => self::STATE_AUTH_OK,
        pqConnection::SETENV            => self::STATE_SETENV,
        pqConnection::AWAITING_RESPONSE => self::STATE_AWAITING_RESPONSE,
        pqConnection::OK                => self::STATE_OK,
        pqConnection::BAD               => self::STATE_BAD,
    ];

    /**
     * @var callable
     */
    private $channelNotificationHandler;

    /**
     * @var string
     */
    private $dsn;

    /**
     * @var string[]
     */
    private $notices = [];

    public function __construct(string $dsn, array $options = [])
    {
        $this->dsn = $dsn;

        $this->mutex = new QueuedExclusiveMutex();

        $this->channelNotificationHandler = function(string $channel, string $message, int $pid) {
            foreach ($this->subscribedChannels[$channel] ?? [] as $callback) {
                $callback($message, $pid, $channel);
            }
        };

        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
    }

    public function __destruct()
    {
        if ($this->pqConnection !== null) {
            $this->close();
        }
    }

    private function awaitWritable(): Promise
    {
        assert(
            $this->writableDeferred === null,
            new InternalLogicErrorException('Cannot await writable from more than one source')
        );

        enable($this->writableWatcherID);

        $this->writableDeferred = new Deferred();
        return $this->writableDeferred->promise();
    }

    private function awaitReadableOrWritable(): Promise
    {
        return resolve(function() {
            $result = yield first([$this->awaitReadable(), $this->awaitWritable()]);

            if ($result === self::IS_WRITABLE) {
                $this->readableDeferred = null;
            } else if ($result === self::IS_READABLE) {
                $this->writableDeferred = null;
                disable($this->writableWatcherID);
            }

            return $result;
        });
    }

    private function generateChannelSubscriptionId(string $channel)
    {
        return $channel . '_' . $this->channelSubscriptionIdCounter++;
    }

    private function registerChannelSubscription(string $channel, callable $callback): string
    {
        $id = $this->generateChannelSubscriptionId($channel);
        $this->subscribedChannels[$channel][$id] = $callback;

        return $id;
    }

    /**
     * Release a registered callback by ID. If there are no callbacks left for that channel, return the channel name
     * so the caller can unlisten().
     *
     * @param string $id
     * @return null|string
     * @throws \Amp\Pgsql\Exceptions\InvalidOperationException
     */
    private function unregisterChannelSubscription(string $id)
    {
        $channel = explode('_', $id, 2)[0];

        if (!isset($this->subscribedChannels[$channel][$id])) {
            throw new InvalidOperationException('Invalid channel listener ID');
        }

        unset($this->subscribedChannels[$channel][$id]);

        return isset($this->subscribedChannels[$channel]) ? $channel : null;
    }

    private function prepare(string $name, string $sql, array $types = null)
    {
        if ($this->getConnectionState() !== self::STATE_OK) {
            throw new InvalidOperationException('Cannot execute commands before connection');
        }

        return $this->mutex->withLock(function() use($name, $sql, $types) {
            $method = [$this->pqConnection, 'prepareAsync'];
            $args = [$name, $sql, $types];

            $stmt = yield from $this->callAsyncPqMethodAndAwaitResult($method, $args);

            $this->getResultAndThrowIfNotOK();

            return $stmt;
        });
    }

    public function getResultAndThrowIfNotOK(): pqResult
    {
        if (!$result = $this->pqConnection->getResult()) {
            throw new ResultFetchFailureException('Failed to get a result');
        }

        switch ($result->status) {
            case pqResult::COMMAND_OK:
            case pqResult::SINGLE_TUPLE:
            case pqResult::TUPLES_OK:
                return $result;
            case pqResult::FATAL_ERROR:
                throw new CommandErrorException($result->errorMessage, $result->status);
            case pqResult::BAD_RESPONSE:
                throw new ServerProtocolViolationException('Could not understand the server\'s response');
        }

        throw new UnexpectedResultStatusException('Unexpected result status number ' . $result->status, $result->status);
    }

    public function callAsyncPqMethodAndAwaitResult(callable $method, array $args): \Generator
    {
        yield from $this->clearPendingResults();
        $this->notices = [];

        $result = $method(...$args);

        yield from $this->flushUntilCommandSent();
        yield from $this->pollUntilNotBusy();

        return $result;
    }

    public function pollUntilNotBusy(): \Generator
    {
        while ($this->pqConnection->busy) {
            yield $this->awaitReadable();

            if (pqConnection::POLLING_FAILED === $this->pqConnection->poll()) {
                throw new CommandDispatchFailureException($this->pqConnection->errorMessage);
            }
        }
    }

    public function flushUntilCommandSent(): \Generator
    {
        try {
            while (!$this->pqConnection->flush()) {
                if (self::IS_READABLE === yield $this->awaitReadableOrWritable()) {
                    $this->pqConnection->poll();
                }
            }
        } catch (pqRuntimeException $e) {
            throw new CommandDispatchFailureException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function clearPendingResults(): \Generator
    {
        do {
            yield from $this->pollUntilNotBusy();
        } while($result = $this->pqConnection->getResult());
    }

    public function awaitReadable(): Promise
    {
        assert(
            $this->readableDeferred === null,
            new InternalLogicErrorException('Cannot await readable from more than one source')
        );

        $this->readableDeferred = new Deferred();
        return $this->readableDeferred->promise();
    }

    public function getConnectionState(): int
    {
        if (!isset($this->pqConnection)) {
            return self::STATE_NONE;
        }

        assert(
            isset(self::$statusMap[$this->pqConnection->status]),
            new InternalLogicErrorException('Unknown connection status number ' . $this->pqConnection->status)
        );

        return self::$statusMap[$this->pqConnection->status];
    }

    public function getSubscribedChannels(): array
    {
        return array_keys($this->subscribedChannels);
    }

    public function connect(): Promise
    {
        if (isset($this->pqConnection)) {
            throw new InvalidOperationException('Cannot connect: connection not closed');
        }

        $this->pqConnection = new pqConnection($this->dsn, pqConnection::ASYNC);
        $this->pqConnection->unbuffered = true;
        $this->pqConnection->nonblocking = true;

        /** @noinspection PhpUnusedParameterInspection */
        $this->pqConnection->on(pqConnection::EVENT_NOTICE, function(pqConnection $con, string $notice) {
            $this->notices[] = $notice;
        });

        $this->readableWatcherID = onReadable($this->pqConnection->socket, function() {
            if ($this->readableDeferred === null) {
                $this->pqConnection->poll();
                return;
            }

            $deferred = $this->readableDeferred;
            $this->readableDeferred = null;

            $deferred->succeed(self::IS_READABLE);
        });
        $this->writableWatcherID = onWritable($this->pqConnection->socket, function() {
            assert(
                $this->writableDeferred !== null,
                new InternalLogicErrorException('Writable watcher invoked with no deferred??')
            );

            disable($this->writableWatcherID);

            $deferred = $this->writableDeferred;
            $this->writableDeferred = null;

            $deferred->succeed(self::IS_WRITABLE);
        }, ['enable' => false]);

        return resolve(function() {
            yield $this->awaitWritable();

            while (pqConnection::POLLING_OK !== $status = $this->pqConnection->poll()) {
                switch ($status) {
                    case pqConnection::POLLING_READING:
                        yield $this->awaitReadable();
                        break;

                    case pqConnection::POLLING_WRITING:
                        yield $this->awaitWritable();
                        break;

                    case pqConnection::POLLING_FAILED:
                        throw new ConnectFailureException($this->pqConnection->errorMessage);

                    default:
                        throw new ConnectFailureException('Unknown poll status: ' . $status);
                }
            }

            $this->pqConnection->encoding = $this->options[self::OPTION_ENCODING];
        });
    }

    public function close()
    {
        if ($this->pqConnection === null) {
            throw new InvalidOperationException('Cannot close connection: not open');
        }

        cancel($this->readableWatcherID);
        cancel($this->writableWatcherID);

        $this->pqConnection = null;
    }

    public function reset(): Promise
    {
        throw new NotImplementedException('todo'); //todo
    }

    public function setOption(int $option, $value) /* : void */
    {
        if (!array_key_exists($option, $this->options)) {
            throw new UnknownOptionException('Invalid option number ' . $option);
        }

        try {
            switch ($option) {
                case self::OPTION_ENCODING:
                    $value = (string)$value;

                    if (isset($this->pqConnection)) {
                        $this->pqConnection->encoding = $value;
                    }

                    $this->options[self::OPTION_ENCODING] = $value;
                    return;

                case self::OPTION_DEFAULT_FETCH_TYPE:
                    static $supportedFetchTypes = [
                        PgsqlCursor::FETCH_ARRAY, PgsqlCursor::FETCH_ASSOC, PgsqlCursor::FETCH_OBJECT
                    ];

                    $value = (int)$value;

                    if (!in_array($value, $supportedFetchTypes)) {
                        throw new OptionDefinitionFailureException('Invalid fetch style number ' . $value);
                    }

                    $this->options[self::OPTION_DEFAULT_FETCH_TYPE] = $value;
                    return;
            }
        } catch (\Throwable $e) {
            if (!$e instanceof OptionDefinitionFailureException) {
                $e = new OptionDefinitionFailureException($e->getMessage(), $e->getCode(), $e);
            }

            throw $e;
        }
    }

    public function getOption(int $option) /* : mixed */
    {
        if (!array_key_exists($option, $this->options)) {
            throw new UnknownOptionException('Invalid option number ' . $option);
        }

        return $this->options[$option];
    }

    public function getNotices(): array
    {
        return $this->notices;
    }

    public function executeQuery(string $sql, array $params = null, array $types = null): Promise
    {
        if ($params !== null) {
            $method = [$this->pqConnection, 'execParamsAsync'];
            $args = [$sql, $params, $types];
        } else {
            $method = [$this->pqConnection, 'execAsync'];
            $args = [$sql];
        }

        return resolve(function() use($method, $args) {
            /** @var Lock $lock */
            $lock = yield $this->mutex->getLock();

            if ($this->getConnectionState() < self::STATE_OK) {
                throw new InvalidOperationException('Cannot execute commands before connection');
            }

            try {
                yield from $this->callAsyncPqMethodAndAwaitResult($method, $args);

                $result = $this->getResultAndThrowIfNotOK();

                return new Cursor($this, $this->pqConnection, $result, $lock);
            } catch (\Throwable $e) {
                $lock->release();
                throw $e;
            }
        });
    }

    public function executeCommand(string $sql, array $params = null, array $types = null): Promise
    {
        if ($params !== null) {
            $method = [$this->pqConnection, 'execParamsAsync'];
            $args = [$sql, $params, $types];
        } else {
            $method = [$this->pqConnection, 'execAsync'];
            $args = [$sql];
        }

        return $this->mutex->withLock(function() use($method, $args) {
            if ($this->getConnectionState() < self::STATE_OK) {
                throw new InvalidOperationException('Cannot execute commands before connection');
            }

            yield from $this->callAsyncPqMethodAndAwaitResult($method, $args);

            return $this->getResultAndThrowIfNotOK()->affectedRows;
        });
    }

    public function prepareQuery(string $name, string $sql, array $types = null): Promise
    {
        return pipe($this->prepare($name, $sql, $types), function($stmt) use($sql, $types) {
            return new QueryStatement($this, $this->pqConnection, $stmt, $this->mutex, $sql, $types);
        });
    }

    public function prepareCommand(string $name, string $sql, array $types = null): Promise
    {
        return pipe($this->prepare($name, $sql, $types), function($stmt) use($sql, $types) {
            return new CommandStatement($this, $this->pqConnection, $stmt, $this->mutex, $sql, $types);
        });
    }

    public function beginTransaction(int $flags = 0): Promise
    {
        throw new NotImplementedException('todo'); //todo
    }

    public function listen(string $channel, callable $callback): Promise
    {
        if (isset($this->subscribedChannels[$channel])) {
            return new Success($this->registerChannelSubscription($channel, $callback));
        }

        $method = [$this->pqConnection, 'listenAsync'];
        $args = [$channel, $this->channelNotificationHandler];

        return $this->mutex->withLock(function() use($method, $args, $channel, $callback) {
            if ($this->getConnectionState() < self::STATE_OK) {
                throw new InvalidOperationException('Cannot execute commands before connection');
            }

            yield from $this->callAsyncPqMethodAndAwaitResult($method, $args);

            $this->getResultAndThrowIfNotOK();

            return $this->registerChannelSubscription($channel, $callback);
        });
    }

    public function unlisten(string $subscriptionId): Promise
    {
        if (!$channel = $this->unregisterChannelSubscription($subscriptionId)) {
            return new Success();
        }

        $method = [$this->pqConnection, 'unlistenAsync'];
        $args = [$channel];

        return $this->mutex->withLock(function() use($method, $args, $channel) {
            if ($this->getConnectionState() < self::STATE_OK) {
                throw new InvalidOperationException('Cannot execute commands before connection');
            }

            yield from $this->callAsyncPqMethodAndAwaitResult($method, $args);

            $this->getResultAndThrowIfNotOK();
        });
    }

    public function notify(string $channel, string $message): Promise
    {
        $method = [$this->pqConnection, 'notifyAsync'];
        $args = [$channel, $message];

        return $this->mutex->withLock(function() use($method, $args, $channel, $message) {
            if ($this->getConnectionState() < self::STATE_OK) {
                throw new InvalidOperationException('Cannot execute commands before connection');
            }

            yield from $this->callAsyncPqMethodAndAwaitResult($method, $args);

            $this->getResultAndThrowIfNotOK();
        });
    }
}
