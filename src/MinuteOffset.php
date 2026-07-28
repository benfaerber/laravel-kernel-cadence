<?php

namespace Faerber\KernelCadence;

use Faerber\KernelCadence\Exceptions\InvalidOffset;
use Stringable;

/**
 * A phase within the hour, measured in whole minutes from :00.
 */
final readonly class MinuteOffset implements Stringable {
    private function __construct(public int $minutes) {
        $this->guardWithinTheHour();
    }

    public static function of(int $minutes): self {
        return new self($minutes);
    }

    public static function none(): self {
        return new self(0);
    }

    public function isNone(): bool {
        return $this->minutes === 0;
    }

    /** Slides a fixed clock minute forward by this phase, staying inside the hour. */
    public function shift(int $minute): int {
        $shifted = $minute + $this->minutes;

        if ($shifted > MinuteInterval::LAST_MINUTE) {
            throw InvalidOffset::pushesPastTheHour($minute, $this->minutes);
        }

        return $shifted;
    }

    public function equals(self $other): bool {
        return $this->minutes === $other->minutes;
    }

    public function __toString(): string {
        return sprintf(':%02d', $this->minutes);
    }

    private function guardWithinTheHour(): void {
        if ($this->minutes < 0 || $this->minutes > MinuteInterval::LAST_MINUTE) {
            throw InvalidOffset::outsideTheHour($this->minutes);
        }
    }
}
