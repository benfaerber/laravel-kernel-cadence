<?php

namespace Faerber\KernelCadence\Exceptions;

use InvalidArgumentException;

/**
 * Base for every cadence misconfiguration.
 *
 * Extends InvalidArgumentException so a misconfigured schedule fails loudly at
 * definition time, during deploy, rather than silently never running.
 */
abstract class CadenceException extends InvalidArgumentException {
}
