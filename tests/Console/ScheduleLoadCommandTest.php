<?php

namespace Faerber\KernelCadence\Tests\Console;

use Faerber\KernelCadence\Cadence;
use Faerber\KernelCadence\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;

class ScheduleLoadCommandTest extends TestCase {
    public function test_it_prints_a_histogram_of_the_schedule(): void {
        $this->schedule(['0 * * * *', '0 * * * *', '7 * * * *']);

        $this->artisan('schedule:load')
            ->expectsOutputToContain('  :00   2  ##')
            ->expectsOutputToContain('  :07   1  #')
            ->expectsOutputToContain('peaking at :00 (2 tasks)')
            ->assertSuccessful();
    }

    public function test_it_passes_when_every_minute_is_within_the_budget(): void {
        $this->schedule(Cadence::everyFifteenMinutes()->spreadAcross(15)->expressions());

        $this->artisan('schedule:load --max=12')
            ->expectsOutputToContain('No minute carries more than 12 tasks.')
            ->assertSuccessful();
    }

    public function test_it_fails_when_a_minute_exceeds_the_budget(): void {
        $this->schedule(array_fill(0, 13, '*/15 * * * *'));

        $this->artisan('schedule:load --max=12')
            ->expectsOutputToContain('These minutes exceed the limit of 12')
            ->assertFailed();
    }

    public function test_it_ignores_tasks_that_do_not_repeat_every_hour_by_default(): void {
        $this->schedule(array_fill(0, 20, '0 3 * * *'));

        $this->artisan('schedule:load --max=12')->assertSuccessful();
        $this->artisan('schedule:load --max=12 --all')->assertFailed();
    }

    /** @param list<string> $expressions */
    private function schedule(array $expressions): void {
        $schedule = $this->app->make(Schedule::class);

        foreach ($expressions as $index => $expression) {
            $schedule->command("cron:task{$index}")->cron($expression);
        }
    }
}
