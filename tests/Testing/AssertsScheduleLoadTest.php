<?php

namespace Faerber\KernelCadence\Tests\Testing;

use Faerber\KernelCadence\Cadence;
use Faerber\KernelCadence\MinuteInterval;
use Faerber\KernelCadence\Testing\AssertsScheduleLoad;
use Faerber\KernelCadence\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\AssertionFailedError;

/**
 * The assertions this package ships, exercised against a real Schedule.
 *
 * This is the shape an application test takes: build the schedule the way
 * production builds it, then hold it to a per-minute budget.
 */
class AssertsScheduleLoadTest extends TestCase {
    use AssertsScheduleLoad;

    private const MAX_TASKS_PER_MINUTE = 12;

    public function test_a_spread_schedule_stays_under_the_budget(): void {
        $schedule = $this->scheduleOf(
            Cadence::everyFifteenMinutes()->spreadAcross(15)->expressions(),
        );

        $this->assertNoMinuteCarriesMoreThan(self::MAX_TASKS_PER_MINUTE, $this->hourlyRecurringLoad($schedule));
    }

    public function test_it_fails_when_a_single_minute_carries_too_much(): void {
        $schedule = $this->scheduleOf(array_fill(0, 13, '*/15 * * * *'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Minute :00 has 13 every-hour tasks scheduled (limit 12)');

        $this->assertNoMinuteCarriesMoreThan(self::MAX_TASKS_PER_MINUTE, $this->hourlyRecurringLoad($schedule));
    }

    public function test_the_failure_message_shows_the_histogram(): void {
        $schedule = $this->scheduleOf(array_fill(0, 13, '0 * * * *'));

        try {
            $this->assertNoMinuteCarriesMoreThan(self::MAX_TASKS_PER_MINUTE, $this->hourlyRecurringLoad($schedule));
        } catch (AssertionFailedError $failure) {
            $this->assertStringContainsString('  :00  13  #############', $failure->getMessage());

            return;
        }

        $this->fail('The budget should have been exceeded.');
    }

    public function test_the_top_of_the_hour_is_not_an_outlier_when_work_is_spread(): void {
        $schedule = $this->scheduleOf(
            Cadence::everyThirtyMinutes()->spreadAcross(30)->expressions(),
        );

        $this->assertTopOfTheHourIsNotAnOutlier($this->hourlyRecurringLoad($schedule));
    }

    public function test_it_catches_the_top_of_the_hour_outlier(): void {
        $schedule = $this->scheduleOf([
            ...array_fill(0, 10, '0 * * * *'),
            '7 * * * *',
        ]);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Minute :00 carries 10 every-hour tasks, more than 2.0x');

        $this->assertTopOfTheHourIsNotAnOutlier($this->hourlyRecurringLoad($schedule));
    }

    public function test_tasks_that_do_not_repeat_every_hour_are_left_out_of_the_budget(): void {
        $schedule = $this->scheduleOf(array_fill(0, 20, '0 3 * * *'));

        $this->assertNoMinuteCarriesMoreThan(self::MAX_TASKS_PER_MINUTE, $this->hourlyRecurringLoad($schedule));
        $this->assertSame(20, $this->scheduleLoad($schedule)->at(0)->tasks);
    }

    public function test_it_orders_a_pipeline_of_commands(): void {
        $schedule = new Schedule();
        $schedule->command('cron:rawImport')->cron(Cadence::everyThirtyMinutes(3)->expression());
        $schedule->command('cron:import')->cron(Cadence::everyThirtyMinutes(13)->expression());
        $schedule->command('cron:updateShipping')->cron(Cadence::everyThirtyMinutes(23)->expression());

        $this->assertRunsBefore('cron:rawImport', 'cron:import', $schedule);
        $this->assertRunsBefore('cron:import', 'cron:updateShipping', $schedule);
    }

    public function test_it_catches_a_pipeline_running_out_of_order(): void {
        $schedule = new Schedule();
        $schedule->command('cron:rawImport')->cron(Cadence::everyThirtyMinutes(20)->expression());
        $schedule->command('cron:import')->cron(Cadence::everyThirtyMinutes(5)->expression());

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('cron:rawImport (:20) must run before cron:import (:05)');

        $this->assertRunsBefore('cron:rawImport', 'cron:import', $schedule);
    }

    public function test_it_fails_when_an_expected_command_is_not_scheduled(): void {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('cron:missing is not scheduled.');

        $this->assertRunsBefore('cron:missing', 'cron:alsoMissing', new Schedule());
    }

    public function test_a_full_hour_of_lanes_uses_every_minute_exactly_once(): void {
        $load = $this->hourlyRecurringLoad($this->scheduleOf(
            Cadence::everyMinutes(MinuteInterval::LAST_MINUTE + 1 - 30)->spreadAcross(30)->expressions(),
        ));

        $this->assertSame(1, $load->peak()->tasks);
    }

    /** @param list<string> $expressions */
    private function scheduleOf(array $expressions): Schedule {
        $schedule = new Schedule();

        foreach ($expressions as $index => $expression) {
            $schedule->command("cron:task{$index}")->cron($expression);
        }

        return $schedule;
    }
}
