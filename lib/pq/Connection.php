<?php

namespace Amp\Pgsql\pq;

use Amp\Deferred;
use Amp\Mutex\Lock;
use Amp\Mutex\Mutex;
use Amp\Mutex\QueuedExclusiveMutex;
use Amp\Pgsql\Connection as PgsqlConnection;
use Amp\Pgsql\Cursor as PgsqlCursor;
use Amp\Pgsql\NotImplementedException;
use Amp\Pgsql\OptionDefinitionFailureException;
use Amp\Pgsql\UnknownOptionException;
use Amp\Promise;
use Amp\Success;
use pq\Connection as pqConnection;
use pq\Result as pqResult;
use function Amp\any;
use function Amp\cancel;
use function Amp\disable;
use function Amp\enable;
use function Amp\first;
use function Amp\immediately;
use function Amp\onReadable;
use function Amp\onWritable;
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
     * @var int
     */
    private $connectionState = self::STATE_CLOSED;

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
     * @var callable
     */
    private $channelNotificationHandler;

    /**
     * @var string
     */
    private $dsn;

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
            new \LogicException('Cannot await writable from more than one source')
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
     * @return string|null
     */
    private function unregisterChannelSubscription(string $id)
    {
        $channel = explode('_', $id, 2)[0];

        if (!isset($this->subscribedChannels[$channel][$id])) {
            throw new \LogicException('Invalid channel listener ID'); //todo ex class
        }

        unset($this->subscribedChannels[$channel][$id]);

        return isset($this->subscribedChannels[$channel]) ? $channel : null;
    }

    public function callAsyncPqMethodAndAwaitResult(callable $method, array $args): \Generator
    {
        yield from $this->clearPendingResults();

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
                throw new \RuntimeException($this->pqConnection->errorMessage); // todo ex class
            }
        }
    }

    public function flushUntilCommandSent(): \Generator
    {
        while (!$this->pqConnection->flush()) {
            if (self::IS_READABLE === yield $this->awaitReadableOrWritable()) {
                $this->pqConnection->poll();
            }
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
            new \LogicException('Cannot await readable from more than one source')
        );

        $this->readableDeferred = new Deferred();
        return $this->readableDeferred->promise();
    }

    /**
     * @return int
     */
    public function getConnectionState(): int
    {
        return $this->connectionState;
    }

    /**
     * @return string[]
     */
    public function getSubscribedChannels(): array
    {
        return array_keys($this->subscribedChannels);
    }

    public function close()
    {
        if ($this->pqConnection === null) {
            throw new \LogicException('Cannot close connection: not open');
        }

        cancel($this->readableWatcherID);
        cancel($this->writableWatcherID);

        $this->pqConnection = null;
    }

    /**
     * @return Promise
     */
    public function connect(): Promise
    {
        if ($this->connectionState !== self::STATE_CLOSED) {
            throw new \LogicException('Cannot connect: connection not closed');
        }

        $this->pqConnection = new pqConnection($this->dsn, pqConnection::ASYNC);
        $this->pqConnection->unbuffered = true;
        $this->pqConnection->nonblocking = true;

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
            if ($this->writableDeferred === null) {
                throw new \LogicException('Writable watcher invoked with no deferred??');
            }

            disable($this->writableWatcherID);

            $deferred = $this->writableDeferred;
            $this->writableDeferred = null;

            $deferred->succeed(self::IS_WRITABLE);
        }, ['enable' => false]);

        $this->connectionState = self::STATE_CONNECTING;

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
                        throw new \RuntimeException($this->pqConnection->errorMessage); // todo ex class

                    default:
                        throw new \RuntimeException('Unknown poll status: ' . $status); // todo ex class
                }
            }

            $this->pqConnection->encoding = $this->options[self::OPTION_ENCODING];
            $this->connectionState = self::STATE_CONNECTED;
        });
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

    public function executeCommand(string $sql, array $params = null, array $types = null): Promise
    {
        if ($this->connectionState !== self::STATE_CONNECTED) {
            throw new \LogicException('Cannot execute commands before connection');
        }

        if ($params !== null) {
            $method = [$this->pqConnection, 'execParamsAsync'];
            $args = [$sql, $params, $types];
        } else {
            $method = [$this->pqConnection, 'execAsync'];
            $args = [$sql];
        }

        return $this->mutex->withLock(function() use($method, $args) {
            yield from $this->callAsyncPqMethodAndAwaitResult($method, $args);

            if (!$result = $this->pqConnection->getResult()) {
                throw new \RuntimeException('Failed to get a result'); //todo ex class
            }

            if ($result->status !== pqResult::COMMAND_OK) {
                throw new \RuntimeException($result->errorMessage, $result->status); // todo ex class, handle warnings somehow
            }

            return $result->affectedRows;
        });
    }

    public function executeQuery(string $sql, array $params = null, array $types = null): Promise
    {
        if ($this->connectionState !== self::STATE_CONNECTED) {
            throw new \LogicException('Cannot execute commands before connection');
        }

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

            try {
                yield from $this->callAsyncPqMethodAndAwaitResult($method, $args);

                if (!$result = $this->pqConnection->getResult()) {
                    throw new \RuntimeException('Failed to get a result'); //todo ex class
                }

                if ($result->status !== pqResult::SINGLE_TUPLE && $result->status !== pqResult::TUPLES_OK) {
                    throw new \RuntimeException($result->errorMessage, $result->status); //todo ex class, handle warnings somehow
                }

                return new Cursor($this, $this->pqConnection, $result, $lock);
            } catch (\Throwable $e) {
                $lock->release();
                throw $e;
            }
        });
    }

    public function listen(string $channel, callable $callback): Promise
    {
        if ($this->connectionState !== self::STATE_CONNECTED) {
            throw new \LogicException('Cannot execute commands before connection');
        }

        if (isset($this->subscribedChannels[$channel])) {
            return new Success($this->registerChannelSubscription($channel, $callback));
        }

        return $this->mutex->withLock(function() use($channel, $callback) {
            $method = [$this->pqConnection, 'listenAsync'];
            $args = [$channel, $this->channelNotificationHandler];

            yield from $this->callAsyncPqMethodAndAwaitResult($method, $args);

            if (!$result = $this->pqConnection->getResult()) {
                throw new \RuntimeException('Failed to get a result'); //todo ex class
            }

            if ($result->status !== pqResult::COMMAND_OK) {
                throw new \RuntimeException($result->errorMessage, $result->status); // todo ex class, handle warnings somehow
            }

            return $this->registerChannelSubscription($channel, $callback);
        });
    }

    public function notify(string $channel, string $message): Promise
    {
        if ($this->connectionState !== self::STATE_CONNECTED) {
            throw new \LogicException('Cannot execute commands before connection');
        }

        return $this->mutex->withLock(function() use($channel, $message) {
            $method = [$this->pqConnection, 'notifyAsync'];
            $args = [$channel, $message];

            yield from $this->callAsyncPqMethodAndAwaitResult($method, $args);

            if (!$result = $this->pqConnection->getResult()) {
                throw new \RuntimeException('Failed to get a result'); //todo ex class
            }

            if ($result->status !== pqResult::COMMAND_OK) {
                throw new \RuntimeException($result->errorMessage, $result->status); // todo ex class, handle warnings somehow
            }
        });
    }

    public function prepare(string $name, string $sql, array $types = null): Promise
    {
        if ($this->connectionState !== self::STATE_CONNECTED) {
            throw new \LogicException('Cannot execute commands before connection');
        }

        return $this->mutex->withLock(function() use($name, $sql, $types) {
            $method = [$this->pqConnection, 'prepareAsync'];
            $args = [$name, $sql, $types];

            $stmt = yield from $this->callAsyncPqMethodAndAwaitResult($method, $args);

            if (!$result = $this->pqConnection->getResult()) {
                throw new \RuntimeException('Failed to get a result'); //todo ex class
            }

            if ($result->status !== pqResult::COMMAND_OK) {
                throw new \RuntimeException($result->errorMessage, $result->status); // todo ex class, handle warnings somehow
            }

            return new Statement($this, $this->pqConnection, $stmt, $this->mutex, $sql, $types);
        });
    }

    public function reset(): Promise
    {
        throw new NotImplementedException('todo'); //todo
    }

    public function beginTransaction(int $flags = 0): Promise
    {
        throw new NotImplementedException('todo'); //todo
    }

    public function unlisten(string $subscriptionId): Promise
    {
        if ($this->connectionState !== self::STATE_CONNECTED) {
            throw new \LogicException('Cannot execute commands before connection');
        }

        if (!$channel = $this->unregisterChannelSubscription($subscriptionId)) {
            return new Success();
        }

        return $this->mutex->withLock(function() use($channel) {
            $method = [$this->pqConnection, 'unlistenAsync'];
            $args = [$channel];

            yield from $this->callAsyncPqMethodAndAwaitResult($method, $args);

            if (!$result = $this->pqConnection->getResult()) {
                throw new \RuntimeException('Failed to get a result'); //todo ex class
            }

            if ($result->status !== pqResult::COMMAND_OK) {
                throw new \RuntimeException($result->errorMessage, $result->status); // todo ex class, handle warnings somehow
            }
        });
    }
}
