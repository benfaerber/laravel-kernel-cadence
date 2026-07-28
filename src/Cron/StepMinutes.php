<?php

namespace Faerber\KernelCadence\Cron;

use Faerber\KernelCadence\Exceptions\InvalidOffset;
use Faerber\KernelCadence\MinuteInterval;
use Faerber\KernelCadence\MinuteOffset;

/**
 * A stepped minute field: '*&#47;15' at phase zero, '8-59/15' at any other phase.
 */
final readonly class StepMinutes implements MinuteField {
    private function __construct(
        public MinuteInterval $interval,
        public MinuteOffset $offset,
    ) {
        $this->guardPhase();
    }

    public static function every(MinuteInterval $interval, ?MinuteOffset $offset = null): self {
        return new self($interval, $offset ?? MinuteOffset::none());
    }

    public function offsetBy(MinuteOffset $offset): self {
        return new self($this->interval, $offset);
    }

    public function toExpression(): string {
        if ($this->offset->isNone()) {
            return "*/{$this->interval->minutes}";
        }

        return "{$this->offset->minutes}-" . MinuteInterval::LAST_MINUTE . "/{$this->interval->minutes}";
    }

    /** @return list<int> */
    public function minutes(): array {
        $minutes = [];

        for ($minute = $this->offset->minutes; $minute <= MinuteInterval::LAST_MINUTE; $minute += $this->interval->minutes) {
            $minutes[] = $minute;
        }

        return $minutes;
    }

    public function __toString(): string {
        return $this->toExpression();
    }

    /**
     * An offset at or beyond the interval is just a different phase of the same
     * cadence, which makes two schedule lines that look different behave alike.
     */
    private function guardPhase(): void {
        if (! $this->interval->permits($this->offset)) {
            throw InvalidOffset::outOfPhase($this->offset->minutes, $this->interval->minutes);
        }
    }
}
