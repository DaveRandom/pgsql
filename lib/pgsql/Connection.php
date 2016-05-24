<?php declare(strict_types = 1);

namespace Amp\Pgsql\pgsql;

use Amp\Pgsql\Connection as PgsqlConnection;
use Amp\Pgsql\Exceptions\NotImplementedException;
use Amp\Promise;

class Connection implements PgsqlConnection
{
    public function getConnectionState(): int
    {
        throw new NotImplementedException; // todo
    }

    public function getSubscribedChannels(): array
    {
        throw new NotImplementedException; // todo
    }

    public function connect(): Promise
    {
        throw new NotImplementedException; // todo
    }

    public function close() /* : void */
    {
        throw new NotImplementedException; // todo
    }

    public function reset(): Promise
    {
        throw new NotImplementedException; // todo
    }

    public function setOption(int $option, /* mixed */ $value) /* : void */
    {
        throw new NotImplementedException; // todo
    }

    public function getOption(int $option) /* : mixed */
    {
        throw new NotImplementedException; // todo
    }

    public function getNotices(): array
    {
        throw new NotImplementedException; // todo
    }

    public function executeQuery(string $sql, array $params = null, array $types = null): Promise
    {
        throw new NotImplementedException; // todo
    }

    public function executeCommand(string $sql, array $params = null, array $types = null): Promise
    {
        throw new NotImplementedException; // todo
    }

    public function prepareQuery(string $name, string $sql, array $types = null): Promise
    {
        throw new NotImplementedException; // todo
    }

    public function prepareCommand(string $name, string $sql, array $types = null): Promise
    {
        throw new NotImplementedException; // todo
    }

    public function beginTransaction(int $flags = 0): Promise
    {
        throw new NotImplementedException; // todo
    }

    public function listen(string $channel, callable $callback): Promise
    {
        throw new NotImplementedException; // todo
    }

    public function unlisten(string $subscriptionId): Promise
    {
        throw new NotImplementedException; // todo
    }

    public function notify(string $channel, string $message): Promise
    {
        throw new NotImplementedException; // todo
    }
}
