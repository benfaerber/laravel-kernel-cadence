<?php

namespace Faerber\KernelCadence\Cron;

use Faerber\KernelCadence\Exceptions\UnsupportedMinuteField;
use Faerber\KernelCadence\MinuteInterval;
use Faerber\KernelCadence\MinuteOffset;

/**
 * The wildcard minute field, '*'. Fires on every minute, so it has no phase.
 */
final readonly class EveryMinute implements MinuteField {
    public static function wildcard(): self {
        return new self();
    }

    public function offsetBy(MinuteOffset $offset): self {
        throw UnsupportedMinuteField::everyMinuteHasNoPhase();
    }

    public function toExpression(): string {
        return '*';
    }

    /** @return list<int> */
    public function minutes(): array {
        return range(0, MinuteInterval::LAST_MINUTE);
    }

    public function __toString(): string {
        return $this->toExpression();
    }
}
