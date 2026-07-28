<?php

namespace Faerber\KernelCadence\Exceptions;

final class InvalidInterval extends CadenceException {
    public static function outsideTheHour(int $minutes): self {
        return new self(
            "Cadence interval must be between 1 and 59 minutes, got {$minutes}. "
            . 'Use hourlyAt() or everySixHours() for hour-based cadences.',
        );
    }

    public static function doesNotDivideTheHour(int $minutes): self {
        return new self(
            "Cadence interval must divide 60 evenly, got {$minutes}, "
            . 'otherwise the gap across the top of the hour is uneven.',
        );
    }
}
