<?php

namespace Amp\Pgsql\Exceptions;

/**
 * Used only for assertions. Really want this to explode, so not based on our base Exception class.
 */
class InternalLogicErrorException extends \LogicException {}
