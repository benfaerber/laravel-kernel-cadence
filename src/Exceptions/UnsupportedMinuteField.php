<?php

namespace Faerber\KernelCadence\Exceptions;

final class UnsupportedMinuteField extends CadenceException {
    public static function unparsable(string $field): self {
        return new self(
            "Cannot phase-shift the minute field '{$field}'. "
            . 'Supported forms are *, */step, offset-59/step, and comma separated minutes.',
        );
    }

    public static function everyMinuteHasNoPhase(): self {
        return new self(
            'A schedule that runs every minute has no phase to offset. '
            . 'Reach for everyMinutes() with an interval of 2 or more first.',
        );
    }

    public static function notFiveFields(string $expression, int $found): self {
        return new self(
            "A cron expression must have 5 fields, got {$found} in '{$expression}'.",
        );
    }
}
