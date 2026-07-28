<?php

namespace Faerber\KernelCadence\Cron;

use Faerber\KernelCadence\MinuteOffset;

/**
 * A fixed set of clock minutes: '0' from hourly(), '0,30' from a hand written field.
 *
 * Offsetting slides every minute in the set forward by the same amount, so
 * hourly()->offsetBy(7) becomes hourlyAt(7).
 */
final readonly class ExactMinutes implements MinuteField {
    /** @param list<int> $minutes */
    private function __construct(private array $minutes) {
    }

    /** @param list<int> $minutes */
    public static function at(array $minutes): self {
        return new self($minutes);
    }

    public function offsetBy(MinuteOffset $offset): self {
        return new self(array_map(
            fn (int $minute): int => $offset->shift($minute),
            $this->minutes,
        ));
    }

    public function toExpression(): string {
        return implode(',', $this->minutes);
    }

    /** @return list<int> */
    public function minutes(): array {
        return $this->minutes;
    }

    public function __toString(): string {
        return $this->toExpression();
    }
}
