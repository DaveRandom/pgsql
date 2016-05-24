<?php

namespace Amp\Pgsql\Exceptions;

/**
 * Base class for exceptions thrown during operations that communicate with the server
 */
abstract class ServerOperationFailureException extends Exception {}
