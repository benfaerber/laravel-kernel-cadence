<?php

namespace Faerber\KernelCadence;

use ArrayIterator;
use Countable;
use Faerber\KernelCadence\Exceptions\InvalidSpread;
use IteratorAggregate;
use Traversable;

/**
 * A set of cadences that share one interval and divide it into evenly phased lanes.
 *
 * For fanning related work out instead of piling it onto one minute:
 *
 *   $lanes = CadenceSpread::of(MinuteInterval::of(15), 3);  // :00, :05, :10
 *
 * @implements IteratorAggregate<int, Cadence>
 */
final readonly class CadenceSpread implements Countable, IteratorAggregate {
    /** @param list<Cadence> $lanes */
    private function __construct(private array $lanes) {
    }

    public static function of(MinuteInterval $interval, int $lanes): self {
        self::guardLaneCount($interval, $lanes);

        return new self(array_map(
            fn (int $lane): Cadence => Cadence::everyMinutes(
                $interval->minutes,
                $interval->phaseOfLane($lane, $lanes)->minutes,
            ),
            range(0, $lanes - 1),
        ));
    }

    public function lane(int $index): Cadence {
        return $this->lanes[$index];
    }

    /** @return list<Cadence> */
    public function all(): array {
        return $this->lanes;
    }

    /** @return list<string> */
    public function expressions(): array {
        return array_map(fn (Cadence $lane): string => $lane->expression(), $this->lanes);
    }

    /** @return list<int> */
    public function offsets(): array {
        return array_map(fn (Cadence $lane): int => $lane->offset()->minutes, $this->lanes);
    }

    public function count(): int {
        return count($this->lanes);
    }

    /** @return Traversable<int, Cadence> */
    public function getIterator(): Traversable {
        return new ArrayIterator($this->lanes);
    }

    /**
     * More lanes than minutes in the interval would collide, and cron cannot
     * resolve finer than a minute, so two lanes would fire together.
     */
    private static function guardLaneCount(MinuteInterval $interval, int $lanes): void {
        if ($lanes < 1 || $lanes > $interval->minutes) {
            throw InvalidSpread::tooManyLanes($interval, $lanes);
        }
    }
}
