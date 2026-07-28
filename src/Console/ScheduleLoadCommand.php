<?php

namespace Faerber\KernelCadence\Console;

use Faerber\KernelCadence\Analysis\MinuteLoad;
use Faerber\KernelCadence\Analysis\ScheduleLoad;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use InvalidArgumentException;

/**
 * Prints the per-minute load of the real schedule, and optionally fails when a
 * single minute carries too much. Lets CI hold a load budget without a test.
 *
 *   php artisan schedule:load --max=12
 */
final class ScheduleLoadCommand extends Command {
    protected $signature = 'schedule:load
        {--max= : Fail when any single minute carries more than this many tasks}
        {--all : Include tasks that do not repeat every hour}';

    protected $description = 'Show how many scheduled tasks fire on each minute of the hour';

    public function handle(Schedule $schedule): int {
        $load = $this->loadOf($schedule);

        foreach ($load->histogramRows() as $row) {
            $this->line($row);
        }

        $this->summarize($load);

        return $this->verdict($load);
    }

    private function loadOf(Schedule $schedule): ScheduleLoad {
        return $this->option('all')
            ? ScheduleLoad::of($schedule)
            : ScheduleLoad::ofHourlyRecurring($schedule);
    }

    private function summarize(ScheduleLoad $load): void {
        $peak = $load->peak();

        $this->newLine();
        $this->line(sprintf(
            '%d task firings per hour, %.1f per minute on average, peaking at %s.',
            $load->totalTasks(),
            $load->mean(),
            $peak,
        ));
    }

    private function verdict(ScheduleLoad $load): int {
        $limit = $this->limit();

        if ($limit === null) {
            return self::SUCCESS;
        }

        $over = $load->exceeding($limit);

        if ($over === []) {
            $this->info("No minute carries more than {$limit} tasks.");

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'These minutes exceed the limit of %d: %s',
            $limit,
            implode(', ', array_map(fn (MinuteLoad $minute): string => (string) $minute, $over)),
        ));

        return self::FAILURE;
    }

    /** A budget that is not a whole number of tasks is a typo, not a limit of zero. */
    private function limit(): ?int {
        $max = $this->option('max');

        if ($max === null) {
            return null;
        }

        if (! is_string($max) || ! ctype_digit($max)) {
            throw new InvalidArgumentException(
                '--max must be a whole number of tasks, got ' . json_encode($max) . '.',
            );
        }

        return (int) $max;
    }
}
