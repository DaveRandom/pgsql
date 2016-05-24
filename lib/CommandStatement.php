<?php declare(strict_types=1);

namespace Amp\Pgsql;

use Amp\Promise;

interface CommandStatement extends Statement
{
    /**
     * Execute the statement and return the number of affected rows
     *
     * @param array|null $params
     * @return Promise<int>
     * @throws InvalidOperationException
     * @throws CommandDispatchFailureException
     */
    public function execute(array $params = null): Promise;
}
