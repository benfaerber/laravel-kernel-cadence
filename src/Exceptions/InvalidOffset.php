<?php

namespace Faerber\KernelCadence\Exceptions;

final class InvalidOffset extends CadenceException {
    public static function outsideTheHour(int $minutes): self {
        return new self(
            "Cadence offset must be between 0 and 59 minutes, got {$minutes}.",
        );
    }

    public static function outOfPhase(int $offset, int $interval): self {
        return new self(
            "Cadence offset must be between 0 and {$interval} minutes (exclusive), got {$offset}.",
        );
    }

    public static function pushesPastTheHour(int $minute, int $offset): self {
        return new self(
            "Offsetting minute {$minute} by {$offset} pushes past :59, "
            . 'which would move the task into the next hour.',
        );
    }
}
