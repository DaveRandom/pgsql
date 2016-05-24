<?php declare(strict_types=1);

namespace Amp\Pgsql;

use Amp\Promise;

interface QueryStatement extends Statement
{
    /**
     * Execute the statement and return a cursor for the result set
     *
     * @param array|null $params
     * @return Promise<Cursor>
     * @throws InvalidOperationException
     * @throws CommandDispatchFailureException
     */
    public function execute(array $params = null): Promise;
}
