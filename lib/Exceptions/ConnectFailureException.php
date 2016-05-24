<?php

namespace Amp\Pgsql\Exceptions;

/**
 * Thrown when establishing a connection with the server fails
 */
class ConnectFailureException extends ServerOperationFailureException {}
