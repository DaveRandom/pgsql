<?php

namespace Amp\Pgsql\Exceptions;

/**
 * Thrown when the server does something we don't understand
 */
class ServerProtocolViolationException extends ServerOperationFailureException {}
