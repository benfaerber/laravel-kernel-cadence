<?php

namespace Faerber\KernelCadence;

use Faerber\KernelCadence\Exceptions\InvalidInterval;
use Stringable;

/**
 * A sub-hourly interval that tiles the hour evenly.
 */
final readonly class MinuteInterval implements Stringable {
    public const MINUTES_PER_HOUR = 60;
    public const LAST_MINUTE = self::MINUTES_PER_HOUR - 1;

    private function __construct(public int $minutes) {
        $this->guardWithinTheHour();
        $this->guardDividesTheHour();
    }

    public static function of(int $minutes): self {
        return new self($minutes);
    }

    /** Every phase of this interval is a distinct schedule; anything else repeats one. */
    public function permits(MinuteOffset $offset): bool {
        return $offset->minutes < $this->minutes;
    }

    public function isEveryMinute(): bool {
        return $this->minutes === 1;
    }

    public function firesPerHour(): int {
        return intdiv(self::MINUTES_PER_HOUR, $this->minutes);
    }

    /**
     * The phase for one lane of an even spread, rounded down to a whole minute.
     *
     * Lane 1 of 3 across a 15 minute interval is :05; lane 1 of 2 is :07, since
     * cron has no finer resolution than a minute.
     */
    public function phaseOfLane(int $lane, int $lanes): MinuteOffset {
        return MinuteOffset::of(intdiv($this->minutes * $lane, $lanes));
    }

    public function equals(self $other): bool {
        return $this->minutes === $other->minutes;
    }

    public function __toString(): string {
        return "{$this->minutes}m";
    }

    private function guardWithinTheHour(): void {
        if ($this->minutes < 1 || $this->minutes > self::LAST_MINUTE) {
            throw InvalidInterval::outsideTheHour($this->minutes);
        }
    }

    /**
     * An interval that does not divide the hour leaves an uneven gap when the
     * cadence wraps past :59, so the effective spacing would not be the interval.
     */
    private function guardDividesTheHour(): void {
        if (self::MINUTES_PER_HOUR % $this->minutes !== 0) {
            throw InvalidInterval::doesNotDivideTheHour($this->minutes);
        }
    }
}
