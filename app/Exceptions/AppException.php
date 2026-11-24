<?php

declare(strict_types=1);

namespace Patylook\Exceptions;

use RuntimeException;

/**
 * Base class of every error the application raises on purpose. The front controller translates
 * the subclasses into HTTP responses; anything else is treated as an internal error.
 */
abstract class AppException extends RuntimeException
{
}
