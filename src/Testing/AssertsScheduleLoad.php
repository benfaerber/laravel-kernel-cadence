<?php

namespace Faerber\KernelCadence\Testing;

use Cron\CronExpression;
use Faerber\KernelCadence\Analysis\ScheduleLoad;
use Faerber\KernelCadence\MinuteInterval;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Assertions that guard against the top-of-hour stampede.
 *
 * Mix into a test case to hold the whole schedule to a load budget:
 *
 *   $this->assertNoMinuteCarriesMoreThan(12, $this->hourlyRecurringLoad(app(Schedule::class)));
 */
trait AssertsScheduleLoad {
    /** No single clock minute may carry more than $limit every-hour tasks. */
    public function assertNoMinuteCarriesMoreThan(int $limit, ScheduleLoad $load): void {
        $this->assertScheduleWasMeasured($load);

        $peak = $load->peak();

        $this->assertLessThanOrEqual(
            $limit,
            $peak->tasks,
            sprintf(
                "Minute :%02d has %d every-hour tasks scheduled (limit %d).\n"
                . "Give some of them an explicit offset with Cadence::everyMinutes(\$interval, offsetBy: \$n).\n%s",
                $peak->minute,
                $peak->tasks,
                $limit,
                $load->histogram(),
            ),
        );
    }

    /** The named minute may not carry more than $factor times the hourly average. */
    public function assertMinuteIsNotAnOutlier(int $minute, ScheduleLoad $load, float $factor = 2.0): void {
        $this->assertScheduleWasMeasured($load);

        $mean = $load->mean();
        $actual = $load->at($minute);

        $this->assertLessThanOrEqual(
            $mean * $factor,
            $actual->tasks,
            sprintf(
                "Minute :%02d carries %d every-hour tasks, more than %.1fx the %.1f average.\n"
                . "Something was probably scheduled with hourly()/everyNMinutes() instead of an explicit offset.\n%s",
                $minute,
                $actual->tasks,
                $factor,
                $mean,
                $load->histogram(),
            ),
        );
    }

    /** Shorthand for the common case: :00 is the minute that collects stragglers. */
    public function assertTopOfTheHourIsNotAnOutlier(ScheduleLoad $load, float $factor = 2.0): void {
        $this->assertMinuteIsNotAnOutlier(0, $load, $factor);
    }

    /**
     * $earlier must fire before $later within the hour, for pipelines where one
     * command reads the rows the previous one wrote.
     *
     * @param Schedule|iterable<Event> $events
     */
    public function assertRunsBefore(string $earlier, string $later, Schedule|iterable $events): void {
        $first = $this->firstMinuteOfCommand($earlier, $events);
        $second = $this->firstMinuteOfCommand($later, $events);

        $this->assertLessThan(
            $second,
            $first,
            sprintf('%s (:%02d) must run before %s (:%02d).', $earlier, $first, $later, $second),
        );
    }

    /**
     * A budget assertion against an empty schedule passes without measuring
     * anything, which is worse than no test at all. Usually it means the
     * schedule was read from the container before artisan populated it.
     */
    public function assertScheduleWasMeasured(ScheduleLoad $load): void {
        $this->assertGreaterThan(
            0,
            $load->totalTasks(),
            'The schedule carries no tasks, so there is nothing to hold to a budget. '
            . 'Build it with ApplicationSchedule::resolve($this->app) rather than resolving Schedule directly.',
        );
    }

    /** @param Schedule|iterable<Event> $events */
    public function hourlyRecurringLoad(Schedule|iterable $events): ScheduleLoad {
        return ScheduleLoad::ofHourlyRecurring($events);
    }

    /** @param Schedule|iterable<Event> $events */
    public function scheduleLoad(Schedule|iterable $events): ScheduleLoad {
        return ScheduleLoad::of($events);
    }

    /** @param Schedule|iterable<Event> $events */
    private function firstMinuteOfCommand(string $command, Schedule|iterable $events): int {
        $events = $events instanceof Schedule ? $events->events() : $events;

        foreach ($events as $event) {
            if (! str_contains((string) $event->command, $command)) {
                continue;
            }

            $minutes = $this->minutesFiring($event->expression);

            if ($minutes === []) {
                $this->fail("{$command} never fires within an hour.");
            }

            return min($minutes);
        }

        $this->fail("{$command} is not scheduled.");
    }

    /** @return list<int> */
    private function minutesFiring(string $expression): array {
        $cron = new CronExpression($expression);

        return array_values(array_filter(
            range(0, MinuteInterval::LAST_MINUTE),
            fn (int $minute): bool => $cron->isDue(sprintf('2026-01-01 00:%02d:00', $minute)),
        ));
    }
}
