<?php

namespace Faerber\KernelCadence\Analysis;

use Stringable;

/**
 * How many tasks share one clock minute.
 */
final readonly class MinuteLoad implements Stringable {
    public function __construct(
        public int $minute,
        public int $tasks,
    ) {
    }

    public function exceeds(int $limit): bool {
        return $this->tasks > $limit;
    }

    public function __toString(): string {
        return sprintf(':%02d (%d tasks)', $this->minute, $this->tasks);
    }
}
