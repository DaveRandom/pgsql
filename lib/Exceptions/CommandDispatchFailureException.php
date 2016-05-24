<?php

namespace Amp\Pgsql\Exceptions;

/**
 * Thrown when a command cannot be sent to the server
 */
class CommandDispatchFailureException extends ServerOperationFailureException {}
