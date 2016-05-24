<?php declare(strict_types = 1);

/**
 * This demo script is only for testing while under active dev
 *
 * @todo remove this and replace with proper examples and tests
 */

namespace Amp\Pgsql;

use Amp\Pgsql\pq\Connection as pqConnection;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/config.php';

\Amp\run(function() {
    /** @var Cursor $cur */
    /** @var QueryStatement $stmt */

    $conn = new pqConnection(sprintf('postgres://%s:%s@%s/%s', PGSQL_USER, PGSQL_PASS, PGSQL_HOST, PGSQL_DBNAME));

    yield $conn->connect();

    $cur = yield $conn->executeQuery("SELECT * FROM shared.companies ORDER BY id ASC LIMIT 5");
    while ($row = yield $cur->fetchRow()) {
        var_dump($row);
    }
    $cur->close();

    $stmt = yield $conn->prepareQuery('test', "SELECT * FROM shared.companies WHERE id = $1 ORDER BY id ASC LIMIT 5");
    $cur = yield $stmt->execute([1]);
    while ($row = yield $cur->fetchRow()) {
        var_dump($row);
    }
    $cur->close();

    var_dump(yield $conn->executeCommand("INSERT INTO shared.agencies (company_id) VALUES (1)"));

    $conn->close();
});
