<?php

namespace Faerber\KernelCadence\Analysis;

use Cron\CronExpression;
use Faerber\KernelCadence\Cron\CronFields;
use Faerber\KernelCadence\MinuteInterval;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * A histogram of how many scheduled tasks fire on each minute of the hour.
 *
 * Laravel's hourly()/everyFiveMinutes()/everyFifteenMinutes()/... helpers all
 * phase-lock to minute 0, so reaching for them instead of an explicit offset
 * silently piles another task onto :00. Most scheduled commands are thin
 * dispatchers onto one queue, which fans straight out to every worker and lands
 * on the database all at once.
 *
 * This measures that pile-up so a test or a CI check can fail on it.
 */
final readonly class ScheduleLoad {
    /** Any instant works; the schedule shape within an hour is what matters. */
    private const REFERENCE_HOUR = '2026-01-01 00:%02d:00';

    /** @param non-empty-array<int, int> $tasksByMinute one entry per minute of the hour */
    private function __construct(private array $tasksByMinute) {
    }

    /**
     * Every task in the schedule, counted on the minutes of the hour it lands on.
     *
     * @param Schedule|iterable<Event> $events
     */
    public static function of(Schedule|iterable $events): self {
        return self::fromExpressions(self::expressionsOf($events));
    }

    /**
     * Only the tasks that repeat every hour, which is the load that actually
     * competes for workers on any given minute. A daily 03:00 report is not
     * part of the :00 stampede the rest of the day.
     *
     * @param Schedule|iterable<Event> $events
     */
    public static function ofHourlyRecurring(Schedule|iterable $events): self {
        return self::fromExpressions(array_filter(
            self::expressionsOf($events),
            fn (string $expression): bool => CronFields::parse($expression)->repeatsEveryHour(),
        ));
    }

    /** @param iterable<string> $expressions */
    public static function fromExpressions(iterable $expressions): self {
        $tasksByMinute = array_fill(0, MinuteInterval::MINUTES_PER_HOUR, 0);

        foreach ($expressions as $expression) {
            foreach (self::minutesFiring($expression) as $minute) {
                $tasksByMinute[$minute]++;
            }
        }

        return new self($tasksByMinute);
    }

    public function at(int $minute): MinuteLoad {
        return new MinuteLoad($minute, $this->tasksByMinute[$minute]);
    }

    /** The busiest minute; ties resolve to the earliest, which is usually :00. */
    public function peak(): MinuteLoad {
        $busiest = max($this->tasksByMinute);

        return new MinuteLoad((int) array_search($busiest, $this->tasksByMinute, true), $busiest);
    }

    /** @return list<MinuteLoad> every minute carrying more than $limit tasks, busiest first */
    public function exceeding(int $limit): array {
        $over = array_filter($this->tasksByMinute, fn (int $tasks): bool => $tasks > $limit);
        arsort($over);

        return array_map(
            fn (int $minute): MinuteLoad => $this->at($minute),
            array_keys($over),
        );
    }

    public function mean(): float {
        return $this->totalTasks() / MinuteInterval::MINUTES_PER_HOUR;
    }

    public function totalTasks(): int {
        return array_sum($this->tasksByMinute);
    }

    /** @return array<int, int> minute of hour => number of tasks firing */
    public function tasksByMinute(): array {
        return $this->tasksByMinute;
    }

    /** A one-line-per-minute bar chart, for a failing assertion message. */
    public function histogram(): string {
        return implode("\n", $this->histogramRows());
    }

    /** @return list<string> one bar chart row per minute of the hour */
    public function histogramRows(): array {
        $rows = [];

        foreach ($this->tasksByMinute as $minute => $tasks) {
            $rows[] = sprintf('  :%02d  %2d  %s', $minute, $tasks, str_repeat('#', $tasks));
        }

        return $rows;
    }

    /**
     * @param Schedule|iterable<Event> $events
     * @return list<string>
     */
    private static function expressionsOf(Schedule|iterable $events): array {
        $events = $events instanceof Schedule ? $events->events() : $events;
        $expressions = [];

        foreach ($events as $event) {
            $expressions[] = $event->expression;
        }

        return $expressions;
    }

    /**
     * The minutes of the hour a task lands on, whichever hours it runs in. A
     * daily 03:00 report still competes for workers at :00 on the hour it runs,
     * so its minute is what matters here, not its hour.
     *
     * @return list<int>
     */
    private static function minutesFiring(string $expression): array {
        $everyHour = new CronExpression(CronFields::parse($expression)->minuteFieldExpression() . ' * * * *');
        $minutes = [];

        foreach (range(0, MinuteInterval::LAST_MINUTE) as $minute) {
            if ($everyHour->isDue(sprintf(self::REFERENCE_HOUR, $minute))) {
                $minutes[] = $minute;
            }
        }

        return $minutes;
    }
}
