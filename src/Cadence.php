<?php

namespace Faerber\KernelCadence;

use Faerber\KernelCadence\Cron\CronFields;
use Faerber\KernelCadence\Cron\StepMinutes;
use Stringable;

/**
 * A sub-hourly cron cadence with an explicit phase offset.
 *
 * Laravel's everyFiveMinutes()/everyTenMinutes()/everyFifteenMinutes()/... helpers
 * all phase-lock to minute 0, so using them silently stacks another task onto the
 * top of the hour. The hour-based helpers already accept an offset
 * (everySixHours(18), everyTwoHours(25)); this fills the same gap for minutes.
 *
 *   Cadence::everyMinutes(15, offsetBy: 8)  //=> '8-59/15 * * * *'  (:08 :23 :38 :53)
 */
final readonly class Cadence implements Stringable {
    private function __construct(private StepMinutes $minutes) {
    }

    /** A cadence firing every $interval minutes, phased $offsetBy minutes past :00. */
    public static function everyMinutes(int $interval, int $offsetBy = 0): self {
        return new self(StepMinutes::every(
            MinuteInterval::of($interval),
            MinuteOffset::of($offsetBy),
        ));
    }

    public static function everyTwoMinutes(int $offsetBy = 0): self {
        return self::everyMinutes(2, $offsetBy);
    }

    public static function everyThreeMinutes(int $offsetBy = 0): self {
        return self::everyMinutes(3, $offsetBy);
    }

    public static function everyFourMinutes(int $offsetBy = 0): self {
        return self::everyMinutes(4, $offsetBy);
    }

    public static function everyFiveMinutes(int $offsetBy = 0): self {
        return self::everyMinutes(5, $offsetBy);
    }

    public static function everyTenMinutes(int $offsetBy = 0): self {
        return self::everyMinutes(10, $offsetBy);
    }

    public static function everyFifteenMinutes(int $offsetBy = 0): self {
        return self::everyMinutes(15, $offsetBy);
    }

    public static function everyThirtyMinutes(int $offsetBy = 0): self {
        return self::everyMinutes(30, $offsetBy);
    }

    public function offsetBy(int $minutes): self {
        return new self($this->minutes->offsetBy(MinuteOffset::of($minutes)));
    }

    /** Splits this cadence into $lanes evenly phased cadences, for fanning work out. */
    public function spreadAcross(int $lanes): CadenceSpread {
        return CadenceSpread::of($this->interval(), $lanes);
    }

    /** The five field cron expression, ready for $schedule->cron(). */
    public function expression(): string {
        return $this->fields()->toExpression();
    }

    public function fields(): CronFields {
        return CronFields::parse($this->minuteField() . ' * * * *');
    }

    public function minuteField(): string {
        return $this->minutes->toExpression();
    }

    /** @return list<int> the clock minutes this cadence fires on, ascending */
    public function minutes(): array {
        return $this->minutes->minutes();
    }

    public function interval(): MinuteInterval {
        return $this->minutes->interval;
    }

    public function offset(): MinuteOffset {
        return $this->minutes->offset;
    }

    public function firesPerHour(): int {
        return $this->interval()->firesPerHour();
    }

    public function equals(self $other): bool {
        return $this->expression() === $other->expression();
    }

    public function __toString(): string {
        return $this->expression();
    }
}
