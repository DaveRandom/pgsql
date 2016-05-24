<?php

namespace Amp\Pgsql\Exceptions;

/**
 * Thrown when a server result was expected but could not be retrieved
 */
class ResultFetchFailureException extends ServerOperationFailureException {}
