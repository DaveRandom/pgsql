<?php

namespace Amp\Pgsql\Exceptions;

/**
 * Thrown when an attempt is made to access an option with an invalid option number
 */
class UnknownOptionException extends OptionException {}
