<?php

namespace Faerber\KernelCadence\Cron;

use Faerber\KernelCadence\MinuteOffset;
use Stringable;

/**
 * The first field of a cron expression, in a form that can be re-phased.
 */
interface MinuteField extends Stringable {
    /** Returns the same cadence shifted to a new phase within the hour. */
    public function offsetBy(MinuteOffset $offset): self;

    public function toExpression(): string;

    /** @return list<int> the clock minutes this field fires on, ascending */
    public function minutes(): array;
}
