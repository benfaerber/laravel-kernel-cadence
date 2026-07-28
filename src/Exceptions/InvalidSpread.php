<?php

namespace Faerber\KernelCadence\Exceptions;

use Faerber\KernelCadence\MinuteInterval;

final class InvalidSpread extends CadenceException {
    public static function tooManyLanes(MinuteInterval $interval, int $lanes): self {
        return new self(
            "A {$interval->minutes} minute cadence supports between 1 and {$interval->minutes} lanes, got {$lanes}. "
            . 'Cron cannot resolve finer than a minute, so extra lanes would collide.',
        );
    }
}
