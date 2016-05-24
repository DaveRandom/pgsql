<?php

namespace Amp\Pgsql\Exceptions;

/**
 * Thrown when the result status is not expected in the context of the current operation
 */
class UnexpectedResultStatusException extends ServerOperationFailureException {}
